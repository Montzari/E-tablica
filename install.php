<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ensureDirectory(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0755, true) || @mkdir($dir, 0777, true);
}

function writeFile(string $path, string $content): bool
{
    return @file_put_contents($path, $content, LOCK_EX) !== false;
}

function ensureFile(string $path, string $content): bool
{
    if (file_exists($path)) {
        return true;
    }

    return writeFile($path, $content);
}

function readUsers(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $raw = @file_get_contents($path);

    if ($raw === false) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function writeUsers(string $path, array $users): bool
{
    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return writeFile($path, $json);
}

function appendLog(string $path, string $message): void
{
    $line = sprintf(
        '[%s] IP: %s | %s%s',
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        str_replace(["\r", "\n"], ' ', $message),
        PHP_EOL
    );

    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$uploadRoot = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
$uploadDir = $uploadRoot . DIRECTORY_SEPARATOR . 'images';

$usersFile = $dataDir . DIRECTORY_SEPARATOR . 'users.json';
$rateFile = $dataDir . DIRECTORY_SEPARATOR . 'rate_limit.json';
$logFile = $dataDir . DIRECTORY_SEPARATOR . 'logs.txt';

$dataHtaccess = $dataDir . DIRECTORY_SEPARATOR . '.htaccess';
$uploadHtaccess = $uploadRoot . DIRECTORY_SEPARATOR . '.htaccess';
$uploadImagesHtaccess = $uploadDir . DIRECTORY_SEPARATOR . '.htaccess';

$denyHtaccess = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";

$imagesHtaccess = "Options -Indexes\n\n<IfModule mod_authz_core.c>\n    <FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phps|pl|py|jsp|asp|sh|cgi)$\">\n        Require all denied\n    </FilesMatch>\n</IfModule>\n\n<IfModule !mod_authz_core.c>\n    <FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phps|pl|py|jsp|asp|sh|cgi)$\">\n        Order deny,allow\n        Deny from all\n    </FilesMatch>\n</IfModule>\n";

$users = readUsers($usersFile);

$ownerExists = false;

foreach ($users as $entry) {
    $role = is_array($entry) ? (string)($entry['role'] ?? '') : 'owner';

    if ($role === 'owner') {
        $ownerExists = true;
        break;
    }
}

if ($ownerExists) {
    appendLog($logFile, 'INSTALL: zablokowana próba otwarcia instalatora (konto OWNER istnieje)');

    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalator wyłączony</title>
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
    background: #0f172a;
    font-family: Inter, Arial, sans-serif;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 460px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 40px 32px;
    text-align: center;
}

.lock {
    width: 72px;
    height: 72px;
    margin: 0 auto 22px;
    border-radius: 50%;
    background: rgba(220, 38, 38, 0.15);
    border: 1px solid rgba(220, 38, 38, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lock svg {
    width: 30px;
    height: 30px;
    stroke: #f87171;
}

h1 {
    color: #ffffff;
    font-size: 22px;
    margin-bottom: 10px;
}

p {
    color: rgba(255, 255, 255, 0.55);
    font-size: 14px;
    line-height: 1.6;
}

.hint {
    margin-top: 22px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.4);
    font-size: 12px;
    line-height: 1.6;
}

.hint strong {
    color: rgba(255, 255, 255, 0.7);
}

a {
    display: inline-block;
    margin-top: 24px;
    color: #0f172a;
    background: #ffffff;
    font-weight: 700;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 10px;
}
</style>
</head>
<body>
<div class="card">
    <div class="lock">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>

    <h1>Instalator jest wyłączony</h1>
    <p>System posiada już konto OWNER.<br>Ten plik został automatycznie zablokowany.</p>

    <div class="hint">
        Reinstalacja jest możliwa wyłącznie po usunięciu pliku <strong>data/users.json</strong> w menedżerze plików hostingu.
    </div>

    <a href="login.php">Przejdź do logowania</a>
</div>
</body>
</html>
    <?php
    exit;
}

ensureDirectory($dataDir);
ensureDirectory($uploadRoot);
ensureDirectory($uploadDir);

writeFile($dataHtaccess, $denyHtaccess);
writeFile($uploadHtaccess, $imagesHtaccess);
writeFile($uploadImagesHtaccess, $imagesHtaccess);

ensureFile($rateFile, "{}\n");
ensureFile($logFile, '');
ensureFile($usersFile, "[]\n");

$checks = [
    'PHP version 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'JSON extension' => function_exists('json_encode'),
    'Password hashing' => function_exists('password_hash'),
    'Data directory writable' => is_dir($dataDir) && is_writable($dataDir),
    'Users file writable' => file_exists($usersFile) ? is_writable($usersFile) : is_writable($dataDir),
    'Uploads/images writable' => is_dir($uploadDir) && is_writable($uploadDir)
];

$allOk = !in_array(false, $checks, true);

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    if (!$allOk) {
        $error = 'Napraw czerwone wymagania poniżej.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username) !== 1) {
            $error = 'Login może zawierać tylko litery, cyfry, myślnik i podkreślnik. Długość: 3-32.';
        } elseif (strlen($password) < 8) {
            $error = 'Hasło musi mieć minimum 8 znaków.';
        } elseif ($password !== $confirm) {
            $error = 'Hasła nie są identyczne.';
        } else {
            $users = readUsers($usersFile);

            if (isset($users[$username])) {
                $error = 'Konto o tej nazwie już istnieje.';
            } else {
                $users[$username] = [
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'owner',
                    'created' => time()
                ];

                if (writeUsers($usersFile, $users)) {
                    appendLog($logFile, 'INSTALL: utworzono konto OWNER: ' . $username);
                    $success = true;
                } else {
                    $error = 'Nie można zapisać data/users.json. Ustaw chmod 755, a w razie potrzeby 777, na katalogu data.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalacja</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Inter, Arial, sans-serif;
    background: #f3f4f6;
    padding: 30px 16px;
}

.wrap {
    max-width: 700px;
    margin: 0 auto;
}

.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 18px;
}

h1 {
    font-size: 24px;
    margin-bottom: 10px;
}

h2 {
    font-size: 18px;
    margin-bottom: 12px;
}

.muted {
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 14px;
}

.success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 14px;
}

.warning {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 12px;
    margin-top: 14px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

td {
    padding: 9px 8px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.ok {
    color: #166534;
    font-weight: 700;
}

.fail {
    color: #991b1b;
    font-weight: 700;
}

label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    margin: 12px 0 6px;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    border-radius: 10px;
    padding: 11px 12px;
    outline: none;
}

button {
    margin-top: 16px;
    width: 100%;
    border: 0;
    background: #111827;
    color: #ffffff;
    font-weight: 700;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
}

a {
    color: #111827;
    font-weight: 700;
}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Instalacja</h1>
        <div class="muted">
            Kreator tworzy pierwsze konto z rolą OWNER oraz katalogi data i uploads/images.
            Po utworzeniu konta OWNER ten plik wyłączy się automatycznie i można go bezpiecznie zostawić na serwerze.
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">Konto OWNER zostało utworzone.</div>
        <div class="warning">
            Plik install.php wyłączy się automatycznie przy następnej wizycie.
            Przejdź do <a href="login.php">logowania</a>.
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Stan serwera</h2>

        <table>
            <?php foreach ($checks as $label => $ok): ?>
                <tr>
                    <td><?php echo h($label); ?></td>
                    <td class="<?php echo $ok ? 'ok' : 'fail'; ?>">
                        <?php echo $ok ? 'OK' : 'BŁĄD'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if (!$allOk): ?>
            <div class="warning">
                Jeśli data lub uploads ma status BŁĄD: ustaw najpierw chmod 755. Jeśli nadal jest błąd, ustaw tymczasowo chmod 777.
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($users) && !$success): ?>
        <div class="card">
            <h2>Wykryto istniejące konta</h2>

            <div class="muted">
                W systemie istnieją już konta (admin), ale brak konta OWNER.
                Utwórz konto OWNER poniżej, aby odblokować pełne uprawnienia. Istniejące konta zostaną zachowane.
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
        <div class="card">
            <h2>Utwórz konto OWNER</h2>

            <form method="post">
                <label for="username">Login</label>
                <input type="text" id="username" name="username" required autocomplete="off">

                <label for="password">Hasło</label>
                <input type="password" id="password" name="password" required autocomplete="off">

                <label for="confirm">Powtórz hasło</label>
                <input type="password" id="confirm" name="confirm" required autocomplete="off">

                <button type="submit" name="create" value="1">Utwórz konto</button>
            </form>

            <div class="muted" style="margin-top: 12px;">
                Pierwsze konto otrzymuje pełne uprawnienia (rola OWNER). Kolejne konta (admin) dodasz w panelu.
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>