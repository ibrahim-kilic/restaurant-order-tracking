<?php
class WaiterController {
    private function db(){ return db(); }
    private function userId(){
        if (function_exists('auth') && auth()) return auth()->id();
        return $_SESSION['user']['id'] ?? null;
    }

    // POST /waiter/api/add-items
    public function addItems() {
        header('Content-Type: application/json; charset=utf-8');

        $userId  = $this->userId();
        if(!$userId){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Auth required']); return; }

        $tableId = (int)($_POST['table_id'] ?? 0);

        // items hem JSON hem form-data array gelebilir
        $items = $_POST['items'] ?? [];
        if (is_string($items)) {
            $tmp = json_decode($items, true);
            if (json_last_error() === JSON_ERROR_NONE) $items = $tmp;
        }

        if($tableId<=0 || !is_array($items) || count($items)==0){
            http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Bad payload']); return;
        }

        $db = $this->db(); $db->beginTransaction();
        try {
            // 1) açık ticket bul / yoksa oluştur
            $stmt = $db->prepare("SELECT id FROM tickets WHERE table_id=? AND status='open' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$tableId]);
            $ticketId = $stmt->fetchColumn();
            if (!$ticketId) {
                $db->prepare("INSERT INTO tickets (table_id, opened_by, status, opened_at) VALUES (?,?, 'open', NOW())")
                    ->execute([$tableId, $userId]);
                $ticketId = $db->lastInsertId();
            }

            // 2) satırları ekle (fiyatı view'larda ürün tablosundan alıyoruz)
            $ins = $db->prepare("INSERT INTO ticket_items
        (ticket_id, product_id, qty, status, served_at, served_by, source)
        VALUES (?, ?, ?, 'served', NOW(), ?, 'waiter')");

            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                $qty = (float)($it['qty'] ?? 1);
                if($pid>0 && $qty>0){ $ins->execute([$ticketId, $pid, $qty, $userId]); }
            }

            $db->commit();
            echo json_encode(['ok'=>true,'ticket_id'=>$ticketId]);
        } catch (Throwable $e) {
            $db->rollBack();
            http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
        }
    }
}
