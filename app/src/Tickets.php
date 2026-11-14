<?php
declare(strict_types=1);

final class Tickets {
    public const OPEN = ['new', 'acknowledged', 'in_progress', 'on_hold'];
    public const TRANSITIONS = [
        'new'          => ['acknowledged', 'in_progress', 'on_hold', 'resolved', 'cancelled'],
        'acknowledged' => ['in_progress', 'on_hold', 'resolved', 'cancelled'],
        'in_progress'  => ['on_hold', 'resolved', 'cancelled'],
        'on_hold'      => ['in_progress', 'resolved', 'cancelled'],
        'resolved'     => ['closed', 'in_progress'],
        'closed'       => ['in_progress'],
        'cancelled'    => ['new'],
    ];
    private const PRIORITY_RANK = ['low' => 1, 'normal' => 2, 'high' => 3, 'urgent' => 4, 'critical' => 5];
    public static function prank(?string $p): int { return self::PRIORITY_RANK[$p ?? 'normal'] ?? 2; }

    /** Keyword router used by the guest portal, Canary, email, sensors and the MCP server. */
    public static function classify(string $text): array {
        $t = ' ' . strtolower($text) . ' ';
        $best = null; $bestScore = 0;
        foreach (Db::all('SELECT id, department_id, keywords, default_priority FROM categories WHERE active = 1 AND keywords IS NOT NULL') as $c) {
            $score = 0;
            foreach (explode(',', strtolower((string)$c['keywords'])) as $k) {
                $k = trim($k);
                if ($k !== '' && str_contains($t, $k)) $score += strlen($k) > 5 ? 2 : 1;
            }
            if ($score > $bestScore) { $best = $c; $bestScore = $score; }
        }
        $priority = $best['default_priority'] ?? 'normal';
        if (preg_match('/\b(flood|fire|smoke|gas smell|sparks|no heat|stuck in (the )?elevator|injur|bleeding|unconscious|emergency|leak(ing)? (from|through) (the )?ceiling)\b/', $t)) $priority = 'urgent';
        return ['category_id' => $best['id'] ?? null, 'department_id' => $best['department_id'] ?? null, 'priority' => $priority, 'confidence' => $bestScore];
    }

    public static function slaFor(?int $categoryId, string $priority): ?array {
        if ($categoryId) {
            $s = Db::one('SELECT s.* FROM categories c JOIN sla_policies s ON s.id = c.sla_policy_id WHERE c.id = ? AND s.active = 1', [$categoryId]);
            if ($s) {
                // A higher-priority ticket never gets a looser SLA than the priority default.
                $p = Db::one('SELECT * FROM sla_policies WHERE priority = ? AND active = 1 ORDER BY id LIMIT 1', [$priority]);
                if ($p && $p['resolve_minutes'] < $s['resolve_minutes'] && self::prank($priority) >= 3) return $p;
                return $s;
            }
        }
        return Db::one('SELECT * FROM sla_policies WHERE priority = ? AND active = 1 ORDER BY id LIMIT 1', [$priority]);
    }

    /** Build the WHERE clause limiting a user to tickets they may see. */
    public static function scopeSql(?array $u = null): array {
        $u ??= Auth::$user;
        if (!$u || Auth::seesAll($u)) return ['1=1', []];
        if ($u['role'] === 'contractor') return ['t.assigned_to = ?', [(int)$u['id']]];
        $deps = Auth::departments($u);
        $in = $deps ? 't.department_id IN (' . implode(',', array_map('intval', $deps)) . ') OR ' : '';
        return ["($in t.assigned_to = ? OR t.created_by = ?)", [(int)$u['id'], (int)$u['id']]];
    }

    public static function canSee(array $t): bool {
        $u = Auth::$user;
        if (!$u) return false;
        if (Auth::seesAll()) return true;
        if ($u['role'] === 'contractor') return (int)$t['assigned_to'] === (int)$u['id'];
        return in_array((int)$t['department_id'], Auth::departments(), true) || (int)$t['assigned_to'] === (int)$u['id'] || (int)$t['created_by'] === (int)$u['id'];
    }

    public static function get(int $id): array {
        $t = Db::one('SELECT * FROM tickets WHERE id = ?', [$id]);
        if (!$t) throw new HttpError(404, 'Ticket not found');
        if (!self::canSee($t)) throw new HttpError(403, 'You cannot view this ticket');
        return $t;
    }

    public static function create(array $in, string $source = 'staff', ?array $actor = null): array {
        $actor ??= Auth::$user;
        $title = U::str($in['title'] ?? null, 255);
        $desc = U::str($in['description'] ?? null);
        if (!$title && $desc) $title = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 90);
        if (!$title) throw new HttpError(422, 'Describe the request');

