<?php
declare(strict_types=1);

/**
 * Authentication and authorisation.
 *  - Sessions: secure, HttpOnly, SameSite cookies; idle and absolute timeouts (AC-11, AC-12, SC-23)
 *  - Passwords: Argon2id, NIST 800-63B length rules and blocklist, lockout (IA-5, AC-7)
 *  - MFA: RFC 6238 TOTP (IA-2(1))
 *  - API tokens: random 256-bit, stored as SHA-256 only, scoped, expiring, revocable (IA-5, AC-6)
 *  - RBAC by role rank plus department scope (AC-2, AC-3, AC-6)
 */
final class Auth {
    public static ?array $user = null;
    public static string $via = '';        // session | token | guest | system
    public static array $scopes = [];

    public const ROLES = [
        'admin'      => ['label' => 'Administrator', 'rank' => 100],
        'gm'         => ['label' => 'General Manager', 'rank' => 90],
        'manager'    => ['label' => 'Department Manager', 'rank' => 70],
        'supervisor' => ['label' => 'Supervisor', 'rank' => 50],
        'frontdesk'  => ['label' => 'Front Desk / Guest Services', 'rank' => 40],
        'staff'      => ['label' => 'Staff', 'rank' => 30],
        'contractor' => ['label' => 'Contractor', 'rank' => 20],
        'auditor'    => ['label' => 'Auditor (read-only)', 'rank' => 15],
    ];

    private const BLOCKLIST = ['password', 'password1', 'password123', 'qwerty', '123456', '12345678', '123456789', '1234567890', 'letmein', 'welcome', 'welcome1',
        'hotel', 'hotel2026', 'welcome1', 'qwerty123', 'iloveyou', 'admin', 'administrator', 'changeme', 'hotel123', 'summer2026', 'winter2026', 'passw0rd', 'trustno1'];

    public static function rank(?array $u = null): int {
        $u ??= self::$user;
        return $u ? (self::ROLES[$u['role']]['rank'] ?? 0) : 0;
    }
    public static function isAuditor(?array $u = null): bool { return (($u ?? self::$user)['role'] ?? '') === 'auditor'; }

    public static function require(int $minRank = 1, bool $write = false): array {
        if (!self::$user) throw new HttpError(401, 'Sign in required');
        if ($write && self::isAuditor()) throw new HttpError(403, 'Auditor accounts are read-only');
        if ($write && self::$via === 'token' && !self::hasScope('write')) throw new HttpError(403, 'Token lacks write scope');
        if (self::rank() < $minRank && !(self::isAuditor() && !$write)) throw new HttpError(403, 'You do not have permission for this action');
        return self::$user;
    }

    public static function hasScope(string $s): bool {
        return self::$via !== 'token' || in_array($s, self::$scopes, true) || in_array('admin', self::$scopes, true);
    }

    /** Department ids the user belongs to. */
    public static function departments(?array $u = null): array {
        $u ??= self::$user;
        if (!$u) return [];
        $ids = array_filter([(int)($u['department_id'] ?? 0)]);
        foreach ((array)json_decode((string)($u['extra_departments'] ?? '[]'), true) as $d) $ids[] = (int)$d;
        return array_values(array_unique(array_filter($ids)));
    }

    /** Can this user see every department's tickets? */
    public static function seesAll(?array $u = null): bool {
        $u ??= self::$user;
        return in_array($u['role'] ?? '', ['admin', 'gm', 'auditor', 'frontdesk'], true);
    }

