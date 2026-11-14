<?php
declare(strict_types=1);

/** Background jobs. Runs every minute from cron, with a request-driven fallback. */
final class Scheduler {
    public static function setting(string $k): ?string { return Db::val('SELECT value FROM settings WHERE `key` = ?', [$k]); }
    public static function setSetting(string $k, string $v): void {
        if (Db::val('SELECT COUNT(*) FROM settings WHERE `key` = ?', [$k])) Db::q('UPDATE settings SET value = ? WHERE `key` = ?', [$v, $k]);
        else Db::insert('settings', ['key' => $k, 'value' => $v]);
    }

    /** Opportunistic run from web requests if cron has not run in the last 2 minutes. */
    public static function maybeRun(): void {
        $last = (int)(self::setting('scheduler_last') ?? 0);
        if (time() - $last < 120) return;
        self::setSetting('scheduler_last', (string)time());
        try { self::run(); } catch (Throwable $e) { U::log('scheduler', $e->getMessage()); }
    }

    public static function run(): array {
        $out = [];
        self::setSetting('scheduler_last', (string)time());
        $every = function (string $job, int $minutes, callable $fn) use (&$out) {
            $k = "job_$job";
            if (time() - (int)(self::setting($k) ?? 0) < $minutes * 60 - 5) return;
            self::setSetting($k, (string)time());
            try { $out[$job] = $fn(); } catch (Throwable $e) { $out[$job] = 'error: ' . $e->getMessage(); U::log('scheduler', "$job: " . $e->getMessage()); }
        };
        $every('sla', 1, fn() => Tickets::slaSweep());
        $every('notify', 1, fn() => Notify::dispatch());
        $every('pm', 30, fn() => self::generatePm());
        $c = Integrations::config('opera');
        $every('opera', max(2, (int)($c['sync_minutes'] ?? 10)), fn() => Integrations::operaSync());
        $every('email_in', 2, fn() => Integrations::pollEmail());
        $every('hygiene', 60, fn() => self::hygiene());
        $every('daily', 60, fn() => self::daily());
        return $out;
    }

    public static function nextDate(string $from, string $freq, int $days): string {
        $d = new DateTime($from);
        $mod = match ($freq) { 'daily' => '+1 day', 'weekly' => '+1 week', 'biweekly' => '+2 weeks', 'monthly' => '+1 month', 'quarterly' => '+3 months', 'semiannual' => '+6 months', 'annual' => '+1 year', default => '+' . max(1, $days) . ' days' };
        $d->modify($mod);
        return $d->format('Y-m-d');
    }

