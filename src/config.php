<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Warsaw');

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data');
define('UPLOAD_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images');
define('LOG_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'logs.txt');
define('USERS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'users.json');
define('SESSIONS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'sessions.json');
define('RATE_LIMIT_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'rate_limit.json');

define('ALLOWED_IMAGE_MIME', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif'
]);

define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024);
define('MIN_PASSWORD_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 600);