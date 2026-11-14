<?php
declare(strict_types=1);

final class Api {
    private static array $routes = [];
    private static function on(string $m, string $pattern, callable $fn): void { self::$routes[] = [$m, '#^' . $pattern . '$#', $fn]; }

    public static function handle(string $method, string $path): void {
        self::register();
        foreach (self::$routes as [$m, $re, $fn]) {
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) continue;
            if (preg_match($re, $path, $mm)) {
                $args = array_values(array_filter($mm, fn($k) => is_int($k) && $k > 0, ARRAY_FILTER_USE_KEY));
                if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !str_starts_with($path, '/auth/') && !str_starts_with($path, '/public/')) Auth::checkCsrf();
                $r = $fn(...$args);
                if ($r !== null) U::json($r);
                return;
            }
        }
        throw new HttpError(404, 'No such endpoint');
    }

    private static function q(): array { return $_GET; }

    private static function register(): void {
        if (self::$routes) return;
        $b = fn() => U::body();

        // ---------- health and auth ----------
        self::on('GET', '/health', fn() => ['ok' => true, 'app' => cfg('app_name'), 'version' => EXPTIX_VERSION, 'db' => Db::$driver, 'time' => gmdate('c')]);
        self::on('POST', '/auth/login', function () use ($b) { $in = $b(); return Auth::login((string)($in['username'] ?? ''), (string)($in['password'] ?? '')) + ['csrf' => $_SESSION['csrf'] ?? null]; });
        self::on('POST', '/auth/mfa', function () use ($b) { return Auth::verifyMfa((string)($b()['code'] ?? '')) + ['csrf' => $_SESSION['csrf'] ?? null]; });
        self::on('POST', '/auth/logout', function () { if (Auth::$user) Audit::log('auth.logout', 'users', Auth::$user['id']); Auth::destroySession(); return ['ok' => true]; });
        self::on('GET', '/auth/me', fn() => ['user' => Auth::publicUser(), 'csrf' => Auth::$via === 'session' ? Auth::csrfToken() : null]);

        // ---------- guest portal (no sign-in, rate limited) ----------
        self::on('GET', '/public/portal/([A-Za-z0-9_-]{8,40})', fn($tok) => self::portalInfo($tok));
        self::on('POST', '/public/portal/([A-Za-z0-9_-]{8,40})', fn($tok) => self::portalSubmit($tok, $b()));
        self::on('GET', '/public/ticket/([A-Za-z0-9_-]{8,40})', fn($tok) => self::guestTicket($tok));
        self::on('POST', '/public/ticket/([A-Za-z0-9_-]{8,40})/message', fn($tok) => self::guestMessage($tok, $b()));
        self::on('POST', '/public/ticket/([A-Za-z0-9_-]{8,40})/rate', fn($tok) => self::guestRate($tok, $b()));

        // ---------- everything below needs a signed-in user or token ----------
        self::on('GET', '/bootstrap', fn() => self::bootstrap());
        self::on('GET', '/search', fn() => self::search((string)($_GET['q'] ?? '')));
        self::on('GET', '/refs/([a-z_0-9]+)', fn($e) => self::refs($e));
        self::on('GET', '/dashboard', function () { Auth::require(); Scheduler::maybeRun(); return Reports::dashboard(); });
        self::on('GET', '/reports', fn() => Reports::full(self::q()));
        self::on('GET', '/reports/tickets\.csv', function () { Reports::csv(self::q()); return null; });
        self::on('GET', '/calendar', fn() => self::calendar());

        // tickets
        self::on('GET', '/tickets', function () { Auth::require(); return Tickets::list(self::q()); });
        self::on('POST', '/tickets', function () use ($b) { Auth::require(20, true); $src = Auth::$via === 'token' ? 'api' : ($b()['source'] ?? 'staff'); return Tickets::create($b(), in_array($src, ['staff', 'phone', 'front_desk', 'api'], true) ? $src : 'staff'); });
        self::on('GET', '/tickets/(\d+)', function ($id) { Auth::require(); return Tickets::detail((int)$id); });
        self::on('PATCH', '/tickets/(\d+)', fn($id) => Tickets::update((int)$id, $b()));
        self::on('POST', '/tickets/(\d+)/comments', fn($id) => Tickets::comment((int)$id, (string)($b()['body'] ?? ''), (string)($b()['visibility'] ?? 'internal')));
        self::on('POST', '/tickets/(\d+)/time', function ($id) use ($b) { Tickets::logTime((int)$id, (int)($b()['minutes'] ?? 0), $b()['note'] ?? null); return ['ok' => true]; });
        self::on('POST', '/tickets/(\d+)/parts', function ($id) use ($b) { Tickets::usePart((int)$id, (int)($b()['part_id'] ?? 0), (float)($b()['qty'] ?? 1)); return ['ok' => true]; });
        self::on('POST', '/tickets/(\d+)/checklist', function ($id) use ($b) { Tickets::get((int)$id); Auth::require(20, true); Tickets::addChecklist((int)$id, (string)($b()['item'] ?? '')); return ['ok' => true]; });
        self::on('PATCH', '/tickets/(\d+)/checklist/(\d+)', function ($id, $cid) use ($b) {
            Tickets::get((int)$id); Auth::require(20, true);
            $done = !empty($b()['done']);
            Db::q('UPDATE ticket_checklist SET done = ?, done_by = ?, done_at = ? WHERE id = ? AND ticket_id = ?', [$done ? 1 : 0, $done ? Auth::$user['id'] : null, $done ? U::now() : null, (int)$cid, (int)$id]);
            Db::q('UPDATE tickets SET updated_at = ? WHERE id = ?', [U::now(), (int)$id]);
            return ['ok' => true];
        });
        self::on('POST', '/tickets/(\d+)/attachments', function ($id) {
            Tickets::get((int)$id); Auth::require(20, true);
            if (empty($_FILES['file'])) throw new HttpError(422, 'No file');
            return Tickets::storeUpload('tickets', (int)$id, $_FILES['file']);
        });
        self::on('POST', '/tickets/(\d+)/children', function ($id) use ($b) { $p = Tickets::get((int)$id); $in = $b(); $in['parent_id'] = (int)$id; $in['room_id'] ??= $p['room_id']; return Tickets::create($in); });
        self::on('POST', '/tickets/bulk', function () use ($b) {
            Auth::require(50, true); $in = $b(); $n = 0;
            foreach (array_slice((array)($in['ids'] ?? []), 0, 200) as $id) { try { Tickets::update((int)$id, (array)($in['changes'] ?? [])); $n++; } catch (HttpError $e) {} }
            return ['updated' => $n];
        });

        // attachments
        self::on('GET', '/files/(\d+)', function ($id) {
            Auth::require();
            $a = Db::one('SELECT * FROM attachments WHERE id = ?', [(int)$id]);
            if (!$a) throw new HttpError(404, 'File not found');
            if ($a['entity'] === 'tickets') Tickets::get((int)$a['entity_id']);
            $path = cfg('uploads.path') . '/' . $a['stored_name'];
            if (!is_file($path)) throw new HttpError(404, 'File missing');
            header('Content-Type: ' . $a['mime']);
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: ' . (str_starts_with($a['mime'], 'image/') || $a['mime'] === 'application/pdf' ? 'inline' : 'attachment') . '; filename="' . rawurlencode($a['filename']) . '"');
            header('Cache-Control: private, max-age=3600');
            readfile($path);
            return null;
        });
        self::on('POST', '/e/([a-z_0-9]+)/(\d+)/attachments', function ($e, $id) {
            Registry::get($e); Auth::require(20, true);
            if (empty($_FILES['file'])) throw new HttpError(422, 'No file');
            return Tickets::storeUpload($e, (int)$id, $_FILES['file']);
        });

        // housekeeping
        self::on('GET', '/hk/board', fn() => self::hkBoard());
        self::on('POST', '/hk/rooms/(\d+)', function ($id) use ($b) {
            Auth::require(20, true);
            $in = $b();
            if (Auth::rank() < 40) $in = array_intersect_key($in, array_flip(['hk_status', 'dnd', 'make_up_room', 'eco_skip', 'notes']));
            if (($in['hk_status'] ?? null) === 'inspected' && Auth::rank() < 50) throw new HttpError(403, 'Only supervisors can mark rooms inspected');
            return Rooms::setStatus((int)$id, $in, 'staff');
        });
        self::on('POST', '/hk/assign', function () use ($b) {
            Auth::require(50, true); $in = $b(); $n = 0;
            foreach ((array)($in['room_ids'] ?? []) as $rid) { Rooms::setStatus((int)$rid, ['attendant_id' => $in['attendant_id'] ? (int)$in['attendant_id'] : null, 'hk_section' => $in['section'] ?? null], 'staff'); $n++; }
            return ['assigned' => $n];
        });
        self::on('POST', '/hk/auto-assign', fn() => self::hkAutoAssign());
        self::on('GET', '/rooms/(\d+)/history', function ($id) {
            Auth::require();
            return ['log' => Db::all('SELECT l.*, u.name AS user_name FROM room_status_log l LEFT JOIN users u ON u.id = l.user_id WHERE room_id = ? ORDER BY l.id DESC LIMIT 100', [(int)$id]),
                'tickets' => Db::all('SELECT id, number, title, status, priority, created_at FROM tickets WHERE room_id = ? ORDER BY id DESC LIMIT 50', [(int)$id])];
        });

        // food safety
        self::on('GET', '/food/points', function () { Auth::require(); return ['points' => FoodSafety::dueChecks()]; });
        self::on('POST', '/food/readings', function () use ($b) { Auth::require(20, true); $in = $b(); return FoodSafety::record((int)($in['point_id'] ?? 0), (float)($in['reading_c'] ?? 0), 'manual', $in['corrective_action'] ?? null); });

        // checklists / inspections
        self::on('POST', '/checklists/run', fn() => self::runChecklist($b()));

        // logbook acknowledgement
        self::on('POST', '/logbook/(\d+)/ack', function ($id) {
            Auth::require(20, true);
            $l = Db::one('SELECT * FROM logbook WHERE id = ?', [(int)$id]);
            if (!$l) throw new HttpError(404, 'Not found');
            $acks = json_decode((string)$l['acknowledged_by'], true) ?: [];
            $acks[(string)Auth::$user['id']] = ['name' => Auth::$user['name'], 'at' => U::now()];
            Db::update('logbook', ['acknowledged_by' => json_encode($acks)], (int)$id);
            return ['ok' => true];
        });

        // minibar
        self::on('POST', '/minibar', function () use ($b) {
            Auth::require(20, true); $in = $b();
            $id = Db::insert('minibar_postings', ['room_id' => (int)$in['room_id'], 'item' => U::str($in['item'] ?? null, 120) ?? 'Item', 'qty' => max(1, (int)($in['qty'] ?? 1)), 'amount' => (float)($in['amount'] ?? 0), 'posted_by' => Auth::$user['id'], 'created_at' => U::now()]);
            Integrations::postMinibar($id);
            Audit::log('minibar.post', 'minibar_postings', $id, $in);
            return Db::one('SELECT * FROM minibar_postings WHERE id = ?', [$id]);
        });

        // purchase requests
        self::on('POST', '/pr/(\d+)/(approve|reject|ordered|received)', function ($id, $act) use ($b) {
            Auth::require($act === 'received' ? 30 : 70, true);
            $map = ['approve' => 'approved', 'reject' => 'rejected', 'ordered' => 'ordered', 'received' => 'received'];
            $upd = ['status' => $map[$act], 'updated_at' => U::now()];
            if ($act === 'approve') { $upd['approved_by'] = Auth::$user['id']; $upd['approved_at'] = U::now(); }
            if (!empty($b()['po_number'])) $upd['po_number'] = U::str($b()['po_number'], 40);
            Db::update('purchase_requests', $upd, (int)$id);
            Audit::log("pr.$act", 'purchase_requests', $id, $upd);
            return Db::one('SELECT * FROM purchase_requests WHERE id = ?', [(int)$id]);
        });

        // stock
        self::on('POST', '/parts/(\d+)/adjust', function ($id) use ($b) {
            Auth::require(30, true); $in = $b(); $qty = (float)($in['qty'] ?? 0);
            if (!$qty) throw new HttpError(422, 'Quantity required');
            Db::q('UPDATE parts SET qty_on_hand = qty_on_hand + ?, updated_at = ? WHERE id = ?', [$qty, U::now(), (int)$id]);
            Db::insert('stock_movements', ['part_id' => (int)$id, 'qty' => $qty, 'reason' => U::str($in['reason'] ?? 'adjust', 40), 'user_id' => Auth::$user['id'], 'note' => U::str($in['note'] ?? null, 255), 'created_at' => U::now()]);
            Audit::log('stock.adjust', 'parts', $id, ['qty' => $qty, 'reason' => $in['reason'] ?? null]);
            return Db::one('SELECT * FROM parts WHERE id = ?', [(int)$id]);
        });
        self::on('GET', '/parts/(\d+)/movements', function ($id) { Auth::require(); return ['rows' => Db::all('SELECT m.*, u.name AS user_name FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id WHERE part_id = ? ORDER BY m.id DESC LIMIT 100', [(int)$id])]; });

        // notifications and push
        self::on('GET', '/notifications', function () {
            Auth::require(); $uid = (int)Auth::$user['id'];
            Scheduler::maybeRun();
            $w = !empty($_GET['unread']) ? 'AND read_at IS NULL' : '';
            $lim = min(100, max(1, (int)($_GET['limit'] ?? 30)));
            return ['unread' => (int)Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$uid]),
                'rows' => Db::all("SELECT * FROM notifications WHERE user_id = ? $w ORDER BY id DESC LIMIT $lim", [$uid])];
        });
        self::on('POST', '/notifications/read', function () use ($b) {
            Auth::require(); $ids = array_map('intval', (array)($b()['ids'] ?? []));
            if ($ids) Db::q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND id IN (' . implode(',', $ids) . ')', [U::now(), Auth::$user['id']]);
            else Db::q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [U::now(), Auth::$user['id']]);
            return ['ok' => true];
        });
        self::on('GET', '/push/key', fn() => ['key' => Push::publicKey()]);
        self::on('POST', '/push/subscribe', function () use ($b) {
            Auth::require(); $s = $b();
            if (empty($s['endpoint']) || !preg_match('#^https://#', $s['endpoint'])) throw new HttpError(422, 'Bad subscription');
            Db::q('DELETE FROM push_subscriptions WHERE endpoint = ?', [$s['endpoint']]);
            Db::insert('push_subscriptions', ['user_id' => Auth::$user['id'], 'endpoint' => $s['endpoint'], 'p256dh' => $s['keys']['p256dh'] ?? '', 'auth' => $s['keys']['auth'] ?? '', 'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'created_at' => U::now()]);
            return ['ok' => true];
        });
        self::on('POST', '/push/test', function () { Auth::require(); Notify::inapp((int)Auth::$user['id'], 'Test notification', 'Push alerts are working on this device.', '#/', 'info'); Notify::dispatch(); return ['ok' => true]; });

        // account
        self::on('POST', '/account/password', function () use ($b) {
            Auth::require(1); $in = $b(); $u = Auth::$user;
            if (!password_verify((string)($in['current'] ?? ''), (string)$u['password_hash'])) throw new HttpError(422, 'Current password is incorrect');
            Auth::validatePassword((string)($in['new'] ?? ''), $u['username']);
            Db::update('users', ['password_hash' => Auth::hash((string)$in['new']), 'password_changed_at' => U::now(), 'must_change_password' => 0], (int)$u['id']);
            Audit::log('auth.password_change', 'users', $u['id']);
            return ['ok' => true];
        });
        self::on('POST', '/account/mfa/setup', function () {
            Auth::require(1); $s = Auth::totpSecret(); $_SESSION['mfa_pending_secret'] = $s;
            $label = rawurlencode(cfg('app_name') . ':' . Auth::$user['username']);
            return ['secret' => $s, 'uri' => "otpauth://totp/$label?secret=$s&issuer=" . rawurlencode(cfg('app_name') . ' ' . cfg('property_code')) . '&algorithm=SHA1&digits=6&period=30'];
        });
        self::on('POST', '/account/mfa/enable', function () use ($b) {
            Auth::require(1); $s = $_SESSION['mfa_pending_secret'] ?? '';
            if (!$s || !Auth::totpVerify($s, (string)($b()['code'] ?? ''))) throw new HttpError(422, 'Code did not match. Try the next code.');
            Db::update('users', ['mfa_secret' => $s, 'mfa_enabled' => 1], (int)Auth::$user['id']);
            unset($_SESSION['mfa_pending_secret']);
            Audit::log('auth.mfa_enabled', 'users', Auth::$user['id']);
            return ['ok' => true];
        });
        self::on('POST', '/account/mfa/disable', function () use ($b) {
            Auth::require(1);
            if (!Auth::totpVerify((string)Auth::$user['mfa_secret'], (string)($b()['code'] ?? ''))) throw new HttpError(422, 'Enter a current code to turn MFA off');
            Db::update('users', ['mfa_secret' => null, 'mfa_enabled' => 0], (int)Auth::$user['id']);
            Audit::log('auth.mfa_disabled', 'users', Auth::$user['id']);
            return ['ok' => true];
        });
        self::on('PATCH', '/account', function () use ($b) {
            Auth::require(1); $in = $b(); $upd = [];
            foreach (['notify_email', 'notify_sms', 'notify_push', 'on_shift'] as $k) if (array_key_exists($k, $in)) $upd[$k] = $in[$k] ? 1 : 0;
            foreach (['phone' => 40, 'email' => 190] as $k => $len) if (array_key_exists($k, $in)) $upd[$k] = U::str($in[$k], $len);
            if (isset($upd['email']) && $upd['email'] && !filter_var($upd['email'], FILTER_VALIDATE_EMAIL)) throw new HttpError(422, 'Email address is not valid');
            if ($upd) { $upd['updated_at'] = U::now(); Db::update('users', $upd, (int)Auth::$user['id']); Audit::log('account.update', 'users', Auth::$user['id'], array_keys($upd)); }
            return Auth::publicUser(Db::one('SELECT * FROM users WHERE id = ?', [Auth::$user['id']]));
        });

        // admin
        self::on('POST', '/admin/users', fn() => self::adminCreateUser($b()));
        self::on('POST', '/admin/users/(\d+)/(reset-password|unlock|reset-mfa)', fn($id, $act) => self::adminUserAction((int)$id, $act));
        self::on('GET', '/admin/tokens', function () { Auth::require(90); return ['rows' => Db::all('SELECT t.id, t.name, t.prefix, t.scopes, t.last_used_at, t.last_used_ip, t.expires_at, t.revoked, t.created_at, u.name AS user_name FROM api_tokens t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.id DESC')]; });
        self::on('POST', '/admin/tokens', function () use ($b) {
            Auth::require(90, true); $in = $b();
            $scopes = array_values(array_intersect((array)($in['scopes'] ?? ['read']), ['read', 'write', 'mcp', 'admin']));
            $exp = !empty($in['expires_days']) ? U::ts(time() + (int)$in['expires_days'] * 86400) : null;
            return Auth::createToken((int)($in['user_id'] ?? Auth::$user['id']), U::str($in['name'] ?? 'API token', 120) ?? 'API token', $scopes ?: ['read'], $exp);
        });
        self::on('DELETE', '/admin/tokens/(\d+)', function ($id) { Auth::require(90, true); Db::update('api_tokens', ['revoked' => 1], (int)$id); Audit::log('token.revoke', 'api_tokens', $id); return ['ok' => true]; });
        self::on('GET', '/admin/integrations', function () { Auth::require(90); return ['rows' => Integrations::listForAdmin(), 'endpoints' => self::hookEndpoints()]; });
        self::on('PUT', '/admin/integrations/([a-z_]+)', function ($p) use ($b) { Auth::require(90, true); Integrations::save($p, $b()); return ['ok' => true]; });
        self::on('POST', '/admin/integrations/opera/sync', function () { Auth::require(70, true); return Integrations::operaSync(); });
        self::on('GET', '/admin/audit', fn() => self::audit());
        self::on('GET', '/admin/audit/verify', function () { Auth::require(70); return Audit::verify(); });
        self::on('GET', '/admin/security', fn() => self::securityPosture());
        self::on('POST', '/admin/scheduler/run', function () { Auth::require(90, true); return Scheduler::run(); });
        self::on('GET', '/admin/queue', function () { Auth::require(90); return ['rows' => Db::all('SELECT id, channel, target, subject, status, attempts, last_error, created_at, sent_at FROM notification_queue ORDER BY id DESC LIMIT 100')]; });
        self::on('GET', '/admin/qr', function () {
            Auth::require(50);
            return ['rooms' => Db::all('SELECT id, number, floor, qr_token FROM rooms ORDER BY number'), 'locations' => Db::all("SELECT id, name, qr_token FROM locations WHERE qr_token IS NOT NULL AND active = 1 ORDER BY name"), 'base' => cfg('base_url') . '/g/'];
        });

        // generic records
        self::on('GET', '/e/([a-z_0-9]+)', fn($e) => self::eList($e));
        self::on('POST', '/e/([a-z_0-9]+)', fn($e) => self::eCreate($e, $b()));
        self::on('GET', '/e/([a-z_0-9]+)/(\d+)', fn($e, $id) => self::eGet($e, (int)$id));
        self::on('PATCH', '/e/([a-z_0-9]+)/(\d+)', fn($e, $id) => self::eUpdate($e, (int)$id, $b()));
        self::on('DELETE', '/e/([a-z_0-9]+)/(\d+)', fn($e, $id) => self::eDelete($e, (int)$id));

        self::on('GET', '/openapi\.json', fn() => OpenApi::spec());
    }

    // ================= bootstrap and lookup =================
    private static function bootstrap(): array {
        Auth::require();
        return [
            'user' => Auth::publicUser(), 'csrf' => Auth::$via === 'session' ? Auth::csrfToken() : null,
            'app' => ['name' => cfg('app_name'), 'property' => cfg('property_name'), 'code' => cfg('property_code'), 'timezone' => cfg('timezone'), 'version' => EXPTIX_VERSION, 'demo' => (bool)cfg('demo_mode'), 'base_url' => cfg('base_url'), 'operator' => cfg('operator'), 'locale' => cfg('locale', 'en-US'), 'currency' => cfg('currency', 'USD'), 'source_url' => cfg('source_url')],
            'meta' => Registry::meta(), 'opt' => Registry::OPT, 'roles' => Registry::roles(),
            'departments' => Db::all('SELECT id, code, name, icon, colour, active FROM departments ORDER BY sort, name'),
            'categories' => Db::all('SELECT id, department_id, name, type, default_priority, icon, guest_visible, guest_label FROM categories WHERE active = 1 ORDER BY sort, name'),
            'users' => Db::all('SELECT id, name, role, department_id, extra_departments, title, on_shift, active FROM users WHERE active = 1 ORDER BY name'),
            'rooms' => Db::all('SELECT id, number, floor FROM rooms ORDER BY number'),
            'locations' => Db::all('SELECT id, name, type FROM locations WHERE active = 1 ORDER BY name'),
            'transitions' => Tickets::TRANSITIONS,
        ];
    }

    private static function refs(string $e): array {
        Auth::require();
        if ($e === 'tickets') {
            [$s, $p] = Tickets::scopeSql();
            $q = (string)($_GET['q'] ?? '');
            $rows = Db::all("SELECT t.id, t.number, t.title FROM tickets t WHERE $s" . ($q ? ' AND (t.number LIKE ? OR t.title LIKE ?)' : '') . ' ORDER BY t.id DESC LIMIT 50', $q ? [...$p, "%$q%", "%$q%"] : $p);
            return ['rows' => array_map(fn($r) => ['id' => (int)$r['id'], 'label' => "{$r['number']} {$r['title']}"], $rows)];
        }
        $def = Registry::get($e);
        if (Auth::rank() < $def['read'] && !Auth::isAuditor()) throw new HttpError(403, 'Not permitted');
        $t = $def['title'];
        $extra = $e === 'users' ? ', role, department_id' : '';
        $rows = Db::all("SELECT id, `$t` AS label$extra FROM `$e` ORDER BY `$t` LIMIT 1000");
        return ['rows' => $rows];
    }

    private static function search(string $q): array {
        Auth::require();
        $q = trim($q);
        if (mb_strlen($q) < 2) return ['results' => []];
        $like = "%$q%";
        $res = [];
        foreach (Tickets::list(['q' => $q, 'limit' => 10])['rows'] as $t) $res[] = ['type' => 'Ticket', 'label' => "{$t['number']} {$t['title']}", 'link' => "#/t/{$t['id']}", 'status' => $t['status']];
        foreach (Db::all('SELECT id, number, guest_name, hk_status FROM rooms WHERE number LIKE ? OR guest_name LIKE ? LIMIT 8', ["$q%", $like]) as $r) $res[] = ['type' => 'Room', 'label' => "Room {$r['number']}" . ($r['guest_name'] ? " · {$r['guest_name']}" : ''), 'link' => "#/room/{$r['id']}", 'status' => $r['hk_status']];
        foreach (Db::all('SELECT id, tag, name FROM assets WHERE tag LIKE ? OR name LIKE ? OR serial LIKE ? LIMIT 6', [$like, $like, $like]) as $a) $res[] = ['type' => 'Asset', 'label' => "{$a['tag']} {$a['name']}", 'link' => "#/e/assets/{$a['id']}"];
        if (Auth::rank() >= 30) {
            foreach (Db::all('SELECT id, number, item FROM lost_found WHERE item LIKE ? OR description LIKE ? OR guest_name LIKE ? LIMIT 5', [$like, $like, $like]) as $l) $res[] = ['type' => 'Lost and found', 'label' => "{$l['number']} {$l['item']}", 'link' => "#/e/lost_found/{$l['id']}"];
            foreach (Db::all('SELECT id, guest_name, note FROM guest_notes WHERE guest_name LIKE ? LIMIT 5', [$like]) as $g) $res[] = ['type' => 'Guest note', 'label' => "{$g['guest_name']}: " . mb_substr($g['note'], 0, 60), 'link' => "#/e/guest_notes/{$g['id']}"];
            foreach (Db::all('SELECT id, number, summary FROM incidents WHERE number LIKE ? OR summary LIKE ? LIMIT 5', [$like, $like]) as $i) $res[] = ['type' => 'Incident', 'label' => "{$i['number']} {$i['summary']}", 'link' => "#/e/incidents/{$i['id']}"];
        }
        foreach (Db::all('SELECT id, name, beo_number FROM events WHERE name LIKE ? OR beo_number LIKE ? OR client_name LIKE ? LIMIT 5', [$like, $like, $like]) as $ev) $res[] = ['type' => 'Event', 'label' => trim("{$ev['beo_number']} {$ev['name']}"), 'link' => "#/e/events/{$ev['id']}"];
        return ['results' => $res];
    }

    private static function calendar(): array {
        Auth::require();
        $from = $_GET['from'] ?? U::localDate(); $to = $_GET['to'] ?? U::localDate(time() + 31 * 86400);
        return [
            'pm' => Db::all('SELECT p.id, p.name, p.next_due, d.name AS department FROM pm_schedules p LEFT JOIN departments d ON d.id = p.department_id WHERE p.active = 1 AND p.next_due BETWEEN ? AND ? ORDER BY p.next_due', [$from, $to]),
            'events' => Db::all("SELECT id, name, start_at, end_at, guests, status FROM events WHERE start_at BETWEEN ? AND ? AND status <> 'cancelled' ORDER BY start_at", [$from . ' 00:00:00', $to . ' 23:59:59']),
            'scheduled' => Db::all("SELECT id, number, title, scheduled_for FROM tickets WHERE scheduled_for BETWEEN ? AND ? AND status IN ('new','acknowledged','in_progress','on_hold') ORDER BY scheduled_for", [$from . ' 00:00:00', $to . ' 23:59:59']),
        ];
    }

    // ================= housekeeping =================
    private static function hkBoard(): array {
        Auth::require();
        $rooms = Db::all('SELECT r.*, rt.code AS type_code, rt.name AS type_name, rt.hk_credits, rt.clean_minutes, u.name AS attendant_name,
            (SELECT COUNT(*) FROM tickets t WHERE t.room_id = r.id AND t.status IN (\'new\',\'acknowledged\',\'in_progress\',\'on_hold\')) AS open_tickets
            FROM rooms r LEFT JOIN room_types rt ON rt.id = r.room_type_id LEFT JOIN users u ON u.id = r.attendant_id ORDER BY r.floor, r.number');
        $hk = Db::val("SELECT id FROM departments WHERE code = 'HK'");
        $att = Db::all("SELECT u.id, u.name, u.on_shift, u.hk_credit_capacity, u.role FROM users u WHERE u.active = 1 AND u.department_id = ? AND u.role IN ('staff','supervisor') ORDER BY u.name", [$hk]);
        foreach ($att as &$a) {
            $mine = array_filter($rooms, fn($r) => (int)$r['attendant_id'] === (int)$a['id']);
            $a['rooms'] = count($mine);
            $a['credits'] = round(array_sum(array_map(fn($r) => in_array($r['hk_status'], ['dirty', 'in_progress', 'pickup'], true) ? (float)$r['hk_credits'] * ($r['reservation_status'] === 'stayover' ? 0.6 : 1) : 0, $mine)), 1);
            $a['done'] = count(array_filter($mine, fn($r) => in_array($r['hk_status'], ['clean', 'inspected'], true)));
        }
        return ['rooms' => $rooms, 'attendants' => $att];
    }

    /** Balance dirty rooms across on-shift attendants by credits, keeping floors together and prioritising arrivals. */
    private static function hkAutoAssign(): array {
        Auth::require(50, true);
        $hk = Db::val("SELECT id FROM departments WHERE code = 'HK'");
        $att = Db::all("SELECT id, name, hk_credit_capacity FROM users WHERE active = 1 AND on_shift = 1 AND role = 'staff' AND department_id = ? ORDER BY id", [$hk]);
        if (!$att) throw new HttpError(422, 'No housekeeping attendants are marked on shift');
        $rooms = Db::all("SELECT r.id, r.floor, r.number, r.reservation_status, COALESCE(rt.hk_credits, 1) AS credits FROM rooms r LEFT JOIN room_types rt ON rt.id = r.room_type_id
            WHERE r.hk_status IN ('dirty','pickup') AND r.service_status = 'in_service' AND r.dnd = 0 AND r.eco_skip = 0 ORDER BY r.floor, r.number");
        $load = array_fill_keys(array_column($att, 'id'), 0.0);
        $floorOwner = [];
        $n = 0;
        foreach ($rooms as $r) {
            $c = (float)$r['credits'] * ($r['reservation_status'] === 'stayover' ? 0.6 : 1);
            $pick = $floorOwner[$r['floor']] ?? null;
            $cap = $pick ? (float)(array_column($att, 'hk_credit_capacity', 'id')[$pick] ?? 16) : 0;
            if (!$pick || $load[$pick] + $c > $cap) { asort($load); $pick = array_key_first($load); $floorOwner[$r['floor']] = $pick; }
            $load[$pick] += $c;
            Db::update('rooms', ['attendant_id' => $pick, 'rush' => $r['reservation_status'] === 'arrival' ? 1 : 0], (int)$r['id']);
            $n++;
        }
        Audit::log('hk.auto_assign', 'rooms', null, ['rooms' => $n, 'attendants' => count($att)]);
        return ['assigned' => $n, 'load' => array_map(fn($x) => round($x, 1), $load)];
    }

    // ================= checklists =================
    private static function runChecklist(array $in): array {
        Auth::require(20, true);
        $tpl = Db::one('SELECT * FROM checklist_templates WHERE id = ?', [(int)($in['template_id'] ?? 0)]);
        if (!$tpl) throw new HttpError(404, 'Checklist not found');
        $results = array_values(array_filter((array)($in['results'] ?? []), fn($r) => isset($r['item'])));
        $scored = array_filter($results, fn($r) => in_array($r['result'] ?? '', ['pass', 'fail'], true));
        $pass = count(array_filter($scored, fn($r) => $r['result'] === 'pass'));
        $score = $scored ? (int)round(100 * $pass / count($scored)) : 100;
        $roomId = !empty($in['room_id']) ? (int)$in['room_id'] : null;
        $locId = !empty($in['location_id']) ? (int)$in['location_id'] : null;
        $runId = Db::insert('checklist_runs', ['template_id' => $tpl['id'], 'department_id' => $tpl['department_id'], 'room_id' => $roomId, 'location_id' => $locId, 'performed_by' => Auth::$user['id'],
            'score' => $score, 'passed' => $score >= (int)$tpl['pass_mark'] ? 1 : 0, 'results' => json_encode($results), 'notes' => U::str($in['notes'] ?? null), 'created_at' => U::now()]);
        $made = 0;
        if ($tpl['create_tickets_on_fail']) {
            foreach ($results as $r) {
                if (($r['result'] ?? '') !== 'fail') continue;
                $cls = Tickets::classify($r['item'] . ' ' . ($r['note'] ?? ''));
                Tickets::create(['title' => mb_substr($r['item'], 0, 200) . ($roomId ? '' : ''), 'description' => "Failed during \"{$tpl['name']}\"" . (!empty($r['note']) ? ": {$r['note']}" : '.'),
                    'room_id' => $roomId, 'location_id' => $locId, 'type' => 'inspection', 'department_id' => $cls['department_id'] ?? $tpl['department_id'], 'category_id' => $cls['category_id']], 'inspection');
                $made++;
            }
            Db::update('checklist_runs', ['tickets_created' => $made], $runId);
        }
        if ($tpl['kind'] === 'room_inspection' && $roomId && $score >= (int)$tpl['pass_mark'] && Auth::rank() >= 50) Rooms::setStatus($roomId, ['hk_status' => 'inspected'], 'inspection');
        Audit::log('checklist.run', 'checklist_runs', $runId, ['template' => $tpl['name'], 'score' => $score, 'tickets' => $made]);
        return ['id' => $runId, 'score' => $score, 'passed' => $score >= (int)$tpl['pass_mark'], 'tickets_created' => $made];
    }

    // ================= guest portal =================
    private static function portalTarget(string $tok): array {
        U::rateLimit('portal:' . U::ip(), 120, 600);
        $room = Db::one('SELECT id, number, location_id FROM rooms WHERE qr_token = ?', [$tok]);
        if ($room) return ['room' => $room, 'location' => null];
        $loc = Db::one('SELECT id, name FROM locations WHERE qr_token = ? AND active = 1', [$tok]);
        if ($loc) return ['room' => null, 'location' => $loc];
        throw new HttpError(404, 'This QR code is not active. Please call the front desk.');
    }

    private static function portalInfo(string $tok): array {
        $t = self::portalTarget($tok);
        $cats = Db::all('SELECT c.id, COALESCE(c.guest_label, c.name) AS label, c.icon, d.name AS department FROM categories c JOIN departments d ON d.id = c.department_id WHERE c.guest_visible = 1 AND c.active = 1 ORDER BY d.sort, c.sort, c.name');
        $outlets = Db::all("SELECT name, hours, phone_ext FROM outlets WHERE active = 1 AND kind IN ('restaurant','lounge','in_room_dining','cafe') ORDER BY name");
        return ['property' => cfg('property_name'), 'room' => $t['room']['number'] ?? null, 'location' => $t['location']['name'] ?? null, 'categories' => $cats, 'outlets' => $outlets,
            'eighty_six' => array_column(Db::all('SELECT item FROM menu_86 WHERE active = 1'), 'item')];
    }

    private static function portalSubmit(string $tok, array $in): array {
        $t = self::portalTarget($tok);
        U::rateLimit('portal-submit:' . U::ip(), 8, 600);
        if (!empty($in['website'])) throw new HttpError(422, 'Rejected'); // honeypot
        $cat = !empty($in['category_id']) ? Db::one('SELECT * FROM categories WHERE id = ? AND guest_visible = 1', [(int)$in['category_id']]) : null;
        $details = U::str($in['details'] ?? null, 2000);
        if (!$cat && !$details) throw new HttpError(422, 'Choose a request or tell us what you need');
        $title = $cat ? ($cat['guest_label'] ?: $cat['name']) . ($details ? ': ' . mb_substr($details, 0, 80) : '') : mb_substr((string)$details, 0, 120);
        $ticket = Tickets::create([
            'title' => $title, 'description' => $details, 'category_id' => $cat['id'] ?? null, 'room_id' => $t['room']['id'] ?? null, 'location_id' => $t['location']['id'] ?? null,
            'guest_name' => U::str($in['name'] ?? null, 120), 'guest_contact' => U::str($in['contact'] ?? null, 190), 'type' => $cat['type'] ?? 'guest_request',
            'scheduled_for' => !empty($in['when']) ? (string)$in['when'] : null,
        ], 'guest_portal', ['id' => null, 'username' => 'guest-portal', 'name' => 'Guest (QR portal)']);
        if ($ticket['type'] === 'work_order') Db::update('tickets', ['guest_token' => $ticket['guest_token'] ?: U::token(12)], (int)$ticket['id']);
        $tokOut = Db::val('SELECT guest_token FROM tickets WHERE id = ?', [$ticket['id']]);
        return ['ok' => true, 'number' => $ticket['number'], 'track' => $tokOut];
    }

    private static function guestTicket(string $tok): array {
        U::rateLimit('portal:' . U::ip(), 120, 600);
        $t = Db::one('SELECT * FROM tickets WHERE guest_token = ?', [$tok]);
        if (!$t) throw new HttpError(404, 'Request not found');
        $statusText = ['new' => 'Received', 'acknowledged' => 'On its way', 'in_progress' => 'In progress', 'on_hold' => 'Scheduled', 'resolved' => 'Completed', 'closed' => 'Completed', 'cancelled' => 'Cancelled'];
        return ['number' => $t['number'], 'title' => $t['title'], 'status' => $t['status'], 'status_text' => $statusText[$t['status']] ?? $t['status'], 'created_at' => $t['created_at'], 'resolved_at' => $t['resolved_at'],
            'room' => $t['room_id'] ? Db::val('SELECT number FROM rooms WHERE id = ?', [$t['room_id']]) : null, 'satisfaction' => $t['satisfaction'] ? (int)$t['satisfaction'] : null,
            'messages' => Db::all("SELECT e.body, e.created_at, CASE WHEN e.user_id IS NULL THEN 'guest' ELSE 'staff' END AS sender FROM ticket_events e WHERE e.ticket_id = ? AND e.visibility = 'guest' ORDER BY e.id", [$t['id']]),
            'property' => cfg('property_name')];
    }

    private static function guestMessage(string $tok, array $in): array {
        U::rateLimit('portal-msg:' . U::ip(), 10, 600);
        $t = Db::one('SELECT * FROM tickets WHERE guest_token = ?', [$tok]);
        if (!$t) throw new HttpError(404, 'Request not found');
        $msg = U::str($in['message'] ?? null, 1000);
        if (!$msg) throw new HttpError(422, 'Message is empty');
        Tickets::event((int)$t['id'], 'guest_message', $msg, [], 'guest', ['id' => null]);
        Db::update('tickets', ['updated_at' => U::now()], (int)$t['id']);
        Notify::ticket('ticket.comment', $t, ['comment' => "Guest: $msg"]);
        return ['ok' => true];
    }

    private static function guestRate(string $tok, array $in): array {
        U::rateLimit('portal-msg:' . U::ip(), 10, 600);
        $t = Db::one('SELECT * FROM tickets WHERE guest_token = ?', [$tok]);
        if (!$t) throw new HttpError(404, 'Request not found');
        $score = max(1, min(5, (int)($in['score'] ?? 0)));
        Db::update('tickets', ['satisfaction' => $score, 'satisfaction_comment' => U::str($in['comment'] ?? null, 1000)], (int)$t['id']);
        Tickets::event((int)$t['id'], 'system', "Guest rated this request $score/5" . (!empty($in['comment']) ? ': ' . $in['comment'] : ''), ['score' => $score], 'internal', null);
        if ($score <= 2) {
            $fo = Db::val("SELECT id FROM departments WHERE code = 'FO'");
            Notify::generic('ticket.vip', $fo ? (int)$fo : null, "Low guest rating on {$t['number']}", "Score $score/5. Consider a service recovery call.", '#/t/' . $t['id'], 'high');
        }
        return ['ok' => true];
    }

    // ================= generic records =================
    private const HIDDEN = ['password_hash', 'mfa_secret', 'token_hash'];

    private static function scopeWhere(array $def, string $e): array {
        if (($def['scope'] ?? '') !== 'dept' || Auth::seesAll() || Auth::rank() >= 90 || !Schema::hasColumn($e, 'department_id')) return ['1=1', []];
        $d = Auth::departments() ?: [0];
        return ['(department_id IS NULL OR department_id IN (' . implode(',', array_map('intval', $d)) . '))', []];
    }

    private static function clean(string $e, array $row): array {
        foreach (self::HIDDEN as $h) unset($row[$h]);
        if ($e === 'webhooks' && !empty($row['secret'])) $row['secret'] = '••••••••';
        return $row;
    }

    private static function eList(string $e): array {
        $def = Registry::get($e);
        Auth::require($def['read']);
        [$w, $p] = self::scopeWhere($def, $e);
        $where = [$w];
        $fields = $def['fields'];
        $filters = (array)($_GET['f'] ?? []);
        if (!$filters && !empty($def['filter']) && empty($_GET['all'])) $filters = $def['filter'];
        foreach ($filters as $k => $v) {
            if ($v === '' || !(isset($fields[$k]) || in_array($k, ['id', 'created_at'], true)) || !Schema::hasColumn($e, $k)) continue;
            if ($v === 'null') { $where[] = "`$k` IS NULL"; continue; }
            $where[] = "`$k` = ?"; $p[] = $v;
        }
        if (!empty($_GET['q']) && !empty($def['search'])) {
            $where[] = '(' . implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $def['search'])) . ')';
            foreach ($def['search'] as $_) $p[] = '%' . $_GET['q'] . '%';
        }
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 200)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $wsql = implode(' AND ', $where);
        $order = preg_replace('/[^a-z_, ]/i', '', $def['order'] ?? 'id DESC');
        $total = (int)Db::val("SELECT COUNT(*) FROM `$e` WHERE $wsql", $p);
        $rows = array_map(fn($r) => self::clean($e, $r), Db::all("SELECT * FROM `$e` WHERE $wsql ORDER BY $order LIMIT $limit OFFSET $offset", $p));
        return ['total' => $total, 'rows' => $rows];
    }

    private static function eGet(string $e, int $id): array {
        $def = Registry::get($e);
        Auth::require($def['read']);
        [$w, $p] = self::scopeWhere($def, $e);
        $row = Db::one("SELECT * FROM `$e` WHERE id = ? AND $w", [$id, ...$p]);
        if (!$row) throw new HttpError(404, $def['one'] . ' not found');
        $row = self::clean($e, $row);
        $row['_attachments'] = Db::all('SELECT id, filename, mime, size, created_at FROM attachments WHERE entity = ? AND entity_id = ? ORDER BY id', [$e, $id]);
        if ($e === 'assets') $row['_tickets'] = Db::all('SELECT id, number, title, status, created_at FROM tickets WHERE asset_id = ? ORDER BY id DESC LIMIT 25', [$id]);
        if ($e === 'assets') $row['_pm'] = Db::all('SELECT id, name, frequency, next_due FROM pm_schedules WHERE asset_id = ? ORDER BY next_due', [$id]);
        if ($e === 'events') $row['_tickets'] = Db::all('SELECT id, number, title, status, department_id FROM tickets WHERE event_id = ? ORDER BY id', [$id]);
        if ($e === 'parts') $row['_movements'] = Db::all('SELECT m.*, u.name AS user_name FROM stock_movements m LEFT JOIN users u ON u.id = m.user_id WHERE part_id = ? ORDER BY m.id DESC LIMIT 25', [$id]);
        if ($e === 'logbook') $row['acknowledged_by'] = json_decode((string)$row['acknowledged_by'], true) ?: [];
        if ($e === 'checklist_runs') $row['results'] = json_decode((string)$row['results'], true) ?: [];
        return $row;
    }

    private static function coerce(array $f, $v) {
        $type = $f[1];
        if ($v === '' || $v === null) return $type === 'bool' ? 0 : null;
        return match ($type) {
            'number' => (int)$v, 'decimal' => (float)$v, 'bool' => $v && $v !== 'false' && $v !== '0' ? 1 : 0, 'ref' => (int)$v ?: null,
            'multiref' => json_encode(array_values(array_map('intval', (array)$v))),
            'datetime' => Tickets::toUtc((string)$v), 'date' => preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$v) ? substr((string)$v, 0, 10) : null,
            'select' => isset($f['opts'][$v]) ? $v : throw new HttpError(422, "Invalid value for {$f[0]}"),
            'email' => filter_var($v, FILTER_VALIDATE_EMAIL) ? strtolower((string)$v) : throw new HttpError(422, "{$f[0]} is not a valid email address"),
            'lines', 'textarea' => mb_substr((string)$v, 0, 20000), 'colour' => preg_match('/^#[0-9a-f]{6}$/i', (string)$v) ? $v : null,
            default => mb_substr(trim((string)$v), 0, 500),
        };
    }

    private static function writable(array $def, array $in, bool $creating): array {
        $data = [];
        foreach ($def['fields'] as $k => $f) {
            if (in_array($f[1], ['readonly', 'readonly_ref'], true)) continue;
            if (!empty($f['min_write']) && Auth::rank() < $f['min_write']) continue;
            if (!array_key_exists($k, $in)) { if ($creating && !empty($f['req'])) throw new HttpError(422, "{$f[0]} is required"); continue; }
            $data[$k] = self::coerce($f, $in[$k]);
            if (!empty($f['req']) && ($data[$k] === null || $data[$k] === '')) throw new HttpError(422, "{$f[0]} is required");
        }
        return $data;
    }

    private static function eCreate(string $e, array $in): array {
        $def = Registry::get($e);
        if ($e === 'users') throw new HttpError(400, 'Use the admin user endpoint');
        if ($e === 'temp_logs') { Auth::require(20, true); $r = FoodSafety::record((int)($in['point_id'] ?? 0), (float)($in['reading_c'] ?? 0), 'manual', $in['corrective_action'] ?? null); return self::eGet($e, (int)$r['id']); }
        Auth::require($def['write'], true);
        $data = self::writable($def, $in, true);
        $now = U::now();
        if (Schema::hasColumn($e, 'created_at')) $data['created_at'] = $now;
        if (Schema::hasColumn($e, 'updated_at')) $data['updated_at'] = $now;
        $uid = Auth::$user['id'];
        foreach (['created_by', 'author_id', 'reported_by', 'requested_by', 'issued_by', 'user_id', 'posted_by'] as $c) {
            if (Schema::hasColumn($e, $c) && !isset($data[$c])) $data[$c] = $uid;
        }
        if ($e === 'lost_found' && empty($data['found_at'])) $data['found_at'] = $now;
        if ($e === 'lost_found' && empty($data['dispose_after'])) $data['dispose_after'] = U::localDate(time() + 90 * 86400);
        if ($e === 'key_log' && empty($data['issued_at'])) $data['issued_at'] = $now;
        if ($e === 'logbook' && empty($data['shift'])) $data['shift'] = self::currentShift();
        if (in_array($e, ['rooms', 'locations'], true)) $data['qr_token'] = U::token(12);
        if ($e === 'assets') $data['qr_token'] = U::token(12);
        $id = Db::insert($e, $data);
        if (!empty($def['number'])) Db::update($e, ['number' => $def['number'] . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)], $id);
        Audit::log("$e.create", $e, $id, array_diff_key($data, array_flip(self::HIDDEN)));
        $row = Db::one("SELECT * FROM `$e` WHERE id = ?", [$id]);
        self::afterSave($e, $row, true);
        return self::clean($e, $row);
    }

    private static function eUpdate(string $e, int $id, array $in): array {
        $def = Registry::get($e);
        $before = Db::one("SELECT * FROM `$e` WHERE id = ?", [$id]);
        if (!$before) throw new HttpError(404, 'Not found');
        // Line staff can update rooms through the housekeeping endpoint; supervisors and up here.
        Auth::require($def['write'], true);
        if ($e === 'users') self::guardUserEdit($id, $in);
        $data = self::writable($def, $in, false);
        if ($e === 'rooms') {
            $status = array_intersect_key($data, array_flip(['hk_status', 'fo_status', 'service_status', 'reservation_status', 'attendant_id', 'hk_section', 'dnd', 'make_up_room', 'turndown', 'rush', 'eco_skip', 'ooo_reason', 'ooo_until', 'guest_name', 'reservation_ref', 'arrival_date', 'departure_date', 'adults', 'children', 'vip_code', 'notes']));
            if ($status) Rooms::setStatus($id, $status, 'staff');
            $data = array_diff_key($data, $status);
        }
        if ($data) {
            if (Schema::hasColumn($e, 'updated_at')) $data['updated_at'] = U::now();
            Db::update($e, $data, $id);
            Audit::log("$e.update", $e, $id, Audit::diff($before, $data));
        }
        $row = Db::one("SELECT * FROM `$e` WHERE id = ?", [$id]);
        self::afterSave($e, $row, false, $before);
        return self::clean($e, $row);
    }

    private static function eDelete(string $e, int $id): array {
        $def = Registry::get($e);
        Auth::require(max(70, $def['write']), true);
        $row = Db::one("SELECT * FROM `$e` WHERE id = ?", [$id]);
        if (!$row) throw new HttpError(404, 'Not found');
        if ($e === 'users' || $e === 'departments') {
            if (Schema::hasColumn($e, 'active')) { Db::update($e, ['active' => 0], $id); Audit::log("$e.deactivate", $e, $id); return ['ok' => true, 'deactivated' => true]; }
        }
        Db::q("DELETE FROM `$e` WHERE id = ?", [$id]);
        Audit::log("$e.delete", $e, $id, ['snapshot' => self::clean($e, $row)]);
        return ['ok' => true];
    }

    private static function afterSave(string $e, array $row, bool $created, ?array $before = null): void {
        if ($e === 'incidents' && $created) {
            $sec = Db::val("SELECT id FROM departments WHERE code = 'SEC'");
            $pri = in_array($row['severity'], ['major', 'critical'], true) ? 'urgent' : 'normal';
            Notify::generic('incident.created', $sec ? (int)$sec : null, "Incident {$row['number']}: {$row['summary']}", (Registry::OPT['incident_type'][$row['type']] ?? $row['type']) . ' · ' . (Registry::OPT['severity'][$row['severity']] ?? ''), '#/e/incidents/' . $row['id'], $pri);
        }
        if ($e === 'lost_found' && $created) {
            if ($row['high_value']) { $sec = Db::val("SELECT id FROM departments WHERE code = 'SEC'"); Notify::generic('lost_found.created', $sec ? (int)$sec : null, "High-value item found: {$row['item']}", 'Move to the security safe and log the chain of custody.', '#/e/lost_found/' . $row['id'], 'high'); }
        }
        if ($e === 'logbook' && $created && $row['importance'] === 'action') {
            Notify::generic('logbook.action', $row['department_id'] ? (int)$row['department_id'] : null, 'Logbook: action required', mb_substr((string)$row['entry'], 0, 200), '#/logbook', 'high');
        }
        if ($e === 'menu_86' && $created) {
            foreach (array_merge(Notify::deptMembers((int)Db::val("SELECT id FROM departments WHERE code = 'FB'"), 0), Notify::deptMembers((int)Db::val("SELECT id FROM departments WHERE code = 'FO'"), 0)) as $u) Notify::inapp($u, "86: {$row['item']}", $row['substitute'] ? "Suggest {$row['substitute']} instead." : 'No longer available.', '#/e/menu_86', 'info');
        }
        if ($e === 'purchase_requests' && $created) {
            $mgr = Db::val('SELECT manager_id FROM departments WHERE id = ?', [$row['department_id']]);
            if ($mgr) Notify::inapp((int)$mgr, "Purchase request {$row['number']} needs approval", "{$row['title']} · \$" . number_format((float)$row['amount'], 2), '#/e/purchase_requests/' . $row['id'], 'info');
        }
        if ($e === 'events' && $created) {
            // Event set-up creates linked tasks for the departments involved.
            $start = $row['start_at'];
            $tasks = [['BQ', 'Room set-up: ' . (Registry::OPT['setup'][$row['setup_style']] ?? 'as per BEO'), -120], ['KIT', 'Food production for ' . $row['guests'] . ' guests', -180], ['ENG', 'HVAC and lighting check in venue', -90]];
            if (trim((string)$row['av_requirements']) !== '') $tasks[] = ['IT', 'AV and Wi-Fi set-up: ' . mb_substr($row['av_requirements'], 0, 100), -90];
            if (trim((string)$row['dietary']) !== '') $tasks[] = ['KIT', 'Dietary and allergy plates: ' . mb_substr($row['dietary'], 0, 100), -120];
            $tasks[] = ['HK', 'Post-event clean and reset', 60 + (int)((strtotime(($row['end_at'] ?: $start) . ' UTC') - strtotime($start . ' UTC')) / 60)];
            foreach ($tasks as [$code, $title, $offset]) {
                $dept = Db::val('SELECT id FROM departments WHERE code = ?', [$code]);
                if (!$dept) continue;
                Tickets::create(['title' => $title . ' · ' . $row['name'], 'department_id' => $dept, 'event_id' => $row['id'], 'location_id' => $row['location_id'], 'type' => 'event_task', 'priority' => 'normal',
                    'due_at' => gmdate('Y-m-d H:i:s', strtotime($start . ' UTC') + $offset * 60) . 'Z'], 'staff');
            }
        }
        if ($e === 'users' && !$created) Db::q('UPDATE users SET username = LOWER(username), email = LOWER(email) WHERE id = ?', [$row['id']]);
    }

    private static function currentShift(): string {
        $h = (int)(new DateTime('now', new DateTimeZone(cfg('timezone'))))->format('G');
        return $h >= 7 && $h < 15 ? 'day' : ($h >= 15 && $h < 23 ? 'swing' : 'night');
    }

    // ================= admin =================
    private static function guardUserEdit(int $id, array $in): void {
        Auth::require(90, true);
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (($in['role'] ?? null) === 'admin' && Auth::$user['role'] !== 'admin') throw new HttpError(403, 'Only administrators can grant the administrator role');
        if ($target['role'] === 'admin' && Auth::$user['role'] !== 'admin') throw new HttpError(403, 'Only administrators can change an administrator');
        if ($target['role'] === 'admin' && ((isset($in['role']) && $in['role'] !== 'admin') || (isset($in['active']) && !$in['active']))) {
            if ((int)Db::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1) throw new HttpError(422, 'Keep at least one active administrator');
        }
    }

    private static function adminCreateUser(array $in): array {
        Auth::require(90, true);
        $username = strtolower(trim((string)($in['username'] ?? '')));
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) throw new HttpError(422, 'Username: 3 to 60 letters, numbers, dots, dashes or underscores');
        if (Db::val('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) throw new HttpError(422, 'That username is taken');
        $role = $in['role'] ?? 'staff';
        if (!isset(Auth::ROLES[$role])) throw new HttpError(422, 'Unknown role');
        if ($role === 'admin' && Auth::$user['role'] !== 'admin') throw new HttpError(403, 'Only administrators can create administrators');
        $temp = U::token(12);
        $id = Db::insert('users', ['username' => $username, 'name' => U::str($in['name'] ?? $username, 120), 'email' => !empty($in['email']) ? strtolower(trim($in['email'])) : null, 'phone' => U::str($in['phone'] ?? null, 40),
            'title' => U::str($in['title'] ?? null, 100), 'role' => $role, 'department_id' => !empty($in['department_id']) ? (int)$in['department_id'] : null,
            'extra_departments' => json_encode(array_map('intval', (array)($in['extra_departments'] ?? []))), 'password_hash' => Auth::hash($temp), 'must_change_password' => 1, 'active' => 1, 'created_at' => U::now(), 'updated_at' => U::now()]);
        Audit::log('users.create', 'users', $id, ['username' => $username, 'role' => $role]);
        return ['id' => $id, 'temporary_password' => $temp];
    }

    private static function adminUserAction(int $id, string $act): array {
        Auth::require(90, true);
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) throw new HttpError(404, 'User not found');
        if ($u['role'] === 'admin' && Auth::$user['role'] !== 'admin') throw new HttpError(403, 'Only administrators can change an administrator');
        $out = ['ok' => true];
        if ($act === 'reset-password') { $temp = U::token(12); Db::update('users', ['password_hash' => Auth::hash($temp), 'must_change_password' => 1, 'failed_logins' => 0, 'locked_until' => null], $id); $out['temporary_password'] = $temp; }
        if ($act === 'unlock') Db::update('users', ['failed_logins' => 0, 'locked_until' => null], $id);
        if ($act === 'reset-mfa') Db::update('users', ['mfa_secret' => null, 'mfa_enabled' => 0], $id);
        Audit::log("users.$act", 'users', $id);
        return $out;
    }

    private static function audit(): array {
        Auth::require(70);
        if (Auth::rank() < 90 && !Auth::isAuditor()) throw new HttpError(403, 'Audit log is limited to administrators and auditors');
        $w = ['1=1']; $p = [];
        foreach (['entity', 'action', 'username'] as $k) if (!empty($_GET[$k])) { $w[] = "`$k` LIKE ?"; $p[] = $_GET[$k] . '%'; }
        if (!empty($_GET['entity_id'])) { $w[] = 'entity_id = ?'; $p[] = (int)$_GET['entity_id']; }
        $lim = min(500, (int)($_GET['limit'] ?? 200));
        $off = max(0, (int)($_GET['offset'] ?? 0));
        Audit::log('audit.view', 'audit_log', null, array_intersect_key($_GET, array_flip(['entity', 'action', 'username'])));
        return ['rows' => Db::all('SELECT * FROM audit_log WHERE ' . implode(' AND ', $w) . " ORDER BY id DESC LIMIT $lim OFFSET $off", $p)];
    }

    public static function hookEndpoints(): array {
        $b = cfg('base_url');
        return ['opera_events' => "$b/hooks/opera", 'canary' => "$b/hooks/canary", 'sensor' => "$b/hooks/sensor", 'generic' => "$b/hooks/inbound", 'mcp' => "$b/mcp", 'openapi' => "$b/api/openapi.json", 'api' => "$b/api"];
    }

    /**
     * Is the PHP runtime patched for PHP2Shell (CVE-2026-109618, CVE-2026-109619)?
     * Fixed upstream in 8.2.35, 8.3.36, 8.4.27 and 8.5.12. PHP 8.1 and older get no upstream fix;
     * some distributions backport it without changing the version number, so we warn rather than refuse to run.
     */
    public static function phpRuntimeCheck(): array {
        $fixed = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
        $v = PHP_VERSION; $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (isset($fixed[$branch])) $patched = version_compare($v, $fixed[$branch], '>=');
        else $patched = version_compare($branch, '8.5', '>');
        $progress = filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN);
        if ($patched) {
            $detail = "PHP $v includes the October 2026 security fixes (CVE-2026-109618, CVE-2026-109619). Keep applying PHP security updates.";
        } elseif (version_compare($branch, '8.2', '<')) {
            $detail = "PHP $v is end of life and gets no fix from the PHP project for CVE-2026-109618 and CVE-2026-109619 (unauthenticated remote code execution). Move to PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or newer, unless your distribution has confirmed a backported fix.";
        } else {
            $detail = "PHP $v is older than {$fixed[$branch]}, the release that fixes CVE-2026-109618 and CVE-2026-109619 (unauthenticated remote code execution). Update PHP now, unless your distribution has confirmed a backported fix.";
        }
        if (!$patched) $detail .= $progress ? ' Until then, set session.upload_progress.enabled=0 in php.ini or the PHP-FPM pool and restart PHP.' : ' session.upload_progress.enabled is off, which blocks one known attack path but is not a full fix.';
        return ['version' => $v, 'sapi' => PHP_SAPI, 'patched' => $patched, 'upload_progress' => $progress, 'detail' => $detail];
    }

    private static function securityPosture(): array {
        Auth::require(70);
        $users = Db::one("SELECT COUNT(*) AS total, SUM(mfa_enabled) AS mfa, SUM(CASE WHEN role IN ('admin','gm') THEN 1 ELSE 0 END) AS privileged, SUM(CASE WHEN role IN ('admin','gm') AND mfa_enabled = 1 THEN 1 ELSE 0 END) AS privileged_mfa,
            SUM(CASE WHEN last_login_at IS NULL OR last_login_at < ? THEN 1 ELSE 0 END) AS stale FROM users WHERE active = 1", [U::ts(time() - 90 * 86400)]);
        $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $c = fn(string $id, string $family, string $name, string $status, string $detail) => compact('id', 'family', 'name', 'status', 'detail');
        $v = Audit::verify();
        return ['users' => $users, 'audit' => $v, 'controls' => [
            $c('AC-2', 'Access control', 'Account management', 'implemented', 'Named accounts, roles, department scope, deactivation instead of deletion, stale-account report (' . (int)$users['stale'] . ' not used in 90 days).'),
            $c('AC-3 / AC-6', 'Access control', 'Least privilege', 'implemented', 'Eight roles from contractor to administrator; department-scoped tickets and records; read-only auditor role; scoped API tokens.'),
            $c('AC-7', 'Access control', 'Unsuccessful logon attempts', 'implemented', cfg('security.lockout_threshold') . ' failures locks the account for ' . cfg('security.lockout_minutes') . ' minutes; per-IP rate limits.'),
            $c('AC-11 / AC-12', 'Access control', 'Session lock and termination', 'implemented', cfg('security.session_idle_minutes') . '-minute idle timeout, ' . cfg('security.session_max_hours') . '-hour absolute limit, server-side session destruction on sign-out.'),
            $c('AU-2 / AU-3 / AU-12', 'Audit', 'Event logging', 'implemented', 'Sign-ins, failures, record changes with field-level diffs, exports, admin and integration actions.'),
            $c('AU-9 / AU-10', 'Audit', 'Protection of audit information', $v['intact'] ? 'implemented' : 'attention', 'SHA-256 hash chain over ' . $v['records'] . ' records: ' . ($v['intact'] ? 'intact' : 'BROKEN at ' . implode(',', $v['broken_at'])) . '. Ship to SIEM via webhook for off-box retention.'),
            $c('AU-11', 'Audit', 'Retention', 'implemented', 'Audit retained ' . cfg('security.audit_retention_days') . ' days.'),
            $c('IA-2(1)', 'Identification', 'MFA', (int)$users['privileged'] === (int)$users['privileged_mfa'] ? 'implemented' : 'partial', 'TOTP available to all users; ' . (int)$users['mfa'] . ' of ' . (int)$users['total'] . ' enrolled; enforce by role with security.mfa_required_ranks_at_or_above.'),
            $c('IA-5', 'Identification', 'Authenticator management', 'implemented', 'NIST 800-63B rules: ' . cfg('security.password_min_length') . '+ characters, blocklist, no composition rules or forced rotation, Argon2id hashing. API tokens stored as SHA-256 only.'),
            $c('SC-8', 'System protection', 'Transmission confidentiality', $https ? 'implemented' : 'attention', $https ? 'TLS on every request; HSTS header set.' : 'Request did not arrive over HTTPS.'),
            $c('SC-23', 'System protection', 'Session authenticity', 'implemented', 'Secure, HttpOnly, SameSite cookies; session id rotated at sign-in; CSRF token on every state change.'),
            $c('SC-28', 'System protection', 'Protection at rest', 'partial', 'Integration secrets encrypted with libsodium (XSalsa20-Poly1305), key outside web root. Database encryption at rest depends on the hosting platform: use encrypted storage for production.'),
            $c('SI-2', 'System integrity', 'Flaw remediation', ($php = self::phpRuntimeCheck())['patched'] ? 'implemented' : 'attention', $php['detail']),
            $c('SI-10', 'System integrity', 'Input validation', 'implemented', 'Typed field coercion, allow-listed enums, prepared statements throughout, upload MIME sniffing and allow-list, CSP header.'),
            $c('SI-4', 'System integrity', 'Monitoring', 'partial', 'Failed sign-in and token misuse logged; forward the audit webhook to your SIEM or SOC for alerting in production.'),
            $c('CP-9', 'Contingency', 'Backup', 'partial', 'Nightly database dump script provided (bin/backup.php). Production needs off-site, tested restores.'),
            $c('CM-2 / CM-6', 'Configuration', 'Baseline configuration', 'implemented', 'Configuration in code with local overrides outside the web root; schema migrations versioned.'),
            $c('IR-4 / IR-5', 'Incident response', 'Incident handling and monitoring', 'implemented', 'Incident reports, severity, follow-up, linked tickets, cyber incident type.'),
            $c('PT / privacy', 'Privacy', 'PII minimisation', 'partial', 'Demo data only. Production: retention schedules for guest data, data processing agreement with the hosting provider, data residency and breach notification as your privacy law requires.'),
        ]];
    }
}