        $categoryId = !empty($in['category_id']) ? (int)$in['category_id'] : null;
        $deptId = !empty($in['department_id']) ? (int)$in['department_id'] : null;
        $priority = $in['priority'] ?? null;
        if (!$categoryId || !$deptId) {
            $c = self::classify($title . ' ' . $desc);
            $categoryId ??= $c['category_id'];
            $deptId ??= $c['department_id'];
            $priority ??= $c['priority'];
        }
        if ($categoryId && !$deptId) $deptId = (int)Db::val('SELECT department_id FROM categories WHERE id = ?', [$categoryId]) ?: null;
        if ($categoryId && !$priority) $priority = Db::val('SELECT default_priority FROM categories WHERE id = ?', [$categoryId]);
        if (!$deptId) $deptId = (int)Db::val("SELECT id FROM departments WHERE code = 'FO'") ?: null; // triage at the front desk
        $priority = isset(self::PRIORITY_RANK[$priority ?? '']) ? $priority : 'normal';

        $roomId = !empty($in['room_id']) ? (int)$in['room_id'] : null;
        if (!$roomId && !empty($in['room'])) $roomId = (int)Db::val('SELECT id FROM rooms WHERE number = ?', [trim((string)$in['room'])]) ?: null;
        $room = $roomId ? Db::one('SELECT * FROM rooms WHERE id = ?', [$roomId]) : null;

        $vip = !empty($in['vip']) || ($room && $room['vip_code'] && in_array($room['fo_status'], ['occupied'], true));
        if ($vip && self::prank($priority) < 3) $priority = 'high';

        $sla = self::slaFor($categoryId, $priority);
        $now = U::now();
        $type = $in['type'] ?? (Db::val('SELECT type FROM categories WHERE id = ?', [$categoryId]) ?: 'work_order');
        if (!isset(Registry::OPT['ticket_type'][$type])) $type = 'work_order';

        $row = [
            'type' => $type, 'department_id' => $deptId, 'category_id' => $categoryId, 'title' => $title, 'description' => $desc,
            'priority' => $priority, 'status' => 'new', 'source' => isset(Registry::OPT['source'][$source]) ? $source : 'api',
            'location_id' => !empty($in['location_id']) ? (int)$in['location_id'] : ($room['location_id'] ?? null), 'room_id' => $roomId,
            'asset_id' => !empty($in['asset_id']) ? (int)$in['asset_id'] : null, 'event_id' => !empty($in['event_id']) ? (int)$in['event_id'] : null,
            'guest_name' => U::str($in['guest_name'] ?? null, 120) ?? ($type === 'guest_request' || $type === 'complaint' ? ($room['guest_name'] ?? null) : null),
            'reservation_ref' => U::str($in['reservation_ref'] ?? null, 40) ?? ($room['reservation_ref'] ?? null),
            'guest_contact' => U::str($in['guest_contact'] ?? null, 190), 'vip' => $vip ? 1 : 0,
            'guest_token' => in_array($source, ['guest_portal', 'canary', 'email'], true) || $type === 'guest_request' ? U::token(12) : null,
            'requester_name' => U::str($in['requester_name'] ?? null, 120) ?? ($actor['name'] ?? null),
            'assigned_to' => !empty($in['assigned_to']) ? (int)$in['assigned_to'] : null,
            'created_by' => $actor['id'] ?? null,
            'response_due_at' => $sla ? U::plusMinutes((int)$sla['response_minutes']) : null,
            'due_at' => !empty($in['due_at']) ? self::toUtc($in['due_at']) : ($sla ? U::plusMinutes((int)$sla['resolve_minutes']) : null),
            'scheduled_for' => !empty($in['scheduled_for']) ? self::toUtc($in['scheduled_for']) : null,
            'parent_id' => !empty($in['parent_id']) ? (int)$in['parent_id'] : null, 'pm_schedule_id' => !empty($in['pm_schedule_id']) ? (int)$in['pm_schedule_id'] : null,
            'external_ref' => U::str($in['external_ref'] ?? null, 120), 'external_system' => U::str($in['external_system'] ?? null, 30),
            'tags' => U::str(is_array($in['tags'] ?? null) ? implode(',', $in['tags']) : ($in['tags'] ?? null), 255),
            'created_at' => $now, 'updated_at' => $now,
        ];
        if (!$row['assigned_to']) $row['assigned_to'] = self::autoAssign($deptId, $roomId);

        // Repeat-issue detection: same room and category in the last 30 days (quality signal for engineering).
        if ($roomId && $categoryId) {
            $row['repeat_count'] = (int)Db::val('SELECT COUNT(*) FROM tickets WHERE room_id = ? AND category_id = ? AND created_at > ?', [$roomId, $categoryId, U::ts(time() - 30 * 86400)]);
        }

