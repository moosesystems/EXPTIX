<?php
declare(strict_types=1);

final class Notify {
    /** Ticket events: default in-app routing plus configured rules plus webhooks. */
    public static function ticket(string $event, array $t, array $extra = []): void {
        $dept = $t['department_id'] ? (int)$t['department_id'] : null;
        $num = $t['number'] ?: ('#' . $t['id']);
        $room = $t['room_id'] ? Db::val('SELECT number FROM rooms WHERE id = ?', [$t['room_id']]) : null;
        $where = $room ? " · Room $room" : '';
        $title = match ($event) {
            'ticket.created' => "New: {$t['title']}",
            'ticket.assigned' => "Assigned to you: {$t['title']}",
            'ticket.status' => "{$num} is now " . (Registry::OPT['ticket_status'][$t['status']] ?? $t['status']),
            'ticket.resolved' => "Resolved: {$t['title']}",
            'ticket.comment' => "Comment on {$num}",
            'ticket.sla_warning' => "SLA at risk: {$t['title']}",
            'ticket.sla_breach' => "SLA missed: {$t['title']}",
            'ticket.vip' => "VIP request: {$t['title']}",
            default => $t['title'],
        };
        $body = "$num · " . (Registry::OPT['priority'][$t['priority']] ?? '') . $where . (isset($extra['comment']) ? "\n" . mb_substr($extra['comment'], 0, 300) : '');
        $link = '#/t/' . $t['id'];
        $level = in_array($event, ['ticket.sla_breach', 'ticket.vip'], true) || Tickets::prank($t['priority']) >= 4 ? 'alert' : 'info';
        $actorId = (int)(Auth::$user['id'] ?? 0);

        $recipients = [];
        switch ($event) {
            case 'ticket.created':
                if ($t['assigned_to']) break; // assignment notice covers it
                $recipients = self::deptMembers($dept, Tickets::prank($t['priority']) >= 3 ? 0 : 50);
                break;
            case 'ticket.assigned': if ($t['assigned_to']) $recipients = [(int)$t['assigned_to']]; break;
            case 'ticket.status': case 'ticket.resolved': case 'ticket.comment':
                $recipients = array_filter([(int)$t['created_by'], (int)$t['assigned_to']]); break;
            case 'ticket.sla_warning': $recipients = array_filter([(int)$t['assigned_to']]) ?: self::deptMembers($dept, 50); break;
            case 'ticket.sla_breach': case 'ticket.vip': $recipients = array_merge(self::deptMembers($dept, 50), array_filter([(int)$t['assigned_to']])); break;
        }
        $recipients = array_values(array_unique(array_diff($recipients, [$actorId, 0])));
        foreach ($recipients as $uid) self::inapp($uid, $title, $body, $link, $level, (int)$t['id']);

        self::applyRules($event, $dept, $t['priority'], $title, $body, $link, (int)$t['id'], $t);
        self::webhooks($event, ['ticket' => self::ticketPayload($t)] + $extra);
    }

    public static function generic(string $event, ?int $dept, string $title, string $body, string $link, string $priority = 'normal', array $payload = []): void {
        foreach (self::deptMembers($dept, 50) as $uid) self::inapp($uid, $title, $body, $link, Tickets::prank($priority) >= 3 ? 'alert' : 'info');
        self::applyRules($event, $dept, $priority, $title, $body, $link, null, null);
        self::webhooks($event, ['title' => $title, 'body' => $body, 'department_id' => $dept] + $payload);
    }

    public static function escalate(array $t, string $role, ?int $userId): void {
        $ids = $userId ? [$userId] : array_map('intval', array_column(Db::all('SELECT id FROM users WHERE active = 1 AND role = ?', [$role]), 'id'));
        foreach ($ids as $uid) self::inapp($uid, "Escalation: {$t['title']}", ($t['number'] ?? '') . ' has been open past its SLA.', '#/t/' . $t['id'], 'alert', (int)$t['id']);
        self::applyRules('ticket.sla_breach', $t['department_id'] ? (int)$t['department_id'] : null, 'critical', "Escalation: {$t['title']}", ($t['number'] ?? '') . ' is past SLA and escalated.', '#/t/' . $t['id'], (int)$t['id'], $t);
    }

