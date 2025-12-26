<?php
class AuthController {

    public function login() {
        if (is_post()) {
            // LOGIN'de CSRF aramayalım; token login sonrası üretilecek.
            $u = trim($_POST['username'] ?? '');
            $p = $_POST['password'] ?? '';

            if ($u === '' || $p === '') json_err('Kullanıcı adı/şifre girin');

            $stmt = db()->prepare("SELECT id,name,username,password_hash,role,active FROM users WHERE username=? AND active=1");
            $stmt->execute([$u]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($p, $user['password_hash'])) {
                json_err('Bilgiler hatalı');
            }

            // Oturum aç
            secure_session_start();
            session_regenerate_id(true);

            $_SESSION['uid']  = (int)$user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['csrf'] = bin2hex(random_bytes(16));           // sonraki POST’lar için
            $_SESSION['fp']   = null;                                // fingerprint login sonrası üretilecek

            // Rolüne göre yönlendirme
            $to = role_home($user['role']);

            json_ok(['redirect' => $to]);
        } else {
            // Girişliyse direk ana sayfasına
            if (is_logged_in()) redirect(role_home($_SESSION['role']));
            view('login.html');
        }
    }

    public function logout() {
        secure_session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('/auth/login');
    }
}
