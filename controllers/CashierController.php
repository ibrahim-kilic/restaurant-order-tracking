<?php
class CashierController {

    private function colExists($table, $col) {
        $db = db();
        $st = $db->prepare("
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    public function apiTables() {
        require_role(['cashier','admin']);

        $db = db();

        // is_active var mı? admin tümünü isterse ?all=1 ile görebilsin
        $useIsActive = $this->colExists('pos_tables','is_active');
        $adminAll = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && (($_GET['all'] ?? '') === '1'));
        $where = ($useIsActive && !$adminAll) ? 'WHERE t.is_active=1' : 'WHERE 1';

        // süre hesabı için uygun kolon
        if ($this->colExists('tickets','opened_at')) {
            $openedCol = 'k.opened_at';
        } elseif ($this->colExists('tickets','created_at')) {
            $openedCol = 'k.created_at';
        } else {
            $openedCol = 'NULL';
        }

        // is_active varsa select'e ekleyelim ki admin UI’da toggle gösterebilelim
        $isActiveSelect = $useIsActive ? 't.is_active' : 'NULL AS is_active';

        $sql = "SELECT
                t.id,
                t.name,
                CASE 
                  WHEN COALESCE(SUM(i.qty),0) = 0 THEN 'empty'
                  WHEN k.status = 'payment' THEN 'payment'
                  ELSE 'open'
                END AS status,
                COALESCE(SUM(i.qty * i.price),0) AS total,
                COALESCE(SUM(i.qty),0) AS item_count,
                CASE 
                  WHEN MIN($openedCol) IS NULL THEN NULL
                  ELSE TIMESTAMPDIFF(MINUTE, MIN($openedCol), NOW())
                END AS open_min,
                $isActiveSelect
            FROM pos_tables t
            LEFT JOIN tickets k ON k.table_id=t.id AND k.status IN ('open','payment')
            LEFT JOIN ticket_items i ON i.ticket_id = k.id
            $where
            GROUP BY t.id, t.name, k.status
            ORDER BY t.id ASC";

        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            // Pasif masaya özel etiket
            if ($useIsActive && isset($r['is_active']) && (int)$r['is_active'] === 0) {
                $r['status_label'] = 'Pasif';
            } else {
                if ($r['status'] === 'open')        $r['status_label'] = 'Dolu';
                elseif ($r['status'] === 'payment') $r['status_label'] = 'Ödeme';
                else                                 $r['status_label'] = 'Boş';
            }
        }
        json_ok(['tables' => $rows]);
    }

