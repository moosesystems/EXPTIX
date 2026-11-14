<?php
declare(strict_types=1);

/**
 * Third-party connectors. Each provider has mock and live modes so the demo behaves realistically
 * before credentials exist. Secrets are encrypted at rest with libsodium (SC-28) using a key file
 * outside the web root.
 */
final class Integrations {
    public const PROVIDERS = [
        'opera' => ['label' => 'Oracle Opera Cloud (OHIP)', 'secrets' => ['client_secret', 'app_key', 'password', 'events_secret'],
            'fields' => ['gateway_url' => 'OHIP gateway URL (e.g. https://xxx-ohip.hospitality-api.us-ashburn-1.ocs.oraclecloud.com)', 'app_key' => 'Application key (x-app-key)', 'client_id' => 'Client id', 'client_secret' => 'Client secret',
                'enterprise_id' => 'Enterprise id', 'hotel_id' => 'Hotel id (property code)', 'grant' => 'Grant type (client_credentials or password)', 'username' => 'Integration user (password grant only)', 'password' => 'Integration password (password grant only)',
                'events_secret' => 'Business events webhook secret (HMAC-SHA256)', 'sync_minutes' => 'Room status sync interval (minutes)', 'push_room_status' => 'Push housekeeping status back to Opera (yes/no)', 'post_minibar' => 'Post minibar charges to folio (yes/no)']],
        'canary' => ['label' => 'Canary Technologies (guest messaging)', 'secrets' => ['api_key', 'webhook_secret'],
            'fields' => ['api_base' => 'Canary API base URL', 'api_key' => 'API key', 'property_id' => 'Canary property id', 'webhook_secret' => 'Inbound webhook signing secret', 'auto_reply' => 'Send guest acknowledgements (yes/no)']],
        'email_in' => ['label' => 'Email to ticket (IMAP)', 'secrets' => ['password'],
            'fields' => ['host' => 'IMAP host', 'port' => 'Port (993)', 'username' => 'Mailbox', 'password' => 'Password or app password', 'folder' => 'Folder (INBOX)', 'default_department' => 'Default department code (FO)']],
        'email_out' => ['label' => 'Email notifications', 'secrets' => [], 'fields' => ['note' => 'Uses the server mail service. Switch on to send real email.']],
        'twilio' => ['label' => 'Twilio SMS', 'secrets' => ['auth_token'], 'fields' => ['account_sid' => 'Account SID', 'auth_token' => 'Auth token', 'from_number' => 'From number (+1...)']],
        'sensors' => ['label' => 'IoT sensors (temperature, leak, humidity)', 'secrets' => ['token'], 'fields' => ['token' => 'Shared ingest token (sent as X-Sensor-Token)']],
        'teams' => ['label' => 'Microsoft Teams', 'secrets' => [], 'fields' => ['note' => 'Add a Teams Workflows webhook URL as a notification rule target.']],
        'dormakaba' => ['label' => 'Door locks (dormakaba Community / Ambiance)', 'secrets' => ['api_key'], 'fields' => ['api_base' => 'Lock system API URL', 'api_key' => 'API key', 'note' => 'Lock audit trail pulls and low-battery alerts to engineering tickets.']],
        'bms' => ['label' => 'Building management system (BACnet/IP gateway)', 'secrets' => ['token'], 'fields' => ['gateway_url' => 'Gateway URL', 'token' => 'Token', 'note' => 'Alarms post to /hooks/sensor with kind=bms.']],
    ];

    // ---------- secrets ----------
    private static function key(): string {
        $f = APP_ROOT . '/data/app.key';
        if (!is_file($f)) { file_put_contents($f, sodium_bin2hex(sodium_crypto_secretbox_keygen())); chmod($f, 0600); }
        return sodium_hex2bin(trim(file_get_contents($f)));
    }
    public static function encrypt(string $v): string {
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'enc:' . base64_encode($n . sodium_crypto_secretbox($v, $n, self::key()));
    }
    public static function decrypt(string $v): string {
        if (!str_starts_with($v, 'enc:')) return $v;
        $raw = base64_decode(substr($v, 4));
        $p = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $p === false ? '' : $p;
    }

