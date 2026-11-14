<?php
declare(strict_types=1);

/**
 * Web Push (RFC 8030) with VAPID (RFC 8292). Pushes carry no payload, so no message encryption is
 * needed: the service worker wakes up and fetches the user's newest notification over the authenticated API.
 */
final class Push {
    public static function keys(): array {
        $pem = Db::val("SELECT value FROM settings WHERE `key` = 'vapid_private'");
        if (!$pem) {
            $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            openssl_pkey_export($k, $pem);
            Db::insert('settings', ['key' => 'vapid_private', 'value' => $pem]);
        }
        $k = openssl_pkey_get_private($pem);
        $d = openssl_pkey_get_details($k);
        $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
        return ['private' => $k, 'public' => U::b64url($pub)];
    }

    public static function publicKey(): string { return self::keys()['public']; }

    private static function jwt(string $aud): string {
        $keys = self::keys();
        $h = U::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $c = U::b64url(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => cfg('push.subject')]));
        openssl_sign("$h.$c", $der, $keys['private'], OPENSSL_ALGO_SHA256);
        return "$h.$c." . U::b64url(self::derToRaw($der));
    }

    private static function derToRaw(string $der): string {
        // SEQUENCE { INTEGER r, INTEGER s } to 64-byte r||s
        $pos = 2; if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    public static function sendToUser(int $userId) {
        $subs = Db::all('SELECT * FROM push_subscriptions WHERE user_id = ?', [$userId]);
        if (!$subs) return 'skipped';
        $pub = self::publicKey();
        foreach ($subs as $s) {
            $p = parse_url($s['endpoint']);
            $aud = $p['scheme'] . '://' . $p['host'];
            $r = U::http('POST', $s['endpoint'], ['TTL' => '3600', 'Urgency' => 'high', 'Authorization' => 'vapid t=' . self::jwt($aud) . ', k=' . $pub, 'Content-Length' => '0'], '', 10);
            if (in_array($r['status'], [404, 410], true)) Db::q('DELETE FROM push_subscriptions WHERE id = ?', [$s['id']]);
        }
        return true;
    }
}