    public static function deptMembers(?int $dept, int $minRank = 0): array {
        if (!$dept) return array_map('intval', array_column(Db::all("SELECT id FROM users WHERE active = 1 AND role IN ('gm','manager') "), 'id'));
        $out = [];
        foreach (Db::all('SELECT id, role, department_id, extra_departments, on_shift FROM users WHERE active = 1') as $u) {
            if (!in_array($dept, Auth::departments($u), true)) continue;
            $rank = Auth::ROLES[$u['role']]['rank'] ?? 0;
            if ($rank < $minRank) continue;
            if ($minRank === 0 && $rank < 50 && !$u['on_shift']) continue; // line staff only when on shift
            $out[] = (int)$u['id'];
        }
        return $out;
    }

    public static function inapp(int $userId, string $title, string $body, string $link, string $level = 'info', ?int $ticketId = null): void {
        Db::insert('notifications', ['user_id' => $userId, 'ticket_id' => $ticketId, 'title' => mb_substr($title, 0, 255), 'body' => $body, 'link' => $link, 'level' => $level, 'created_at' => U::now()]);
        $u = Db::one('SELECT notify_push, notify_email, email, notify_sms, phone FROM users WHERE id = ?', [$userId]);
        if ($u && $u['notify_push'] && Db::val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$userId])) {
            self::queue('push', (string)$userId, $title, $body, ['link' => $link]);
        }
        if ($u && $level === 'alert' && $u['notify_sms'] && $u['phone']) self::queue('sms', $u['phone'], $title, $title . ' ' . cfg('base_url') . '/' . $link);
    }

    private static function applyRules(string $event, ?int $dept, ?string $priority, string $title, string $body, string $link, ?int $ticketId, ?array $t): void {
        foreach (Db::all('SELECT * FROM notification_rules WHERE active = 1 AND event = ?', [$event]) as $r) {
            if ($r['department_id'] && (int)$r['department_id'] !== $dept) continue;
            if ($r['min_priority'] && Tickets::prank($priority) < Tickets::prank($r['min_priority'])) continue;
            $users = match ($r['target_type']) {
                'department' => self::deptMembers($r['target'] ? (int)$r['target'] : $dept, 0),
                'department_leads' => self::deptMembers($r['target'] ? (int)$r['target'] : $dept, 50),
                'role' => array_map('intval', array_column(Db::all('SELECT id FROM users WHERE active = 1 AND role = ?', [$r['target']]), 'id')),
                'user' => [(int)$r['target']],
                'assignee' => $t && $t['assigned_to'] ? [(int)$t['assigned_to']] : [],
                default => [],
            };
            $url = cfg('base_url') . '/' . $link;
            switch ($r['channel']) {
                case 'inapp': foreach ($users as $u) self::inapp($u, $title, $body, $link, 'info', $ticketId); break;
                case 'push': foreach ($users as $u) self::queue('push', (string)$u, $title, $body, ['link' => $link]); break;
                case 'email':
                    $to = $r['target_type'] === 'address' ? [$r['target']] : array_filter(array_column(Db::all('SELECT email FROM users WHERE notify_email = 1 AND id IN (' . (implode(',', $users ?: [0])) . ')'), 'email'));
                    foreach ($to as $e) self::queue('email', $e, $title, "$body\n\nOpen: $url", ['link' => $url]);
                    break;
                case 'sms':
                    $to = $r['target_type'] === 'address' ? [$r['target']] : array_filter(array_column(Db::all('SELECT phone FROM users WHERE notify_sms = 1 AND id IN (' . (implode(',', $users ?: [0])) . ')'), 'phone'));
                    foreach ($to as $ph) self::queue('sms', $ph, $title, mb_substr("$title. $url", 0, 300));
                    break;
                case 'teams': case 'slack': case 'webhook':
                    if ($r['target']) self::queue($r['channel'], $r['target'], $title, $body, ['link' => $url, 'event' => $event, 'priority' => $priority]);
                    break;
            }
        }
    }

    public static function webhooks(string $event, array $data): void {
        foreach (Db::all('SELECT * FROM webhooks WHERE active = 1') as $w) {
            $events = array_map('trim', explode(',', (string)$w['events']));
            if (!in_array('*', $events, true) && !in_array($event, $events, true)) continue;
            self::queue('webhook_out', (string)$w['id'], $event, '', ['event' => $event, 'occurred_at' => gmdate('c'), 'property' => cfg('property_code'), 'data' => $data]);
        }
    }

    public static function ticketPayload(array $t): array {
        return ['id' => (int)$t['id'], 'number' => $t['number'], 'title' => $t['title'], 'type' => $t['type'], 'status' => $t['status'], 'priority' => $t['priority'],
            'department' => $t['department_id'] ? Db::val('SELECT code FROM departments WHERE id = ?', [$t['department_id']]) : null,
            'room' => $t['room_id'] ? Db::val('SELECT number FROM rooms WHERE id = ?', [$t['room_id']]) : null, 'guest_name' => $t['guest_name'],
            'assigned_to' => $t['assigned_to'] ? Db::val('SELECT name FROM users WHERE id = ?', [$t['assigned_to']]) : null,
            'due_at' => $t['due_at'], 'created_at' => $t['created_at'], 'source' => $t['source'], 'external_ref' => $t['external_ref'], 'url' => cfg('base_url') . '/#/t/' . $t['id']];
    }

    public static function queue(string $channel, string $target, string $subject, string $body, array $payload = []): void {
        Db::insert('notification_queue', ['channel' => $channel, 'target' => mb_substr($target, 0, 500), 'subject' => mb_substr($subject, 0, 255), 'body' => $body, 'payload' => json_encode($payload), 'status' => 'pending', 'created_at' => U::now()]);
    }

    /** Deliver queued messages. Runs from cron and opportunistically after requests. */
    public static function dispatch(int $max = 40): array {
        $sent = 0; $failed = 0;
        foreach (Db::all("SELECT * FROM notification_queue WHERE status IN ('pending','retry') AND attempts < 5 ORDER BY id LIMIT $max") as $m) {
            $p = json_decode((string)$m['payload'], true) ?: [];
            try {
                $ok = match ($m['channel']) {
                    'email' => self::sendEmail($m['target'], $m['subject'], $m['body']),
                    'sms' => self::sendSms($m['target'], $m['body']),
                    'teams' => self::sendTeams($m['target'], $m['subject'], $m['body'], $p['link'] ?? ''),
                    'slack' => self::post($m['target'], ['text' => "*{$m['subject']}*\n{$m['body']}\n<" . ($p['link'] ?? '') . '|Open in ExpTix>']),
                    'webhook' => self::post($m['target'], ['event' => $p['event'] ?? null, 'title' => $m['subject'], 'body' => $m['body'], 'link' => $p['link'] ?? null]),
                    'webhook_out' => self::sendWebhook((int)$m['target'], $p),
                    'push' => Push::sendToUser((int)$m['target']),
                    default => throw new RuntimeException('Unknown channel'),
                };
                if ($ok === 'skipped') { Db::update('notification_queue', ['status' => 'skipped', 'attempts' => $m['attempts'] + 1, 'sent_at' => U::now()], (int)$m['id']); continue; }
                Db::update('notification_queue', ['status' => 'sent', 'attempts' => $m['attempts'] + 1, 'sent_at' => U::now()], (int)$m['id']);
                $sent++;
            } catch (Throwable $e) {
                Db::update('notification_queue', ['status' => $m['attempts'] + 1 >= 5 ? 'failed' : 'retry', 'attempts' => $m['attempts'] + 1, 'last_error' => mb_substr($e->getMessage(), 0, 1000)], (int)$m['id']);
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    private static function sendEmail(string $to, string $subject, string $body) {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bad address');
        if (cfg('demo_mode') && !Integrations::enabled('email_out')) return 'skipped'; // demo: no real mail unless switched on
        $from = cfg('mail.from'); $name = cfg('mail.from_name');
        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#2A2F36"><p style="font-size:16px;font-weight:bold;color:#0F1E24">' . htmlspecialchars($subject) . '</p><p>' . nl2br(htmlspecialchars($body)) . '</p><p style="color:#7FA4A8;font-size:12px">' . htmlspecialchars(cfg('app_name') . ' · ' . cfg('property_name')) . '</p></div>';
        $headers = "From: $name <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nX-Mailer: ExpTix";
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, "-f$from")) throw new RuntimeException('mail() failed');
        return true;
    }

    private static function sendSms(string $to, string $body) {
        $c = Integrations::config('twilio');
        if (!$c || !Integrations::enabled('twilio')) return 'skipped';
        $r = U::http('POST', "https://api.twilio.com/2010-04-01/Accounts/{$c['account_sid']}/Messages.json",
            ['Authorization' => 'Basic ' . base64_encode("{$c['account_sid']}:{$c['auth_token']}"), 'Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query(['To' => $to, 'From' => $c['from_number'], 'Body' => mb_substr($body, 0, 320)]));
        if ($r['status'] >= 300) throw new RuntimeException("Twilio HTTP {$r['status']}");
        return true;
    }

    private static function sendTeams(string $url, string $title, string $body, string $link) {
        // Adaptive Card for Teams Workflows (Power Automate) webhooks
        $card = ['type' => 'message', 'attachments' => [['contentType' => 'application/vnd.microsoft.card.adaptive', 'content' => [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json', 'type' => 'AdaptiveCard', 'version' => '1.4',
            'body' => [['type' => 'TextBlock', 'text' => $title, 'weight' => 'Bolder', 'size' => 'Medium', 'wrap' => true], ['type' => 'TextBlock', 'text' => $body, 'wrap' => true]],
            'actions' => $link ? [['type' => 'Action.OpenUrl', 'title' => 'Open in ExpTix', 'url' => $link]] : [],
        ]]]];
        return self::post($url, $card);
    }

    private static function post(string $url, array $json) {
        if (!preg_match('#^https://#', $url)) throw new RuntimeException('Only https targets are allowed');
        $r = U::http('POST', $url, ['Content-Type' => 'application/json'], json_encode($json), 10);
        if ($r['status'] < 200 || $r['status'] >= 300) throw new RuntimeException("HTTP {$r['status']} {$r['error']}");
        return true;
    }

    private static function sendWebhook(int $id, array $payload) {
        $w = Db::one('SELECT * FROM webhooks WHERE id = ?', [$id]);
        if (!$w || !$w['active']) return 'skipped';
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $ts = (string)time();
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'ExpTix-Webhook/1', 'X-ExpTix-Event' => $payload['event'] ?? '', 'X-ExpTix-Timestamp' => $ts];
        if ($w['secret']) $headers['X-ExpTix-Signature'] = 'sha256=' . hash_hmac('sha256', "$ts.$body", $w['secret']);
        $r = U::http('POST', $w['url'], $headers, $body, 10);
        Db::update('webhooks', ['last_status' => $r['status'], 'last_at' => U::now()], $id);
        if ($r['status'] < 200 || $r['status'] >= 300) throw new RuntimeException("HTTP {$r['status']}");
        return true;
    }
}
