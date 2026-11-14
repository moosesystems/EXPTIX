<?php
// ExpTix command line: php bin/cli.php migrate | create-admin [username] ["Full name"] [email] | seed [--reset] | cron | verify-audit | check-php | backup | token <username> <name> <scopes>
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__));
$cmd = $argv[1] ?? 'help';
if ($cmd === 'seed' && in_array('--reset', $argv, true)) {
    $cfg = require APP_ROOT . '/config/config.php';
    if (($cfg['db']['driver'] ?? 'sqlite') === 'sqlite') { foreach (glob($cfg['db']['path'] . '*') as $f) unlink($f); }
}
require APP_ROOT . '/src/bootstrap.php';
Auth::$via = 'system';
switch ($cmd) {
    case 'migrate': print_r(Schema::migrate()); break;
    case 'create-admin':
        Schema::migrate();
        $un = $argv[2] ?? 'admin';
        if (!preg_match('/^[a-z0-9._-]{2,60}$/', $un)) { echo "Username: 2 to 60 characters, a-z 0-9 . _ -\n"; exit(1); }
        if (Db::one('SELECT id FROM users WHERE username = ?', [$un])) { echo "User $un already exists.\n"; exit(1); }
        $pw = U::token(16); $now = U::now();
        $id = Db::insert('users', ['username' => $un, 'name' => $argv[3] ?? 'Administrator', 'email' => $argv[4] ?? null, 'role' => 'admin', 'title' => 'System administrator',
            'password_hash' => Auth::hash($pw), 'active' => 1, 'must_change_password' => 1, 'password_changed_at' => $now, 'on_shift' => 0, 'created_at' => $now, 'updated_at' => $now]);
        Audit::log('user.create', 'users', $id, ['username' => $un, 'role' => 'admin', 'via' => 'cli'], ['id' => null, 'username' => 'system']);
        echo "Created administrator '$un'. Temporary password: $pw\nSign in and change it straight away. Turn on MFA under My profile.\n"; break;
    case 'seed':
        if (in_array('--reset', $argv, true) && Db::$driver === 'mysql') {
            Db::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_keys(Schema::tables()) as $t) Db::$pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        Schema::migrate();
        if ((int)Db::val('SELECT COUNT(*) FROM users') > 0) { echo "Database already has users. Use seed --reset.\n"; exit(1); }
        $r = Seed::run();
        file_put_contents(APP_ROOT . '/data/demo-credentials.txt', json_encode($r, JSON_PRETTY_PRINT) . "\n"); chmod(APP_ROOT . '/data/demo-credentials.txt', 0600);
        echo json_encode($r, JSON_PRETTY_PRINT), "\n"; break;
    case 'cron':
        $lock = fopen(APP_ROOT . '/data/cron.lock', 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) exit(0);
        $r = Scheduler::run();
        if ($r) U::log('cron', json_encode($r));
        break;
    case 'verify-audit': print_r(Audit::verify()); break;
    case 'check-php': $r = Api::phpRuntimeCheck(); echo ($r['patched'] ? 'OK  ' : 'FIX ') . $r['detail'] . "\n"; exit($r['patched'] ? 0 : 1);
    case 'backup':
        $dir = APP_ROOT . '/data/backups'; @mkdir($dir, 0700, true);
        $f = $dir . '/exptix-' . gmdate('Ymd-His');
        if (Db::$driver === 'sqlite') { Db::$pdo->exec("VACUUM INTO '$f.sqlite'"); $f .= '.sqlite'; }
        else {
            $c = cfg('db'); $cnf = tempnam($dir, 'my'); file_put_contents($cnf, "[client]\nuser={$c['user']}\npassword=\"" . addcslashes($c['pass'], '"\\') . "\"\nhost={$c['host']}\n"); chmod($cnf, 0600);
            exec('mysqldump --defaults-extra-file=' . escapeshellarg($cnf) . ' --single-transaction ' . escapeshellarg($c['name']) . ' | gzip > ' . escapeshellarg("$f.sql.gz"), $o, $rc); unlink($cnf); $f .= '.sql.gz';
        }
        foreach (glob("$dir/exptix-*") as $old) if (filemtime($old) < time() - 14 * 86400) unlink($old);
        Audit::log('system.backup', null, null, ['file' => basename($f)], ['id' => null, 'username' => 'system']);
        echo "$f\n"; break;
    case 'token':
        $u = Db::one('SELECT * FROM users WHERE username = ?', [$argv[2] ?? '']);
        if (!$u) { echo "No such user\n"; exit(1); }
        Auth::$user = $u;
        print_r(Auth::createToken((int)$u['id'], $argv[3] ?? 'CLI token', explode(',', $argv[4] ?? 'read,write,mcp'), null)); break;
    default: echo "migrate | create-admin [username] [\"Full name\"] [email] | seed [--reset] | cron | verify-audit | check-php | backup | token <username> <name> <scopes>\n";
}
