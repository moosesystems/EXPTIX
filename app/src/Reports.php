<?php
declare(strict_types=1);

final class Reports {
    public static function dashboard(): array {
        [$scope, $p] = Tickets::scopeSql();
        $open = "t.status IN ('new','acknowledged','in_progress','on_hold')";
        $now = U::now();
        $dayStart = (new DateTime('today', new DateTimeZone(cfg('timezone'))))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $k = fn(string $w, array $extra = []) => (int)Db::val("SELECT COUNT(*) FROM tickets t WHERE $scope AND $w", [...$p, ...$extra]);
        $uid = (int)Auth::$user['id'];

        $rooms = Db::one("SELECT COUNT(*) AS total,
            SUM(CASE WHEN fo_status='occupied' THEN 1 ELSE 0 END) AS occupied,
            SUM(CASE WHEN hk_status='dirty' THEN 1 ELSE 0 END) AS dirty,
            SUM(CASE WHEN hk_status='in_progress' THEN 1 ELSE 0 END) AS cleaning,
            SUM(CASE WHEN hk_status='clean' THEN 1 ELSE 0 END) AS clean,
            SUM(CASE WHEN hk_status='inspected' THEN 1 ELSE 0 END) AS inspected,
            SUM(CASE WHEN service_status<>'in_service' THEN 1 ELSE 0 END) AS ooo,
            SUM(CASE WHEN reservation_status='arrival' THEN 1 ELSE 0 END) AS arrivals,
            SUM(CASE WHEN reservation_status='arrival' AND hk_status='inspected' THEN 1 ELSE 0 END) AS arrivals_ready,
            SUM(CASE WHEN reservation_status='due_out' THEN 1 ELSE 0 END) AS due_out,
            SUM(CASE WHEN vip_code IS NOT NULL AND vip_code <> '' AND fo_status='occupied' THEN 1 ELSE 0 END) AS vip_in_house
            FROM rooms");

        $byDept = Db::all("SELECT d.id, d.name, d.icon, d.colour,
            SUM(CASE WHEN $open THEN 1 ELSE 0 END) AS open,
            SUM(CASE WHEN $open AND t.sla_breached = 1 THEN 1 ELSE 0 END) AS breached,
            SUM(CASE WHEN $open AND t.priority IN ('urgent','critical') THEN 1 ELSE 0 END) AS urgent
            FROM departments d LEFT JOIN tickets t ON t.department_id = d.id AND $scope WHERE d.active = 1 GROUP BY d.id, d.name, d.icon, d.colour, d.sort ORDER BY d.sort, d.name", $p);

        $mins = Db::minutesBetween('t.created_at', 't.resolved_at');
        $resp = Db::minutesBetween('t.created_at', 't.first_response_at');
        $since30 = U::ts(time() - 30 * 86400);
        $perf = Db::one("SELECT COUNT(*) AS resolved, AVG($mins) AS avg_resolve, AVG(CASE WHEN t.first_response_at IS NOT NULL THEN $resp END) AS avg_response,
            SUM(CASE WHEN t.sla_breached = 0 THEN 1 ELSE 0 END) AS within_sla, AVG(t.satisfaction) AS csat
            FROM tickets t WHERE $scope AND t.resolved_at >= ?", [...$p, $since30]);

        return [
            'kpi' => [
                'open' => $k($open), 'unassigned' => $k("$open AND t.assigned_to IS NULL"), 'overdue' => $k("$open AND t.due_at < ?", [$now]),
                'breached' => $k("$open AND t.sla_breached = 1"), 'urgent' => $k("$open AND t.priority IN ('urgent','critical')"),
                'created_today' => $k('t.created_at >= ?', [$dayStart]), 'resolved_today' => $k('t.resolved_at >= ?', [$dayStart]),
                'guest_open' => $k("$open AND t.type IN ('guest_request','complaint')"), 'vip_open' => $k("$open AND t.vip = 1"),
                'mine' => (int)Db::val("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = ? AND $open", [$uid]),
            ],
            'rooms' => array_map('intval', $rooms ?? []),
            'by_department' => $byDept,
            'perf_30d' => ['resolved' => (int)$perf['resolved'], 'avg_resolve_min' => $perf['avg_resolve'] !== null ? round((float)$perf['avg_resolve']) : null,
                'avg_response_min' => $perf['avg_response'] !== null ? round((float)$perf['avg_response']) : null,
                'sla_pct' => $perf['resolved'] ? round(100 * $perf['within_sla'] / $perf['resolved']) : null, 'csat' => $perf['csat'] !== null ? round((float)$perf['csat'], 1) : null],
            'my_tickets' => Tickets::list(['assigned_to' => 'me', 'status' => 'open', 'sort' => 'priority', 'limit' => 8])['rows'],
            'hot' => Tickets::list(['status' => 'open', 'sort' => 'priority', 'limit' => 8, 'breached' => '1'])['rows'] ?: Tickets::list(['status' => 'open', 'sort' => 'priority', 'limit' => 8])['rows'],
            'announcements' => Db::all('SELECT a.*, u.name AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by WHERE (a.starts_at IS NULL OR a.starts_at <= ?) AND (a.ends_at IS NULL OR a.ends_at >= ?) AND (a.department_id IS NULL OR a.department_id IN (' . implode(',', Auth::departments() ?: [0]) . ')' . (Auth::seesAll() ? ' OR 1=1' : '') . ') ORDER BY a.pinned DESC, a.id DESC LIMIT 5', [$now, $now]),
            'logbook' => Db::all("SELECT l.*, u.name AS author, d.name AS department FROM logbook l LEFT JOIN users u ON u.id = l.author_id LEFT JOIN departments d ON d.id = l.department_id WHERE l.created_at >= ? AND l.importance <> 'info' ORDER BY l.id DESC LIMIT 6", [U::ts(time() - 36 * 3600)]),
            'events_today' => Db::all('SELECT e.*, l.name AS venue FROM events e LEFT JOIN locations l ON l.id = e.location_id WHERE e.start_at >= ? AND e.start_at < ? AND e.status <> ? ORDER BY e.start_at', [$dayStart, U::ts(strtotime($dayStart . ' UTC') + 2 * 86400), 'cancelled']),
            'food_safety' => array_values(array_filter(FoodSafety::dueChecks(), fn($x) => $x['overdue'] || !$x['last_in_range'])),
            'eighty_six' => Db::all('SELECT m.item, m.substitute, o.name AS outlet FROM menu_86 m LEFT JOIN outlets o ON o.id = m.outlet_id WHERE m.active = 1 ORDER BY m.id DESC LIMIT 10'),
        ];
    }

    public static function full(array $q): array {
        Auth::require(50);
        $days = max(1, min(365, (int)($q['days'] ?? 30)));
        $since = U::ts(time() - $days * 86400);
        $dept = !empty($q['department_id']) ? (int)$q['department_id'] : null;
        $dw = $dept ? ' AND t.department_id = ' . $dept : '';
        $mins = Db::minutesBetween('t.created_at', 't.resolved_at');
        $resp = Db::minutesBetween('t.created_at', 't.first_response_at');
        $day = Db::dateOf('t.created_at');
        $rday = Db::dateOf('t.resolved_at');
        return [
            'days' => $days,
            'volume' => Db::all("SELECT $day AS d, COUNT(*) AS n FROM tickets t WHERE t.created_at >= ? $dw GROUP BY $day ORDER BY d", [$since]),
            'resolved' => Db::all("SELECT $rday AS d, COUNT(*) AS n FROM tickets t WHERE t.resolved_at >= ? $dw GROUP BY $rday ORDER BY d", [$since]),
            'by_department' => Db::all("SELECT d.name, COUNT(t.id) AS created, SUM(CASE WHEN t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved, AVG($resp) AS avg_response, AVG($mins) AS avg_resolve,
                SUM(CASE WHEN t.sla_breached = 1 THEN 1 ELSE 0 END) AS breached, SUM(t.labour_minutes) AS labour, SUM(t.parts_cost) AS parts
                FROM tickets t JOIN departments d ON d.id = t.department_id WHERE t.created_at >= ? $dw GROUP BY d.id, d.name ORDER BY created DESC", [$since]),
            'by_category' => Db::all("SELECT c.name, d.name AS department, COUNT(*) AS n, AVG($mins) AS avg_resolve FROM tickets t JOIN categories c ON c.id = t.category_id LEFT JOIN departments d ON d.id = t.department_id WHERE t.created_at >= ? $dw GROUP BY c.id, c.name, d.name ORDER BY n DESC LIMIT 15", [$since]),
            'by_source' => Db::all("SELECT t.source, COUNT(*) AS n FROM tickets t WHERE t.created_at >= ? $dw GROUP BY t.source ORDER BY n DESC", [$since]),
            'by_priority' => Db::all("SELECT t.priority, COUNT(*) AS n, AVG($mins) AS avg_resolve, SUM(CASE WHEN t.sla_breached = 1 THEN 1 ELSE 0 END) AS breached FROM tickets t WHERE t.created_at >= ? $dw GROUP BY t.priority", [$since]),
            'repeat_rooms' => Db::all("SELECT r.number, COUNT(*) AS n, GROUP_CONCAT(DISTINCT c.name) AS issues FROM tickets t JOIN rooms r ON r.id = t.room_id LEFT JOIN categories c ON c.id = t.category_id WHERE t.created_at >= ? AND t.type IN ('work_order','complaint') $dw GROUP BY r.id, r.number HAVING COUNT(*) > 1 ORDER BY n DESC LIMIT 15", [$since]),
            'top_assets' => Db::all("SELECT a.tag, a.name, COUNT(*) AS n, SUM(t.labour_minutes) AS labour, SUM(t.parts_cost) AS parts FROM tickets t JOIN assets a ON a.id = t.asset_id WHERE t.created_at >= ? $dw GROUP BY a.id, a.tag, a.name ORDER BY n DESC LIMIT 10", [$since]),
            'staff' => Db::all("SELECT u.name, d.name AS department, COUNT(*) AS resolved, AVG($mins) AS avg_resolve, SUM(t.labour_minutes) AS labour FROM tickets t JOIN users u ON u.id = t.assigned_to LEFT JOIN departments d ON d.id = u.department_id WHERE t.resolved_at >= ? $dw GROUP BY u.id, u.name, d.name ORDER BY resolved DESC LIMIT 20", [$since]),
            'guest' => Db::one("SELECT COUNT(*) AS requests, SUM(CASE WHEN t.type='complaint' THEN 1 ELSE 0 END) AS complaints, AVG(t.satisfaction) AS csat, SUM(t.recovery_value) AS recovery, AVG($mins) AS avg_resolve FROM tickets t WHERE t.created_at >= ? AND t.type IN ('guest_request','complaint') $dw", [$since]),
            'housekeeping' => Db::all("SELECT u.name, COUNT(*) AS rooms FROM room_status_log l JOIN users u ON u.id = l.user_id WHERE l.field = 'hk_status' AND l.new_value = 'clean' AND l.created_at >= ? GROUP BY u.id, u.name ORDER BY rooms DESC", [$since]),
            'inspections' => Db::one('SELECT COUNT(*) AS runs, AVG(score) AS avg_score, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passed FROM checklist_runs WHERE created_at >= ?', [$since]),
            'haccp' => Db::one('SELECT COUNT(*) AS readings, SUM(CASE WHEN in_range = 0 THEN 1 ELSE 0 END) AS out_of_range FROM temp_logs WHERE created_at >= ?', [$since]),
            'pm' => Db::one("SELECT COUNT(*) AS pm_generated, SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN t.status IN ('resolved','closed') AND t.resolved_at <= t.due_at THEN 1 ELSE 0 END) AS on_time FROM tickets t WHERE t.type = 'pm' AND t.created_at >= ?", [$since]),
        ];
    }

    public static function csv(array $q): void {
        Auth::require(30);
        $rows = Tickets::list($q + ['limit' => 500])['rows'];
        Audit::log('report.export', 'tickets', null, ['rows' => count($rows), 'filters' => $q]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exptix-tickets-' . U::localDate() . '.csv"');
        $o = fopen('php://output', 'w');
        $depts = array_column(Db::all('SELECT id, name FROM departments'), 'name', 'id');
        fputcsv($o, ['Number', 'Title', 'Type', 'Department', 'Priority', 'Status', 'Source', 'Room', 'Guest', 'Assigned to', 'Created (UTC)', 'Due (UTC)', 'Resolved (UTC)', 'SLA breached']);
        foreach ($rows as $r) fputcsv($o, [$r['number'], $r['title'], $r['type'], $depts[$r['department_id']] ?? '', $r['priority'], $r['status'], $r['source'], $r['room_number'], $r['guest_name'], $r['assignee_name'], $r['created_at'], $r['due_at'], $r['resolved_at'], $r['sla_breached'] ? 'yes' : 'no']);
        fclose($o);
    }
}
