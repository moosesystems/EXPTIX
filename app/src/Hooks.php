<?php
declare(strict_types=1);

/** Inbound webhooks from Opera business events, Canary, sensors and generic automation platforms. */
final class Hooks {
    public static function handle(string $provider): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new HttpError(405, 'POST only');
        U::rateLimit("hook:$provider:" . U::ip(), 300, 300);
        Auth::$via = "hook:$provider";
        $raw = U::rawBody();
        $in = json_decode($raw, true);
        if (!is_array($in)) throw new HttpError(400, 'Expected JSON');
        $r = match ($provider) {
            'opera' => self::opera($in, $raw),
            'canary' => self::canary($in, $raw),
            'sensor' => self::sensor($in),
            'inbound' => self::inbound($in),
            default => throw new HttpError(404, 'Unknown hook'),
        };
        U::json($r);
    }

    private static function sharedSecretOk(string $provider, string $field, string $raw): bool {
        $c = Integrations::config($provider);
        $secret = $c[$field] ?? '';
        if ($secret === '') return cfg('demo_mode') === true; // demo accepts unsigned events; production must set a secret
        $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? $_SERVER['HTTP_X_CANARY_SIGNATURE'] ?? $_SERVER['HTTP_X_EXPTIX_SIGNATURE'] ?? '';
        $sig = preg_replace('/^sha256=/', '', $sig);
        if ($sig && hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) return true;
        return hash_equals($secret, (string)($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
    }

    /** OHIP business events (CHECKIN, CHECKOUT, UPDATE_ROOM_STATUS, NEW_RESERVATION, ROOM_MOVE ...). */
    private static function opera(array $in, string $raw): array {
        if (!Integrations::enabled('opera')) throw new HttpError(409, 'Opera integration is off');
        if (!self::sharedSecretOk('opera', 'events_secret', $raw)) throw new HttpError(401, 'Bad signature');
        $events = $in['events'] ?? [$in];
        $done = [];
        foreach ($events as $ev) {
            $name = strtoupper((string)($ev['eventName'] ?? $ev['event'] ?? ''));
            $d = $ev['detail'] ?? $ev['data'] ?? $ev;
            $num = (string)($d['roomId'] ?? $d['roomNumber'] ?? $d['room'] ?? '');
            $room = $num !== '' ? Db::one('SELECT * FROM rooms WHERE number = ? OR opera_room_id = ?', [$num, $num]) : null;
            if (!$room) { $done[] = "$name: room not matched"; continue; }
            $guest = trim(($d['guestFirstName'] ?? '') . ' ' . ($d['guestLastName'] ?? '')) ?: ($d['guestName'] ?? null);
            switch (true) {
                case str_contains($name, 'CHECKOUT'): case str_contains($name, 'CHECK_OUT'):
                    Rooms::setStatus((int)$room['id'], ['fo_status' => 'vacant', 'hk_status' => 'dirty', 'reservation_status' => 'departed', 'dnd' => 0], 'opera'); break;
                case str_contains($name, 'CHECKIN'): case str_contains($name, 'CHECK_IN'):
                    Rooms::setStatus((int)$room['id'], array_filter(['fo_status' => 'occupied', 'reservation_status' => 'arrived', 'guest_name' => $guest, 'reservation_ref' => $d['confirmationNumber'] ?? null, 'departure_date' => $d['departureDate'] ?? null, 'vip_code' => $d['vipStatus'] ?? null]), 'opera'); break;
                case str_contains($name, 'ROOM_STATUS'): case str_contains($name, 'HOUSEKEEPING'):
                    $map = ['DIRTY' => 'dirty', 'CLEAN' => 'clean', 'INSPECTED' => 'inspected', 'PICKUP' => 'pickup'];
                    if ($hk = $map[strtoupper((string)($d['roomStatus'] ?? ''))] ?? null) Rooms::setStatus((int)$room['id'], ['hk_status' => $hk], 'opera'); break;
                case str_contains($name, 'RESERVATION'):
                    Rooms::setStatus((int)$room['id'], array_filter(['reservation_status' => 'arrival', 'guest_name' => $guest, 'reservation_ref' => $d['confirmationNumber'] ?? null, 'arrival_date' => $d['arrivalDate'] ?? null, 'departure_date' => $d['departureDate'] ?? null, 'vip_code' => $d['vipStatus'] ?? null]), 'opera'); break;
                case str_contains($name, 'ROOM_MOVE'):
                    Rooms::setStatus((int)$room['id'], ['fo_status' => 'vacant', 'hk_status' => 'dirty', 'reservation_status' => 'departed'], 'opera'); break;
            }
            $done[] = "$name room $num";
        }
        Integrations::log('opera', 'in', 'business events', 'ok', implode('; ', $done));
        return ['ok' => true, 'processed' => $done];
    }

    /** Canary guest messages and requests become tickets, threaded by conversation id. */
    private static function canary(array $in, string $raw): array {
        if (!Integrations::enabled('canary')) throw new HttpError(409, 'Canary integration is off');
        if (!self::sharedSecretOk('canary', 'webhook_secret', $raw)) throw new HttpError(401, 'Bad signature');
        $d = $in['data'] ?? $in;
        $conv = (string)($d['conversation_id'] ?? $d['thread_id'] ?? '');
        $msg = U::str($d['message'] ?? $d['body'] ?? $d['text'] ?? null, 4000);
        if (!$msg) throw new HttpError(422, 'No message text');
        if ($conv && ($tid = Db::val("SELECT id FROM tickets WHERE external_system = 'canary' AND external_ref = ? AND status NOT IN ('closed','cancelled') ORDER BY id DESC LIMIT 1", [$conv]))) {
            Tickets::event((int)$tid, 'guest_message', $msg, [], 'guest', ['id' => null]);
            $t = Db::one('SELECT * FROM tickets WHERE id = ?', [$tid]);
            Notify::ticket('ticket.comment', $t, ['comment' => "Guest via Canary: $msg"]);
            Integrations::log('canary', 'in', 'message (threaded)', 'ok', $t['number']);
            return ['ok' => true, 'ticket' => $t['number'], 'threaded' => true];
        }
        $t = Tickets::create(['title' => mb_substr($msg, 0, 120), 'description' => $msg, 'room' => $d['room_number'] ?? $d['room'] ?? null, 'guest_name' => $d['guest_name'] ?? null,
            'guest_contact' => $d['guest_phone'] ?? $d['guest_email'] ?? null, 'reservation_ref' => $d['reservation_id'] ?? $d['confirmation_number'] ?? null, 'type' => 'guest_request', 'external_ref' => $conv ?: null, 'external_system' => 'canary'],
            'canary', ['id' => null, 'username' => 'canary', 'name' => 'Canary']);
        Tickets::event((int)$t['id'], 'guest_message', $msg, [], 'guest', ['id' => null]);
        Integrations::log('canary', 'in', 'request', 'ok', $t['number'] . ' ' . mb_substr($msg, 0, 100));
        $c = Integrations::config('canary');
        if (($c['auto_reply'] ?? 'yes') !== 'no') Integrations::guestReply($t, 'Thanks, we have your request and the team has been notified.');
        return ['ok' => true, 'ticket' => $t['number']];
    }

    private static function sensor(array $in): array {
        if (!Integrations::enabled('sensors')) throw new HttpError(409, 'Sensor integration is off');
        $c = Integrations::config('sensors');
        if (($c['token'] ?? '') === '' || !hash_equals((string)$c['token'], (string)($_SERVER['HTTP_X_SENSOR_TOKEN'] ?? ''))) throw new HttpError(401, 'Bad sensor token');
        $readings = isset($in['readings']) ? $in['readings'] : [$in];
        $out = [];
        foreach (array_slice($readings, 0, 100) as $r) { try { $out[] = Integrations::sensorReading((array)$r); } catch (HttpError $e) { $out[] = ['error' => $e->getMessage(), 'sensor_id' => $r['sensor_id'] ?? null]; } }
        return ['ok' => true, 'results' => $out];
    }

    /** Generic inbound for Zapier, Make, Power Automate: needs an API token with write scope. */
    private static function inbound(array $in): array {
        Auth::resolve();
        if (Auth::$via !== 'token') throw new HttpError(401, 'Send an API token as a Bearer token');
        Auth::require(20, true);
        $t = Tickets::create($in, 'api');
        return ['ok' => true, 'ticket' => Notify::ticketPayload($t)];
    }
}