    // ---------- sessions ----------
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_save_path(APP_ROOT . '/data/sessions');
        session_name('exptix_sid');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)(cfg('security.session_max_hours') * 3600));
        session_set_cookie_params(['lifetime' => 0, 'path' => cfg('base_path') . '/', 'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    public static function resolve(): void {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $hdr, $m)) { self::fromToken($m[1]); return; }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) { self::fromToken($_SERVER['HTTP_X_API_KEY']); return; }

        self::startSession();
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) return;
        $now = time();
        $idle = cfg('security.session_idle_minutes') * 60;
        $max = cfg('security.session_max_hours') * 3600;
        if ($now - ($_SESSION['last'] ?? 0) > $idle || $now - ($_SESSION['created'] ?? 0) > $max) {
            Audit::log('auth.session_timeout', 'users', $uid, [], ['id' => $uid, 'username' => $_SESSION['username'] ?? '']);
            self::destroySession();
            return;
        }
        $u = Db::one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]);
        if (!$u) { self::destroySession(); return; }
        $_SESSION['last'] = $now;
        self::$user = $u;
        self::$via = 'session';
    }

    private static function fromToken(string $raw): void {
        $t = Db::one('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked = 0', [hash('sha256', $raw)]);
        if (!$t || ($t['expires_at'] && $t['expires_at'] < U::now())) {
            U::rateLimit('badtoken:' . U::ip(), 30, 300);
            throw new HttpError(401, 'Invalid or expired API token');
        }
        $u = Db::one('SELECT * FROM users WHERE id = ? AND active = 1', [$t['user_id']]);
        if (!$u) throw new HttpError(401, 'Token owner is disabled');
        self::$user = $u;
        self::$via = 'token';
        self::$scopes = array_filter(array_map('trim', explode(',', (string)$t['scopes'])));
        if (!$t['last_used_at'] || strtotime($t['last_used_at'] . ' UTC') < time() - 60) {
            Db::update('api_tokens', ['last_used_at' => U::now(), 'last_used_ip' => U::ip()], (int)$t['id']);
        }
    }

    public static function csrfToken(): string {
        self::startSession();
        return $_SESSION['csrf'] ??= U::token(32);
    }

    public static function checkCsrf(): void {
        if (self::$via !== 'session') return;
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) throw new HttpError(419, 'Security token expired. Reload the page.');
    }

    public static function destroySession(): void {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        self::$user = null;
    }

    // ---------- login ----------
    public static function login(string $username, string $password): array {
        U::rateLimit('login:' . U::ip(), 20, 300);
        $u = Db::one('SELECT * FROM users WHERE (username = ? OR email = ?)', [strtolower(trim($username)), strtolower(trim($username))]);
        $fail = function (string $why) use ($u, $username) {
            Audit::log('auth.login_failed', 'users', $u['id'] ?? null, ['username' => $username, 'reason' => $why], $u ?: ['id' => null, 'username' => $username]);
            throw new HttpError(401, 'Incorrect username or password');
        };
        if (!$u || !$u['active']) { password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$ZmFrZWhhc2g'); $fail('unknown_or_inactive'); }
        if ($u['locked_until'] && $u['locked_until'] > U::now()) {
            Audit::log('auth.login_locked', 'users', $u['id'], [], $u);
            throw new HttpError(423, 'Account temporarily locked after repeated failed sign-ins. Try again later or contact an administrator.');
        }
        if (!password_verify($password, (string)$u['password_hash'])) {
            $n = (int)$u['failed_logins'] + 1;
            $upd = ['failed_logins' => $n];
            if ($n >= cfg('security.lockout_threshold')) { $upd['locked_until'] = U::plusMinutes(cfg('security.lockout_minutes')); $upd['failed_logins'] = 0; }
            Db::update('users', $upd, (int)$u['id']);
            $fail('bad_password');
        }
        if (password_needs_rehash($u['password_hash'], self::algo())) Db::update('users', ['password_hash' => self::hash($password)], (int)$u['id']);

        self::startSession();
        session_regenerate_id(true);
        $mfaForced = self::rank($u) >= cfg('security.mfa_required_ranks_at_or_above');
        if ($u['mfa_enabled']) {
            $_SESSION = ['mfa_uid' => $u['id'], 'mfa_at' => time()];
            return ['mfa_required' => true];
        }
        self::completeLogin($u);
        return ['ok' => true, 'mfa_setup_required' => $mfaForced];
    }

    public static function verifyMfa(string $code): array {
        self::startSession();
        U::rateLimit('mfa:' . U::ip(), 10, 300);
        $uid = $_SESSION['mfa_uid'] ?? null;
        if (!$uid || time() - ($_SESSION['mfa_at'] ?? 0) > 300) throw new HttpError(401, 'Sign-in expired. Start again.');
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$u || !self::totpVerify((string)$u['mfa_secret'], $code)) {
            Audit::log('auth.mfa_failed', 'users', $uid, [], $u);
            throw new HttpError(401, 'That code did not match. Check your authenticator app.');
        }
        session_regenerate_id(true);
        self::completeLogin($u);
        return ['ok' => true];
    }

    private static function completeLogin(array $u): void {
        $_SESSION = ['uid' => (int)$u['id'], 'username' => $u['username'], 'created' => time(), 'last' => time(), 'csrf' => U::token(32)];
        Db::update('users', ['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => U::now(), 'last_login_ip' => U::ip()], (int)$u['id']);
        self::$user = $u; self::$via = 'session';
        Audit::log('auth.login', 'users', $u['id'], ['mfa' => (bool)$u['mfa_enabled']], $u);
    }

    public static function algo() { return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT; }
    public static function hash(string $p): string { return password_hash($p, self::algo()); }

    public static function validatePassword(string $p, string $username = ''): void {
        $min = cfg('security.password_min_length');
        if (mb_strlen($p) < $min) throw new HttpError(422, "Passwords need at least $min characters. A short phrase works well.");
        if (mb_strlen($p) > 128) throw new HttpError(422, 'Passwords can be at most 128 characters.');
        $l = strtolower($p);
        foreach (self::BLOCKLIST as $b) if ($l === $b || ($b !== '' && str_contains($l, $b) && mb_strlen($l) - mb_strlen($b) < 4)) throw new HttpError(422, 'That password is too common. Choose something less predictable.');
        if ($username && str_contains($l, strtolower($username))) throw new HttpError(422, 'Passwords cannot contain your username.');
        if (preg_match('/^(.)\1+$/', $p)) throw new HttpError(422, 'Passwords cannot be a single repeated character.');
    }

    // ---------- TOTP (RFC 6238) ----------
    public static function totpSecret(): string {
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = '';
        for ($i = 0; $i < 32; $i++) $s .= $alpha[random_int(0, 31)];
        return $s;
    }
    private static function b32decode(string $s): string {
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
        foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
        return $out;
    }
    public static function totpCode(string $secret, int $step): string {
        $h = hash_hmac('sha1', pack('N*', 0, $step), self::b32decode($secret), true);
        $o = ord($h[19]) & 0xf;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }
    public static function totpVerify(string $secret, string $code): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || $secret === '') return false;
        $t = (int)floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) if (hash_equals(self::totpCode($secret, $t + $i), $code)) return true;
        return false;
    }

    // ---------- API tokens ----------
    public static function createToken(int $userId, string $name, array $scopes, ?string $expires): array {
        $raw = 'xtx_' . U::token(32);
        $id = Db::insert('api_tokens', ['name' => $name, 'user_id' => $userId, 'token_hash' => hash('sha256', $raw), 'prefix' => substr($raw, 0, 12),
            'scopes' => implode(',', $scopes), 'expires_at' => $expires, 'created_by' => self::$user['id'] ?? null, 'created_at' => U::now()]);
        Audit::log('token.create', 'api_tokens', $id, ['name' => $name, 'scopes' => $scopes, 'for_user' => $userId]);
        return ['id' => $id, 'token' => $raw];
    }

    public static function publicUser(?array $u = null): ?array {
        $u ??= self::$user;
        if (!$u) return null;
        return ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'],
            'role_label' => self::ROLES[$u['role']]['label'] ?? $u['role'], 'rank' => self::rank($u), 'department_id' => $u['department_id'] ? (int)$u['department_id'] : null,
            'departments' => self::departments($u), 'title' => $u['title'], 'mfa_enabled' => (bool)$u['mfa_enabled'], 'phone' => $u['phone'],
            'notify_email' => (bool)$u['notify_email'], 'notify_sms' => (bool)$u['notify_sms'], 'notify_push' => (bool)$u['notify_push'], 'on_shift' => (bool)$u['on_shift'],
            'must_change_password' => (bool)$u['must_change_password'], 'sees_all' => self::seesAll($u), 'read_only' => self::isAuditor($u)];
    }
}