    public static function generatePm(): array {
        $today = U::localDate(); $made = 0;
        foreach (Db::all('SELECT * FROM pm_schedules WHERE active = 1 AND next_due IS NOT NULL') as $s) {
            $trigger = (new DateTime($s['next_due']))->modify('-' . (int)$s['lead_days'] . ' days')->format('Y-m-d');
            if ($trigger > $today) continue;
            $open = Db::val("SELECT COUNT(*) FROM tickets WHERE pm_schedule_id = ? AND status IN ('new','acknowledged','in_progress','on_hold')", [$s['id']]);
            if ($open) continue;
            $tpl = $s['checklist_template_id'] ? Db::val('SELECT items FROM checklist_templates WHERE id = ?', [$s['checklist_template_id']]) : null;
            $asset = $s['asset_id'] ? Db::one('SELECT * FROM assets WHERE id = ?', [$s['asset_id']]) : null;
            $t = Tickets::create([
                'title' => 'PM: ' . $s['name'], 'description' => trim(($s['instructions'] ?? '') . ($s['compliance'] ? "\n\nCompliance: {$s['compliance']}" : '') . "\n\nDue {$s['next_due']}. Estimated {$s['estimated_minutes']} min."),
                'department_id' => $s['department_id'], 'category_id' => $s['category_id'], 'priority' => $s['priority'], 'asset_id' => $s['asset_id'], 'location_id' => $s['location_id'] ?: ($asset['location_id'] ?? null),
                'assigned_to' => $s['assigned_to'], 'pm_schedule_id' => $s['id'], 'type' => 'pm', 'due_at' => $s['next_due'] . ' 17:00:00',
                'checklist' => $tpl ? array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $tpl)), fn($l) => $l !== '' && !str_starts_with($l, '##'))) : null,
            ], 'pm', null);
            Db::update('pm_schedules', ['next_due' => self::nextDate($s['next_due'], $s['frequency'], (int)$s['interval_days']), 'last_generated_at' => U::now()], (int)$s['id']);
            Notify::generic('pm.due', $s['department_id'] ? (int)$s['department_id'] : null, "PM due: {$s['name']}", $t['number'], '#/t/' . $t['id'], $s['priority']);
            $made++;
        }
        return ['generated' => $made];
    }

    private static function hygiene(): array {
        $n = [];
        // Auto-close resolved tickets after 72 hours
        $n['auto_closed'] = Db::q("UPDATE tickets SET status = 'closed', closed_at = ?, updated_at = ? WHERE status = 'resolved' AND resolved_at < ?", [U::now(), U::now(), U::ts(time() - 72 * 3600)])->rowCount();
        // Out-of-order rooms whose end date has passed come back as dirty
        foreach (Db::all("SELECT id FROM rooms WHERE service_status IN ('ooo','oos') AND ooo_until IS NOT NULL AND ooo_until < ?", [U::localDate()]) as $r) Rooms::setStatus((int)$r['id'], ['service_status' => 'in_service', 'hk_status' => 'dirty'], 'system');
        // Overdue keys
        foreach (Db::all('SELECT * FROM key_log WHERE returned_at IS NULL AND due_back_at IS NOT NULL AND due_back_at < ?', [U::now()]) as $k) {
            $sec = Db::val("SELECT id FROM departments WHERE code = 'SEC'");
            $seen = Db::val("SELECT COUNT(*) FROM notifications WHERE link = ? AND created_at > ?", ['#/e/key_log/' . $k['id'], U::ts(time() - 6 * 3600)]);
            if (!$seen) Notify::generic('incident.created', $sec ? (int)$sec : null, "Key overdue: {$k['key_name']}", "Issued to {$k['issued_to']}", '#/e/key_log/' . $k['id'], 'high');
        }
        // Temperature checks overdue (manual points only)
        $kit = Db::val("SELECT id FROM departments WHERE code = 'KIT'");
        foreach (FoodSafety::dueChecks() as $p) {
            if (!$p['overdue'] || $p['sensor_id']) continue;
            $seen = Db::val('SELECT COUNT(*) FROM notifications WHERE link = ? AND created_at > ?', ['#/food', U::ts(time() - (int)$p['check_every_hours'] * 3600)]);
            if (!$seen) Notify::generic('temp.out_of_range', $kit ? (int)$kit : null, "Temperature check due: {$p['name']}", 'Log a reading to stay HACCP compliant.', '#/food', 'normal');
        }
        // 86 items past their end time
        Db::q('UPDATE menu_86 SET active = 0 WHERE active = 1 AND until IS NOT NULL AND until < ?', [U::now()]);
        Db::q('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?', [U::ts(time() - 30 * 86400)]);
        Db::q("DELETE FROM notification_queue WHERE status IN ('sent','skipped') AND created_at < ?", [U::ts(time() - 14 * 86400)]);
        return $n;
    }

    /** Once per property-local day, after 03:00: night-audit room roll and compliance reminders. */
    private static function daily(): array {
        $local = new DateTime('now', new DateTimeZone(cfg('timezone')));
        if ((int)$local->format('G') < 3) return ['skipped' => 'before 03:00'];
        $today = $local->format('Y-m-d');
        if (self::setting('daily_done') === $today) return ['skipped' => 'done'];
        self::setSetting('daily_done', $today);
        $out = [];
        // Night audit: occupied rooms need service; departures today are due out.
        foreach (Db::all("SELECT * FROM rooms WHERE fo_status = 'occupied'") as $r) {
            $chg = ['hk_status' => $r['eco_skip'] ? $r['hk_status'] : 'dirty', 'reservation_status' => $r['departure_date'] && $r['departure_date'] <= $today ? 'due_out' : 'stayover', 'dnd' => 0, 'make_up_room' => 0, 'eco_skip' => 0];
            Rooms::setStatus((int)$r['id'], $chg, 'system');
        }
        Db::q("UPDATE rooms SET reservation_status = 'none', guest_name = NULL, reservation_ref = NULL, vip_code = NULL, adults = 0, children = 0 WHERE fo_status = 'vacant' AND reservation_status IN ('departed')");
        if (cfg('demo_mode')) $out['demo'] = Seed::dailyArrivals();
        // Vendor compliance documents expiring within 30 days
        $soon = (new DateTime($today))->modify('+30 days')->format('Y-m-d');
        foreach (Db::all('SELECT * FROM vendors WHERE active = 1 AND ((insurance_expiry IS NOT NULL AND insurance_expiry <= ?) OR (workers_comp_expiry IS NOT NULL AND workers_comp_expiry <= ?))', [$soon, $soon]) as $v) {
            $eng = Db::val("SELECT id FROM departments WHERE code = 'ENG'");
            Notify::generic('stock.low', $eng ? (int)$eng : null, "Vendor paperwork expiring: {$v['name']}", "Insurance {$v['insurance_expiry']}, workers' compensation {$v['workers_comp_expiry']}", '#/e/vendors/' . $v['id'], 'normal');
        }
        // Lost and found past hold date
        $lf = (int)Db::val("SELECT COUNT(*) FROM lost_found WHERE status IN ('logged','held') AND dispose_after IS NOT NULL AND dispose_after < ?", [$today]);
        if ($lf) { $hk = Db::val("SELECT id FROM departments WHERE code = 'HK'"); Notify::generic('lost_found.created', $hk ? (int)$hk : null, "$lf lost and found item(s) past hold date", 'Review for donation or disposal.', '#/e/lost_found', 'low'); }
        // Audit retention (AU-11)
        $out['audit_purged'] = Db::q('DELETE FROM audit_log WHERE at < ?', [U::ts(time() - cfg('security.audit_retention_days') * 86400)])->rowCount();
        return $out;
    }
}
