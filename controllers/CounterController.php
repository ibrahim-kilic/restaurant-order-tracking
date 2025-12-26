<?php
class CounterController {
    private function db(){ return db(); }

    // GET ?route=counter
    public function index(){
        require __DIR__ . '/../view/counter/main.php';
    }

    // GET ?route=api/counter/feed
    public function feed(){
        header('Content-Type: application/json; charset=utf-8');
        $since = $_GET['since'] ?? date('Y-m-d H:i:s', time()-1800);
        $q = $this->db()->prepare("SELECT * FROM v_counter_feed WHERE COALESCE(served_at, NOW()) >= ? ORDER BY item_id DESC LIMIT 200");
        $q->execute([$since]);
        echo json_encode(['ok'=>true,'rows'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
