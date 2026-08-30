<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'src/functions.php';

start_secure_session();
send_security_headers();
require_login();

function adminRespond(bool $ok, string $message, array $extra = []): void
{
    if (!empty($_POST['ajax'])) {
        output_json(array_merge([
            'ok' => $ok,
            'message' => $message
        ], $extra));

        exit;
    }

    flash($ok ? 'success' : 'error', $message);
    redirect('admin.php');
}

if (isset($_GET['api']) && $_GET['api'] === 'images') {
    output_json(get_images());
    exit;
}

$user = current_user() ?? '';
$role = current_role();
$isOwner = $role === 'owner';
$minPasswordLength = defined('MIN_PASSWORD_LENGTH') ? (int)MIN_PASSWORD_LENGTH : 8;
$currentSid = $_SESSION['sid'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        adminRespond(false, 'Błąd bezpieczeństwa. Odśwież stronę.');
    }

    if (isset($_POST['upload'])) {
        $result = upload_image($_FILES['image'] ?? []);

        if ($result['ok']) {
            log_action($user, 'Dodano zdjęcie', 'Plik: ' . $result['file']);

            adminRespond(true, 'Dodano zdjęcie.', [
                'file' => $result['file'],
                'url' => 'uploads/images/' . rawurlencode($result['file'])
            ]);
        }

        adminRespond(false, $result['message']);
    }

    if (isset($_POST['delete'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $file = (string)($_POST['file'] ?? '');

        if (delete_image($file)) {
            log_action($user, 'Usunięto zdjęcie', 'Plik: ' . $file);
            adminRespond(true, 'Usunięto zdjęcie.');
        }

        adminRespond(false, 'Nie można usunąć zdjęcia.');
    }

    if (isset($_POST['delete_all'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $count = delete_all_images();

        log_action($user, 'Usunięto wszystkie zdjęcia', 'Liczba usuniętych: ' . $count);
        adminRespond(true, 'Usunięto zdjęcia: ' . $count, [
            'count' => $count
        ]);
    }

    if (isset($_POST['change_password'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < $minPasswordLength) {
            adminRespond(false, 'Nowe hasło musi mieć minimum ' . $minPasswordLength . ' znaków.');
        } elseif ($newPassword !== $confirmPassword) {
            adminRespond(false, 'Nowe hasła nie są identyczne.');
        } elseif (change_password($user, $currentPassword, $newPassword)) {
            log_action($user, 'Zmieniono hasło', 'Zmiana własnego hasła');
            adminRespond(true, 'Hasło zostało zmienione.');
        } else {
            adminRespond(false, 'Aktualne hasło jest nieprawidłowe.');
        }
    }

    if (isset($_POST['add_user'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $newRole = ($_POST['role'] ?? 'admin') === 'owner' ? 'owner' : 'admin';

        if (!valid_username($username)) {
            adminRespond(false, 'Login może zawierać tylko litery, cyfry, myślnik i podkreślnik. Długość: 3-32.');
        } elseif (strlen($password) < $minPasswordLength) {
            adminRespond(false, 'Hasło musi mieć minimum ' . $minPasswordLength . ' znaków.');
        } elseif (create_user($username, $password, $newRole)) {
            log_action($user, 'Dodano konto', 'Login: ' . $username . ' | Rola: ' . $newRole);
            adminRespond(true, 'Dodano konto: ' . $username);
        } else {
            adminRespond(false, 'Konto o tej nazwie już istnieje.');
        }
    }

    if (isset($_POST['remove_user'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $target = trim((string)($_POST['username'] ?? ''));

        if ($target === $user) {
            adminRespond(false, 'Nie można usunąć własnego konta.');
        } elseif (remove_user($target)) {
            log_action($user, 'Usunięto konto', 'Login: ' . $target);
            adminRespond(true, 'Usunięto konto: ' . $target);
        } else {
            adminRespond(false, 'Nie można usunąć konta.');
        }
    }

    if (isset($_POST['reset_password_user'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $target = trim((string)($_POST['reset_username'] ?? ''));
        $newPassword = (string)($_POST['reset_password'] ?? '');

        if (strlen($newPassword) < $minPasswordLength) {
            adminRespond(false, 'Hasło musi mieć minimum ' . $minPasswordLength . ' znaków.');
        } elseif (set_password($target, $newPassword)) {
            log_action($user, 'Zmieniono hasło użytkownika', 'Konto: ' . $target);
            adminRespond(true, 'Hasło użytkownika ' . $target . ' zostało zmienione.');
        } else {
            adminRespond(false, 'Nie można zmienić hasła.');
        }
    }

    if (isset($_POST['kill_session'])) {
        if (!$isOwner) {
            adminRespond(false, 'Brak uprawnień.');
        }

        $sid = (string)($_POST['sid'] ?? '');

        if ($sid === '' || $sid === $currentSid) {
            adminRespond(false, 'Nie można usunąć bieżącej sesji.');
        }

        $sessions = get_sessions();
        $sessionUser = $sessions[$sid]['user'] ?? 'nieznany';

        if (kill_session($sid)) {
            log_action($user, 'Usunięto sesję', 'Konto: ' . $sessionUser);
            adminRespond(true, 'Sesja użytkownika ' . $sessionUser . ' została usunięta.');
        }

        adminRespond(false, 'Nie znaleziono sesji.');
    }
}

$images = null;
$users = [];
$sessions = [];
$logs = [];

if ($isOwner) {
    $users = get_users();
    $sessions = get_sessions();
    $logs = get_logs(100);

    uasort($sessions, function (array $a, array $b): int {
        return (int)($b['last'] ?? 0) <=> (int)($a['last'] ?? 0);
    });
}

$flash = get_flash();
$token = csrf_token();
$maxUploadBytes = defined('MAX_UPLOAD_BYTES') ? (int)MAX_UPLOAD_BYTES : 8 * 1024 * 1024;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel administracyjny</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f3f4f6;
    color: #111827;
    font-family: Inter, Arial, sans-serif;
}

header {
    background: #0f172a;
    color: #ffffff;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 700;
}

.brand img {
    height: 34px;
}

nav {
    display: flex;
    align-items: center;
    gap: 10px;
}

nav a {
    color: #ffffff;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 8px 12px;
    font-weight: 600;
}

nav a:hover {
    background: rgba(255, 255, 255, 0.16);
}

.container {
    max-width: 1200px;
    margin: 24px auto;
    padding: 0 20px;
}

.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
}

.card h2 {
    font-size: 18px;
    margin-bottom: 14px;
}

.flash {
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.flash.success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.flash.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    padding: 30px 20px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.18s ease;
}

.dropzone.dragover {
    border-color: #111827;
    background: #eef2f7;
    transform: scale(1.005);
}

.dropzone strong {
    display: block;
    font-size: 16px;
    margin-bottom: 6px;
}

.muted {
    color: #6b7280;
    font-size: 13px;
}

.progress-wrap {
    height: 8px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 16px;
    display: none;
}

.progress-bar {
    height: 100%;
    width: 0;
    background: #111827;
    transition: width 0.2s ease;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
}

.thumb {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.thumb img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
    background: #0f172a;
}

.thumb-footer {
    padding: 10px;
}

.file-name {
    font-size: 12px;
    color: #6b7280;
    word-break: break-all;
    margin-bottom: 8px;
}

.btn {
    border: 0;
    border-radius: 9px;
    padding: 10px 14px;
    font-weight: 700;
    cursor: pointer;
}

.btn-dark {
    background: #111827;
    color: #ffffff;
}

.btn-red {
    background: #dc2626;
    color: #ffffff;
}

.btn-small {
    padding: 7px 10px;
    font-size: 12px;
}

input[type="password"],
input[type="text"] {
    border: 1px solid #d1d5db;
    background: #f9fafb;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 12px;
}

.pw-form input[type="password"] {
    width: 100%;
}

.empty {
    color: #6b7280;
}

#uploadStatus {
    margin-top: 10px;
    color: #374151;
    font-weight: 600;
}

#fileInput {
    display: none;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}

.table th {
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
}

.table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    vertical-align: middle;
}

.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.badge-owner {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge-admin {
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
}

.badge-now {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.form-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 14px;
}

.form-row input,
.form-row select {
    flex: 1;
    min-width: 160px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 0;
}

.inline {
    display: inline;
}

.right {
    text-align: right;
}

.strong {
    font-weight: 700;
}

.logs {
    background: #0f172a;
    border-radius: 12px;
    padding: 14px;
    max-height: 340px;
    overflow: auto;
    font-family: ui-monospace, Consolas, monospace;
    font-size: 12px;
}

.log-line {
    color: #cbd5e1;
    padding: 3px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    word-break: break-all;
}
</style>
</head>
<body>
<header>
    <div class="brand">
        <img src="logo.png" alt="" onerror="this.style.display='none'">
    </div>

    <nav>
        <a href="index.php" target="_blank" rel="noopener">Podgląd TV</a>
        <a href="logout.php">Wyloguj</a>
    </nav>
</header>

<div class="container">
    <div id="messages">
        <?php if ($flash !== null): ?>
            <div class="flash <?php echo html($flash['type']); ?>">
                <?php echo html($flash['message']); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Wgrywanie zdjęć</h2>

        <div id="dropzone">
            <strong>Przeciągnij zdjęcia tutaj albo</strong>

            <button type="button" id="browseBtn" class="btn btn-dark">Wybierz zdjęcia</button>

            <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif">

            <div class="muted" style="margin-top: 10px;">
                Obsługiwane formaty: JPG, PNG, WEBP, GIF. Maksymalnie 8 MB na plik.
            </div>
        </div>

        <div id="uploadStatus"></div>

        <div id="progressWrap" class="progress-wrap">
            <div id="progressBar" class="progress-bar"></div>
        </div>
    </div>

    <div class="card">
        <h2>Zdjęcia</h2>

        <?php if ($isOwner): ?>
            <div style="margin-bottom: 16px;">
                <button type="button" id="deleteAllBtn" class="btn btn-red">Usuń wszystkie zdjęcia</button>
            </div>
        <?php endif; ?>

        <div id="gallery" class="grid"></div>
    </div>

    <?php if ($isOwner): ?>
        <div class="card">
            <h2>Konta</h2>

            <table class="table">
                <thead>
                <tr>
                    <th>Login</th>
                    <th>Rola</th>
                    <th>Utworzono</th>
                    <th class="right">Akcje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $name => $u): ?>
                    <tr>
                        <td class="strong">
                            <?php echo html($name); ?>
                            <?php if ($name === $user): ?><span class="muted">(Ty)</span><?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo html($u['role'] ?? 'admin'); ?>"><?php echo html($u['role'] ?? 'admin'); ?></span></td>
                        <td class="muted"><?php echo html(date('d.m.Y', (int)($u['created'] ?? time()))); ?></td>
                        <td class="right">
                            <?php if ($name !== $user): ?>
                                <form method="post" action="admin.php" class="inline" onsubmit="return confirm('Usunąć konto <?php echo html($name); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">
                                    <input type="hidden" name="username" value="<?php echo html($name); ?>">
                                    <button type="submit" name="remove_user" value="1" class="btn btn-red btn-small">Usuń</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="admin.php" class="form-row">
                <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">
                <input type="text" name="username" placeholder="Login nowego konta" required>
                <input type="password" name="password" placeholder="Hasło (min <?php echo $minPasswordLength; ?> znaków)" required>
                <select name="role">
                    <option value="admin">admin</option>
                    <option value="owner">owner</option>
                </select>
                <button type="submit" name="add_user" value="1" class="btn btn-dark">Dodaj konto</button>
            </form>

            <form method="post" action="admin.php" class="form-row">
                <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">
                <select name="reset_username">
                    <?php foreach ($users as $name => $u): ?>
                        <option value="<?php echo html($name); ?>"><?php echo html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="password" name="reset_password" placeholder="Nowe hasło dla wybranego konta" required>
                <button type="submit" name="reset_password_user" value="1" class="btn btn-dark">Zmień hasło użytkownika</button>
            </form>
        </div>

        <div class="card">
            <h2>Aktywne sesje</h2>

            <table class="table">
                <thead>
                <tr>
                    <th>Użytkownik</th>
                    <th>IP</th>
                    <th>Zalogowano</th>
                    <th>Ostatnia aktywność</th>
                    <th class="right">Akcje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $sid => $s): ?>
                    <tr>
                        <td class="strong">
                            <?php echo html($s['user'] ?? '?'); ?>
                            <?php if ($sid === $currentSid): ?><span class="badge badge-now">Ta sesja</span><?php endif; ?>
                        </td>
                        <td class="muted"><?php echo html($s['ip'] ?? '?'); ?></td>
                        <td class="muted"><?php echo html(date('d.m.Y H:i', (int)($s['created'] ?? 0))); ?></td>
                        <td class="muted"><?php echo html(date('d.m.Y H:i', (int)($s['last'] ?? 0))); ?></td>
                        <td class="right">
                            <?php if ($sid !== $currentSid): ?>
                                <form method="post" action="admin.php" class="inline" onsubmit="return confirm('Usunąć sesję użytkownika <?php echo html($s['user'] ?? '?'); ?>? Zostanie wylogowany.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">
                                    <input type="hidden" name="sid" value="<?php echo html($sid); ?>">
                                    <button type="submit" name="kill_session" value="1" class="btn btn-red btn-small">Wyloguj</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Logi</h2>

            <div class="logs">
                <?php if (empty($logs)): ?>
                    <div class="log-line">Brak logów.</div>
                <?php else: ?>
                    <?php foreach ($logs as $line): ?>
                        <div class="log-line"><?php echo html($line); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Zmiana hasła</h2>

            <form method="post" action="admin.php" class="pw-form">
                <input type="hidden" name="csrf_token" value="<?php echo html($token); ?>">

                <input type="password" name="current_password" placeholder="Aktualne hasło" required>
                <input type="password" name="new_password" placeholder="Nowe hasło, minimum <?php echo $minPasswordLength; ?> znaków" required>
                <input type="password" name="confirm_password" placeholder="Powtórz nowe hasło" required>

                <button type="submit" name="change_password" value="1" class="btn btn-dark">Zmień hasło</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const maxUploadBytes = <?php echo $maxUploadBytes; ?>;
const isOwner = <?php echo $isOwner ? 'true' : 'false'; ?>;

const messages = document.getElementById('messages');
const gallery = document.getElementById('gallery');
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const browseBtn = document.getElementById('browseBtn');
const uploadStatus = document.getElementById('uploadStatus');
const progressWrap = document.getElementById('progressWrap');
const progressBar = document.getElementById('progressBar');
const deleteAllBtn = document.getElementById('deleteAllBtn');

let isUploading = false;

function esc(value) {
    return String(value).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[c]));
}

function showMessage(type, text) {
    const box = document.createElement('div');
    box.className = 'flash ' + type;
    box.textContent = text;

    messages.prepend(box);

    setTimeout(() => {
        box.remove();
    }, 6000);
}

function setProgress(value) {
    progressWrap.style.display = 'block';
    progressBar.style.width = value + '%';
}

function hideProgress() {
    progressWrap.style.display = 'none';
    progressBar.style.width = '0%';
}

function validateFile(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    const extension = file.name.split('.').pop().toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
        return 'Dozwolone są tylko pliki JPG, PNG, WEBP i GIF.';
    }

    if (file.size > maxUploadBytes) {
        return 'Plik jest za duży. Maksymalnie 8 MB.';
    }

    return null;
}

function uploadFile(file) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();

        formData.append('csrf_token', csrfToken);
        formData.append('upload', '1');
        formData.append('ajax', '1');
        formData.append('image', file);

        const xhr = new XMLHttpRequest();

        xhr.open('POST', 'admin.php');

        xhr.upload.onprogress = event => {
            if (event.lengthComputable) {
                setProgress(Math.round((event.loaded / event.total) * 100));
            }
        };

        xhr.onload = () => {
            if (xhr.status < 200 || xhr.status >= 300) {
                reject(new Error('Błąd serwera.'));
                return;
            }

            try {
                const data = JSON.parse(xhr.responseText);

                if (data.ok) {
                    resolve(data);
                } else {
                    reject(new Error(data.message || 'Błąd wysyłania.'));
                }
            } catch (error) {
                reject(new Error('Nieprawidłowa odpowiedź serwera.'));
            }
        };

        xhr.onerror = () => {
            reject(new Error('Błąd sieci.'));
        };

        xhr.send(formData);
    });
}

