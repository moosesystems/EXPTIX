<?php
declare(strict_types=1);

/**
 * Portable schema. Type tokens: pk, fk, int, dec, bool, str:N, text, dt, date, json.
 * A default is appended with "|value". "_idx" lists indexed columns; "_uniq" unique columns.
 * migrate() creates missing tables and adds missing columns, so it is safe to rerun.
 */
final class Schema {
    public static function tables(): array {
        $ts = ['created_at' => 'dt', 'updated_at' => 'dt'];
        return [
            'settings' => ['id' => 'pk', 'key' => 'str:100', 'value' => 'text', '_uniq' => ['key']],
            'rate_limits' => ['id' => 'pk', 'key' => 'str:190', 'window_start' => 'int', 'hits' => 'int|0', '_idx' => ['key']],

            'departments' => ['id' => 'pk', 'code' => 'str:20', 'name' => 'str:100', 'colour' => 'str:20', 'icon' => 'str:16', 'email' => 'str:190', 'phone_ext' => 'str:20', 'manager_id' => 'fk', 'description' => 'text', 'sort' => 'int|0', 'active' => 'bool|1'] + $ts,

            'users' => ['id' => 'pk', 'username' => 'str:60', 'email' => 'str:190', 'name' => 'str:120', 'phone' => 'str:40', 'title' => 'str:100',
                'password_hash' => 'str:255', 'role' => 'str:20|staff', 'department_id' => 'fk', 'extra_departments' => 'json', 'active' => 'bool|1',
                'mfa_secret' => 'str:64', 'mfa_enabled' => 'bool|0', 'failed_logins' => 'int|0', 'locked_until' => 'dt', 'last_login_at' => 'dt', 'last_login_ip' => 'str:64',
                'password_changed_at' => 'dt', 'must_change_password' => 'bool|0', 'notify_email' => 'bool|1', 'notify_sms' => 'bool|0', 'notify_push' => 'bool|1',
                'language' => 'str:8|en', 'on_shift' => 'bool|0', 'hk_credit_capacity' => 'int|16'] + $ts + ['_uniq' => ['username'], '_idx' => ['department_id']],

            'api_tokens' => ['id' => 'pk', 'name' => 'str:120', 'user_id' => 'fk', 'token_hash' => 'str:64', 'prefix' => 'str:16', 'scopes' => 'str:255', 'last_used_at' => 'dt', 'last_used_ip' => 'str:64', 'expires_at' => 'dt', 'revoked' => 'bool|0', 'created_by' => 'fk', 'created_at' => 'dt', '_idx' => ['token_hash']],
            'push_subscriptions' => ['id' => 'pk', 'user_id' => 'fk', 'endpoint' => 'text', 'p256dh' => 'str:255', 'auth' => 'str:255', 'user_agent' => 'str:255', 'created_at' => 'dt', '_idx' => ['user_id']],

            'locations' => ['id' => 'pk', 'code' => 'str:30', 'name' => 'str:120', 'type' => 'str:30|area', 'parent_id' => 'fk', 'floor' => 'str:10', 'department_id' => 'fk', 'qr_token' => 'str:40', 'notes' => 'text', 'active' => 'bool|1'] + $ts,
            'room_types' => ['id' => 'pk', 'code' => 'str:20', 'name' => 'str:100', 'hk_credits' => 'dec|1', 'clean_minutes' => 'int|30', 'beds' => 'str:60', 'max_occupancy' => 'int|2', 'description' => 'text'] + $ts,
            'rooms' => ['id' => 'pk', 'number' => 'str:10', 'floor' => 'str:10', 'room_type_id' => 'fk', 'location_id' => 'fk',
                'fo_status' => 'str:20|vacant', 'hk_status' => 'str:20|clean', 'service_status' => 'str:20|in_service', 'reservation_status' => 'str:20|none',
                'guest_name' => 'str:120', 'reservation_ref' => 'str:40', 'arrival_date' => 'date', 'departure_date' => 'date', 'adults' => 'int|0', 'children' => 'int|0',
                'vip_code' => 'str:20', 'dnd' => 'bool|0', 'make_up_room' => 'bool|0', 'turndown' => 'bool|0', 'rush' => 'bool|0', 'eco_skip' => 'bool|0', 'attendant_id' => 'fk', 'hk_section' => 'str:20',
                'hk_started_at' => 'dt', 'last_cleaned_at' => 'dt', 'last_inspected_at' => 'dt', 'inspected_by' => 'fk', 'ooo_reason' => 'str:255', 'ooo_until' => 'date',
                'opera_room_id' => 'str:40', 'qr_token' => 'str:40', 'features' => 'str:255', 'notes' => 'text'] + $ts + ['_uniq' => ['number'], '_idx' => ['floor', 'hk_status']],
            'room_status_log' => ['id' => 'pk', 'room_id' => 'fk', 'field' => 'str:30', 'old_value' => 'str:60', 'new_value' => 'str:60', 'user_id' => 'fk', 'source' => 'str:20', 'created_at' => 'dt', '_idx' => ['room_id']],
            'staff_shifts' => ['id' => 'pk', 'user_id' => 'fk', 'department_id' => 'fk', 'shift_date' => 'date', 'shift' => 'str:20|day', 'section' => 'str:40', 'start_time' => 'str:5', 'end_time' => 'str:5', 'notes' => 'str:255'] + $ts,

            'sla_policies' => ['id' => 'pk', 'name' => 'str:100', 'priority' => 'str:20', 'response_minutes' => 'int|30', 'resolve_minutes' => 'int|240', 'escalate_after_minutes' => 'int|0', 'escalate_role' => 'str:20|manager', 'escalate_user_id' => 'fk', 'active' => 'bool|1'] + $ts,
            'categories' => ['id' => 'pk', 'department_id' => 'fk', 'name' => 'str:120', 'type' => 'str:30|work_order', 'default_priority' => 'str:20|normal', 'sla_policy_id' => 'fk', 'checklist_template_id' => 'fk', 'keywords' => 'str:255', 'guest_visible' => 'bool|0', 'guest_label' => 'str:120', 'icon' => 'str:16', 'sort' => 'int|0', 'active' => 'bool|1'] + $ts,

            'tickets' => ['id' => 'pk', 'number' => 'str:20', 'type' => 'str:30|work_order', 'department_id' => 'fk', 'category_id' => 'fk', 'title' => 'str:255', 'description' => 'text',
                'priority' => 'str:20|normal', 'status' => 'str:20|new', 'source' => 'str:30|staff', 'location_id' => 'fk', 'room_id' => 'fk', 'asset_id' => 'fk', 'event_id' => 'fk',
                'guest_name' => 'str:120', 'reservation_ref' => 'str:40', 'guest_contact' => 'str:190', 'vip' => 'bool|0', 'guest_token' => 'str:40', 'requester_name' => 'str:120',
                'assigned_to' => 'fk', 'created_by' => 'fk', 'response_due_at' => 'dt', 'due_at' => 'dt', 'first_response_at' => 'dt', 'started_at' => 'dt', 'resolved_at' => 'dt', 'closed_at' => 'dt',
                'scheduled_for' => 'dt', 'sla_breached' => 'bool|0', 'escalation_level' => 'int|0', 'labour_minutes' => 'int|0', 'parts_cost' => 'dec|0',
                'resolution' => 'text', 'recovery_action' => 'str:255', 'recovery_value' => 'dec|0', 'satisfaction' => 'int', 'satisfaction_comment' => 'text',
                'parent_id' => 'fk', 'pm_schedule_id' => 'fk', 'external_ref' => 'str:120', 'external_system' => 'str:30', 'tags' => 'str:255', 'repeat_count' => 'int|0'] + $ts
                + ['_idx' => ['status', 'department_id', 'assigned_to', 'room_id', 'created_at', 'guest_token']],
            'ticket_events' => ['id' => 'pk', 'ticket_id' => 'fk', 'user_id' => 'fk', 'kind' => 'str:20|comment', 'body' => 'text', 'data' => 'json', 'visibility' => 'str:10|internal', 'created_at' => 'dt', '_idx' => ['ticket_id']],
            'ticket_checklist' => ['id' => 'pk', 'ticket_id' => 'fk', 'item' => 'str:255', 'done' => 'bool|0', 'done_by' => 'fk', 'done_at' => 'dt', 'sort' => 'int|0', '_idx' => ['ticket_id']],
            'ticket_time' => ['id' => 'pk', 'ticket_id' => 'fk', 'user_id' => 'fk', 'minutes' => 'int|0', 'note' => 'str:255', 'created_at' => 'dt', '_idx' => ['ticket_id']],
            'ticket_parts' => ['id' => 'pk', 'ticket_id' => 'fk', 'part_id' => 'fk', 'qty' => 'dec|1', 'unit_cost' => 'dec|0', 'user_id' => 'fk', 'created_at' => 'dt', '_idx' => ['ticket_id']],
            'attachments' => ['id' => 'pk', 'entity' => 'str:40', 'entity_id' => 'int', 'filename' => 'str:255', 'stored_name' => 'str:80', 'mime' => 'str:100', 'size' => 'int|0', 'sha256' => 'str:64', 'uploaded_by' => 'fk', 'created_at' => 'dt', '_idx' => ['entity', 'entity_id']],

            'assets' => ['id' => 'pk', 'tag' => 'str:40', 'name' => 'str:150', 'category' => 'str:60', 'location_id' => 'fk', 'room_id' => 'fk', 'department_id' => 'fk', 'manufacturer' => 'str:100', 'model' => 'str:100', 'serial' => 'str:100',
                'install_date' => 'date', 'warranty_until' => 'date', 'vendor_id' => 'fk', 'status' => 'str:20|operational', 'criticality' => 'str:20|medium', 'meter_reading' => 'dec', 'meter_unit' => 'str:20', 'replacement_cost' => 'dec', 'expected_life_years' => 'int', 'qr_token' => 'str:40', 'notes' => 'text'] + $ts,
            'pm_schedules' => ['id' => 'pk', 'name' => 'str:150', 'asset_id' => 'fk', 'location_id' => 'fk', 'department_id' => 'fk', 'category_id' => 'fk', 'frequency' => 'str:20|monthly', 'interval_days' => 'int|30', 'next_due' => 'date', 'lead_days' => 'int|3',
                'checklist_template_id' => 'fk', 'assigned_to' => 'fk', 'priority' => 'str:20|normal', 'estimated_minutes' => 'int|60', 'instructions' => 'text', 'compliance' => 'str:100', 'last_generated_at' => 'dt', 'active' => 'bool|1'] + $ts,
            'checklist_templates' => ['id' => 'pk', 'name' => 'str:150', 'department_id' => 'fk', 'kind' => 'str:30|inspection', 'items' => 'text', 'pass_mark' => 'int|80', 'create_tickets_on_fail' => 'bool|1', 'active' => 'bool|1'] + $ts,
            'checklist_runs' => ['id' => 'pk', 'template_id' => 'fk', 'department_id' => 'fk', 'location_id' => 'fk', 'room_id' => 'fk', 'performed_by' => 'fk', 'score' => 'int', 'passed' => 'bool|0', 'results' => 'json', 'notes' => 'text', 'tickets_created' => 'int|0', 'created_at' => 'dt'],
            'parts' => ['id' => 'pk', 'sku' => 'str:60', 'name' => 'str:150', 'category' => 'str:60', 'department_id' => 'fk', 'storage_location' => 'str:100', 'unit' => 'str:20|each', 'qty_on_hand' => 'dec|0', 'par_level' => 'dec|0', 'reorder_point' => 'dec|0', 'reorder_qty' => 'dec|0', 'unit_cost' => 'dec|0', 'vendor_id' => 'fk', 'active' => 'bool|1'] + $ts,
            'stock_movements' => ['id' => 'pk', 'part_id' => 'fk', 'qty' => 'dec', 'reason' => 'str:40', 'ticket_id' => 'fk', 'user_id' => 'fk', 'note' => 'str:255', 'created_at' => 'dt', '_idx' => ['part_id']],
            'vendors' => ['id' => 'pk', 'name' => 'str:150', 'trade' => 'str:100', 'contact_name' => 'str:120', 'phone' => 'str:40', 'email' => 'str:190', 'emergency_phone' => 'str:40', 'contract_ref' => 'str:100', 'contract_expiry' => 'date', 'insurance_expiry' => 'date', 'workers_comp_expiry' => 'date', 'rating' => 'int', 'notes' => 'text', 'active' => 'bool|1'] + $ts,
            'purchase_requests' => ['id' => 'pk', 'number' => 'str:20', 'department_id' => 'fk', 'requested_by' => 'fk', 'vendor_id' => 'fk', 'title' => 'str:200', 'details' => 'text', 'amount' => 'dec|0', 'needed_by' => 'date', 'status' => 'str:20|submitted', 'approved_by' => 'fk', 'approved_at' => 'dt', 'ticket_id' => 'fk', 'po_number' => 'str:40'] + $ts,

            'lost_found' => ['id' => 'pk', 'number' => 'str:20', 'item' => 'str:150', 'description' => 'text', 'category' => 'str:40', 'found_location' => 'str:150', 'room_id' => 'fk', 'found_by' => 'fk', 'found_at' => 'dt', 'guest_name' => 'str:120', 'guest_contact' => 'str:190', 'status' => 'str:20|logged', 'storage_location' => 'str:100', 'high_value' => 'bool|0', 'returned_to' => 'str:150', 'returned_at' => 'dt', 'shipping_tracking' => 'str:100', 'dispose_after' => 'date', 'notes' => 'text'] + $ts,
            'incidents' => ['id' => 'pk', 'number' => 'str:20', 'type' => 'str:30|security', 'severity' => 'str:20|minor', 'occurred_at' => 'dt', 'location_id' => 'fk', 'room_id' => 'fk', 'summary' => 'str:255', 'details' => 'text', 'persons_involved' => 'text', 'witnesses' => 'text', 'injury' => 'bool|0', 'first_aid_given' => 'bool|0', 'emergency_services' => 'str:100', 'police_file' => 'str:60', 'cctv_reference' => 'str:100', 'reported_by' => 'fk', 'status' => 'str:20|open', 'follow_up' => 'text', 'ticket_id' => 'fk', 'workers_comp_reportable' => 'bool|0'] + $ts,
            'key_log' => ['id' => 'pk', 'key_name' => 'str:100', 'key_type' => 'str:30|master', 'issued_to' => 'str:120', 'issued_to_user_id' => 'fk', 'issued_by' => 'fk', 'issued_at' => 'dt', 'due_back_at' => 'dt', 'returned_at' => 'dt', 'received_by' => 'fk', 'purpose' => 'str:255', 'notes' => 'text'] + $ts,

            'logbook' => ['id' => 'pk', 'department_id' => 'fk', 'shift' => 'str:20|day', 'entry' => 'text', 'importance' => 'str:20|info', 'room_id' => 'fk', 'author_id' => 'fk', 'acknowledged_by' => 'json', 'follow_up_ticket_id' => 'fk', 'created_at' => 'dt', '_idx' => ['department_id', 'created_at']],
            'announcements' => ['id' => 'pk', 'title' => 'str:200', 'body' => 'text', 'department_id' => 'fk', 'starts_at' => 'dt', 'ends_at' => 'dt', 'pinned' => 'bool|0', 'created_by' => 'fk', 'created_at' => 'dt'],
            'guest_notes' => ['id' => 'pk', 'guest_name' => 'str:120', 'reservation_ref' => 'str:40', 'room_id' => 'fk', 'kind' => 'str:30|preference', 'note' => 'text', 'alert_departments' => 'str:255', 'valid_until' => 'date', 'created_by' => 'fk'] + $ts,

            'temp_points' => ['id' => 'pk', 'name' => 'str:120', 'kind' => 'str:30|fridge', 'location_id' => 'fk', 'outlet_id' => 'fk', 'min_c' => 'dec', 'max_c' => 'dec', 'check_every_hours' => 'int|4', 'sensor_id' => 'str:80', 'active' => 'bool|1'] + $ts,
            'temp_logs' => ['id' => 'pk', 'point_id' => 'fk', 'reading_c' => 'dec', 'in_range' => 'bool|1', 'source' => 'str:20|manual', 'recorded_by' => 'fk', 'corrective_action' => 'text', 'ticket_id' => 'fk', 'created_at' => 'dt', '_idx' => ['point_id', 'created_at']],
            'outlets' => ['id' => 'pk', 'name' => 'str:120', 'kind' => 'str:30|restaurant', 'location_id' => 'fk', 'hours' => 'str:255', 'manager_id' => 'fk', 'phone_ext' => 'str:20', 'capacity' => 'int', 'active' => 'bool|1'] + $ts,
            'menu_86' => ['id' => 'pk', 'outlet_id' => 'fk', 'item' => 'str:150', 'reason' => 'str:255', 'substitute' => 'str:150', 'user_id' => 'fk', 'until' => 'dt', 'active' => 'bool|1', 'created_at' => 'dt'],
            'allergens' => ['id' => 'pk', 'guest_name' => 'str:120', 'room_id' => 'fk', 'event_id' => 'fk', 'allergen' => 'str:100', 'severity' => 'str:20|moderate', 'notes' => 'text', 'created_by' => 'fk', 'created_at' => 'dt'],
            'events' => ['id' => 'pk', 'beo_number' => 'str:30', 'name' => 'str:200', 'client_name' => 'str:150', 'contact' => 'str:190', 'location_id' => 'fk', 'start_at' => 'dt', 'end_at' => 'dt', 'guests' => 'int|0', 'setup_style' => 'str:40', 'menu' => 'text', 'beverage' => 'text', 'av_requirements' => 'text', 'dietary' => 'text', 'notes' => 'text', 'status' => 'str:20|definite', 'owner_id' => 'fk'] + $ts,
            'minibar_postings' => ['id' => 'pk', 'room_id' => 'fk', 'item' => 'str:120', 'qty' => 'int|1', 'amount' => 'dec|0', 'posted_by' => 'fk', 'pms_posted' => 'bool|0', 'pms_reference' => 'str:60', 'created_at' => 'dt'],

            'notifications' => ['id' => 'pk', 'user_id' => 'fk', 'ticket_id' => 'fk', 'title' => 'str:255', 'body' => 'text', 'link' => 'str:255', 'level' => 'str:10|info', 'read_at' => 'dt', 'created_at' => 'dt', '_idx' => ['user_id', 'read_at']],
            'notification_queue' => ['id' => 'pk', 'channel' => 'str:20', 'target' => 'str:500', 'subject' => 'str:255', 'body' => 'text', 'payload' => 'json', 'status' => 'str:20|pending', 'attempts' => 'int|0', 'last_error' => 'text', 'created_at' => 'dt', 'sent_at' => 'dt', '_idx' => ['status']],
            'notification_rules' => ['id' => 'pk', 'name' => 'str:150', 'event' => 'str:60', 'department_id' => 'fk', 'min_priority' => 'str:20', 'channel' => 'str:20|inapp', 'target_type' => 'str:20|department', 'target' => 'str:500', 'active' => 'bool|1'] + $ts,
            'webhooks' => ['id' => 'pk', 'name' => 'str:150', 'url' => 'str:500', 'events' => 'str:500', 'secret' => 'str:100', 'active' => 'bool|1', 'last_status' => 'int', 'last_at' => 'dt'] + $ts,
            'integrations' => ['id' => 'pk', 'provider' => 'str:30', 'enabled' => 'bool|0', 'mode' => 'str:10|mock', 'config' => 'json', 'last_sync_at' => 'dt', 'last_status' => 'str:255'] + $ts + ['_uniq' => ['provider']],
            'integration_log' => ['id' => 'pk', 'provider' => 'str:30', 'direction' => 'str:10', 'action' => 'str:100', 'status' => 'str:20', 'summary' => 'text', 'created_at' => 'dt', '_idx' => ['provider', 'created_at']],

            'audit_log' => ['id' => 'pk', 'at' => 'dt', 'user_id' => 'fk', 'username' => 'str:60', 'ip' => 'str:64', 'user_agent' => 'str:255', 'action' => 'str:60', 'entity' => 'str:40', 'entity_id' => 'int', 'detail' => 'json', 'prev_hash' => 'str:64', 'hash' => 'str:64', '_idx' => ['at', 'entity']],
        ];
    }