        $id = Db::insert('tickets', $row);
        $number = cfg('property_code') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        Db::update('tickets', ['number' => $number], $id);
        self::event($id, 'system', 'Ticket created from ' . (Registry::OPT['source'][$row['source']] ?? $row['source']), ['priority' => $priority, 'sla' => $sla['name'] ?? null], 'internal', $actor);
        if ($row['repeat_count'] > 0) self::event($id, 'system', "Repeat issue: {$row['repeat_count']} similar ticket(s) for this room in the last 30 days.", [], 'internal', null);

        // Category checklist
        $tpl = $categoryId ? Db::val('SELECT t.items FROM categories c JOIN checklist_templates t ON t.id = c.checklist_template_id WHERE c.id = ?', [$categoryId]) : null;
        if (!empty($in['checklist']) && is_array($in['checklist'])) $tpl = implode("\n", $in['checklist']);
        if ($tpl) self::addChecklist($id, (string)$tpl);

        // Out of order / make-up room side effects
        if ($room && !empty($in['set_room_ooo'])) {
            Rooms::setStatus($roomId, ['service_status' => 'ooo', 'ooo_reason' => mb_substr($title, 0, 255)], 'ticket');
        }

        $t = Db::one('SELECT * FROM tickets WHERE id = ?', [$id]);
        Audit::log('ticket.create', 'tickets', $id, ['number' => $number, 'source' => $row['source'], 'department_id' => $deptId, 'priority' => $priority], $actor);
        Notify::ticket('ticket.created', $t);
        if ($t['vip']) Notify::ticket('ticket.vip', $t);
        if ($t['assigned_to']) Notify::ticket('ticket.assigned', $t);
        return $t;
    }

    public static function toUtc(string $local): string {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $local) && str_ends_with($local, 'Z') === false && strlen($local) === 19 && !str_contains($local, 'T')) {
            // already storage format
        }
        $d = new DateTime($local, new DateTimeZone(cfg('timezone')));
        $d->setTimezone(new DateTimeZone('UTC'));
        return $d->format('Y-m-d H:i:s');
    }

    /** Round-robin to on-shift staff in the department, preferring the room's housekeeping attendant. */
    public static function autoAssign(?int $deptId, ?int $roomId): ?int {
        if (!$deptId) return null;
        $code = Db::val('SELECT code FROM departments WHERE id = ?', [$deptId]);
        if ($code === 'HK' && $roomId) {
            $att = Db::val('SELECT attendant_id FROM rooms WHERE id = ?', [$roomId]);
            if ($att) return (int)$att;
        }
        if (!in_array($code, ['ENG', 'HK', 'IT', 'SEC'], true)) return null; // other departments triage from their queue
        $u = Db::one("SELECT u.id, (SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = u.id AND t.status IN ('new','acknowledged','in_progress')) AS workload
            FROM users u WHERE u.active = 1 AND u.on_shift = 1 AND u.department_id = ? AND u.role IN ('staff','supervisor') ORDER BY workload ASC, u.id LIMIT 1", [$deptId]);
        return $u ? (int)$u['id'] : null;
    }

    public static function addChecklist(int $ticketId, string $items): void {
        $i = 0;
        foreach (preg_split('/\r?\n/', $items) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '##')) continue;
            Db::insert('ticket_checklist', ['ticket_id' => $ticketId, 'item' => mb_substr($line, 0, 255), 'sort' => $i++]);
        }
    }

    public static function event(int $ticketId, string $kind, ?string $body, array $data = [], string $vis = 'internal', ?array $actor = null): int {
        $actor = $actor === null && $kind !== 'system' ? Auth::$user : $actor;
        return Db::insert('ticket_events', ['ticket_id' => $ticketId, 'user_id' => $actor['id'] ?? null, 'kind' => $kind, 'body' => $body, 'data' => $data ? json_encode($data) : null, 'visibility' => $vis, 'created_at' => U::now()]);
    }

    public static function update(int $id, array $in): array {
        $t = self::get($id);
        Auth::require(20, true);
        $u = Auth::$user;
        $upd = [];
        $events = [];
        $allowed = ['title', 'description', 'priority', 'department_id', 'category_id', 'location_id', 'room_id', 'asset_id', 'event_id', 'guest_name', 'guest_contact', 'reservation_ref', 'due_at', 'scheduled_for', 'resolution', 'recovery_action', 'recovery_value', 'tags', 'type', 'vip', 'satisfaction', 'satisfaction_comment', 'external_ref'];
        if ($u['role'] === 'contractor') $allowed = ['resolution'];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $in)) continue;
            $v = $in[$f];
            if (in_array($f, ['department_id', 'category_id', 'location_id', 'room_id', 'asset_id', 'event_id'], true)) $v = $v ? (int)$v : null;
            if (in_array($f, ['due_at', 'scheduled_for'], true)) $v = $v ? self::toUtc((string)$v) : null;
            if ($f === 'priority' && !isset(self::PRIORITY_RANK[$v])) continue;
            if ($f === 'vip') $v = $v ? 1 : 0;
            if ((string)$t[$f] !== (string)$v) $upd[$f] = $v;
        }
        if (isset($upd['priority'])) $events[] = ['priority', 'Priority changed to ' . Registry::OPT['priority'][$upd['priority']], ['from' => $t['priority'], 'to' => $upd['priority']]];
        if (isset($upd['department_id'])) $events[] = ['system', 'Moved to ' . Db::val('SELECT name FROM departments WHERE id = ?', [$upd['department_id']]), []];

        if (array_key_exists('assigned_to', $in)) {
            $to = $in['assigned_to'] ? (int)$in['assigned_to'] : null;
            if ($to !== ($t['assigned_to'] ? (int)$t['assigned_to'] : null)) {
                if (Auth::rank() < 50 && $to !== (int)$u['id']) throw new HttpError(403, 'Only supervisors can assign tickets to others. You can take it yourself.');
                $upd['assigned_to'] = $to;
                $name = $to ? Db::val('SELECT name FROM users WHERE id = ?', [$to]) : 'nobody';
                $events[] = ['assign', "Assigned to $name", ['from' => $t['assigned_to'], 'to' => $to]];
            }
        }

        $statusChanged = false;
        if (!empty($in['status']) && $in['status'] !== $t['status']) {
            $to = $in['status'];
            if (!in_array($to, self::TRANSITIONS[$t['status']] ?? [], true)) throw new HttpError(422, "Cannot move from {$t['status']} to $to");
            if ($to === 'cancelled' && Auth::rank() < 50 && (int)$t['created_by'] !== (int)$u['id']) throw new HttpError(403, 'Only supervisors can cancel tickets');
            if ($to === 'closed' && Auth::rank() < 40 && $u['role'] !== 'staff') throw new HttpError(403, 'Not permitted');
            if ($to === 'resolved') {
                $open = (int)Db::val('SELECT COUNT(*) FROM ticket_checklist WHERE ticket_id = ? AND done = 0', [$id]);
                if ($open && empty($in['force'])) throw new HttpError(422, "$open checklist item(s) still open. Complete them or resolve anyway.", ['checklist_open' => $open]);
            }
            $upd['status'] = $to;
            $now = U::now();
            if (!$t['first_response_at'] && $to !== 'new') $upd['first_response_at'] = $now;
            if ($to === 'in_progress' && !$t['started_at']) $upd['started_at'] = $now;
            if ($to === 'in_progress' && !$t['assigned_to'] && !isset($upd['assigned_to'])) { $upd['assigned_to'] = (int)$u['id']; $events[] = ['assign', "Assigned to {$u['name']}", ['to' => (int)$u['id']]]; }
            if ($to === 'resolved') $upd['resolved_at'] = $now;
            if ($to === 'closed') { $upd['closed_at'] = $now; if (!$t['resolved_at']) $upd['resolved_at'] = $now; }
            if (in_array($t['status'], ['resolved', 'closed'], true) && $to === 'in_progress') { $upd['resolved_at'] = null; $upd['closed_at'] = null; }
            $label = Registry::OPT['ticket_status'][$to];
            $note = U::str($in['status_note'] ?? null);
            $events[] = ['status', "Status changed to $label" . ($note ? ": $note" : ''), ['from' => $t['status'], 'to' => $to]];
            $statusChanged = true;
        }
        if (!$upd) return $t;
        $upd['updated_at'] = U::now();
        Db::update('tickets', $upd, $id);
        foreach ($events as [$kind, $body, $data]) self::event($id, $kind, $body, $data);
        Audit::log('ticket.update', 'tickets', $id, Audit::diff($t, $upd));
        $nt = Db::one('SELECT * FROM tickets WHERE id = ?', [$id]);

        if (isset($upd['assigned_to']) && $upd['assigned_to'] && (int)$upd['assigned_to'] !== (int)$u['id']) Notify::ticket('ticket.assigned', $nt);
        if ($statusChanged) {
            Notify::ticket($nt['status'] === 'resolved' ? 'ticket.resolved' : 'ticket.status', $nt);
            Integrations::onTicketStatus($nt, $t['status']);
            // Engineering fixed an out-of-order room: put it back to dirty so housekeeping cleans it.
            if (in_array($nt['status'], ['resolved', 'closed'], true) && $nt['room_id']) {
                $room = Db::one('SELECT * FROM rooms WHERE id = ?', [$nt['room_id']]);
                $otherOpen = (int)Db::val("SELECT COUNT(*) FROM tickets WHERE room_id = ? AND id <> ? AND status IN ('new','acknowledged','in_progress','on_hold') AND type = 'work_order'", [$nt['room_id'], $id]);
                if ($room && $room['service_status'] === 'ooo' && !$otherOpen && !empty($in['return_room_to_service'])) {
                    Rooms::setStatus((int)$nt['room_id'], ['service_status' => 'in_service', 'hk_status' => 'dirty', 'ooo_reason' => null], 'ticket');
                }
            }
        }
        return $nt;
    }

    public static function comment(int $id, string $body, string $vis = 'internal'): array {
        $t = self::get($id);
        Auth::require(20, true);
        $body = U::str($body, 20000);
        if (!$body) throw new HttpError(422, 'Comment is empty');
        $vis = $vis === 'guest' ? 'guest' : 'internal';
        $eid = self::event($id, 'comment', $body, [], $vis);
        $upd = ['updated_at' => U::now()];
        if (!$t['first_response_at'] && (int)$t['created_by'] !== (int)Auth::$user['id']) $upd['first_response_at'] = U::now();
        Db::update('tickets', $upd, $id);
        Audit::log('ticket.comment', 'tickets', $id, ['visibility' => $vis]);
        $nt = Db::one('SELECT * FROM tickets WHERE id = ?', [$id]);
        Notify::ticket('ticket.comment', $nt, ['comment' => $body]);
        if ($vis === 'guest') Integrations::guestReply($nt, $body);
        return ['id' => $eid];
    }

    public static function detail(int $id): array {
        $t = self::get($id);
        $t['events'] = Db::all('SELECT e.*, u.name AS user_name FROM ticket_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.ticket_id = ? ORDER BY e.id', [$id]);
        $t['checklist'] = Db::all('SELECT c.*, u.name AS done_by_name FROM ticket_checklist c LEFT JOIN users u ON u.id = c.done_by WHERE ticket_id = ? ORDER BY sort, id', [$id]);
        $t['time'] = Db::all('SELECT tt.*, u.name AS user_name FROM ticket_time tt LEFT JOIN users u ON u.id = tt.user_id WHERE ticket_id = ? ORDER BY id', [$id]);
        $t['parts'] = Db::all('SELECT tp.*, p.name AS part_name, p.sku FROM ticket_parts tp LEFT JOIN parts p ON p.id = tp.part_id WHERE ticket_id = ? ORDER BY tp.id', [$id]);
        $t['attachments'] = Db::all("SELECT id, filename, mime, size, created_at, uploaded_by FROM attachments WHERE entity = 'tickets' AND entity_id = ? ORDER BY id", [$id]);
        $t['children'] = Db::all('SELECT id, number, title, status, department_id FROM tickets WHERE parent_id = ? ORDER BY id', [$id]);
        $t['room'] = $t['room_id'] ? Db::one('SELECT * FROM rooms WHERE id = ?', [$t['room_id']]) : null;
        $t['asset'] = $t['asset_id'] ? Db::one('SELECT * FROM assets WHERE id = ?', [$t['asset_id']]) : null;
        $t['guest_notes'] = ($t['guest_name'] || $t['room_id']) ? Db::all('SELECT * FROM guest_notes WHERE (guest_name = ? OR (room_id = ? AND room_id IS NOT NULL)) AND (valid_until IS NULL OR valid_until >= ?) ORDER BY id DESC LIMIT 10', [$t['guest_name'] ?? '', $t['room_id'] ?? 0, U::localDate()]) : [];
        $t['transitions'] = self::TRANSITIONS[$t['status']] ?? [];
        $t['guest_link'] = $t['guest_token'] ? cfg('base_url') . '/g/t/' . $t['guest_token'] : null;
        if ($t['asset_id']) $t['asset_history'] = Db::all('SELECT id, number, title, status, created_at FROM tickets WHERE asset_id = ? AND id <> ? ORDER BY id DESC LIMIT 10', [$t['asset_id'], $id]);
        if ($t['room_id']) $t['room_history'] = Db::all('SELECT id, number, title, status, created_at FROM tickets WHERE room_id = ? AND id <> ? ORDER BY id DESC LIMIT 10', [$t['room_id'], $id]);
        return $t;
    }

    public static function list(array $q): array {
        [$scope, $p] = self::scopeSql();
        $w = [$scope];
        $f = fn($k) => isset($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        if ($v = $f('status')) {
            if ($v === 'open') $w[] = "t.status IN ('new','acknowledged','in_progress','on_hold')";
            elseif ($v === 'done') $w[] = "t.status IN ('resolved','closed')";
            else { $st = array_intersect(explode(',', $v), array_keys(Registry::OPT['ticket_status'])); if ($st) $w[] = "t.status IN ('" . implode("','", $st) . "')"; }
        }
        foreach (['department_id', 'category_id', 'room_id', 'asset_id', 'location_id', 'event_id', 'created_by'] as $k) if ($v = $f($k)) { $w[] = "t.$k = ?"; $p[] = (int)$v; }
        foreach (['priority', 'type', 'source'] as $k) if ($v = $f($k)) { $w[] = "t.$k = ?"; $p[] = $v; }
        if ($v = $f('assigned_to')) {
            if ($v === 'me') { $w[] = 't.assigned_to = ?'; $p[] = (int)Auth::$user['id']; }
            elseif ($v === 'none') $w[] = 't.assigned_to IS NULL';
            else { $w[] = 't.assigned_to = ?'; $p[] = (int)$v; }
        }
        if ($f('mine')) { $w[] = '(t.assigned_to = ? OR t.created_by = ?)'; $p[] = (int)Auth::$user['id']; $p[] = (int)Auth::$user['id']; }
        if ($f('overdue')) { $w[] = "t.status IN ('new','acknowledged','in_progress','on_hold') AND t.due_at < ?"; $p[] = U::now(); }
        if ($f('breached')) $w[] = 't.sla_breached = 1';
        if ($f('vip')) $w[] = 't.vip = 1';
        if ($v = $f('since')) { $w[] = 't.created_at >= ?'; $p[] = $v; }
        if ($v = $f('q')) {
            $w[] = '(t.number LIKE ? OR t.title LIKE ? OR t.description LIKE ? OR t.guest_name LIKE ? OR r.number = ?)';
            array_push($p, "%$v%", "%$v%", "%$v%", "%$v%", $v);
        }
        $order = match ($f('sort')) {
            'oldest' => 't.id ASC', 'due' => 'CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END, t.due_at ASC', 'updated' => 't.updated_at DESC',
            'priority' => "CASE t.priority WHEN 'critical' THEN 1 WHEN 'urgent' THEN 2 WHEN 'high' THEN 3 WHEN 'normal' THEN 4 ELSE 5 END, t.id DESC",
            default => 't.id DESC',
        };
        $limit = min(500, max(1, (int)($q['limit'] ?? 100)));
        $offset = max(0, (int)($q['offset'] ?? 0));
        $where = implode(' AND ', $w);
        $total = (int)Db::val("SELECT COUNT(*) FROM tickets t LEFT JOIN rooms r ON r.id = t.room_id WHERE $where", $p);
        $rows = Db::all("SELECT t.id, t.number, t.type, t.title, t.priority, t.status, t.source, t.department_id, t.category_id, t.room_id, r.number AS room_number, t.location_id, t.asset_id,
            t.assigned_to, u.name AS assignee_name, t.guest_name, t.vip, t.due_at, t.response_due_at, t.sla_breached, t.created_at, t.updated_at, t.resolved_at, t.repeat_count, t.escalation_level,
            (SELECT COUNT(*) FROM ticket_checklist c WHERE c.ticket_id = t.id) AS checklist_total, (SELECT COUNT(*) FROM ticket_checklist c WHERE c.ticket_id = t.id AND c.done = 1) AS checklist_done
            FROM tickets t LEFT JOIN rooms r ON r.id = t.room_id LEFT JOIN users u ON u.id = t.assigned_to WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset", $p);
        return ['total' => $total, 'rows' => $rows];
    }

    public static function logTime(int $id, int $minutes, ?string $note): void {
        self::get($id); Auth::require(20, true);
        if ($minutes <= 0 || $minutes > 1440) throw new HttpError(422, 'Minutes must be between 1 and 1440');
        Db::insert('ticket_time', ['ticket_id' => $id, 'user_id' => Auth::$user['id'], 'minutes' => $minutes, 'note' => U::str($note, 255), 'created_at' => U::now()]);
        Db::q('UPDATE tickets SET labour_minutes = (SELECT COALESCE(SUM(minutes),0) FROM ticket_time WHERE ticket_id = ?), updated_at = ? WHERE id = ?', [$id, U::now(), $id]);
        self::event($id, 'time', "Logged $minutes min" . ($note ? ": $note" : ''));
    }

    public static function usePart(int $id, int $partId, float $qty): void {
        self::get($id); Auth::require(20, true);
        $part = Db::one('SELECT * FROM parts WHERE id = ?', [$partId]);
        if (!$part || $qty <= 0) throw new HttpError(422, 'Choose a part and quantity');
        Db::tx(function () use ($id, $part, $qty) {
            Db::insert('ticket_parts', ['ticket_id' => $id, 'part_id' => $part['id'], 'qty' => $qty, 'unit_cost' => $part['unit_cost'], 'user_id' => Auth::$user['id'], 'created_at' => U::now()]);
            Db::q('UPDATE parts SET qty_on_hand = qty_on_hand - ?, updated_at = ? WHERE id = ?', [$qty, U::now(), $part['id']]);
            Db::insert('stock_movements', ['part_id' => $part['id'], 'qty' => -$qty, 'reason' => 'ticket', 'ticket_id' => $id, 'user_id' => Auth::$user['id'], 'created_at' => U::now()]);
            Db::q('UPDATE tickets SET parts_cost = (SELECT COALESCE(SUM(qty * unit_cost),0) FROM ticket_parts WHERE ticket_id = ?), updated_at = ? WHERE id = ?', [$id, U::now(), $id]);
        });
        self::event($id, 'parts', "Used $qty × {$part['name']}");
        $p = Db::one('SELECT * FROM parts WHERE id = ?', [$part['id']]);
        if ((float)$p['qty_on_hand'] <= (float)$p['reorder_point']) Notify::generic('stock.low', $p['department_id'] ? (int)$p['department_id'] : null, "Low stock: {$p['name']}", "{$p['qty_on_hand']} {$p['unit']} left (reorder point {$p['reorder_point']}).", '#/e/parts/' . $p['id'], 'normal');
    }

    /** SLA sweep: warnings, breaches and escalation. Called by the scheduler. */
    public static function slaSweep(): array {
        $now = U::now(); $n = ['warned' => 0, 'breached' => 0, 'escalated' => 0];
        foreach (Db::all("SELECT * FROM tickets WHERE status IN ('new','acknowledged','in_progress') AND sla_breached = 0 AND ((first_response_at IS NULL AND response_due_at < ?) OR due_at < ?)", [$now, $now]) as $t) {
            Db::update('tickets', ['sla_breached' => 1, 'escalation_level' => 1], (int)$t['id']);
            $what = (!$t['first_response_at'] && $t['response_due_at'] < $now) ? 'response' : 'resolution';
            self::event((int)$t['id'], 'system', "SLA $what target missed. Escalated to department leads.", ['sla' => $what]);
            Notify::ticket('ticket.sla_breach', $t);
            $n['breached']++;
        }
        // Second-level escalation: still open an hour after breach goes to the GM / policy escalation contact.
        foreach (Db::all("SELECT t.*, s.escalate_after_minutes, s.escalate_role, s.escalate_user_id FROM tickets t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN sla_policies s ON s.id = COALESCE(c.sla_policy_id, (SELECT id FROM sla_policies WHERE priority = t.priority LIMIT 1))
            WHERE t.status IN ('new','acknowledged','in_progress') AND t.sla_breached = 1 AND t.escalation_level = 1") as $t) {
            $after = (int)($t['escalate_after_minutes'] ?: 60);
            if ($t['due_at'] && strtotime($t['due_at'] . ' UTC') + $after * 60 < time()) {
                Db::update('tickets', ['escalation_level' => 2], (int)$t['id']);
                self::event((int)$t['id'], 'system', 'Escalated to ' . (Auth::ROLES[$t['escalate_role'] ?: 'gm']['label'] ?? 'management') . '.', []);
                Notify::escalate($t, $t['escalate_role'] ?: 'gm', $t['escalate_user_id'] ? (int)$t['escalate_user_id'] : null);
                $n['escalated']++;
            }
        }
        // 80 per cent warning (once, tracked by event marker)
        foreach (Db::all("SELECT * FROM tickets WHERE status IN ('new','acknowledged','in_progress') AND sla_breached = 0 AND due_at IS NOT NULL AND due_at > ?", [$now]) as $t) {
            $start = strtotime($t['created_at'] . ' UTC'); $due = strtotime($t['due_at'] . ' UTC');
            if ($due - $start > 0 && (time() - $start) / ($due - $start) >= 0.8) {
                $warned = Db::val("SELECT COUNT(*) FROM ticket_events WHERE ticket_id = ? AND kind = 'system' AND body LIKE 'SLA at risk%'", [$t['id']]);
                if (!$warned) { self::event((int)$t['id'], 'system', 'SLA at risk: 80% of the resolution window used.', []); Notify::ticket('ticket.sla_warning', $t); $n['warned']++; }
            }
        }
        return $n;
    }

    public static function storeUpload(string $entity, int $entityId, array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new HttpError(422, 'Upload failed');
        if ($file['size'] > cfg('uploads.max_mb') * 1048576) throw new HttpError(413, 'File is too large');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($mime, cfg('uploads.types'), true)) throw new HttpError(415, "File type $mime is not allowed");
        $stored = bin2hex(random_bytes(16));
        $dest = cfg('uploads.path') . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) throw new HttpError(500, 'Could not store file');
        @chmod($dest, 0640);
        $name = preg_replace('/[^\w.\- ]+/u', '_', basename((string)$file['name'])) ?: 'upload';
        $id = Db::insert('attachments', ['entity' => $entity, 'entity_id' => $entityId, 'filename' => mb_substr($name, 0, 255), 'stored_name' => $stored, 'mime' => $mime, 'size' => (int)$file['size'], 'sha256' => hash_file('sha256', $dest), 'uploaded_by' => Auth::$user['id'] ?? null, 'created_at' => U::now()]);
        Audit::log('attachment.upload', $entity, $entityId, ['attachment_id' => $id, 'filename' => $name, 'mime' => $mime]);
        if ($entity === 'tickets') self::event($entityId, 'attachment', "Attached $name", ['attachment_id' => $id]);
        return ['id' => $id, 'filename' => $name, 'mime' => $mime];
    }
}

