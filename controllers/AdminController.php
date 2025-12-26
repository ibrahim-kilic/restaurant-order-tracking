<?php
class AdminController {

    /* ====================== */
    /*  YARDIMCI FONKSİYONLAR */
    /* ====================== */

    private static $colCache = []; // ['table.col' => true/false]
    private static $tblCache = []; // ['table'     => true/false]

    /** Belirtilen tabloda kolon var mı? */
    private function colExists($table, $col)
    {
        $table = trim($table);
        $col   = trim($col);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $col))   return false;

        $key = strtolower($table . '.' . $col);
        if (array_key_exists($key, self::$colCache)) return self::$colCache[$key];

        $db = db();
        $st = $db->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
            LIMIT 1
        ");
        $st->execute([$table, $col]);
        $exists = (bool) $st->fetchColumn();

        self::$colCache[$key] = $exists;
        return $exists;
    }

    /** Tablo var mı? */
    private function tableExists($table)
    {
        $table = trim($table);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;

        $key = strtolower($table);
        if (array_key_exists($key, self::$tblCache)) return self::$tblCache[$key];

        $db = db();
        $st = $db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
            LIMIT 1
        ");
        $st->execute([$table]);
        $exists = (bool) $st->fetchColumn();

        self::$tblCache[$key] = $exists;
        return $exists;
    }

    /** Fiyat girişini normalize eder (virgül/nokta, 2 ondalık kabul) */
    private function normalizePrice($raw){
        if ($raw === null) return null;
        $s = trim((string)$raw);
        if ($s === '') return null;
        $s = str_replace(',', '.', $s);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $s)) return null;
        return (string)$s;
    }

    /** Y-m-d tarihini doğrula, yoksa null döndür */
    private function normDate($s){
        if (!$s) return null;
        $s = trim((string)$s);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
        return $s;
    }

    /** Tickets için tarih ifadesi: closed_at > close_time > created_at */
    private function tDateExpr(): ?string {
        if (!$this->tableExists('tickets')) return null;
        $cands = [];
        if ($this->colExists('tickets','closed_at'))  $cands[] = 't.closed_at';
        if ($this->colExists('tickets','close_time')) $cands[] = 't.close_time';
        if ($this->colExists('tickets','created_at')) $cands[] = 't.created_at';
        return $cands ? ('COALESCE('.implode(',', $cands).')') : null;
    }

    /** Ticket items için tarih ifadesi */
    private function itDateExpr(): ?string {
        if ($this->tableExists('ticket_items') && $this->colExists('ticket_items','created_at')) return 'it.created_at';
        return null;
    }

    /* ====================== */
    /*        MASA API        */
    /* ====================== */

    // GET /admin/api/tables
    public function apiTables() {
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->tableExists('pos_tables')) {
            echo json_encode(['status'=>true,'data'=>['tables'=>[]]]); return;
        }
        $db = db();

        $cols = ['id','name'];
        if ($this->colExists('pos_tables','capacity'))  $cols[] = 'capacity';
        if ($this->colExists('pos_tables','status'))    $cols[] = 'status';
        if ($this->colExists('pos_tables','is_active')) $cols[] = 'is_active';

        $sql = "SELECT ".implode(',', $cols)." FROM pos_tables ORDER BY id ASC";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>true,'data'=>['tables'=>$rows]]);
    }

    // POST /admin/api/table-create
    public function apiTableCreate() {
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $name     = trim($_POST['name'] ?? '');
        $capacity = $_POST['capacity'] ?? null;
        $area     = trim($_POST['area'] ?? '');

        if ($name === '') { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Masa adı zorunlu']); return; }

        $cap = null;
        if ($capacity !== null && $capacity !== '') {
            if (!ctype_digit((string)$capacity)) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kapasite sayısal olmalı']); return; }
            $cap = (int)$capacity;
        }

        $db = db();
        try {
            if (!$this->tableExists('pos_tables')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'pos_tables tablosu yok']); return; }

            $colsAvail = [];
            $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_tables'");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN,0) as $c) $colsAvail[strtolower($c)] = true;

            if (isset($colsAvail['name'])) {
                $chk = $db->prepare("SELECT id FROM pos_tables WHERE name=? LIMIT 1");
                $chk->execute([$name]);
                if ($chk->fetchColumn()) { http_response_code(409); echo json_encode(['status'=>false,'message'=>'Bu isimde masa zaten var']); return; }
            }

            $insertCols   = ['name'];
            $valuesSql    = ['?'];
            $params       = [$name];

            if (isset($colsAvail['capacity']) && $cap !== null) { $insertCols[]='capacity'; $valuesSql[]='?'; $params[]=$cap; }
            if (isset($colsAvail['area']) && $area !== '')      { $insertCols[]='area';     $valuesSql[]='?'; $params[]=$area; }
            if (isset($colsAvail['status']))                    { $insertCols[]='status';   $valuesSql[]='?'; $params[]='free'; }
            if (isset($colsAvail['created_at']))                { $insertCols[]='created_at'; $valuesSql[]='NOW()'; }

            $sql = "INSERT INTO pos_tables (`".implode("`,`",$insertCols)."`) VALUES (".implode(',',$valuesSql).")";
            $db->prepare($sql)->execute($params);

            echo json_encode(['status'=>true, 'data'=>['id'=>$db->lastInsertId()], 'message'=>'Masa eklendi']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>false, 'message'=>'Kaydedilemedi: '.$e->getMessage()]);
        }
    }

    // POST /admin/api/table-update
    public function apiTableUpdate() {
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        $name     = isset($_POST['name']) ? trim($_POST['name']) : null;
        $capacity = array_key_exists('capacity', $_POST) ? $_POST['capacity'] : null;
        $area     = isset($_POST['area']) ? trim($_POST['area']) : null;

        $db = db();
        try {
            if (!$this->tableExists('pos_tables')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'pos_tables tablosu yok']); return; }

            $colsAvail = [];
            $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_tables'");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN,0) as $c) $colsAvail[strtolower($c)] = true;

            if ($name !== null && isset($colsAvail['name'])) {
                if ($name===''){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Masa adı boş olamaz']); return; }
                $chk = $db->prepare("SELECT id FROM pos_tables WHERE name=? AND id<>? LIMIT 1");
                $chk->execute([$name,$id]);
                if ($chk->fetchColumn()) { http_response_code(409); echo json_encode(['status'=>false,'message'=>'Bu isimde masa var']); return; }
            }

            $set = []; $params = [];
            if ($name !== null && isset($colsAvail['name'])) { $set[]='name=?'; $params[]=$name; }

            if ($capacity !== null && isset($colsAvail['capacity'])) {
                if ($capacity === '' || $capacity === null) { $set[]='capacity=NULL'; }
                else {
                    if (!ctype_digit((string)$capacity)) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kapasite sayısal olmalı']); return; }
                    $set[]='capacity=?'; $params[]=(int)$capacity;
                }
            }

            if ($area !== null && isset($colsAvail['area'])) {
                if ($area==='') { $set[]='area=NULL'; } else { $set[]='area=?'; $params[]=$area; }
            }

            if (empty($set)) { echo json_encode(['status'=>true,'message'=>'Değişiklik yok']); return; }

            $sql = "UPDATE pos_tables SET ".implode(',',$set)." WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);

            echo json_encode(['status'=>true,'message'=>'Güncellendi']);
        } catch (Throwable $e) {
            http_response_code(500); echo json_encode(['status'=>false,'message'=>$e->getMessage()]);
        }
    }

    // POST /admin/api/table-delete
    public function apiTableDelete() {
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        $db = db();
        try {
            if ($this->tableExists('tickets') && $this->colExists('tickets','table_id') && $this->colExists('tickets','status')) {
                $q = $db->prepare("SELECT COUNT(*) FROM tickets WHERE table_id=? AND status <> 'closed'");
                $q->execute([$id]);
                if ((int)$q->fetchColumn() > 0) {
                    http_response_code(409);
                    echo json_encode(['status'=>false,'message'=>'Bu masanın açık adisyonu var, silinemez']);
                    return;
                }
            }

            $db->prepare("DELETE FROM pos_tables WHERE id=?")->execute([$id]);
            echo json_encode(['status'=>true,'message'=>'Silindi']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>false,'message'=>'Silinemedi: '.$e->getMessage()]);
        }
    }

    // GET /admin/api/table-get?id=...
    public function apiTableGet() {
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        $db = db();
        $st = $db->prepare("SELECT * FROM pos_tables WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['status'=>false,'message'=>'Masa bulunamadı']); return; }

        $out = [
            'id'   => $row['id'],
            'name' => $row['name'],
        ];
        $out['capacity'] = array_key_exists('capacity', $row) ? $row['capacity'] : null;
        $out['area']     = array_key_exists('area',     $row) ? $row['area']     : null;
        $out['status']   = array_key_exists('status',   $row) ? $row['status']   : null;
        $out['is_active']= array_key_exists('is_active',$row) ? $row['is_active']: null;

        echo json_encode(['status'=>true,'data'=>['table'=>$out]]);
    }

    // POST /admin/api/table-set-active
    public function apiTableSetActive() {
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id     = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['active']) ? (int)$_POST['active'] : null;
        if ($id <= 0 || ($active!==0 && $active!==1)) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz parametre']); return; }

        $db = db();

        $hasIsActive = $this->colExists('pos_tables','is_active');
        $hasStatus   = $this->colExists('pos_tables','status');

        try {
            if ($hasIsActive) {
                $db->prepare("UPDATE pos_tables SET is_active=? WHERE id=?")->execute([$active,$id]);
            } elseif ($hasStatus) {
                if ($active) {
                    $db->prepare("UPDATE pos_tables SET status=CASE WHEN status='disabled' THEN 'free' ELSE status END WHERE id=?")->execute([$id]);
                } else {
                    $db->prepare("UPDATE pos_tables SET status='disabled' WHERE id=?")->execute([$id]);
                }
            } else {
                http_response_code(422);
                echo json_encode(['status'=>false,'message'=>"Bu özellik için pos_tables tablosunda 'is_active' veya 'status' kolonu gerekli."]);
                return;
            }

            echo json_encode(['status'=>true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>false,'message'=>'Güncellenemedi: '.$e->getMessage()]);
        }
    }

    /* ====================== */
    /*   KATEGORİ API'LERİ    */
    /* ====================== */

    // GET /admin/api/categories
    public function apiCategories(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->tableExists('product_categories')) {
            echo json_encode(['status'=>true,'data'=>['categories'=>[]]]); return;
        }

        $db = db();
        $sortCol = $this->colExists('product_categories','sort_order') ? 'sort_order'
            : ($this->colExists('product_categories','sort') ? 'sort' : null);

        $cols = "id, name";
        if ($sortCol) $cols .= ", $sortCol AS sort_order";

        $sql = "SELECT $cols FROM product_categories";
        $sql .= $sortCol ? " ORDER BY $sortCol, id" : " ORDER BY id";

        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>true,'data'=>['categories'=>$rows]]);
    }

    // GET /admin/api/category-get?id=...
    public function apiCategoryGet(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_GET['id'] ?? 0);
        if ($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        if (!$this->tableExists('product_categories')) {
            http_response_code(404); echo json_encode(['status'=>false,'message'=>'Kategori bulunamadı']); return;
        }

        $db = db();
        $sortCol = $this->colExists('product_categories','sort_order') ? 'sort_order'
            : ($this->colExists('product_categories','sort') ? 'sort' : null);

        $cols = "id,name";
        if ($sortCol) $cols .= ", $sortCol AS sort_order";

        $st = $db->prepare("SELECT $cols FROM product_categories WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if(!$row){ http_response_code(404); echo json_encode(['status'=>false,'message'=>'Kategori bulunamadı']); return; }

        echo json_encode(['status'=>true,'data'=>['category'=>$row]]);
    }

    // POST /admin/api/category
    public function apiCategoryCreate(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $name = trim($_POST['name'] ?? '');
        $sort = isset($_POST['sort_order']) ? trim((string)$_POST['sort_order']) : (isset($_POST['sort']) ? trim((string)$_POST['sort']) : null);

        if ($name === ''){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori adı zorunlu']); return; }
        if (!$this->tableExists('product_categories')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'product_categories tablosu yok']); return; }
        $db = db();

        $sortCol = $this->colExists('product_categories','sort_order') ? 'sort_order'
            : ($this->colExists('product_categories','sort') ? 'sort' : null);

        $cols = ['name']; $ph=['?']; $pr=[$name];
        if ($sortCol && $sort !== null && $sort !== ''){
            if (!ctype_digit((string)$sort)) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Sıra sayısal olmalı']); return; }
            $cols[]=$sortCol; $ph[]='?'; $pr[]=(int)$sort;
        }

        $sql="INSERT INTO product_categories (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
        $db->prepare($sql)->execute($pr);
        echo json_encode(['status'=>true,'data'=>['id'=>$db->lastInsertId()]]);
    }

    // POST /admin/api/category-update
    public function apiCategoryUpdate(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id   = (int)($_POST['id'] ?? 0);
        $name = array_key_exists('name', $_POST) ? trim((string)$_POST['name']) : null;
        $sort = array_key_exists('sort_order', $_POST) ? trim((string)$_POST['sort_order'])
            : (array_key_exists('sort', $_POST) ? trim((string)$_POST['sort']) : null);

        if ($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }
        if (!$this->tableExists('product_categories')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'product_categories tablosu yok']); return; }

        $db = db();
        $set=[]; $pr=[];
        if ($name !== null){
            if ($name===''){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori adı boş olamaz']); return; }
            $set[]='name=?'; $pr[]=$name;
        }

        $sortCol = $this->colExists('product_categories','sort_order') ? 'sort_order'
            : ($this->colExists('product_categories','sort') ? 'sort' : null);

        if ($sortCol && $sort !== null){
            if ($sort===''){ $set[]="$sortCol=NULL"; } else {
                if (!ctype_digit((string)$sort)){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Sıra sayısal olmalı']); return; }
                $set[]="$sortCol=?"; $pr[]=(int)$sort;
            }
        }

        if (!$set){ echo json_encode(['status'=>true,'message'=>'Değişiklik yok']); return; }
        $pr[]=$id;
        $db->prepare("UPDATE product_categories SET ".implode(',',$set)." WHERE id=?")->execute($pr);
        echo json_encode(['status'=>true]);
    }

    // POST /admin/api/category-delete
    public function apiCategoryDelete(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }
        if (!$this->tableExists('product_categories')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'product_categories tablosu yok']); return; }

        $db = db();
        if ($this->tableExists('products') && $this->colExists('products','category_id')) {
            $chk = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0){
                http_response_code(409);
                echo json_encode(['status'=>false,'message'=>'Bu kategoriye bağlı ürün var. Önce ürünleri taşı/sil.']);
                return;
            }
        }
        $db->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>true]);
    }

    /* ====================== */
    /*      ÜRÜN API'LERİ     */
    /* ====================== */

    // GET /admin/api/products  (?category_id=) veya (?id=)
    public function apiProducts(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->tableExists('products')) {
            echo json_encode(['status'=>true,'data'=>['products'=>[]]]); return;
        }

        $db   = db();
        $id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $catId= isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

        $activeCol = $this->colExists('products','is_active') ? 'is_active'
            : ($this->colExists('products','active') ? 'active' : null);
        $favCol    = $this->colExists('products','is_favorite') ? 'is_favorite'
            : ($this->colExists('products','fav') ? 'fav' : null);
        $sortCol   = $this->colExists('products','sort_order') ? 'sort_order'
            : ($this->colExists('products','sort') ? 'sort' : null);

        $cols = ['id','name','category_id','price'];
        $cols[] = $activeCol ? "$activeCol AS is_active" : "1 AS is_active";
        $cols[] = $favCol    ? "$favCol AS is_favorite"  : "0 AS is_favorite";
        $cols[] = $sortCol   ? "$sortCol AS sort_order"  : "NULL AS sort_order";

        if ($id > 0) {
            $sql = "SELECT ".implode(',', $cols)." FROM products WHERE id = ? LIMIT 1";
            $st  = $db->prepare($sql);
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if(!$row){ echo json_encode(['status'=>false,'message'=>'Ürün bulunamadı']); return; }
            echo json_encode(['status'=>true,'data'=>['product'=>$row]]);
            return;
        }

        $where = []; $pr = [];
        if ($catId > 0) { $where[] = "category_id = ?"; $pr[] = $catId; }

        $sql = "SELECT ".implode(',', $cols)." FROM products";
        if ($where) $sql .= " WHERE ".implode(' AND ', $where);
        $sql .= $sortCol ? " ORDER BY $sortCol ASC, id ASC" : " ORDER BY id ASC";

        $st = $db->prepare($sql);
        $st->execute($pr);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>true,'data'=>['products'=>$rows]]);
    }

    // POST /admin/api/product
    public function apiProductCreate(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = $this->normalizePrice($_POST['price'] ?? null);
        $activeParam = isset($_POST['active']) ? (int)$_POST['active']
            : (isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1);

        if ($name===''){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Ürün adı zorunlu']); return; }
        if ($category_id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori zorunlu']); return; }
        if ($price===null){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Fiyat geçersiz']); return; }

        $db = db();

        if (!$this->tableExists('product_categories')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori bulunamadı']); return; }
        $ck = $db->prepare("SELECT id FROM product_categories WHERE id=?");
        $ck->execute([$category_id]);
        if (!$ck->fetchColumn()){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori bulunamadı']); return; }

        if (!$this->tableExists('products')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'products tablosu yok']); return; }

        $activeCol = $this->colExists('products','is_active') ? 'is_active'
            : ($this->colExists('products','active') ? 'active' : null);

        $cols=['name','category_id','price']; $ph=['?','?','?']; $pr=[$name,$category_id,$price];
        if ($activeCol){ $cols[]=$activeCol; $ph[]='?'; $pr[]=$activeParam?1:0; }

        $sql="INSERT INTO products (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
        $db->prepare($sql)->execute($pr);

        echo json_encode(['status'=>true,'data'=>['id'=>$db->lastInsertId()]]);
    }

    // POST /admin/api/product-update
    public function apiProductUpdate(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        $name = array_key_exists('name', $_POST) ? trim((string)$_POST['name']) : null;
        $category_id = array_key_exists('category_id', $_POST) ? (int)$_POST['category_id'] : null;
        $priceRaw = array_key_exists('price', $_POST) ? $_POST['price'] : null;
        $priceNorm = array_key_exists('price', $_POST) ? $this->normalizePrice($priceRaw) : null;
        $active = array_key_exists('active', $_POST) ? (int)$_POST['active']
            : (array_key_exists('is_active', $_POST) ? (int)$_POST['is_active'] : null);

        $db = db();
        $set=[]; $pr=[];

        if ($name !== null){
            if ($name===''){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Ürün adı boş olamaz']); return; }
            $set[]='name=?'; $pr[]=$name;
        }
        if ($category_id !== null){
            if ($category_id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz kategori']); return; }
            if ($this->tableExists('product_categories')) {
                $ck = $db->prepare("SELECT id FROM product_categories WHERE id=?"); $ck->execute([$category_id]);
                if (!$ck->fetchColumn()){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Kategori bulunamadı']); return; }
            }
            $set[]='category_id=?'; $pr[]=$category_id;
        }
        if ($priceRaw !== null){
            if ($priceNorm === null){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Fiyat geçersiz']); return; }
            $set[]='price=?'; $pr[]=$priceNorm;
        }

        $activeCol = $this->colExists('products','is_active') ? 'is_active'
            : ($this->colExists('products','active') ? 'active' : null);
        if ($activeCol && $active !== null){
            $set[]="$activeCol=?"; $pr[] = $active?1:0;
        }

        if (!$set){ echo json_encode(['status'=>true,'message'=>'Değişiklik yok']); return; }
        $pr[]=$id;
        $db->prepare("UPDATE products SET ".implode(',',$set)." WHERE id=?")->execute($pr);
        echo json_encode(['status'=>true]);
    }

    // POST /admin/api/product-delete
    public function apiProductDelete(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        $db = db();
        if ($this->tableExists('ticket_items') && $this->colExists('ticket_items','product_id')){
            $q = $db->prepare("SELECT COUNT(*) FROM ticket_items WHERE product_id=?");
            $q->execute([$id]);
            if ((int)$q->fetchColumn() > 0){
                http_response_code(409);
                echo json_encode(['status'=>false,'message'=>'Bu ürün geçmiş adisyonlarda kullanılmış. Silmek yerine pasif yapın.']);
                return;
            }
        }
        $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        echo json_encode(['status'=>true]);
    }

    // POST /admin/api/product-favorite
    public function apiProductFavorite() {
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id    = (int)($_POST['product_id'] ?? 0);
        $state = (int)($_POST['state'] ?? -1);
        if ($id <= 0 || ($state !== 0 && $state !== 1)) {
            http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz parametre']); return;
        }

        if (!$this->tableExists('products')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'products tablosu yok']); return; }

        $db = db();
        $favCol = $this->colExists('products','is_favorite') ? 'is_favorite'
            : ($this->colExists('products','fav') ? 'fav' : null);
        if (!$favCol) { http_response_code(422); echo json_encode(['status'=>false,'message'=>"products tablosunda 'is_favorite'/'fav' kolonu yok"]); return; }

        $ok = $db->prepare("UPDATE products SET $favCol=? WHERE id=?")->execute([$state, $id]);
        if (!$ok) { http_response_code(500); echo json_encode(['status'=>false,'message'=>'Güncellenemedi']); return; }

        $curr = $db->prepare("SELECT $favCol FROM products WHERE id=?");
        $curr->execute([$id]);
        $val = (int)$curr->fetchColumn();

        echo json_encode(['status'=>true,'data'=>['product_id'=>$id,'is_favorite'=>$val]]);
    }

    // POST /admin/api/product-toggle
    public function apiProductToggle(){
        require_role(['admin']);
        require_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['active']) ? (int)$_POST['active']
            : (isset($_POST['is_active']) ? (int)$_POST['is_active'] : null);

        if ($id<=0 || ($active!==0 && $active!==1)){
            http_response_code(422);
            echo json_encode(['status'=>false,'message'=>'Parametre hatalı']);
            return;
        }

        if (!$this->tableExists('products')) { http_response_code(422); echo json_encode(['status'=>false,'message'=>'products tablosu yok']); return; }

        $activeCol = $this->colExists('products','is_active') ? 'is_active'
            : ($this->colExists('products','active') ? 'active' : null);
        if (!$activeCol){
            http_response_code(422);
            echo json_encode(['status'=>false,'message'=>"products tablosunda 'active'/'is_active' kolonu yok"]);
            return;
        }

        $db = db();
        $db->prepare("UPDATE products SET $activeCol=? WHERE id=?")->execute([$active,$id]);
        echo json_encode(['status'=>true]);
    }

    // GET /admin/api/product-get?id=...
    public function apiProductGet(){ // tekil
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $id=(int)($_GET['id']??0);
        if($id<=0){ http_response_code(422); echo json_encode(['status'=>false,'message'=>'Geçersiz id']); return; }

        if (!$this->tableExists('products')) { http_response_code(404); echo json_encode(['status'=>false,'message'=>'Bulunamadı']); return; }

        $activeCol = $this->colExists('products','is_active') ? 'is_active'
            : ($this->colExists('products','active') ? 'active' : null);
        $favCol    = $this->colExists('products','is_favorite') ? 'is_favorite'
            : ($this->colExists('products','fav') ? 'fav' : null);
        $sortCol   = $this->colExists('products','sort_order') ? 'sort_order'
            : ($this->colExists('products','sort') ? 'sort' : null);

        $cols = "id,name,price,category_id";
        if ($sortCol)   $cols .= ", $sortCol AS sort_order";
        if ($activeCol) $cols .= ", $activeCol AS is_active";
        if ($favCol)    $cols .= ", $favCol AS is_favorite";

        $st=db()->prepare("SELECT $cols FROM products WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row){ http_response_code(404); echo json_encode(['status'=>false,'message'=>'Bulunamadı']); return; }
        echo json_encode(['status'=>true,'data'=>['product'=>$row]]);
    }

    /* ====================== */
    /*       RAPOR API'LERİ   */
    /* ====================== */

    // GET /admin/api/reports/sales?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
    public function apiReportsSales(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        $hasT = $this->tableExists('tickets');
        $hasI = $this->tableExists('ticket_items');
        if (!$hasT && !$hasI){ echo json_encode(['status'=>true,'data'=>['sales'=>[],'total_sales'=>0]]); return; }

        $db = db();

        $dExprT = $this->tDateExpr();
        $dExprI = $this->itDateExpr();
        $dateExpr = $dExprT ?: $dExprI;

        $useTTotal = $hasT && $this->colExists('tickets','total');
        $tStatus   = $hasT && $this->colExists('tickets','status');

        $iHas = $hasI && $this->colExists('ticket_items','ticket_id')
            && ($this->colExists('ticket_items','price') || $this->colExists('ticket_items','unit_price'))
            && ($this->colExists('ticket_items','quantity') || $this->colExists('ticket_items','qty'));

        $rows=[]; $total=0;

        if ($dateExpr){
            $where=[]; $pr=[];
            if ($tStatus) { $where[]="(t.status IS NULL OR t.status IN ('closed','paid','completed','done'))"; }
            if ($from)    { $where[]="DATE($dateExpr) >= ?"; $pr[]=$from; }
            if ($to)      { $where[]="DATE($dateExpr) <= ?"; $pr[]=$to; }

            if ($useTTotal && $iHas){
                $qtyCol   = $this->colExists('ticket_items','quantity') ? 'quantity':'qty';
                $sql = "SELECT DATE($dateExpr) d,
                           SUM(t.total) amt,
                           COUNT(DISTINCT t.id) tickets,
                           SUM(it.$qtyCol) qty
                    FROM tickets t
                    LEFT JOIN ticket_items it ON it.ticket_id=t.id
                    ".($where?'WHERE '.implode(' AND ',$where):'')."
                    GROUP BY DATE($dateExpr) ORDER BY d";
            } elseif ($useTTotal) {
                $sql = "SELECT DATE($dateExpr) d,
                           SUM(t.total) amt,
                           COUNT(DISTINCT t.id) tickets,
                           NULL AS qty
                    FROM tickets t
                    ".($where?'WHERE '.implode(' AND ',$where):'')."
                    GROUP BY DATE($dateExpr) ORDER BY d";
            } elseif ($iHas){
                $priceCol = $this->colExists('ticket_items','price') ? 'price':'unit_price';
                $qtyCol   = $this->colExists('ticket_items','quantity') ? 'quantity':'qty';
                $joinT    = $hasT ? "JOIN tickets t ON t.id=it.ticket_id" : "";
                $baseExpr = $dExprT ?: $dExprI;
                $whereTI  = $where ? "WHERE ".implode(' AND ',$where) : '';
                $sql = "SELECT DATE($baseExpr) d,
                           SUM(it.$priceCol*it.$qtyCol) amt,
                           COUNT(DISTINCT ".($hasT?'t.id':'it.ticket_id').") tickets,
                           SUM(it.$qtyCol) qty
                    FROM ticket_items it
                    $joinT
                    $whereTI
                    GROUP BY DATE($baseExpr) ORDER BY d";
            } else {
                echo json_encode(['status'=>true,'data'=>['sales'=>[],'total_sales'=>0]]); return;
            }

            $st=$db->prepare($sql); $st->execute($pr);
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
                $rows[]=['date'=>$r['d'],'amount'=>(float)$r['amt'],'tickets'=>(int)$r['tickets'],'qty'=>($r['qty']!==null?(int)$r['qty']:null)];
                $total += (float)$r['amt'];
            }
        }

        echo json_encode(['status'=>true,'data'=>['sales'=>$rows,'total_sales'=>$total]]);
    }

    // GET /admin/api/reports/by-category?date_from&date_to
    public function apiReportsByCategory(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        $need = $this->tableExists('ticket_items') && $this->tableExists('tickets')
            && $this->tableExists('products') && $this->tableExists('product_categories');
        if (!$need){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $db = db();
        $priceCol  = $this->colExists('ticket_items','price') ? 'price' : ($this->colExists('ticket_items','unit_price') ? 'unit_price' : null);
        $qtyCol    = $this->colExists('ticket_items','quantity') ? 'quantity' : ($this->colExists('ticket_items','qty') ? 'qty' : null);
        $tStatus   = $this->colExists('tickets','status');
        $dateExpr  = $this->tDateExpr() ?: 't.created_at';

        if (!$priceCol || !$qtyCol || !$dateExpr){
            echo json_encode(['status'=>true,'data'=>[]]); return;
        }

        $where = []; $pr = [];
        if ($tStatus) { $where[] = "(t.status IS NULL OR t.status IN ('closed','paid','completed','done'))"; }
        if ($from) { $where[] = "DATE($dateExpr) >= ?"; $pr[] = $from; }
        if ($to)   { $where[] = "DATE($dateExpr) <= ?"; $pr[] = $to; }

        $sql = "SELECT c.name AS category, SUM(it.$priceCol*it.$qtyCol) AS total, SUM(it.$qtyCol) AS qty
            FROM ticket_items it
            JOIN tickets t ON t.id = it.ticket_id
            JOIN products p ON p.id = it.product_id
            JOIN product_categories c ON c.id = p.category_id
            ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY c.id
            ORDER BY total DESC";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /admin/api/reports/by-product?date_from&date_to&limit=20
    public function apiReportsByProduct(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from  = $this->normDate($_GET['date_from'] ?? null);
        $to    = $this->normDate($_GET['date_to']   ?? null);
        $limit = (int)($_GET['limit'] ?? 20);
        if ($limit<=0 || $limit>200) $limit = 20;

        $need = $this->tableExists('ticket_items') && $this->tableExists('tickets') && $this->tableExists('products');
        if (!$need){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $db = db();
        $priceCol  = $this->colExists('ticket_items','price') ? 'price' : ($this->colExists('ticket_items','unit_price') ? 'unit_price' : null);
        $qtyCol    = $this->colExists('ticket_items','quantity') ? 'quantity' : ($this->colExists('ticket_items','qty') ? 'qty' : null);
        $tStatus   = $this->colExists('tickets','status');
        $dateExpr  = $this->tDateExpr() ?: 't.created_at';

        if (!$priceCol || !$qtyCol || !$dateExpr){
            echo json_encode(['status'=>true,'data'=>[]]); return;
        }

        $where = []; $pr = [];
        if ($tStatus) { $where[] = "(t.status IS NULL OR t.status IN ('closed','paid','completed','done'))"; }
        if ($from) { $where[] = "DATE($dateExpr) >= ?"; $pr[] = $from; }
        if ($to)   { $where[] = "DATE($dateExpr) <= ?"; $pr[] = $to; }

        $sql = "SELECT p.id, p.name, SUM(it.$qtyCol) AS qty, SUM(it.$priceCol*it.$qtyCol) AS total
            FROM ticket_items it
            JOIN tickets t ON t.id = it.ticket_id
            JOIN products p ON p.id = it.product_id
            ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY p.id
            ORDER BY total DESC
            LIMIT $limit";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /admin/api/reports/payments?date_from&date_to
    public function apiReportsPayments(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        if (!$this->tableExists('payments')){
            echo json_encode(['status'=>true,'data'=>[]]); return;
        }

        $db = db();
        $hasMethod   = $this->colExists('payments','method');
        $hasAmount   = $this->colExists('payments','amount');
        $hasCreated  = $this->colExists('payments','created_at');

        if (!$hasMethod || !$hasAmount){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $where = []; $pr = [];
        if ($from && $hasCreated) { $where[] = "DATE(created_at) >= ?"; $pr[] = $from; }
        if ($to   && $hasCreated) { $where[] = "DATE(created_at) <= ?"; $pr[] = $to; }

        $sql = "SELECT method, SUM(amount) AS total, COUNT(*) AS cnt
            FROM payments ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY method
            ORDER BY total DESC";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /admin/api/reports/hours?date_from&date_to
    public function apiReportsHours(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        if (!$this->tableExists('tickets')){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $db = db();
        $useTotal  = $this->colExists('tickets','total');
        $tStatus   = $this->colExists('tickets','status');
        $dExpr     = $this->tDateExpr();

        if (!$dExpr){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $where = []; $pr = [];
        if ($tStatus) { $where[] = "(t.status IS NULL OR t.status IN ('closed','paid','completed','done'))"; }
        if ($from) { $where[] = "DATE($dExpr) >= ?"; $pr[] = $from; }
        if ($to)   { $where[] = "DATE($dExpr) <= ?"; $pr[] = $to; }

        $sumExpr = $useTotal ? "SUM(t.total)" : "COUNT(*)";
        $sql = "SELECT HOUR($dExpr) AS h, {$sumExpr} AS v
            FROM tickets t ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY HOUR($dExpr)
            ORDER BY h ASC";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /admin/api/reports/tables?date_from&date_to
    public function apiReportsTables(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        $need = $this->tableExists('tickets') && $this->colExists('tickets','table_id') && $this->tableExists('pos_tables');
        if (!$need){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $db = db();
        $tStatus  = $this->colExists('tickets','status');
        $useTotal = $this->colExists('tickets','total');
        $dExpr    = $this->tDateExpr();

        if (!$dExpr){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $where = []; $pr = [];
        if ($tStatus) { $where[] = "(t.status IS NULL OR t.status IN ('closed','paid','completed','done'))"; }
        if ($from) { $where[] = "DATE($dExpr) >= ?"; $pr[] = $from; }
        if ($to)   { $where[] = "DATE($dExpr) <= ?"; $pr[] = $to; }

        $sumExpr = $useTotal ? "SUM(t.total)" : "COUNT(*)";
        $sql = "SELECT pt.id, pt.name, {$sumExpr} AS total, COUNT(*) AS tickets
            FROM tickets t
            JOIN pos_tables pt ON pt.id = t.table_id
            ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY pt.id
            ORDER BY total DESC";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /admin/api/reports/staff?date_from&date_to
    public function apiReportsStaff(){
        require_role(['admin']);
        header('Content-Type: application/json; charset=utf-8');

        $from = $this->normDate($_GET['date_from'] ?? null);
        $to   = $this->normDate($_GET['date_to']   ?? null);

        if (!$this->tableExists('tickets') || !$this->colExists('tickets','user_id')){
            echo json_encode(['status'=>true,'data'=>[]]); return;
        }

        $db = db();
        $tStatus  = $this->colExists('tickets','status');
        $useTotal = $this->colExists('tickets','total');
        $dExpr    = $this->tDateExpr();

        if (!$dExpr){ echo json_encode(['status'=>true,'data'=>[]]); return; }

        $where = []; $pr = [];
        if ($tStatus) { $where[] = "(status IS NULL OR status IN ('closed','paid','completed','done'))"; }
        if ($from) { $where[] = "DATE($dExpr) >= ?"; $pr[] = $from; }
        if ($to)   { $where[] = "DATE($dExpr) <= ?"; $pr[] = $to; }

        $sumExpr = $useTotal ? "SUM(total)" : "COUNT(*)";
        $sql = "SELECT user_id, {$sumExpr} AS total, COUNT(*) AS tickets
            FROM tickets ".($where ? "WHERE ".implode(' AND ',$where) : "")."
            GROUP BY user_id
            ORDER BY total DESC";
        $st = $db->prepare($sql); $st->execute($pr);
        echo json_encode(['status'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
