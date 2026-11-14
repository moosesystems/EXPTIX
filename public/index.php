<?php
declare(strict_types=1);
// The app folder lives outside the web root. Default: ../app next to this public folder.
// Override with SetEnv EXPTIX_APP_ROOT /path/to/app (Apache) or fastcgi_param (nginx).
define('APP_ROOT', rtrim(getenv('EXPTIX_APP_ROOT') ?: dirname(__DIR__) . '/app', '/'));
require APP_ROOT . '/src/bootstrap.php';
App::handle();