    public static function row(string $p): ?array { return Db::one('SELECT * FROM integrations WHERE provider = ?', [$p]); }
    public static function enabled(string $p): bool { $r = self::row($p); return $r && (int)$r['enabled'] === 1; }
    public static function live(string $p): bool { $r = self::row($p); return $r && (int)$r['enabled'] === 1 && $r['mode'] === 'live'; }
    public static function config(string $p): array {
        $r = self::row($p);
        $c = $r ? (json_decode((string)$r['config'], true) ?: []) : [];
        foreach ($c as $k => $v) if (is_string($v)) $c[$k] = self::decrypt($v);
        return $c;
    }

    public static function save(string $p, array $in): void {
        if (!isset(self::PROVIDERS[$p])) throw new HttpError(404, 'Unknown integration');
        $cur = self::row($p);
        $old = $cur ? (json_decode((string)$cur['config'], true) ?: []) : [];
        $cfg = [];
        foreach (self::PROVIDERS[$p]['fields'] as $k => $_) {
            $v = $in['config'][$k] ?? null;
            if (in_array($k, self::PROVIDERS[$p]['secrets'], true)) {
                if ($v === null || $v === '' || $v === '••••••••') { if (isset($old[$k])) $cfg[$k] = $old[$k]; continue; }
                $cfg[$k] = self::encrypt((string)$v);
            } elseif ($v !== null) $cfg[$k] = mb_substr((string)$v, 0, 500);
        }
        $row = ['enabled' => !empty($in['enabled']) ? 1 : 0, 'mode' => ($in['mode'] ?? 'mock') === 'live' ? 'live' : 'mock', 'config' => json_encode($cfg), 'updated_at' => U::now()];
        if ($cur) Db::update('integrations', $row, (int)$cur['id']);
        else Db::insert('integrations', $row + ['provider' => $p, 'created_at' => U::now()]);
        Audit::log('integration.update', 'integrations', $cur['id'] ?? null, ['provider' => $p, 'enabled' => $row['enabled'], 'mode' => $row['mode'], 'fields' => array_keys($cfg)]);
    }

    public static function listForAdmin(): array {
        $out = [];
        foreach (self::PROVIDERS as $p => $def) {
            $r = self::row($p);
            $c = $r ? (json_decode((string)$r['config'], true) ?: []) : [];
            foreach ($def['secrets'] as $s) if (!empty($c[$s])) $c[$s] = '••••••••';
            $out[] = ['provider' => $p, 'label' => $def['label'], 'fields' => $def['fields'], 'secrets' => $def['secrets'], 'enabled' => (bool)($r['enabled'] ?? false), 'mode' => $r['mode'] ?? 'mock',
                'config' => $c, 'last_sync_at' => $r['last_sync_at'] ?? null, 'last_status' => $r['last_status'] ?? null,
                'log' => Db::all('SELECT * FROM integration_log WHERE provider = ? ORDER BY id DESC LIMIT 8', [$p])];
        }
        return $out;
    }

    public static function log(string $p, string $dir, string $action, string $status, string $summary): void {
        Db::insert('integration_log', ['provider' => $p, 'direction' => $dir, 'action' => $action, 'status' => $status, 'summary' => mb_substr($summary, 0, 4000), 'created_at' => U::now()]);
    }
    private static function mark(string $p, string $status): void {
        Db::q('UPDATE integrations SET last_sync_at = ?, last_status = ? WHERE provider = ?', [U::now(), mb_substr($status, 0, 255), $p]);
    }