    private static function colSql(string $name, string $spec): string {
        $mysql = Db::$driver === 'mysql';
        [$type, $def] = array_pad(explode('|', $spec, 2), 2, null);
        $len = null;
        if (str_contains($type, ':')) [$type, $len] = explode(':', $type);
        $sql = match ($type) {
            'pk'   => $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'fk'   => $mysql ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
            'int'  => $mysql ? 'INT NULL' : 'INTEGER NULL',
            'dec'  => $mysql ? 'DECIMAL(12,2) NULL' : 'REAL NULL',
            'bool' => $mysql ? 'TINYINT(1) NOT NULL' : 'INTEGER NOT NULL',
            'str'  => $mysql ? "VARCHAR($len) NULL" : 'TEXT NULL',
            'text', 'json' => $mysql ? 'MEDIUMTEXT NULL' : 'TEXT NULL',
            'dt'   => $mysql ? 'DATETIME NULL' : 'TEXT NULL',
            'date' => $mysql ? 'DATE NULL' : 'TEXT NULL',
            default => throw new RuntimeException("Unknown type $type"),
        };
        if ($type === 'bool' && $def === null) $def = '0';
        if ($def !== null && !in_array($type, ['text', 'json'], true)) {
            $sql .= ' DEFAULT ' . (is_numeric($def) ? $def : Db::$pdo->quote($def));
        }
        return "`$name` $sql";
    }

