<?php
declare(strict_types=1);

class HttpError extends Exception {
    public int $status;
    public array $extra;
    public function __construct(int $status, string $msg, array $extra = []) {
        parent::__construct($msg);
        $this->status = $status;
        $this->extra = $extra;
    }
}

final class U {
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function ts(int $t): string { return gmdate('Y-m-d H:i:s', $t); }
    public static function plusMinutes(int $m, ?string $from = null): string {
        $base = $from ? strtotime($from . ' UTC') : time();
        return gmdate('Y-m-d H:i:s', $base + $m * 60);
    }
    public static function localDate(?int $t = null): string {
        $d = new DateTime('@' . ($t ?? time()));
        $d->setTimezone(new DateTimeZone(cfg('timezone')));
        return $d->format('Y-m-d');
    }
    public static function token(int $bytes = 24): string {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
    public static function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    public static function b64urlDecode(string $s): string { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

    public static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }

    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function body(): array {
        static $b = null;
        if ($b !== null) return $b;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $b = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($b)) throw new HttpError(400, 'Invalid JSON body');
        } else {
            $b = $_POST;
        }
        return $b;
    }

    public static function rawBody(): string {
        static $r = null;
        return $r ??= (string)file_get_contents('php://input');
    }

    public static function str($v, int $max = 65535): ?string {
        if ($v === null) return null;
        $v = trim((string)$v);
        if ($v === '') return null;
        return mb_substr($v, 0, $max);
    }

    public static function log(string $file, string $line): void {
        @file_put_contents(APP_ROOT . "/data/logs/$file.log", '[' . self::now() . "] $line\n", FILE_APPEND | LOCK_EX);
    }

    /** Simple fixed-window rate limiter backed by the database. */
    public static function rateLimit(string $key, int $max, int $windowSec): void {
        $win = (int)(time() / $windowSec) * $windowSec;
        $row = Db::one('SELECT id, hits FROM rate_limits WHERE `key` = ? AND window_start = ?', [$key, $win]);
        if ($row) {
            if ((int)$row['hits'] >= $max) throw new HttpError(429, 'Too many requests. Try again shortly.');
            Db::q('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
        } else {
            Db::insert('rate_limits', ['key' => $key, 'window_start' => $win, 'hits' => 1]);
        }
        if (random_int(1, 50) === 1) Db::q('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
    }

    public static function http(string $method, string $url, array $headers = [], $body = null, int $timeout = 15): array {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) $h[] = "$k: $v";
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        return ['status' => $code, 'body' => $resp === false ? '' : $resp, 'error' => $err, 'json' => is_string($resp) ? json_decode($resp, true) : null];
    }
}