    // ================= OPERA CLOUD (OHIP) =================
    // Endpoint paths follow the OHIP REST catalogue (hsk = housekeeping, rsv = reservations, csh = cashiering).
    // Confirm each against Oracle's published API specs when the property's OHIP application is approved.
    public const OPERA_PATHS = [
        'token' => '/oauth/v1/tokens',
        'housekeeping_overview' => '/hsk/v1/hotels/{hotelId}/housekeepingOverview?limit=500',
        'room_status_update' => '/hsk/v1/hotels/{hotelId}/rooms/{roomId}/housekeepingStatus',
        'out_of_order' => '/hsk/v1/hotels/{hotelId}/outOfOrderRooms',
        'arrivals' => '/rsv/v1/hotels/{hotelId}/reservations?arrivalStartDate={date}&arrivalEndDate={date}&limit=500',
        'post_charge' => '/csh/v1/hotels/{hotelId}/reservations/{reservationId}/charges',
    ];

    private static function operaToken(array $c): string {
        $cached = json_decode((string)Db::val("SELECT value FROM settings WHERE `key` = 'opera_token'"), true);
        if ($cached && $cached['exp'] > time() + 60) return $cached['token'];
        $grant = ($c['grant'] ?? 'client_credentials') === 'password'
            ? ['grant_type' => 'password', 'username' => $c['username'] ?? '', 'password' => $c['password'] ?? '']
            : ['grant_type' => 'client_credentials', 'scope' => 'urn:opc:hgbu:ws:__myscopes__'];
        $r = U::http('POST', rtrim($c['gateway_url'], '/') . self::OPERA_PATHS['token'], [
            'Content-Type' => 'application/x-www-form-urlencoded', 'x-app-key' => $c['app_key'] ?? '', 'enterpriseId' => $c['enterprise_id'] ?? '',
            'Authorization' => 'Basic ' . base64_encode(($c['client_id'] ?? '') . ':' . ($c['client_secret'] ?? '')),
        ], http_build_query($grant));
        if ($r['status'] !== 200 || empty($r['json']['access_token'])) throw new RuntimeException("Opera token request failed (HTTP {$r['status']})");
        $tok = ['token' => $r['json']['access_token'], 'exp' => time() + (int)($r['json']['expires_in'] ?? 3600)];
        Db::q("DELETE FROM settings WHERE `key` = 'opera_token'");
        Db::insert('settings', ['key' => 'opera_token', 'value' => json_encode($tok)]);
        return $tok['token'];
    }

    private static function opera(string $method, string $pathKey, array $vars = [], $body = null): array {
        $c = self::config('opera');
        $path = self::OPERA_PATHS[$pathKey];
        $vars += ['hotelId' => $c['hotel_id'] ?? '', 'date' => U::localDate()];
        foreach ($vars as $k => $v) $path = str_replace('{' . $k . '}', rawurlencode((string)$v), $path);
        $r = U::http($method, rtrim($c['gateway_url'], '/') . $path, ['Authorization' => 'Bearer ' . self::operaToken($c), 'x-app-key' => $c['app_key'] ?? '', 'x-hotelid' => $c['hotel_id'] ?? '', 'Content-Type' => 'application/json', 'Accept' => 'application/json'], $body === null ? null : json_encode($body), 20);
        self::log('opera', 'out', "$method $pathKey", $r['status'] < 300 ? 'ok' : 'error', "HTTP {$r['status']}");
        if ($r['status'] >= 300) throw new RuntimeException("Opera $pathKey HTTP {$r['status']}");
        return $r['json'] ?? [];
    }