    public static function migrate(): array {
        $done = [];
        foreach (self::tables() as $table => $cols) {
            $idx = $cols['_idx'] ?? []; $uniq = $cols['_uniq'] ?? [];
            unset($cols['_idx'], $cols['_uniq']);
            $existing = self::columns($table);
            if (!$existing) {
                $defs = [];
                foreach ($cols as $c => $spec) $defs[] = self::colSql($c, $spec);
                $sql = "CREATE TABLE `$table` (\n  " . implode(",\n  ", $defs) . "\n)";
                if (Db::$driver === 'mysql') $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
                Db::$pdo->exec($sql);
                foreach ($idx as $c) Db::$pdo->exec("CREATE INDEX `ix_{$table}_{$c}` ON `$table` (`$c`" . (Db::$driver === 'mysql' && str_starts_with($cols[$c], 'text') ? '(100)' : '') . ")");
                foreach ($uniq as $c) Db::$pdo->exec("CREATE UNIQUE INDEX `ux_{$table}_{$c}` ON `$table` (`$c`)");
                $done[] = "created $table";
            } else {
                foreach ($cols as $c => $spec) {
                    if (!in_array($c, $existing, true)) {
                        Db::$pdo->exec("ALTER TABLE `$table` ADD COLUMN " . self::colSql($c, $spec));
                        $done[] = "added $table.$c";
                    }
                }
            }
        }
        return $done;
    }

    public static function columns(string $table): array {
        try {
            if (Db::$driver === 'mysql') {
                $exists = Db::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
                if (!$exists) return [];
                return array_column(Db::all("SHOW COLUMNS FROM `$table`"), 'Field');
            }
            return array_column(Db::all("PRAGMA table_info(`$table`)"), 'name');
        } catch (Throwable $e) { return []; }
    }

    public static function hasColumn(string $table, string $col): bool {
        static $cache = [];
        $cache[$table] ??= array_keys(array_diff_key(self::tables()[$table] ?? [], ['_idx' => 1, '_uniq' => 1]));
        return in_array($col, $cache[$table], true);
    }
}
