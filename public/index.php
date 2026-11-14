<?php
declare(strict_types=1);
// PHP built-in server (php -S ... public/index.php): let it serve real files such as assets directly.
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($f !== __DIR__ . '/' && is_file($f) && !str_ends_with($f, '.php')) return false;
}
// The app folder lives outside the web root. Default: ../app next to this public folder.
// Override with SetEnv EXPTIX_APP_ROOT /path/to/app (Apache) or fastcgi_param (nginx).
define('APP_ROOT', rtrim(getenv('EXPTIX_APP_ROOT') ?: dirname(__DIR__) . '/app', '/'));
require APP_ROOT . '/src/bootstrap.php';
App::handle();
