<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'src/functions.php';

start_secure_session();
send_security_headers();
header('Cache-Control: no-store, max-age=0');

if (!is_installed()) {
    redirect('install.php');
}

if (current_user() !== null && !empty($_SESSION['sid']) && session_valid((string)$_SESSION['sid'], (string)current_user())) {
    redirect('admin.php');
}

$error = null;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!check_rate_limit($ip)) {
    $error = 'Zbyt wiele nieudanych prób. Spróbuj ponownie za 10 minut.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf()) {
        $error = 'Błąd bezpieczeństwa. Odśwież stronę.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $authUser = ($username !== '' && $password !== '') ? authenticate($username, $password) : null;

        if ($authUser !== null) {
            session_regenerate_id(true);

            $_SESSION['user'] = $username;
            $_SESSION['role'] = $authUser['role'] ?? 'admin';
            $_SESSION['sid'] = register_session($username, $authUser['role'] ?? 'admin');

            unset($_SESSION['csrf_token']);

            reset_rate_limit($ip);
            log_action($username, 'Zalogowano', 'Pomyślne logowanie');

            redirect('admin.php');
        } else {
            record_failed_login($ip);
            log_action($username !== '' ? $username : 'nieznany', 'Nieudane logowanie', 'IP: ' . $ip);

            $error = 'Błędne dane logowania.';
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logowanie</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    font-family: Inter, Arial, sans-serif;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 380px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    padding: 30px;
}

.logo {
    display: block;
    height: 44px;
    margin: 0 auto 16px;
}

h1 {
    font-size: 22px;
    text-align: center;
    color: #111827;
    margin-bottom: 6px;
}

.sub {
    text-align: center;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    margin-bottom: 24px;
}

label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 6px;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    color: #111827;
    border-radius: 10px;
    padding: 11px 12px;
    outline: none;
    margin-bottom: 16px;
}

input[type="text"]:focus,
input[type="password"]:focus {
    border-color: #111827;
    background: #ffffff;
}

button {
    width: 100%;
    border: 0;
    background: #111827;
    color: #ffffff;
    font-weight: 700;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
}

button:hover {
    background: #1f2937;
}

.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 11px 12px;
    font-size: 13px;
    margin-bottom: 16px;
}
</style>
</head>
<body>
<div class="card">
    <img src="logo.png" alt="" class="logo" onerror="this.style.display='none'">
    <h1>Panel administracyjny</h1>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo html($error); ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">

        <label for="username">Login</label>
        <input type="text" id="username" name="username" required autocomplete="off">

        <label for="password">Hasło</label>
        <input type="password" id="password" name="password" required autocomplete="off">

        <button type="submit" name="login" value="1">Zaloguj się</button>
    </form>
</div>
</body>
</html>
