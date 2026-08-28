<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function html(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443) {
        return true;
    }

    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }

    return false;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'self'; base-uri 'none'");
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }

    return null;
}

function current_user(): ?string
{
    return $_SESSION['user'] ?? null;
}

function ensure_directory(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0755, true) || @mkdir($dir, 0777, true);
}

function read_json(string $path, array $default = []): array
{
    if (!file_exists($path)) {
        return $default;
    }

    $raw = @file_get_contents($path);

    if ($raw === false) {
        return $default;
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : $default;
}

function write_json(string $path, array $data): bool
{
    if (!ensure_directory(dirname($path))) {
        return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $path . '.tmp';

    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    if (@rename($tmp, $path)) {
        return true;
    }

    @unlink($tmp);

    return @file_put_contents($path, $json, LOCK_EX) !== false;
}

function log_action(string $user, string $action, string $details = ''): void
{
    if (!ensure_directory(DATA_DIR)) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $user = str_replace(["\r", "\n"], ' ', $user);
    $action = str_replace(["\r", "\n"], ' ', $action);
    $details = str_replace(["\r", "\n"], ' ', $details);

    $line = sprintf(
        '[%s] IP: %s | USER: %s | ACTION: %s | DETAILS: %s%s',
        date('Y-m-d H:i:s'),
        $ip,
        $user,
        $action,
        $details,
        PHP_EOL
    );

    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function get_logs(int $limit = 100): array
{
    if (!file_exists(LOG_FILE)) {
        return [];
    }

    $lines = @file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    return array_slice(array_reverse($lines), 0, $limit);
}

function get_users(): array
{
    $raw = read_json(USERS_FILE, []);
    $users = [];
    $changed = false;

    foreach ($raw as $name => $data) {
        if (is_string($data)) {
            $users[$name] = [
                'hash' => $data,
                'role' => 'owner',
                'created' => time()
            ];
            $changed = true;
        } else {
            $users[$name] = $data;
        }
    }

    if ($changed) {
        save_users($users);
    }

    return $users;
}

function save_users(array $users): bool
{
    return write_json(USERS_FILE, $users);
}

function is_installed(): bool
{
    return count(get_users()) > 0;
}

function valid_username(string $username): bool
{
    return preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username) === 1;
}

function create_user(string $username, string $password, string $role): bool
{
    $users = get_users();

    if (isset($users[$username])) {
        return false;
    }

    if ($role !== 'owner' && $role !== 'admin') {
        $role = 'admin';
    }

    $users[$username] = [
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'created' => time()
    ];

    return save_users($users);
}

function remove_user(string $username): bool
{
    $users = get_users();

    if (!isset($users[$username])) {
        return false;
    }

    if (($users[$username]['role'] ?? '') === 'owner') {
        $owners = 0;

        foreach ($users as $u) {
            if (($u['role'] ?? '') === 'owner') {
                $owners++;
            }
        }

        if ($owners <= 1) {
            return false;
        }
    }

    unset($users[$username]);

    if (!save_users($users)) {
        return false;
    }

    $sessions = get_sessions();
    $changed = false;

    foreach ($sessions as $sid => $s) {
        if (($s['user'] ?? '') === $username) {
            unset($sessions[$sid]);
            $changed = true;
        }
    }

    if ($changed) {
        save_sessions($sessions);
    }

    return true;
}

function set_password(string $username, string $newPassword): bool
{
    $users = get_users();

    if (!isset($users[$username])) {
        return false;
    }

    $users[$username]['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

    return save_users($users);
}

function change_password(string $username, string $currentPassword, string $newPassword): bool
{
    $users = get_users();

    if (!isset($users[$username])) {
        return false;
    }

    if (!password_verify($currentPassword, (string)($users[$username]['hash'] ?? ''))) {
        return false;
    }

    $users[$username]['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

    return save_users($users);
}

function authenticate(string $username, string $password): ?array
{
    $users = get_users();

    if (!isset($users[$username])) {
        static $dummyHash = null;

        if ($dummyHash === null) {
            $dummyHash = password_hash('dummy-password', PASSWORD_DEFAULT);
        }

        password_verify($password, $dummyHash);

        return null;
    }

    $hash = (string)($users[$username]['hash'] ?? '');

    if ($hash === '' || !password_verify($password, $hash)) {
        return null;
    }

    return $users[$username];
}

function current_role(): string
{
    $user = current_user();

    if ($user === null) {
        return '';
    }

    $users = get_users();

    return (string)($users[$user]['role'] ?? '');
}

function get_sessions(): array
{
    return read_json(SESSIONS_FILE, []);
}

function save_sessions(array $sessions): bool
{
    return write_json(SESSIONS_FILE, $sessions);
}

function register_session(string $user, string $role): string
{
    $sessions = get_sessions();
    $now = time();

    foreach ($sessions as $sid => $s) {
        if ($now - (int)($s['last'] ?? 0) > 2592000) {
            unset($sessions[$sid]);
        }
    }

    $newSid = bin2hex(random_bytes(32));

    $sessions[$newSid] = [
        'user' => $user,
        'role' => $role,
        'created' => $now,
        'last' => $now,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 60)
    ];

    save_sessions($sessions);

    return $newSid;
}

function session_valid(string $sid, string $user): bool
{
    $sessions = get_sessions();

    return isset($sessions[$sid]) && ($sessions[$sid]['user'] ?? '') === $user;
}

function touch_session(string $sid): void
{
    $sessions = get_sessions();

    if (!isset($sessions[$sid])) {
        return;
    }

    if (time() - (int)($sessions[$sid]['last'] ?? 0) < 60) {
        return;
    }

    $sessions[$sid]['last'] = time();

    save_sessions($sessions);
}

function kill_session(string $sid): bool
{
    $sessions = get_sessions();

    if (!isset($sessions[$sid])) {
        return false;
    }

    unset($sessions[$sid]);

    return save_sessions($sessions);
}

function require_login(): void
{
    $user = current_user();
    $sid = $_SESSION['sid'] ?? '';

    $users = get_users();

    if ($user === null || $sid === '' || !isset($users[$user]) || !session_valid($sid, $user)) {
        $_SESSION = [];
        redirect('login.php');
    }

    touch_session($sid);
}

function check_rate_limit(string $ip): bool
{
    $limits = read_json(RATE_LIMIT_FILE, []);

    if (isset($limits[$ip]['until']) && (int)$limits[$ip]['until'] > time()) {
        return false;
    }

    return true;
}

function record_failed_login(string $ip): void
{
    $limits = read_json(RATE_LIMIT_FILE, []);
    $now = time();

    foreach ($limits as $key => $value) {
        if ((int)($value['until'] ?? 0) < $now) {
            unset($limits[$key]);
        }
    }

    if (isset($limits[$ip]['until']) && (int)$limits[$ip]['until'] > $now) {
        return;
    }

    $count = (int)($limits[$ip]['count'] ?? 0) + 1;
    $until = 0;

    if ($count >= LOGIN_MAX_ATTEMPTS) {
        $until = $now + LOGIN_LOCK_SECONDS;
        $count = 0;
    }

    $limits[$ip] = [
        'count' => $count,
        'until' => $until
    ];

    write_json(RATE_LIMIT_FILE, $limits);
}

function reset_rate_limit(string $ip): void
{
    $limits = read_json(RATE_LIMIT_FILE, []);

    if (isset($limits[$ip])) {
        unset($limits[$ip]);
        write_json(RATE_LIMIT_FILE, $limits);
    }
}

function output_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
}

function get_images(): array
{
    $items = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!is_dir(UPLOAD_DIR)) {
        return $items;
    }

    $root = realpath(UPLOAD_DIR);

    if ($root === false) {
        return $items;
    }

    $files = scandir($root);

    if ($files === false) {
        return $items;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $root . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            continue;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        if ((int)@filesize($path) <= 0) {
            continue;
        }

        $mtime = @filemtime($path);

        if ($mtime === false) {
            $mtime = 0;
        }

        $items[] = [
            'file' => $file,
            'url' => 'uploads/images/' . rawurlencode($file),
            'mtime' => $mtime
        ];
    }

    usort($items, function (array $a, array $b): int {
        return $b['mtime'] <=> $a['mtime'];
    });

    return $items;
}

function safe_image_path(string $file): ?string
{
    $file = basename(str_replace(chr(0), '', $file));
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        return null;
    }

    $root = realpath(UPLOAD_DIR);

    if ($root === false) {
        return null;
    }

    $path = realpath($root . DIRECTORY_SEPARATOR . $file);

    if ($path === false || !is_file($path)) {
        return null;
    }

    $prefix = $root . DIRECTORY_SEPARATOR;

    if (strncmp($path, $prefix, strlen($prefix)) !== 0) {
        return null;
    }

    return $path;
}