    /** Pull room and reservation status from Opera (or simulate it in mock mode). */
    public static function operaSync(): array {
        if (!self::enabled('opera')) return ['skipped' => 'disabled'];
        if (!self::live('opera')) return self::operaSimulate();
        try {
            $data = self::opera('GET', 'housekeeping_overview');
            $n = 0;
            foreach (($data['housekeepingRoomInfo']['housekeepingRooms']['room'] ?? $data['rooms'] ?? []) as $r) {
                $num = $r['roomId'] ?? $r['roomNumber'] ?? null;
                if (!$num) continue;
                $room = Db::one('SELECT id FROM rooms WHERE number = ? OR opera_room_id = ?', [$num, $num]);
                if (!$room) continue;
                $map = ['Dirty' => 'dirty', 'Clean' => 'clean', 'Inspected' => 'inspected', 'Pickup' => 'pickup'];
                $hk = $map[$r['housekeeping']['housekeepingRoomStatus']['housekeepingRoomStatus'] ?? $r['roomStatus'] ?? ''] ?? null;
                $fo = ($r['housekeeping']['frontOfficeStatus'] ?? $r['frontOfficeStatus'] ?? '') === 'Occupied' ? 'occupied' : 'vacant';
                $chg = ['fo_status' => $fo];
                if ($hk) $chg['hk_status'] = $hk;
                Rooms::setStatus((int)$room['id'], $chg, 'opera');
                $n++;
            }
            self::mark('opera', "Synced $n rooms");
            return ['rooms' => $n];
        } catch (Throwable $e) {
            self::mark('opera', 'Error: ' . $e->getMessage());
            self::log('opera', 'in', 'sync', 'error', $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /** Mock mode: realistic churn so the housekeeping board and front desk flow can be demonstrated. */
    private static function operaSimulate(): array {
        $events = [];
        $hour = (int)(new DateTime('now', new DateTimeZone(cfg('timezone'))))->format('G');
        // Late morning: due-outs depart and become vacant dirty
        if ($hour >= 7 && $hour <= 13) {
            foreach (Db::all("SELECT id, number, guest_name FROM rooms WHERE reservation_status = 'due_out' AND fo_status = 'occupied' ORDER BY " . Db::rand() . " LIMIT 3") as $r) {
                Rooms::setStatus((int)$r['id'], ['fo_status' => 'vacant', 'hk_status' => 'dirty', 'reservation_status' => 'departed', 'dnd' => 0], 'opera');
                $events[] = "Checked out {$r['number']} ({$r['guest_name']})";
            }
        }
        // Afternoon: arrivals check in to inspected rooms
        if ($hour >= 14 || $hour <= 1) {
            foreach (Db::all("SELECT id, number, guest_name FROM rooms WHERE reservation_status = 'arrival' AND hk_status = 'inspected' AND fo_status = 'vacant' ORDER BY " . Db::rand() . " LIMIT 3") as $r) {
                Rooms::setStatus((int)$r['id'], ['fo_status' => 'occupied', 'reservation_status' => 'arrived'], 'opera');
                $events[] = "Checked in {$r['number']} ({$r['guest_name']})";
            }
        }
        // Guest-initiated make-up-room flags and DND toggles
        foreach (Db::all("SELECT id, number FROM rooms WHERE fo_status = 'occupied' AND reservation_status = 'stayover' ORDER BY " . Db::rand() . " LIMIT 1") as $r) {
            Rooms::setStatus((int)$r['id'], ['make_up_room' => 1, 'dnd' => 0], 'opera');
            $events[] = "Make up room requested in {$r['number']}";
        }
        $summary = $events ? implode('; ', $events) : 'No changes';
        self::log('opera', 'in', 'sync (mock)', 'ok', $summary);
        self::mark('opera', 'Mock sync: ' . count($events) . ' change(s)');
        return ['mock' => true, 'events' => $events];
    }

    public static function pushRoomStatus(array $room, array $changes): void {
        if (!isset($changes['hk_status']) && !isset($changes['service_status'])) return;
        if (!self::enabled('opera')) return;
        $c = self::config('opera');
        if (($c['push_room_status'] ?? 'yes') === 'no') return;
        $map = ['dirty' => 'Dirty', 'clean' => 'Clean', 'inspected' => 'Inspected', 'pickup' => 'Pickup', 'in_progress' => 'Dirty'];
        $summary = "Room {$room['number']}: " . json_encode(array_intersect_key($changes, ['hk_status' => 1, 'service_status' => 1]));
        if (!self::live('opera')) { self::log('opera', 'out', 'room status (mock)', 'ok', $summary); return; }
        try {
            if (isset($changes['hk_status'])) self::opera('PUT', 'room_status_update', ['roomId' => $room['opera_room_id'] ?: $room['number']], ['housekeepingRoomStatus' => ['housekeepingRoomStatus' => $map[$changes['hk_status']] ?? 'Dirty']]);
            if (($changes['service_status'] ?? null) === 'ooo') self::opera('POST', 'out_of_order', [], ['roomOutOfOrder' => ['roomId' => $room['opera_room_id'] ?: $room['number'], 'reasonCode' => 'MAINT', 'startDate' => U::localDate(), 'endDate' => $room['ooo_until'] ?: U::localDate(time() + 86400), 'returnStatus' => 'Dirty', 'comments' => $room['ooo_reason']]]);
        } catch (Throwable $e) { self::log('opera', 'out', 'room status', 'error', $summary . ' ' . $e->getMessage()); }
    }

    public static function postMinibar(int $postingId): void {
        $p = Db::one('SELECT m.*, r.reservation_ref, r.number FROM minibar_postings m JOIN rooms r ON r.id = m.room_id WHERE m.id = ?', [$postingId]);
        if (!$p || !self::enabled('opera')) return;
        if (!self::live('opera')) {
            Db::update('minibar_postings', ['pms_posted' => 1, 'pms_reference' => 'MOCK-' . $postingId], $postingId);
            self::log('opera', 'out', 'minibar charge (mock)', 'ok', "Room {$p['number']}: {$p['qty']} × {$p['item']} \${$p['amount']}");
            return;
        }
        try {
            $r = self::opera('POST', 'post_charge', ['reservationId' => $p['reservation_ref']], ['charges' => [['transactionCode' => 'MINIBAR', 'amount' => ['amount' => (float)$p['amount'], 'currencyCode' => cfg('currency', 'USD')], 'quantity' => (int)$p['qty'], 'reference' => 'ExpTix ' . $postingId, 'remark' => $p['item']]]]);
            Db::update('minibar_postings', ['pms_posted' => 1, 'pms_reference' => (string)($r['id'] ?? 'posted')], $postingId);
        } catch (Throwable $e) { self::log('opera', 'out', 'minibar charge', 'error', $e->getMessage()); }
    }

    // ================= CANARY =================
    public static function guestReply(array $t, string $message): void {
        if ($t['source'] !== 'canary' || !self::enabled('canary')) return;
        $c = self::config('canary');
        if (!self::live('canary')) { self::log('canary', 'out', 'guest message (mock)', 'ok', "{$t['number']}: " . mb_substr($message, 0, 200)); return; }
        $r = U::http('POST', rtrim($c['api_base'] ?? '', '/') . '/messages', ['Authorization' => 'Bearer ' . ($c['api_key'] ?? ''), 'Content-Type' => 'application/json'],
            json_encode(['property_id' => $c['property_id'] ?? null, 'conversation_id' => $t['external_ref'], 'body' => $message]));
        self::log('canary', 'out', 'guest message', $r['status'] < 300 ? 'ok' : 'error', "{$t['number']} HTTP {$r['status']}");
    }

    public static function onTicketStatus(array $t, string $old): void {
        if ($t['source'] === 'canary' && in_array($t['status'], ['acknowledged', 'in_progress', 'resolved'], true) && ($c = self::config('canary')) && ($c['auto_reply'] ?? 'yes') !== 'no') {
            $msg = match ($t['status']) {
                'acknowledged', 'in_progress' => "Thanks, our team is on it: {$t['title']}.",
                'resolved' => "All done: {$t['title']}. Let us know if there is anything else we can do.",
            };
            if ($old === 'new' || $t['status'] === 'resolved') self::guestReply($t, $msg);
        }
    }

    // ================= EMAIL TO TICKET =================
    public static function pollEmail(): array {
        if (!self::enabled('email_in') || !function_exists('imap_open')) return ['skipped' => true];
        $c = self::config('email_in');
        $mbox = '{' . $c['host'] . ':' . ($c['port'] ?: 993) . '/imap/ssl}' . ($c['folder'] ?: 'INBOX');
        $im = @imap_open($mbox, $c['username'], $c['password'], 0, 1);
        if (!$im) { self::mark('email_in', 'Login failed'); return ['error' => imap_last_error()]; }
        $n = 0;
        foreach (imap_search($im, 'UNSEEN') ?: [] as $msg) {
            if ($n >= 20) break;
            $h = imap_headerinfo($im, $msg);
            $subject = iconv_mime_decode($h->subject ?? '(no subject)', 0, 'UTF-8');
            $from = $h->from[0]->mailbox . '@' . $h->from[0]->host;
            $body = trim(strip_tags(imap_fetchbody($im, $msg, '1')));
            $body = mb_substr(quoted_printable_decode($body), 0, 8000);
            if (preg_match('/\[(' . preg_quote(cfg('property_code')) . '-\d{6})\]/', $subject, $m) && ($tid = Db::val('SELECT id FROM tickets WHERE number = ?', [$m[1]]))) {
                Tickets::event((int)$tid, 'comment', "Email from $from:\n$body", [], 'internal', null);
            } else {
                $dept = Db::val('SELECT id FROM departments WHERE code = ?', [$c['default_department'] ?: 'FO']);
                Tickets::create(['title' => $subject, 'description' => $body, 'guest_contact' => $from, 'requester_name' => $from, 'department_id' => null, 'type' => 'guest_request'] + ($dept ? [] : []), 'email', null);
            }
            imap_setflag_full($im, (string)$msg, '\\Seen');
            $n++;
        }
        imap_close($im);
        self::mark('email_in', "Processed $n message(s)");
        return ['processed' => $n];
    }

    // ================= SENSORS =================
    public static function sensorReading(array $in): array {
        $sid = U::str($in['sensor_id'] ?? null, 80);
        $kind = $in['kind'] ?? 'temperature';
        $val = isset($in['value']) ? (float)$in['value'] : null;
        if (!$sid) throw new HttpError(422, 'sensor_id required');
        if ($kind === 'temperature') {
            $pt = Db::one('SELECT * FROM temp_points WHERE sensor_id = ? AND active = 1', [$sid]);
            if (!$pt) throw new HttpError(404, 'No temperature point uses that sensor id');
            return FoodSafety::record((int)$pt['id'], (float)$val, 'sensor', null);
        }
        // Leak, humidity, BMS alarm: open an urgent engineering ticket (deduplicated per sensor while open)
        $open = Db::val("SELECT id FROM tickets WHERE external_system = 'sensor' AND external_ref = ? AND status IN ('new','acknowledged','in_progress','on_hold')", [$sid]);
        if ($open) { Tickets::event((int)$open, 'system', "Sensor reported again: $kind " . ($val ?? '') . ' ' . ($in['unit'] ?? '')); return ['ticket_id' => (int)$open, 'deduplicated' => true]; }
        $eng = Db::val("SELECT id FROM departments WHERE code = 'ENG'");
        $t = Tickets::create(['title' => ucfirst($kind) . ' alarm: ' . ($in['location'] ?? $sid), 'description' => "Sensor $sid reported $kind" . ($val !== null ? " = $val " . ($in['unit'] ?? '') : '') . '. ' . ($in['message'] ?? ''),
            'department_id' => $eng, 'priority' => $kind === 'leak' ? 'urgent' : 'high', 'external_ref' => $sid, 'external_system' => 'sensor', 'room' => $in['room'] ?? null, 'type' => 'work_order'], 'sensor', null);
        return ['ticket_id' => (int)$t['id'], 'number' => $t['number']];
    }
}

/** HACCP temperature logging with automatic corrective tickets. */
final class FoodSafety {
    public static function record(int $pointId, float $reading, string $source, ?string $action): array {
        $pt = Db::one('SELECT * FROM temp_points WHERE id = ?', [$pointId]);
        if (!$pt) throw new HttpError(404, 'Unknown temperature point');
        $in = ($pt['min_c'] === null || $reading >= (float)$pt['min_c']) && ($pt['max_c'] === null || $reading <= (float)$pt['max_c']);
        $logId = Db::insert('temp_logs', ['point_id' => $pointId, 'reading_c' => $reading, 'in_range' => $in ? 1 : 0, 'source' => $source, 'recorded_by' => Auth::$user['id'] ?? null, 'corrective_action' => U::str($action), 'created_at' => U::now()]);
        $ticket = null;
        if (!$in) {
            $open = Db::val("SELECT id FROM tickets WHERE external_system = 'haccp' AND external_ref = ? AND status IN ('new','acknowledged','in_progress','on_hold')", ['point:' . $pointId]);
            if ($open) {
                Tickets::event((int)$open, 'system', "Still out of range: {$reading} °C", []);
                $ticket = ['id' => (int)$open];
            } else {
                $kitchen = Db::val("SELECT id FROM departments WHERE code = 'KIT'");
                $range = ($pt['min_c'] !== null ? $pt['min_c'] : '') . ' to ' . ($pt['max_c'] !== null ? $pt['max_c'] : '') . ' °C';
                $ticket = Tickets::create(['title' => "Temperature out of range: {$pt['name']} at {$reading} °C", 'description' => "Safe range is $range. Check the unit, move product if needed, and record the corrective action. Discard any high-risk food held above 4 °C for more than 2 hours.",
                    'department_id' => $kitchen, 'priority' => 'urgent', 'external_system' => 'haccp', 'external_ref' => 'point:' . $pointId, 'type' => 'work_order',
                    'checklist' => ['Re-check with a calibrated probe thermometer', 'Move product to a working unit', 'Assess and discard unsafe product (record quantities)', 'Call engineering or refrigeration contractor if the unit is faulty', 'Record corrective action on the log']], $source === 'sensor' ? 'sensor' : 'staff');
                Notify::generic('temp.out_of_range', $kitchen ? (int)$kitchen : null, "Out of range: {$pt['name']}", "{$reading} °C (safe $range)", '#/t/' . $ticket['id'], 'urgent');
            }
            Db::update('temp_logs', ['ticket_id' => $ticket['id']], $logId);
        }
        Audit::log('haccp.reading', 'temp_logs', $logId, ['point' => $pt['name'], 'reading' => $reading, 'in_range' => $in]);
        return ['id' => $logId, 'in_range' => $in, 'ticket_id' => $ticket['id'] ?? null];
    }

    public static function dueChecks(): array {
        $out = [];
        foreach (Db::all('SELECT p.*, (SELECT MAX(created_at) FROM temp_logs l WHERE l.point_id = p.id) AS last_at, (SELECT reading_c FROM temp_logs l WHERE l.point_id = p.id ORDER BY id DESC LIMIT 1) AS last_reading, (SELECT in_range FROM temp_logs l WHERE l.point_id = p.id ORDER BY id DESC LIMIT 1) AS last_in_range FROM temp_points p WHERE p.active = 1 ORDER BY p.name') as $p) {
            $p['overdue'] = !$p['last_at'] || strtotime($p['last_at'] . ' UTC') < time() - (int)$p['check_every_hours'] * 3600;
            $out[] = $p;
        }
        return $out;
    }
}
