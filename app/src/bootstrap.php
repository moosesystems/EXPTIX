<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
const EXPTIX_VERSION = '0.9.0-poc';

$GLOBALS['CFG'] = require APP_ROOT . '/config/config.php';
date_default_timezone_set('UTC'); // all storage is UTC; property zone is applied on display

foreach (['Util','Db','Schema','Audit','Auth','Registry','Tickets','Notify','Push','Integrations','Scheduler','Reports','Api','Mcp','Hooks','OpenApi','Seed','App'] as $f) {
    require_once APP_ROOT . "/src/$f.php";
}

function cfg(string $path, $default = null) {
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $path) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

foreach ([APP_ROOT . '/data', APP_ROOT . '/data/sessions', cfg('uploads.path'), APP_ROOT . '/data/logs'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0770, true);
}

Db::connect(cfg('db'));
