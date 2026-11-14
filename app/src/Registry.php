<?php
declare(strict_types=1);

/**
 * Metadata for every record type the generic API and UI handle. The front end renders lists and forms
 * from this, so a new module is mostly a schema entry plus a registry entry.
 * Field types: text, textarea, number, decimal, bool, date, datetime, select, ref, colour, lines, email, tel, readonly
 */
final class Registry {
    public const OPT = [
        'priority' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent', 'critical' => 'Critical'],
        'ticket_status' => ['new' => 'New', 'acknowledged' => 'Acknowledged', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved', 'closed' => 'Closed', 'cancelled' => 'Cancelled'],
        'ticket_type' => ['work_order' => 'Work order', 'guest_request' => 'Guest request', 'complaint' => 'Guest complaint', 'incident' => 'Incident follow-up', 'task' => 'Task', 'pm' => 'Preventive maintenance', 'inspection' => 'Inspection finding', 'event_task' => 'Event task', 'project' => 'Project'],
        'source' => ['staff' => 'Staff', 'phone' => 'Phone', 'front_desk' => 'Front desk', 'guest_portal' => 'Guest QR portal', 'canary' => 'Canary', 'opera' => 'Opera PMS', 'email' => 'Email', 'api' => 'API', 'mcp' => 'AI assistant (MCP)', 'pm' => 'PM schedule', 'sensor' => 'Sensor', 'inspection' => 'Inspection'],
        'hk_status' => ['dirty' => 'Dirty', 'in_progress' => 'Cleaning', 'clean' => 'Clean', 'inspected' => 'Inspected', 'pickup' => 'Pickup'],
        'fo_status' => ['vacant' => 'Vacant', 'occupied' => 'Occupied'],
        'service_status' => ['in_service' => 'In service', 'ooo' => 'Out of order', 'oos' => 'Out of service'],
        'reservation_status' => ['none' => 'No reservation', 'arrival' => 'Due in', 'arrived' => 'Arrived', 'stayover' => 'Stayover', 'due_out' => 'Due out', 'departed' => 'Departed'],
        'location_type' => ['building' => 'Building', 'floor' => 'Floor', 'area' => 'Public area', 'boh' => 'Back of house', 'outlet' => 'F&B outlet', 'meeting' => 'Meeting room', 'mechanical' => 'Mechanical room', 'exterior' => 'Exterior / grounds', 'parking' => 'Parking'],
        'asset_status' => ['operational' => 'Operational', 'degraded' => 'Degraded', 'down' => 'Down', 'standby' => 'Standby', 'retired' => 'Retired'],
        'criticality' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'life_safety' => 'Life safety'],
        'frequency' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'semiannual' => 'Every 6 months', 'annual' => 'Annual', 'custom' => 'Custom (days)'],
        'checklist_kind' => ['inspection' => 'Inspection', 'room_inspection' => 'Room inspection', 'pm' => 'Preventive maintenance', 'opening' => 'Opening', 'closing' => 'Closing', 'haccp' => 'Food safety (HACCP)', 'safety' => 'Health and safety', 'fire' => 'Fire and life safety', 'audit' => 'Brand / quality audit'],
        'lf_status' => ['logged' => 'Logged', 'held' => 'In storage', 'guest_contacted' => 'Guest contacted', 'claimed' => 'Returned in person', 'shipped' => 'Shipped', 'donated' => 'Donated', 'disposed' => 'Disposed'],
        'incident_type' => ['security' => 'Security', 'safety' => 'Health and safety', 'guest_injury' => 'Guest injury', 'staff_injury' => 'Staff injury', 'property_damage' => 'Property damage', 'theft' => 'Theft', 'fire_alarm' => 'Fire alarm', 'medical' => 'Medical', 'disturbance' => 'Disturbance / noise', 'police' => 'Police attendance', 'environmental' => 'Environmental / spill', 'cyber' => 'Cyber / IT security', 'other' => 'Other'],
        'severity' => ['minor' => 'Minor', 'moderate' => 'Moderate', 'major' => 'Major', 'critical' => 'Critical'],
        'incident_status' => ['open' => 'Open', 'investigating' => 'Investigating', 'closed' => 'Closed'],
        'shift' => ['day' => 'Day', 'swing' => 'Evening', 'night' => 'Night (audit)'],
        'importance' => ['info' => 'Info', 'important' => 'Important', 'action' => 'Action required'],
        'temp_kind' => ['fridge' => 'Refrigerator / walk-in cooler', 'freezer' => 'Freezer', 'hot_holding' => 'Hot holding', 'cooling' => 'Cooling (2-stage)', 'receiving' => 'Receiving', 'dishwasher' => 'Dish machine rinse', 'cooking' => 'Cooking (core temp)', 'pool' => 'Pool / hot tub', 'other' => 'Other'],
        'outlet_kind' => ['restaurant' => 'Restaurant', 'lounge' => 'Lounge / bar', 'in_room_dining' => 'In-room dining', 'banquets' => 'Banquets', 'cafe' => 'Café / market', 'staff_cafeteria' => 'Staff cafeteria', 'minibar' => 'Minibar'],
        'event_status' => ['tentative' => 'Tentative', 'definite' => 'Definite', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
        'setup' => ['theatre' => 'Theatre', 'classroom' => 'Classroom', 'boardroom' => 'Boardroom', 'u_shape' => 'U-shape', 'banquet_rounds' => 'Banquet rounds', 'cabaret' => 'Cabaret', 'reception' => 'Reception', 'hollow_square' => 'Hollow square', 'custom' => 'Custom'],
        'pr_status' => ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'ordered' => 'Ordered', 'received' => 'Received', 'cancelled' => 'Cancelled'],
        'key_type' => ['master' => 'Master', 'grand_master' => 'Grand master', 'section' => 'Section master', 'mechanical' => 'Mechanical room', 'vehicle' => 'Vehicle', 'keycard' => 'Key card', 'other' => 'Other'],
        'guest_note_kind' => ['preference' => 'Preference', 'allergy' => 'Allergy / dietary', 'accessibility' => 'Accessibility', 'vip' => 'VIP / amenity', 'complaint_history' => 'Past issue', 'celebration' => 'Celebration', 'security' => 'Security note'],
        'allergen_sev' => ['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe / anaphylaxis'],
        'channel' => ['inapp' => 'In-app', 'push' => 'Mobile push', 'email' => 'Email', 'sms' => 'SMS (Twilio)', 'teams' => 'Microsoft Teams', 'slack' => 'Slack', 'webhook' => 'Webhook'],
        'target_type' => ['department' => 'Department members', 'department_leads' => 'Department supervisors and managers', 'role' => 'Role', 'user' => 'Specific user', 'assignee' => 'Assignee', 'address' => 'Address / URL'],
        'rule_event' => ['ticket.created' => 'Ticket created', 'ticket.assigned' => 'Ticket assigned', 'ticket.status' => 'Status changed', 'ticket.comment' => 'Comment added', 'ticket.sla_warning' => 'SLA at risk', 'ticket.sla_breach' => 'SLA breached', 'ticket.resolved' => 'Ticket resolved', 'ticket.vip' => 'VIP guest request', 'temp.out_of_range' => 'Temperature out of range', 'incident.created' => 'Incident reported', 'room.ready' => 'Room inspected and ready', 'room.ooo' => 'Room out of order', 'stock.low' => 'Stock below reorder point', 'lost_found.created' => 'Lost and found logged', 'pm.due' => 'PM generated', 'logbook.action' => 'Logbook action entry'],
    ];

    public static function roles(): array { return array_map(fn($r) => $r['label'], Auth::ROLES); }

    public static function entities(): array {
        $o = self::OPT;
        return [
            'departments' => ['label' => 'Departments', 'one' => 'Department', 'title' => 'name', 'read' => 1, 'write' => 90, 'group' => 'Admin', 'order' => 'sort, name',
                'list' => ['code', 'name', 'manager_id', 'email', 'phone_ext', 'active'],
                'fields' => ['code' => ['Code', 'text', 'req' => 1], 'name' => ['Name', 'text', 'req' => 1], 'icon' => ['Icon (emoji)', 'text'], 'colour' => ['Colour', 'colour'], 'manager_id' => ['Manager', 'ref', 'ref' => 'users'],
                    'email' => ['Department email', 'email'], 'phone_ext' => ['Phone extension', 'text'], 'description' => ['Description', 'textarea'], 'sort' => ['Sort order', 'number'], 'active' => ['Active', 'bool']]],

            'users' => ['label' => 'Users', 'one' => 'User', 'title' => 'name', 'read' => 1, 'write' => 90, 'group' => 'Admin', 'order' => 'name', 'search' => ['name', 'username', 'email'], 'special' => 'users',
                'list' => ['name', 'username', 'role', 'department_id', 'title', 'on_shift', 'mfa_enabled', 'last_login_at', 'active'],
                'fields' => ['name' => ['Full name', 'text', 'req' => 1], 'username' => ['Username', 'text', 'req' => 1], 'email' => ['Email', 'email'], 'phone' => ['Mobile (for SMS)', 'tel'], 'title' => ['Job title', 'text'],
                    'role' => ['Role', 'select', 'opts' => self::roles(), 'req' => 1], 'department_id' => ['Home department', 'ref', 'ref' => 'departments'], 'extra_departments' => ['Also works in', 'multiref', 'ref' => 'departments'],
                    'hk_credit_capacity' => ['Room credits per shift (housekeeping)', 'number'], 'on_shift' => ['On shift now', 'bool'], 'notify_email' => ['Email alerts', 'bool'], 'notify_sms' => ['SMS alerts', 'bool'], 'notify_push' => ['Push alerts', 'bool'],
                    'mfa_enabled' => ['MFA enabled', 'readonly'], 'last_login_at' => ['Last sign-in', 'readonly'], 'active' => ['Active', 'bool']]],

            'locations' => ['label' => 'Locations', 'one' => 'Location', 'title' => 'name', 'read' => 1, 'write' => 70, 'group' => 'Admin', 'order' => 'type, name', 'search' => ['name', 'code'],
                'list' => ['code', 'name', 'type', 'floor', 'parent_id', 'department_id', 'active'],
                'fields' => ['code' => ['Code', 'text'], 'name' => ['Name', 'text', 'req' => 1], 'type' => ['Type', 'select', 'opts' => $o['location_type']], 'floor' => ['Floor', 'text'], 'parent_id' => ['Inside', 'ref', 'ref' => 'locations'],
                    'department_id' => ['Responsible department', 'ref', 'ref' => 'departments'], 'notes' => ['Notes', 'textarea'], 'active' => ['Active', 'bool']]],

            'room_types' => ['label' => 'Room types', 'one' => 'Room type', 'title' => 'name', 'read' => 1, 'write' => 70, 'group' => 'Admin', 'order' => 'name',
                'list' => ['code', 'name', 'beds', 'hk_credits', 'clean_minutes', 'max_occupancy'],
                'fields' => ['code' => ['Code', 'text', 'req' => 1], 'name' => ['Name', 'text', 'req' => 1], 'beds' => ['Beds', 'text'], 'max_occupancy' => ['Max occupancy', 'number'], 'hk_credits' => ['Cleaning credits', 'decimal'], 'clean_minutes' => ['Standard clean (minutes)', 'number'], 'description' => ['Description', 'textarea']]],

            'rooms' => ['label' => 'Rooms', 'one' => 'Room', 'title' => 'number', 'read' => 1, 'write' => 50, 'group' => 'Rooms', 'order' => 'number', 'search' => ['number', 'guest_name', 'reservation_ref'],
                'list' => ['number', 'floor', 'room_type_id', 'fo_status', 'hk_status', 'service_status', 'reservation_status', 'guest_name', 'attendant_id'],
                'fields' => ['number' => ['Room', 'text', 'req' => 1], 'floor' => ['Floor', 'text'], 'room_type_id' => ['Room type', 'ref', 'ref' => 'room_types'], 'fo_status' => ['Front office status', 'select', 'opts' => $o['fo_status']],
                    'hk_status' => ['Housekeeping status', 'select', 'opts' => $o['hk_status']], 'service_status' => ['Service status', 'select', 'opts' => $o['service_status']], 'ooo_reason' => ['Out of order reason', 'text'], 'ooo_until' => ['Out of order until', 'date'],
                    'reservation_status' => ['Reservation', 'select', 'opts' => $o['reservation_status']], 'guest_name' => ['Guest', 'text'], 'reservation_ref' => ['Confirmation #', 'text'], 'arrival_date' => ['Arrival', 'date'], 'departure_date' => ['Departure', 'date'],
                    'adults' => ['Adults', 'number'], 'children' => ['Children', 'number'], 'vip_code' => ['VIP code', 'text'], 'dnd' => ['Do not disturb', 'bool'], 'make_up_room' => ['Make up room requested', 'bool'], 'turndown' => ['Turndown', 'bool'], 'rush' => ['Rush', 'bool'], 'eco_skip' => ['Guest declined service (green choice)', 'bool'],
                    'attendant_id' => ['Attendant', 'ref', 'ref' => 'users'], 'hk_section' => ['Section', 'text'], 'features' => ['Features', 'text'], 'opera_room_id' => ['Opera room id', 'text'], 'notes' => ['Notes', 'textarea']]],

            'categories' => ['label' => 'Request categories', 'one' => 'Category', 'title' => 'name', 'read' => 1, 'write' => 70, 'group' => 'Admin', 'order' => 'department_id, sort, name', 'search' => ['name', 'keywords'],
                'list' => ['name', 'department_id', 'type', 'default_priority', 'sla_policy_id', 'guest_visible', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'department_id' => ['Department', 'ref', 'ref' => 'departments', 'req' => 1], 'type' => ['Ticket type', 'select', 'opts' => $o['ticket_type']], 'default_priority' => ['Default priority', 'select', 'opts' => $o['priority']],
                    'sla_policy_id' => ['SLA policy', 'ref', 'ref' => 'sla_policies'], 'checklist_template_id' => ['Checklist', 'ref', 'ref' => 'checklist_templates'], 'keywords' => ['Auto-route keywords (comma separated)', 'text'],
                    'guest_visible' => ['Show on guest QR portal', 'bool'], 'guest_label' => ['Guest-facing label', 'text'], 'icon' => ['Icon (emoji)', 'text'], 'sort' => ['Sort', 'number'], 'active' => ['Active', 'bool']]],

            'sla_policies' => ['label' => 'SLA policies', 'one' => 'SLA policy', 'title' => 'name', 'read' => 1, 'write' => 90, 'group' => 'Admin', 'order' => 'response_minutes',
                'list' => ['name', 'priority', 'response_minutes', 'resolve_minutes', 'escalate_after_minutes', 'escalate_role', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'priority' => ['Default for priority', 'select', 'opts' => ['' => '(none)'] + $o['priority']], 'response_minutes' => ['Respond within (minutes)', 'number'], 'resolve_minutes' => ['Resolve within (minutes)', 'number'],
                    'escalate_after_minutes' => ['Escalate after breach by (minutes)', 'number'], 'escalate_role' => ['Escalate to role', 'select', 'opts' => self::roles()], 'escalate_user_id' => ['Escalate to person', 'ref', 'ref' => 'users'], 'active' => ['Active', 'bool']]],

            'assets' => ['label' => 'Assets and equipment', 'one' => 'Asset', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Engineering', 'order' => 'name', 'search' => ['tag', 'name', 'serial', 'model', 'category'], 'scope' => 'dept',
                'list' => ['tag', 'name', 'category', 'location_id', 'status', 'criticality', 'warranty_until'],
                'fields' => ['tag' => ['Asset tag', 'text', 'req' => 1], 'name' => ['Name', 'text', 'req' => 1], 'category' => ['Category', 'text'], 'department_id' => ['Owning department', 'ref', 'ref' => 'departments'], 'location_id' => ['Location', 'ref', 'ref' => 'locations'], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'],
                    'status' => ['Status', 'select', 'opts' => $o['asset_status']], 'criticality' => ['Criticality', 'select', 'opts' => $o['criticality']], 'manufacturer' => ['Manufacturer', 'text'], 'model' => ['Model', 'text'], 'serial' => ['Serial', 'text'],
                    'install_date' => ['Installed', 'date'], 'warranty_until' => ['Warranty until', 'date'], 'expected_life_years' => ['Expected life (years)', 'number'], 'replacement_cost' => ['Replacement cost', 'decimal'], 'vendor_id' => ['Service vendor', 'ref', 'ref' => 'vendors'],
                    'meter_reading' => ['Meter reading', 'decimal'], 'meter_unit' => ['Meter unit', 'text'], 'notes' => ['Notes', 'textarea']]],

            'pm_schedules' => ['label' => 'Preventive maintenance', 'one' => 'PM schedule', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Engineering', 'order' => 'next_due', 'search' => ['name', 'compliance'], 'scope' => 'dept',
                'list' => ['name', 'asset_id', 'frequency', 'next_due', 'assigned_to', 'compliance', 'active'],
                'fields' => ['name' => ['Task', 'text', 'req' => 1], 'asset_id' => ['Asset', 'ref', 'ref' => 'assets'], 'location_id' => ['Location', 'ref', 'ref' => 'locations'], 'department_id' => ['Department', 'ref', 'ref' => 'departments'], 'category_id' => ['Category', 'ref', 'ref' => 'categories'],
                    'frequency' => ['Frequency', 'select', 'opts' => $o['frequency']], 'interval_days' => ['Custom interval (days)', 'number'], 'next_due' => ['Next due', 'date', 'req' => 1], 'lead_days' => ['Create ticket days ahead', 'number'],
                    'checklist_template_id' => ['Checklist', 'ref', 'ref' => 'checklist_templates'], 'assigned_to' => ['Default assignee', 'ref', 'ref' => 'users'], 'priority' => ['Priority', 'select', 'opts' => $o['priority']], 'estimated_minutes' => ['Estimated minutes', 'number'],
                    'compliance' => ['Compliance driver (code, regulation)', 'text'], 'instructions' => ['Instructions', 'textarea'], 'last_generated_at' => ['Last generated', 'readonly'], 'active' => ['Active', 'bool']]],

            'checklist_templates' => ['label' => 'Checklist templates', 'one' => 'Checklist template', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Operations', 'order' => 'name', 'scope' => 'dept',
                'list' => ['name', 'department_id', 'kind', 'pass_mark', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'department_id' => ['Department', 'ref', 'ref' => 'departments'], 'kind' => ['Kind', 'select', 'opts' => $o['checklist_kind']], 'items' => ['Items (one per line, prefix "## " for a section heading)', 'lines'],
                    'pass_mark' => ['Pass mark (%)', 'number'], 'create_tickets_on_fail' => ['Create tickets for failed items', 'bool'], 'active' => ['Active', 'bool']]],

            'checklist_runs' => ['label' => 'Completed checklists', 'one' => 'Checklist run', 'title' => 'id', 'read' => 1, 'write' => 999, 'group' => 'Operations', 'order' => 'created_at DESC', 'scope' => 'dept',
                'list' => ['created_at', 'template_id', 'department_id', 'room_id', 'location_id', 'performed_by', 'score', 'passed', 'tickets_created'],
                'fields' => ['template_id' => ['Checklist', 'ref', 'ref' => 'checklist_templates'], 'department_id' => ['Department', 'ref', 'ref' => 'departments'], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'location_id' => ['Location', 'ref', 'ref' => 'locations'],
                    'performed_by' => ['By', 'ref', 'ref' => 'users'], 'score' => ['Score %', 'number'], 'passed' => ['Passed', 'bool'], 'tickets_created' => ['Tickets created', 'number'], 'notes' => ['Notes', 'textarea'], 'created_at' => ['Completed', 'readonly']]],

            'parts' => ['label' => 'Parts and inventory', 'one' => 'Stock item', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Engineering', 'order' => 'name', 'search' => ['sku', 'name', 'category'], 'scope' => 'dept',
                'list' => ['sku', 'name', 'category', 'department_id', 'qty_on_hand', 'reorder_point', 'unit', 'unit_cost', 'storage_location'],
                'fields' => ['sku' => ['SKU', 'text'], 'name' => ['Item', 'text', 'req' => 1], 'category' => ['Category', 'text'], 'department_id' => ['Department', 'ref', 'ref' => 'departments'], 'storage_location' => ['Stored at', 'text'], 'unit' => ['Unit', 'text'],
                    'qty_on_hand' => ['On hand', 'decimal'], 'par_level' => ['Par level', 'decimal'], 'reorder_point' => ['Reorder point', 'decimal'], 'reorder_qty' => ['Reorder quantity', 'decimal'], 'unit_cost' => ['Unit cost', 'decimal'], 'vendor_id' => ['Vendor', 'ref', 'ref' => 'vendors'], 'active' => ['Active', 'bool']]],

            'vendors' => ['label' => 'Vendors and contractors', 'one' => 'Vendor', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Engineering', 'order' => 'name', 'search' => ['name', 'trade', 'contact_name'],
                'list' => ['name', 'trade', 'contact_name', 'phone', 'emergency_phone', 'insurance_expiry', 'workers_comp_expiry', 'rating'],
                'fields' => ['name' => ['Company', 'text', 'req' => 1], 'trade' => ['Trade / service', 'text'], 'contact_name' => ['Contact', 'text'], 'phone' => ['Phone', 'tel'], 'emergency_phone' => ['After-hours phone', 'tel'], 'email' => ['Email', 'email'],
                    'contract_ref' => ['Contract reference', 'text'], 'contract_expiry' => ['Contract expiry', 'date'], 'insurance_expiry' => ['Insurance certificate expiry', 'date'], 'workers_comp_expiry' => ['Workers\' compensation clearance expiry', 'date'], 'rating' => ['Rating (1 to 5)', 'number'], 'notes' => ['Notes', 'textarea'], 'active' => ['Active', 'bool']]],

            'purchase_requests' => ['label' => 'Purchase requests', 'one' => 'Purchase request', 'title' => 'number', 'read' => 30, 'write' => 30, 'group' => 'Engineering', 'order' => 'created_at DESC', 'scope' => 'dept', 'number' => 'PR',
                'list' => ['number', 'title', 'department_id', 'requested_by', 'amount', 'needed_by', 'status'],
                'fields' => ['number' => ['PR #', 'readonly'], 'title' => ['What is needed', 'text', 'req' => 1], 'department_id' => ['Department', 'ref', 'ref' => 'departments', 'req' => 1], 'vendor_id' => ['Suggested vendor', 'ref', 'ref' => 'vendors'], 'details' => ['Details and justification', 'textarea'],
                    'amount' => ['Estimated amount', 'decimal'], 'needed_by' => ['Needed by', 'date'], 'ticket_id' => ['Related ticket', 'ref', 'ref' => 'tickets'], 'status' => ['Status', 'select', 'opts' => $o['pr_status'], 'min_write' => 70], 'po_number' => ['PO number', 'text'],
                    'requested_by' => ['Requested by', 'readonly_ref', 'ref' => 'users'], 'approved_by' => ['Approved by', 'readonly_ref', 'ref' => 'users'], 'approved_at' => ['Approved', 'readonly']]],

            'lost_found' => ['label' => 'Lost and found', 'one' => 'Lost and found item', 'title' => 'number', 'read' => 30, 'write' => 30, 'group' => 'Rooms', 'order' => 'created_at DESC', 'search' => ['number', 'item', 'description', 'guest_name'], 'number' => 'LF',
                'list' => ['number', 'item', 'category', 'found_location', 'room_id', 'found_at', 'status', 'high_value', 'dispose_after'],
                'fields' => ['number' => ['LF #', 'readonly'], 'item' => ['Item', 'text', 'req' => 1], 'category' => ['Category', 'select', 'opts' => ['clothing' => 'Clothing', 'electronics' => 'Electronics', 'jewellery' => 'Jewellery', 'documents' => 'ID / documents', 'keys' => 'Keys', 'cash' => 'Cash / cards', 'medication' => 'Medication', 'other' => 'Other']],
                    'description' => ['Description', 'textarea'], 'found_location' => ['Found where', 'text'], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'found_by' => ['Found by', 'ref', 'ref' => 'users'], 'found_at' => ['Found at', 'datetime'],
                    'high_value' => ['High value (security holds)', 'bool'], 'storage_location' => ['Stored in', 'text'], 'status' => ['Status', 'select', 'opts' => $o['lf_status']], 'guest_name' => ['Owner / guest', 'text'], 'guest_contact' => ['Owner contact', 'text'],
                    'returned_to' => ['Returned to', 'text'], 'returned_at' => ['Returned at', 'datetime'], 'shipping_tracking' => ['Shipping tracking #', 'text'], 'dispose_after' => ['Hold until', 'date'], 'notes' => ['Notes', 'textarea']]],

            'incidents' => ['label' => 'Incident reports', 'one' => 'Incident report', 'title' => 'number', 'read' => 30, 'write' => 30, 'group' => 'Safety and security', 'order' => 'occurred_at DESC', 'search' => ['number', 'summary', 'details'], 'number' => 'IR',
                'list' => ['number', 'occurred_at', 'type', 'severity', 'summary', 'location_id', 'room_id', 'status'],
                'fields' => ['number' => ['IR #', 'readonly'], 'type' => ['Type', 'select', 'opts' => $o['incident_type'], 'req' => 1], 'severity' => ['Severity', 'select', 'opts' => $o['severity']], 'occurred_at' => ['Occurred', 'datetime', 'req' => 1],
                    'location_id' => ['Location', 'ref', 'ref' => 'locations'], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'summary' => ['Summary', 'text', 'req' => 1], 'details' => ['What happened', 'textarea'], 'persons_involved' => ['Persons involved', 'textarea'], 'witnesses' => ['Witnesses', 'textarea'],
                    'injury' => ['Injury involved', 'bool'], 'first_aid_given' => ['First aid given', 'bool'], 'workers_comp_reportable' => ['Reportable to workers\' compensation', 'bool'], 'emergency_services' => ['Emergency services attended', 'text'], 'police_file' => ['Police file #', 'text'], 'cctv_reference' => ['CCTV reference / clip', 'text'],
                    'status' => ['Status', 'select', 'opts' => $o['incident_status']], 'follow_up' => ['Follow-up actions', 'textarea'], 'ticket_id' => ['Linked ticket', 'ref', 'ref' => 'tickets'], 'reported_by' => ['Reported by', 'readonly_ref', 'ref' => 'users']]],

            'key_log' => ['label' => 'Key control', 'one' => 'Key issue', 'title' => 'key_name', 'read' => 40, 'write' => 40, 'group' => 'Safety and security', 'order' => 'issued_at DESC', 'search' => ['key_name', 'issued_to'],
                'list' => ['key_name', 'key_type', 'issued_to', 'issued_at', 'due_back_at', 'returned_at'],
                'fields' => ['key_name' => ['Key / ring', 'text', 'req' => 1], 'key_type' => ['Type', 'select', 'opts' => $o['key_type']], 'issued_to' => ['Issued to', 'text', 'req' => 1], 'issued_to_user_id' => ['Staff member', 'ref', 'ref' => 'users'], 'issued_at' => ['Issued', 'datetime'],
                    'due_back_at' => ['Due back', 'datetime'], 'returned_at' => ['Returned', 'datetime'], 'received_by' => ['Received by', 'ref', 'ref' => 'users'], 'purpose' => ['Purpose', 'text'], 'notes' => ['Notes', 'textarea'], 'issued_by' => ['Issued by', 'readonly_ref', 'ref' => 'users']]],

            'logbook' => ['label' => 'Shift logbook', 'one' => 'Log entry', 'title' => 'id', 'read' => 30, 'write' => 30, 'group' => 'Operations', 'order' => 'created_at DESC', 'search' => ['entry'], 'scope' => 'dept', 'special' => 'logbook',
                'list' => ['created_at', 'department_id', 'shift', 'importance', 'entry', 'author_id'],
                'fields' => ['department_id' => ['Department', 'ref', 'ref' => 'departments', 'req' => 1], 'shift' => ['Shift', 'select', 'opts' => $o['shift']], 'importance' => ['Importance', 'select', 'opts' => $o['importance']], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'entry' => ['Entry', 'textarea', 'req' => 1],
                    'follow_up_ticket_id' => ['Follow-up ticket', 'ref', 'ref' => 'tickets'], 'author_id' => ['By', 'readonly_ref', 'ref' => 'users'], 'created_at' => ['Logged', 'readonly']]],

            'announcements' => ['label' => 'Announcements', 'one' => 'Announcement', 'title' => 'title', 'read' => 1, 'write' => 50, 'group' => 'Operations', 'order' => 'created_at DESC',
                'list' => ['title', 'department_id', 'starts_at', 'ends_at', 'pinned'],
                'fields' => ['title' => ['Title', 'text', 'req' => 1], 'body' => ['Message', 'textarea'], 'department_id' => ['Department (blank for everyone)', 'ref', 'ref' => 'departments'], 'starts_at' => ['Show from', 'datetime'], 'ends_at' => ['Show until', 'datetime'], 'pinned' => ['Pinned', 'bool']]],

            'guest_notes' => ['label' => 'Guest preferences and alerts', 'one' => 'Guest note', 'title' => 'guest_name', 'read' => 30, 'write' => 40, 'group' => 'Rooms', 'order' => 'created_at DESC', 'search' => ['guest_name', 'reservation_ref', 'note'],
                'list' => ['guest_name', 'room_id', 'kind', 'note', 'alert_departments', 'valid_until'],
                'fields' => ['guest_name' => ['Guest', 'text', 'req' => 1], 'reservation_ref' => ['Confirmation #', 'text'], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'kind' => ['Kind', 'select', 'opts' => $o['guest_note_kind']], 'note' => ['Note', 'textarea', 'req' => 1],
                    'alert_departments' => ['Alert departments (codes, comma separated)', 'text'], 'valid_until' => ['Valid until', 'date']]],

            'temp_points' => ['label' => 'Temperature points', 'one' => 'Temperature point', 'title' => 'name', 'read' => 30, 'write' => 50, 'group' => 'Food and beverage', 'order' => 'name',
                'list' => ['name', 'kind', 'outlet_id', 'min_c', 'max_c', 'check_every_hours', 'sensor_id', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'kind' => ['Kind', 'select', 'opts' => $o['temp_kind']], 'outlet_id' => ['Outlet', 'ref', 'ref' => 'outlets'], 'location_id' => ['Location', 'ref', 'ref' => 'locations'],
                    'min_c' => ['Minimum °C', 'decimal'], 'max_c' => ['Maximum °C', 'decimal'], 'check_every_hours' => ['Check every (hours)', 'number'], 'sensor_id' => ['Sensor id (for automatic readings)', 'text'], 'active' => ['Active', 'bool']]],

            'temp_logs' => ['label' => 'Temperature log', 'one' => 'Reading', 'title' => 'id', 'read' => 30, 'write' => 30, 'group' => 'Food and beverage', 'order' => 'created_at DESC', 'special' => 'temps',
                'list' => ['created_at', 'point_id', 'reading_c', 'in_range', 'source', 'recorded_by', 'corrective_action'],
                'fields' => ['point_id' => ['Point', 'ref', 'ref' => 'temp_points', 'req' => 1], 'reading_c' => ['Reading °C', 'decimal', 'req' => 1], 'corrective_action' => ['Corrective action', 'textarea'], 'in_range' => ['In range', 'readonly'], 'source' => ['Source', 'readonly'],
                    'recorded_by' => ['By', 'readonly_ref', 'ref' => 'users'], 'ticket_id' => ['Ticket', 'readonly_ref', 'ref' => 'tickets'], 'created_at' => ['Recorded', 'readonly']]],

            'outlets' => ['label' => 'F&B outlets', 'one' => 'Outlet', 'title' => 'name', 'read' => 1, 'write' => 70, 'group' => 'Food and beverage', 'order' => 'name',
                'list' => ['name', 'kind', 'hours', 'manager_id', 'phone_ext', 'capacity', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'kind' => ['Kind', 'select', 'opts' => $o['outlet_kind']], 'location_id' => ['Location', 'ref', 'ref' => 'locations'], 'hours' => ['Hours', 'text'], 'manager_id' => ['Manager', 'ref', 'ref' => 'users'], 'phone_ext' => ['Extension', 'text'], 'capacity' => ['Seats', 'number'], 'active' => ['Active', 'bool']]],

            'menu_86' => ['label' => '86 list', 'one' => '86 item', 'title' => 'item', 'read' => 1, 'write' => 30, 'group' => 'Food and beverage', 'order' => 'created_at DESC', 'filter' => ['active' => 1],
                'list' => ['item', 'outlet_id', 'reason', 'substitute', 'until', 'user_id', 'active'],
                'fields' => ['outlet_id' => ['Outlet', 'ref', 'ref' => 'outlets'], 'item' => ['Item', 'text', 'req' => 1], 'reason' => ['Reason', 'text'], 'substitute' => ['Suggest instead', 'text'], 'until' => ['Until', 'datetime'], 'active' => ['Active', 'bool'], 'user_id' => ['By', 'readonly_ref', 'ref' => 'users']]],

            'allergens' => ['label' => 'Allergy alerts', 'one' => 'Allergy alert', 'title' => 'guest_name', 'read' => 30, 'write' => 30, 'group' => 'Food and beverage', 'order' => 'created_at DESC',
                'list' => ['guest_name', 'room_id', 'event_id', 'allergen', 'severity', 'created_at'],
                'fields' => ['guest_name' => ['Guest', 'text', 'req' => 1], 'room_id' => ['Room', 'ref', 'ref' => 'rooms'], 'event_id' => ['Event', 'ref', 'ref' => 'events'], 'allergen' => ['Allergen', 'text', 'req' => 1], 'severity' => ['Severity', 'select', 'opts' => $o['allergen_sev']], 'notes' => ['Notes', 'textarea']]],

            'events' => ['label' => 'Events and BEOs', 'one' => 'Event', 'title' => 'name', 'read' => 1, 'write' => 50, 'group' => 'Food and beverage', 'order' => 'start_at', 'search' => ['beo_number', 'name', 'client_name'],
                'list' => ['beo_number', 'name', 'client_name', 'location_id', 'start_at', 'guests', 'setup_style', 'status'],
                'fields' => ['beo_number' => ['BEO #', 'text'], 'name' => ['Event', 'text', 'req' => 1], 'client_name' => ['Client', 'text'], 'contact' => ['On-site contact', 'text'], 'location_id' => ['Room / venue', 'ref', 'ref' => 'locations'],
                    'start_at' => ['Starts', 'datetime', 'req' => 1], 'end_at' => ['Ends', 'datetime'], 'guests' => ['Guaranteed guests', 'number'], 'setup_style' => ['Set-up', 'select', 'opts' => $o['setup']], 'menu' => ['Menu', 'textarea'], 'beverage' => ['Beverage', 'textarea'],
                    'dietary' => ['Dietary and allergies', 'textarea'], 'av_requirements' => ['AV and IT', 'textarea'], 'notes' => ['Notes', 'textarea'], 'status' => ['Status', 'select', 'opts' => $o['event_status']], 'owner_id' => ['Event manager', 'ref', 'ref' => 'users']]],

            'minibar_postings' => ['label' => 'Minibar postings', 'one' => 'Minibar posting', 'title' => 'id', 'read' => 30, 'write' => 30, 'group' => 'Rooms', 'order' => 'created_at DESC',
                'list' => ['created_at', 'room_id', 'item', 'qty', 'amount', 'posted_by', 'pms_posted'],
                'fields' => ['room_id' => ['Room', 'ref', 'ref' => 'rooms', 'req' => 1], 'item' => ['Item', 'text', 'req' => 1], 'qty' => ['Quantity', 'number'], 'amount' => ['Charge', 'decimal'], 'pms_posted' => ['Posted to Opera', 'readonly'], 'pms_reference' => ['Opera reference', 'readonly'], 'posted_by' => ['By', 'readonly_ref', 'ref' => 'users']]],

            'staff_shifts' => ['label' => 'Staff schedule', 'one' => 'Shift', 'title' => 'id', 'read' => 30, 'write' => 50, 'group' => 'Operations', 'order' => 'shift_date DESC', 'scope' => 'dept',
                'list' => ['shift_date', 'user_id', 'department_id', 'shift', 'section', 'start_time', 'end_time'],
                'fields' => ['user_id' => ['Staff member', 'ref', 'ref' => 'users', 'req' => 1], 'department_id' => ['Department', 'ref', 'ref' => 'departments'], 'shift_date' => ['Date', 'date', 'req' => 1], 'shift' => ['Shift', 'select', 'opts' => $o['shift']], 'section' => ['Section / station', 'text'],
                    'start_time' => ['Start (HH:MM)', 'text'], 'end_time' => ['End (HH:MM)', 'text'], 'notes' => ['Notes', 'text']]],

            'notification_rules' => ['label' => 'Notification rules', 'one' => 'Notification rule', 'title' => 'name', 'read' => 70, 'write' => 90, 'group' => 'Admin', 'order' => 'event, name',
                'list' => ['name', 'event', 'department_id', 'min_priority', 'channel', 'target_type', 'target', 'active'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'event' => ['When', 'select', 'opts' => $o['rule_event'], 'req' => 1], 'department_id' => ['Only for department', 'ref', 'ref' => 'departments'], 'min_priority' => ['Minimum priority', 'select', 'opts' => ['' => 'Any'] + $o['priority']],
                    'channel' => ['Send by', 'select', 'opts' => $o['channel']], 'target_type' => ['To', 'select', 'opts' => $o['target_type']], 'target' => ['Target (role, user id, email, phone or URL)', 'text'], 'active' => ['Active', 'bool']]],

            'webhooks' => ['label' => 'Outbound webhooks', 'one' => 'Webhook', 'title' => 'name', 'read' => 90, 'write' => 90, 'group' => 'Admin', 'order' => 'name',
                'list' => ['name', 'url', 'events', 'active', 'last_status', 'last_at'],
                'fields' => ['name' => ['Name', 'text', 'req' => 1], 'url' => ['URL (https)', 'text', 'req' => 1], 'events' => ['Events (comma separated, * for all)', 'text'], 'secret' => ['Signing secret (HMAC-SHA256)', 'text'], 'active' => ['Active', 'bool'], 'last_status' => ['Last HTTP status', 'readonly'], 'last_at' => ['Last sent', 'readonly']]],
        ];
    }

    public static function get(string $name): array {
        $e = self::entities()[$name] ?? null;
        if (!$e) throw new HttpError(404, 'Unknown record type');
        $e['name'] = $name;
        return $e;
    }

    /** Client-facing metadata, normalised. */
    public static function meta(): array {
        $out = [];
        foreach (self::entities() as $name => $e) {
            if (Auth::rank() < $e['read'] && !Auth::isAuditor()) continue;
            $fields = [];
            foreach ($e['fields'] as $k => $f) {
                $fields[$k] = ['label' => $f[0], 'type' => $f[1], 'required' => !empty($f['req']), 'options' => $f['opts'] ?? null, 'ref' => $f['ref'] ?? null];
            }
            $out[$name] = ['label' => $e['label'], 'one' => $e['one'], 'title' => $e['title'], 'group' => $e['group'], 'list' => $e['list'], 'fields' => $fields,
                'can_write' => !Auth::isAuditor() && Auth::rank() >= $e['write'], 'special' => $e['special'] ?? null, 'search' => !empty($e['search'])];
        }
        return $out;
    }
}
