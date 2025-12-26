<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

/* ==== DATABASE CONNECTION ==== */
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = (new Database())->getConnection();
        // TR saat dilimi
        $pdo->exec("SET time_zone = '+03:00'");
    }
    return $pdo;
}

/* ==== SESSION & SECURITY ==== */

/** Güvenli oturum başlat (PHP 7.1 uyumlu) */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ikpos');

        // PHP 7.1: dizi parametreli API yok. Klasik imza kullanılmalı.
        $lifetime = 0;
        $path     = BASE_URL ?: '/';
        $domain   = ''; // varsayılan
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httponly = true;

        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        // SameSite 7.1'de yerel olarak desteklenmez.

        session_start();
    }
}

/** UA/IP parmak izi ile oturum sıkılaştırma */
function enforce_session_fingerprint(): void {
    if (!isset($_SESSION['uid'])) return; // login değilse geç
    $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $dot = strpos($ip, '.');
    $now = hash('sha256', $ua . '|' . ($dot !== false ? substr($ip, 0, $dot) : ''));

    if (!isset($_SESSION['fp'])) {
        $_SESSION['fp'] = $now;
    } elseif ($_SESSION['fp'] !== $now) {
        session_destroy();
        redirect('/auth/login');
    }
}

/* ==== REQUEST HELPERS ==== */

function is_post(): bool {
    return (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST';
}

function is_ajax(): bool {
    $xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
    $acc = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    return ($xhr === 'XMLHttpRequest') || (strpos($acc, 'application/json') !== false);
}

/** BASE_URL kırpılmış, query’siz path (/cashier/main gibi) */
function request_path(): string {
    $req  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $uri  = strtok($req, '?');
    $base = rtrim(BASE_URL, '/');
    if ($base && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    return $uri === '' ? '/' : $uri;
}

/* ==== RESPONSES ==== */

function json_ok($data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err($msg = 'Hata', $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Basit view yükleyici */
function view(string $path, array $vars = []): void {
    if (!empty($vars)) extract($vars, EXTR_SKIP);
    include __DIR__ . '/../view/partials/header.html';
    include __DIR__ . '/../view/' . $path;
}

/* ==== AUTH & ROLE ==== */

function is_logged_in(): bool {
    return !empty($_SESSION['uid']);
}

function role_home(string $role): string {
    if ($role === 'cashier') return '/cashier/main';
    if ($role === 'admin')   return '/admin/main';
    return '/waiter/main';
}

function require_login(): void {
    if (!is_logged_in()) {
        if (is_ajax() || is_post()) json_err('Yetkisiz', 401);
        redirect('/auth/login');
    }
}

function require_role(array $roles): void {
    require_login();
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if (!in_array($role, $roles, true)) {
        if (is_ajax() || is_post()) json_err('Erişim yok', 403);
        redirect(role_home($role));
    }
}

/* ==== CSRF ==== */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Login dışındaki TÜM POST isteklerinde çağır */
function require_csrf(): void {
    $hdr = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    $tok = isset($_POST['csrf']) ? $_POST['csrf'] : $hdr;
    $sess= isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
    if (!$tok || !hash_equals($sess, $tok)) {
        json_err('CSRF doğrulaması başarısız', 419);
    }
}
