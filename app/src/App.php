<?php
declare(strict_types=1);

final class App {
    public static function handle(): void {
        $base = cfg('base_path');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = str_starts_with($uri, $base) ? substr($uri, strlen($base)) : $uri;
        $path = '/' . ltrim($path, '/');
        self::headers();
        try {
            if (str_starts_with($path, '/api/')) {
                if (!str_starts_with($path, '/api/public/')) Auth::resolve();
                Api::handle($_SERVER['REQUEST_METHOD'], substr($path, 4));
                return;
            }
            if (str_starts_with($path, '/api')) { Auth::resolve(); Api::handle($_SERVER['REQUEST_METHOD'], substr($path, 4) ?: '/'); return; }
            if ($path === '/mcp') { Auth::resolve(); Mcp::handle(); return; }
            if (preg_match('#^/hooks/([a-z]+)$#', $path, $m)) { Hooks::handle($m[1]); return; }
            if (preg_match('#^/g/(t/)?([A-Za-z0-9_-]{8,40})$#', $path, $m)) { self::page('guest', ['mode' => $m[1] ? 'track' : 'portal', 'token' => $m[2]]); return; }
            if ($path === '/docs') { self::page('docs'); return; }
            self::page('app');
        } catch (HttpError $e) {
            if (str_starts_with($path, '/mcp')) { U::json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => $e->getMessage()]], $e->status); return; }
            U::json(['error' => $e->getMessage()] + $e->extra, $e->status);
        } catch (Throwable $e) {
            $ref = substr(bin2hex(random_bytes(4)), 0, 8);
            U::log('error', "$ref {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
            U::json(['error' => "Something went wrong (reference $ref)."], 500);
        }
    }

    private static function headers(): void {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
        header('Strict-Transport-Security: max-age=31536000');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: blob:; connect-src 'self'; worker-src 'self'; manifest-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }

    private static function page(string $which, array $data = []): void {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $b = htmlspecialchars(cfg('base_path'));
        $lang = htmlspecialchars(explode('-', (string)cfg('locale', 'en'))[0]);
        $pub = dirname($_SERVER['SCRIPT_FILENAME']);
        $v = fn($f) => @filemtime("$pub/assets/$f") ?: time();
        $title = htmlspecialchars(cfg('app_name') . ' · ' . cfg('property_name'));
        $boot = htmlspecialchars(json_encode(['base' => cfg('base_path'), 'app' => cfg('app_name'), 'property' => cfg('property_name'), 'operator' => cfg('operator'), 'timezone' => cfg('timezone'), 'locale' => cfg('locale', 'en-US'), 'currency' => cfg('currency', 'USD'), 'source' => cfg('source_url')] + $data), ENT_QUOTES);
        $script = $which === 'guest' ? 'guest.js' : ($which === 'docs' ? 'docs.js' : 'app.js');
        echo <<<HTML
<!doctype html>
<html lang="$lang">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0F1E24">
<meta name="robots" content="noindex, nofollow">
<title>$title</title>
<link rel="manifest" href="$b/assets/manifest.webmanifest">
<link rel="icon" href="$b/assets/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="$b/assets/icons/icon-192.png">
<link rel="stylesheet" href="$b/assets/app.css?v={$v('app.css')}">
</head>
<body class="page-$which">
<div id="app" data-boot="$boot"><div class="boot-splash"><div class="spinner"></div></div></div>
<script src="$b/assets/$script?v={$v($script)}" defer></script>
</body>
</html>
HTML;
    }
}