async function refreshGallery() {
    try {
        const response = await fetch('admin.php?api=images&t=' + Date.now(), {
            cache: 'no-store'
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        renderGallery(Array.isArray(data) ? data : []);
    } catch (error) {
    }
}

function renderGallery(items) {
    gallery.innerHTML = '';

    if (!items.length) {
        gallery.innerHTML = '<div class="empty">Brak zdjęć. Dodaj pierwsze zdjęcie powyżej.</div>';
        return;
    }

    items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'thumb';

        card.innerHTML = `
            <img src="${esc(item.url)}" alt="${esc(item.file)}">
            <div class="thumb-footer">
                <div class="file-name">${esc(item.file)}</div>
                ${isOwner ? '<button type="button" class="btn btn-red btn-small delete-btn">Usuń</button>' : ''}
            </div>
        `;

        if (isOwner) {
            const deleteBtn = card.querySelector('.delete-btn');

            deleteBtn.addEventListener('click', async () => {
                if (!confirm('Usunąć to zdjęcie?')) {
                    return;
                }

                const formData = new FormData();

                formData.append('csrf_token', csrfToken);
                formData.append('delete', '1');
                formData.append('ajax', '1');
                formData.append('file', item.file);

                try {
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.ok) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.94)';

                        setTimeout(() => {
                            card.remove();
                            refreshGallery();
                        }, 260);

                        showMessage('success', data.message || 'Usunięto zdjęcie.');
                    } else {
                        showMessage('error', data.message || 'Nie można usunąć zdjęcia.');
                    }
                } catch (error) {
                    showMessage('error', 'Błąd usuwania zdjęcia.');
                }
            });
        }

        gallery.appendChild(card);
    });
}