/** Room status engine shared by the housekeeping board, Opera sync and tickets. */
final class Rooms {
    public static function setStatus(int $roomId, array $changes, string $source = 'staff'): array {
        $r = Db::one('SELECT * FROM rooms WHERE id = ?', [$roomId]);
        if (!$r) throw new HttpError(404, 'Room not found');
        $allowed = ['hk_status', 'fo_status', 'service_status', 'reservation_status', 'attendant_id', 'hk_section', 'dnd', 'make_up_room', 'turndown', 'rush', 'eco_skip', 'ooo_reason', 'ooo_until', 'guest_name', 'reservation_ref', 'arrival_date', 'departure_date', 'adults', 'children', 'vip_code', 'notes'];
        $upd = [];
        foreach ($allowed as $k) if (array_key_exists($k, $changes) && (string)$r[$k] !== (string)$changes[$k]) $upd[$k] = $changes[$k];
        if (isset($upd['hk_status']) && !isset(Registry::OPT['hk_status'][$upd['hk_status']])) throw new HttpError(422, 'Bad housekeeping status');
        if (isset($upd['service_status']) && !isset(Registry::OPT['service_status'][$upd['service_status']])) throw new HttpError(422, 'Bad service status');
        if (!$upd) return $r;
        $now = U::now();
        if (($upd['hk_status'] ?? null) === 'in_progress') $upd['hk_started_at'] = $now;
        if (($upd['hk_status'] ?? null) === 'clean') { $upd['last_cleaned_at'] = $now; $upd['make_up_room'] = 0; $upd['rush'] = 0; }
        if (($upd['hk_status'] ?? null) === 'inspected') { $upd['last_inspected_at'] = $now; $upd['inspected_by'] = Auth::$user['id'] ?? null; $upd['rush'] = 0; }
        if (($upd['service_status'] ?? null) === 'in_service') { $upd['ooo_reason'] = $upd['ooo_reason'] ?? null; $upd['ooo_until'] = null; }
        $upd['updated_at'] = $now;
        Db::update('rooms', $upd, $roomId);
        foreach (['hk_status', 'fo_status', 'service_status', 'reservation_status', 'attendant_id'] as $k) {
            if (array_key_exists($k, $upd)) Db::insert('room_status_log', ['room_id' => $roomId, 'field' => $k, 'old_value' => (string)$r[$k], 'new_value' => (string)$upd[$k], 'user_id' => Auth::$user['id'] ?? null, 'source' => $source, 'created_at' => $now]);
        }
        Audit::log('room.status', 'rooms', $roomId, Audit::diff($r, $upd) + ['source' => $source]);
        $nr = Db::one('SELECT * FROM rooms WHERE id = ?', [$roomId]);
        $hkDept = Db::val("SELECT id FROM departments WHERE code = 'HK'");
        $foDept = Db::val("SELECT id FROM departments WHERE code = 'FO'");
        if (($upd['hk_status'] ?? null) === 'inspected' && $nr['fo_status'] === 'vacant' && in_array($nr['reservation_status'], ['arrival'], true)) {
            Notify::generic('room.ready', $foDept ? (int)$foDept : null, "Room {$nr['number']} is ready", ($nr['guest_name'] ? "Arrival {$nr['guest_name']} can be checked in." : 'Inspected and ready.'), '#/hk', $nr['vip_code'] ? 'high' : 'normal');
        }
        if (($upd['service_status'] ?? null) === 'ooo') {
            Notify::generic('room.ooo', $foDept ? (int)$foDept : null, "Room {$nr['number']} out of order", (string)($nr['ooo_reason'] ?? ''), '#/hk', 'high');
        }
        if ($source !== 'opera') Integrations::pushRoomStatus($nr, $upd);
        return $nr;
    }
}
