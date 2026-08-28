<?php
declare(strict_types=1);

require_once 'src/functions.php';

start_secure_session();
send_security_headers();

$user = current_user();
$sid = $_SESSION['sid'] ?? '';

if ($user !== null) {
    log_action($user, 'Wylogowano', 'Wylogowanie użytkownika');
}

if ($sid !== '') {
    kill_session($sid);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

redirect('login.php');