async function uploadFiles(files) {
    const list = Array.from(files || []);

    if (!list.length) {
        return;
    }

    if (isUploading) {
        showMessage('error', 'Trwa już wysyłanie zdjęć.');
        return;
    }

    isUploading = true;

    let ok = 0;
    let fail = 0;

    for (const file of list) {
        const error = validateFile(file);

        if (error) {
            fail++;
            showMessage('error', file.name + ': ' + error);
            continue;
        }

        uploadStatus.textContent = 'Wysyłanie: ' + file.name;
        setProgress(0);

        try {
            await uploadFile(file);
            ok++;
        } catch (uploadError) {
            fail++;
            showMessage('error', file.name + ': ' + uploadError.message);
        }
    }

    if (ok > 0) {
        await refreshGallery();
        showMessage('success', 'Wysłane zdjęcia: ' + ok);
    }

    if (fail > 0) {
        showMessage('error', 'Nie udało się wysłać: ' + fail);
    }

    uploadStatus.textContent = '';
    hideProgress();
    fileInput.value = '';
    isUploading = false;
}

browseBtn.addEventListener('click', () => {
    fileInput.click();
});

fileInput.addEventListener('change', () => {
    uploadFiles(fileInput.files);
});

['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.remove('dragover');
    });
});

dropzone.addEventListener('drop', event => {
    uploadFiles(event.dataTransfer.files);
});

document.addEventListener('dragover', event => {
    event.preventDefault();
});

document.addEventListener('drop', event => {
    event.preventDefault();
});

if (deleteAllBtn) {
    deleteAllBtn.addEventListener('click', async () => {
        if (!confirm('Na pewno usunąć wszystkie zdjęcia?')) {
            return;
        }

        const formData = new FormData();

        formData.append('csrf_token', csrfToken);
        formData.append('delete_all', '1');
        formData.append('ajax', '1');

        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.ok) {
                renderGallery([]);
                showMessage('success', data.message || 'Usunięto wszystkie zdjęcia.');
            } else {
                showMessage('error', data.message || 'Nie można usunąć wszystkich zdjęć.');
            }
        } catch (error) {
            showMessage('error', 'Błąd usuwania wszystkich zdjęć.');
        }
    });
}

refreshGallery();
</script>
</body>
</html>