function serve_image(string $file): void
{
    $path = safe_image_path($file);

    if ($path === null) {
        http_response_code(404);
        exit;
    }

    $info = @getimagesize($path);

    if ($info === false || !isset($info['mime']) || !array_key_exists($info['mime'], ALLOWED_IMAGE_MIME)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $info['mime']);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($path));

    readfile($path);
    exit;
}

function delete_image(string $file): bool
{
    $path = safe_image_path($file);

    if ($path === null) {
        return false;
    }

    return @unlink($path);
}

function delete_all_images(): int
{
    $deleted = 0;

    foreach (get_images() as $image) {
        if (delete_image($image['file'])) {
            $deleted++;
        }
    }

    return $deleted;
}

function upload_image(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'message' => 'Plik nie został przesłany prawidłowo.'
        ];
    }

    $size = (int)($file['size'] ?? 0);

    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        return [
            'ok' => false,
            'message' => 'Plik jest za duży. Maksymalny rozmiar to 8 MB.'
        ];
    }

    if (!ensure_directory(UPLOAD_DIR)) {
        return [
            'ok' => false,
            'message' => 'Nie można utworzyć katalogu uploads/images.'
        ];
    }

    $tmp = $file['tmp_name'] ?? '';

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [
            'ok' => false,
            'message' => 'Nieprawidłowy plik.'
        ];
    }

    $info = @getimagesize($tmp);

    if ($info === false || !isset($info['mime']) || !array_key_exists($info['mime'], ALLOWED_IMAGE_MIME)) {
        return [
            'ok' => false,
            'message' => 'Dozwolone są tylko pliki JPG, PNG, WEBP i GIF.'
        ];
    }

    $extension = ALLOWED_IMAGE_MIME[$info['mime']];
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = UPLOAD_DIR . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($tmp, $destination)) {
        return [
            'ok' => false,
            'message' => 'Nie udało się zapisać pliku.'
        ];
    }

    return [
        'ok' => true,
        'file' => $name
    ];
}