    public function apiTableDetail() {
        require_role(['cashier','admin']);
        $tableId = (int)($_GET['table_id'] ?? 0);
        if ($tableId <= 0) json_err('Geçersiz masa');

        $pdo = db();

        // aktif ticket (open/payment) var mı?
        $tk = $pdo->prepare("SELECT * FROM tickets WHERE table_id=? AND status IN('open','payment') ORDER BY id DESC LIMIT 1");
        $tk->execute([$tableId]);
        $ticket = $tk->fetch();

        $items = [];
        $payments = [];
        $totals = ['sum'=>0,'qty'=>0,'paid'=>0,'due'=>0];

        if ($ticket) {
            $it = $pdo->prepare("SELECT id,product_name,qty,price,(qty*price) AS line_total FROM ticket_items WHERE ticket_id=? ORDER BY id");
            $it->execute([$ticket['id']]);
            $items = $it->fetchAll(PDO::FETCH_ASSOC);

            $pay = $pdo->prepare("SELECT id,method,amount,created_at FROM payments WHERE ticket_id=? ORDER BY id");
            $pay->execute([$ticket['id']]);
            $payments = $pay->fetchAll(PDO::FETCH_ASSOC);

            $sum = 0; $qty = 0;
            foreach ($items as $x){ $sum += (float)$x['line_total']; $qty += (float)$x['qty']; }
            $paid = 0; foreach ($payments as $p){ $paid += (float)$p['amount']; }
            $due  = max(0, $sum - $paid);
            $totals = ['sum'=>$sum,'qty'=>$qty,'paid'=>$paid,'due'=>$due];
        }

        // diğer masalar (taşı/birleştir için)
        $useIsActive = $this->colExists('pos_tables','is_active');
        $sqlTables = $useIsActive
            ? "SELECT id,name FROM pos_tables WHERE is_active=1 ORDER BY id"
            : "SELECT id,name FROM pos_tables ORDER BY id";
        $tables = $pdo->query($sqlTables)->fetchAll(PDO::FETCH_ASSOC);

        // kategoriler
        $categories = $pdo->query("SELECT id,name,sort FROM product_categories ORDER BY sort, name")->fetchAll(PDO::FETCH_ASSOC);

        // ÜRÜNLER: fav filtresi için is_favorite'ı da döndür (alias: fav)
        $products = $pdo->query("
        SELECT id, name, price, category_id, is_favorite AS fav
        FROM products
        WHERE active=1
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

        json_ok([
            'table'      => $pdo->query("SELECT id,name FROM pos_tables WHERE id={$tableId}")->fetch(PDO::FETCH_ASSOC),
            'ticket'     => $ticket ?: null,
            'items'      => $items,
            'payments'   => $payments,
            'totals'     => $totals,
            'tables'     => $tables,
            'categories' => $categories,
            'products'   => $products
        ]);
    }


    public function apiAddItem() {
        require_role(['cashier','admin']);
        require_csrf();

        $tableId = (int)($_POST['table_id'] ?? 0);
        $prodId  = (int)($_POST['product_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $price   = (float)($_POST['price'] ?? 0);
        $qty     = (float)($_POST['qty'] ?? 1);

        if ($tableId<=0 || $qty<=0) json_err('Geçersiz veri');

        $pdo = db(); $pdo->beginTransaction();
        try {
            // aktif ticket var mı?
            $tk = $pdo->prepare("SELECT * FROM tickets WHERE table_id=? AND status IN('open','payment') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $tk->execute([$tableId]);
            $ticket = $tk->fetch();

            if (!$ticket) {
                if ($this->colExists('tickets','opened_at')) {
                    $pdo->prepare("INSERT INTO tickets(table_id,status,opened_at) VALUES(?, 'open', NOW())")->execute([$tableId]);
                } else {
                    $pdo->prepare("INSERT INTO tickets(table_id,status) VALUES(?, 'open')")->execute([$tableId]);
                }

                $ticketId = (int)$pdo->lastInsertId();
            } else {
                $ticketId = (int)$ticket['id'];
            }

            // ürün bilgisi
            if ($prodId>0) {
                $p = $pdo->prepare("SELECT name,price FROM products WHERE id=?");
                $p->execute([$prodId]);
                $pp = $p->fetch();
                if ($pp){ $name = $pp['name']; $price = (float)$pp['price']; }
            }
            if ($name==='') json_err('Ürün adı boş', 422);

            // aynı isimli kalem varsa miktarı artır
            $ex = $pdo->prepare("SELECT id,qty FROM ticket_items WHERE ticket_id=? AND product_name=? AND price=?");
            $ex->execute([$ticketId,$name,$price]);
            $row = $ex->fetch();

            if ($row) {
                $pdo->prepare("UPDATE ticket_items SET qty=qty+? WHERE id=?")->execute([$qty, $row['id']]);
            } else {
                $pdo->prepare("INSERT INTO ticket_items(ticket_id,product_id,product_name,qty,price) VALUES(?,?,?,?,?)")
                    ->execute([$ticketId, $prodId ?: null, $name, $qty, $price]);
            }

            $pdo->commit();
            json_ok(['ticket_id'=>$ticketId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_err('Ürün eklenemedi');
        }
    }

    public function apiUpdateItem() {
        require_role(['cashier','admin']);
        require_csrf();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty    = isset($_POST['qty']) ? (float)$_POST['qty'] : null;
        $del    = isset($_POST['delete']) ? (int)$_POST['delete'] : 0;

        if ($itemId<=0) json_err('Geçersiz kalem');

        $pdo = db();
        if ($del) {
            $stmt = $pdo->prepare("DELETE FROM ticket_items WHERE id=?");
            $stmt->execute([$itemId]);
        } else {
            if ($qty===null || $qty<=0) json_err('Miktar > 0 olmalı', 422);
            $stmt = $pdo->prepare("UPDATE ticket_items SET qty=? WHERE id=?");
            $stmt->execute([$qty,$itemId]);
        }
        json_ok();
    }
    public function apiAddPayment() {
        require_role(['cashier','admin']);
        require_csrf();
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $amount   = (float)($_POST['amount'] ?? 0);
        $method   = $_POST['method'] ?? 'cash';
        if ($ticketId<=0 || $amount<=0) json_err('Geçersiz ödeme');

        $pdo = db(); $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO payments(ticket_id,method,amount,created_at) VALUES(?,?,?,NOW())")
                ->execute([$ticketId,$method,$amount]);

            // toplamlar
            $sum = (float)$pdo->query("SELECT COALESCE(SUM(qty*price),0) FROM ticket_items WHERE ticket_id={$ticketId}")->fetchColumn();
            $paid= (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE ticket_id={$ticketId}")->fetchColumn();

            $shouldClose = ($paid + 0.0001) >= $sum; // floating toleransı

            if ($shouldClose) {
                $pdo->prepare("UPDATE tickets SET status='closed', closed_at=NOW() WHERE id=?")->execute([$ticketId]);
            } else {
                // ödeme sürecindeyse 'payment' statüsüne çek
                $pdo->prepare("UPDATE tickets SET status='payment' WHERE id=? AND status='open'")->execute([$ticketId]);
            }

            $pdo->commit();
            json_ok(['closed'=>$shouldClose]);
        } catch(Throwable $e){
            $pdo->rollBack();
            json_err('Ödeme eklenemedi');
        }
    }

    public function apiTransferTable() {
        require_role(['cashier','admin']);
        require_csrf();

        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $toTable  = (int)($_POST['to_table_id'] ?? 0);
        if ($ticketId<=0 || $toTable<=0) json_err('Geçersiz istek');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Kaynak ticket ürün sayısı (en az 1 olmalı)
            $cnt = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM ticket_items WHERE ticket_id={$ticketId}")->fetchColumn();
            if ($cnt <= 0) {
                $pdo->rollBack();
                json_err('Bu masa boş; taşınamaz.', 409);
            }

            // Hedef masada aktif ticket var mı? (müsait olmalı)
            $ex = $pdo->prepare("SELECT id FROM tickets WHERE table_id=? AND status IN('open','payment') LIMIT 1 FOR UPDATE");
            $ex->execute([$toTable]);
            $exists = $ex->fetch();
            if ($exists) {
                $pdo->rollBack();
                json_err('Hedef masa dolu; taşıma yapılamaz. Birleştirmeyi deneyin.', 409);
            }

            // Taşı
            $pdo->prepare("UPDATE tickets SET table_id=? WHERE id=?")->execute([$toTable,$ticketId]);
            $pdo->commit();
            json_ok();
        } catch(Throwable $e){
            $pdo->rollBack();
            json_err('Masa taşınamadı');
        }
    }

    public function apiTablesAges() {
        require_role(['cashier','admin']);

        $useIsActive = $this->colExists('pos_tables','is_active');
        $openedCol   = $this->colExists('tickets','opened_at')
            ? 'k.opened_at'
            : ($this->colExists('tickets','created_at') ? 'k.created_at' : 'NULL');
        $where = $useIsActive ? 'WHERE t.is_active=1' : 'WHERE 1';

        $sql = "SELECT
    t.id,
    CASE WHEN COALESCE(SUM(i.qty),0)=0 THEN NULL
         ELSE TIMESTAMPDIFF(MINUTE, MIN($openedCol), NOW())
    END AS open_min
  FROM pos_tables t
  LEFT JOIN tickets k ON k.table_id=t.id AND k.status IN('open','payment')
  LEFT JOIN ticket_items i ON i.ticket_id=k.id
  $where
  GROUP BY t.id
  ORDER BY t.id ASC";



        $rows = db()->query($sql)->fetchAll();
        json_ok(['ages'=>$rows]);
    }
    public function apiFavorites() {
        require_role(['cashier','admin']);
        $rows = db()->query("
        SELECT id, name, price, image
        FROM products
        WHERE is_favorite=1 AND active=1
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
        json_ok(['favorites'=>$rows]);
    }

    public function apiMergeTable() {
        require_role(['cashier','admin']);
        require_csrf();

        $fromTicket = (int)($_POST['from_ticket_id'] ?? 0);
        $toTicket   = (int)($_POST['to_ticket_id'] ?? 0);
        if ($fromTicket<=0 || $toTicket<=0 || $fromTicket===$toTicket) json_err('Geçersiz seçim');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $fromCnt = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM ticket_items WHERE ticket_id={$fromTicket}")->fetchColumn();
            $toCnt   = (float)$pdo->query("SELECT COALESCE(SUM(qty),0) FROM ticket_items WHERE ticket_id={$toTicket}")->fetchColumn();

            if ($fromCnt <= 0) { $pdo->rollBack(); json_err('Kaynak masa boş; birleştirilemez.', 409); }
            if ($toCnt   <= 0) { $pdo->rollBack(); json_err('Hedef masada ürün yok; önce ürün ekleyin veya taşıma yapın.', 409); }

            $pdo->exec("UPDATE ticket_items SET ticket_id={$toTicket} WHERE ticket_id={$fromTicket}");
            $pdo->exec("UPDATE payments    SET ticket_id={$toTicket} WHERE ticket_id={$fromTicket}");
            $pdo->prepare("DELETE FROM tickets WHERE id=?")->execute([$fromTicket]);

            $pdo->commit();
            json_ok();
        } catch(Throwable $e){
            $pdo->rollBack();
            json_err('Birleştirme başarısız');
        }
    }
}
