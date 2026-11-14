<?php
declare(strict_types=1);

final class OpenApi {
    public static function spec(): array {
        $ref = fn($n) => ['$ref' => "#/components/schemas/$n"];
        $ok = fn($d, $schema = null) => ['200' => ['description' => $d] + ($schema ? ['content' => ['application/json' => ['schema' => $schema]]] : [])];
        $json = fn($schema) => ['required' => true, 'content' => ['application/json' => ['schema' => $schema]]];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $entities = array_keys(Registry::entities());

        $paths = [
            '/tickets' => [
                'get' => ['tags' => ['Tickets'], 'summary' => 'List tickets visible to the caller', 'parameters' => array_map(fn($p) => ['name' => $p, 'in' => 'query', 'schema' => ['type' => 'string']], ['status', 'department_id', 'category_id', 'priority', 'type', 'source', 'room_id', 'asset_id', 'assigned_to', 'q', 'overdue', 'breached', 'vip', 'since', 'sort', 'limit', 'offset']), 'responses' => $ok('Ticket page', ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'rows' => ['type' => 'array', 'items' => $ref('Ticket')]]])],
                'post' => ['tags' => ['Tickets'], 'summary' => 'Create a ticket (department and category are inferred from text when omitted)', 'requestBody' => $json($ref('TicketCreate')), 'responses' => $ok('Created ticket', $ref('Ticket'))],
            ],
            '/tickets/{id}' => [
                'get' => ['tags' => ['Tickets'], 'summary' => 'Ticket with timeline, checklist, labour, parts and attachments', 'parameters' => [$id], 'responses' => $ok('Ticket detail', $ref('Ticket'))],
                'patch' => ['tags' => ['Tickets'], 'summary' => 'Update fields, assign, or change status (validated workflow)', 'parameters' => [$id], 'requestBody' => $json($ref('TicketUpdate')), 'responses' => $ok('Updated ticket', $ref('Ticket'))],
            ],
            '/tickets/{id}/comments' => ['post' => ['tags' => ['Tickets'], 'summary' => 'Add an internal or guest-visible comment', 'parameters' => [$id], 'requestBody' => $json(['type' => 'object', 'required' => ['body'], 'properties' => ['body' => ['type' => 'string'], 'visibility' => ['type' => 'string', 'enum' => ['internal', 'guest']]]]), 'responses' => $ok('Comment id')]],
            '/tickets/{id}/time' => ['post' => ['tags' => ['Tickets'], 'summary' => 'Log labour minutes', 'parameters' => [$id], 'requestBody' => $json(['type' => 'object', 'properties' => ['minutes' => ['type' => 'integer'], 'note' => ['type' => 'string']]]), 'responses' => $ok('OK')]],
            '/tickets/{id}/parts' => ['post' => ['tags' => ['Tickets'], 'summary' => 'Consume stock against a ticket', 'parameters' => [$id], 'requestBody' => $json(['type' => 'object', 'properties' => ['part_id' => ['type' => 'integer'], 'qty' => ['type' => 'number']]]), 'responses' => $ok('OK')]],
            '/tickets/{id}/attachments' => ['post' => ['tags' => ['Tickets'], 'summary' => 'Upload a photo or document (multipart field "file")', 'parameters' => [$id], 'requestBody' => ['content' => ['multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']]]]]], 'responses' => $ok('Attachment')]],
            '/hk/board' => ['get' => ['tags' => ['Housekeeping'], 'summary' => 'Every room with front office, housekeeping and service status, plus attendant workloads', 'responses' => $ok('Board')]],
            '/hk/rooms/{id}' => ['post' => ['tags' => ['Housekeeping'], 'summary' => 'Change room status (dirty, in_progress, clean, inspected, pickup; ooo; DND; rush)', 'parameters' => [$id], 'requestBody' => $json(['type' => 'object', 'properties' => ['hk_status' => ['type' => 'string'], 'service_status' => ['type' => 'string'], 'dnd' => ['type' => 'boolean'], 'rush' => ['type' => 'boolean'], 'ooo_reason' => ['type' => 'string']]]), 'responses' => $ok('Room')]],
            '/hk/auto-assign' => ['post' => ['tags' => ['Housekeeping'], 'summary' => 'Balance dirty rooms across on-shift attendants by cleaning credits', 'responses' => $ok('Assignment result')]],
            '/food/points' => ['get' => ['tags' => ['Food safety'], 'summary' => 'HACCP points with last reading and overdue flags', 'responses' => $ok('Points')]],
            '/food/readings' => ['post' => ['tags' => ['Food safety'], 'summary' => 'Record a temperature; out-of-range opens an urgent ticket', 'requestBody' => $json(['type' => 'object', 'required' => ['point_id', 'reading_c'], 'properties' => ['point_id' => ['type' => 'integer'], 'reading_c' => ['type' => 'number'], 'corrective_action' => ['type' => 'string']]]), 'responses' => $ok('Reading')]],
            '/checklists/run' => ['post' => ['tags' => ['Inspections'], 'summary' => 'Submit a completed checklist; failed items can raise tickets', 'requestBody' => $json(['type' => 'object', 'properties' => ['template_id' => ['type' => 'integer'], 'room_id' => ['type' => 'integer'], 'location_id' => ['type' => 'integer'], 'results' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['item' => ['type' => 'string'], 'result' => ['type' => 'string', 'enum' => ['pass', 'fail', 'na']], 'note' => ['type' => 'string']]]]]]), 'responses' => $ok('Score')]],
            '/minibar' => ['post' => ['tags' => ['Rooms'], 'summary' => 'Post a minibar charge (sent to the Opera folio when live)', 'requestBody' => $json(['type' => 'object', 'properties' => ['room_id' => ['type' => 'integer'], 'item' => ['type' => 'string'], 'qty' => ['type' => 'integer'], 'amount' => ['type' => 'number']]]), 'responses' => $ok('Posting')]],
            '/dashboard' => ['get' => ['tags' => ['Reporting'], 'summary' => 'Operations dashboard', 'responses' => $ok('Dashboard')]],
            '/reports' => ['get' => ['tags' => ['Reporting'], 'summary' => 'Performance reports', 'parameters' => [['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'department_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => $ok('Report')]],
            '/search' => ['get' => ['tags' => ['Reporting'], 'summary' => 'Search tickets, rooms, assets, guests, lost and found, events', 'parameters' => [['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => $ok('Results')]],
            '/calendar' => ['get' => ['tags' => ['Reporting'], 'summary' => 'PM due, events and scheduled work in a date range', 'responses' => $ok('Calendar')]],
            '/notifications' => ['get' => ['tags' => ['Notifications'], 'summary' => 'Caller notifications', 'responses' => $ok('Notifications')]],
        ];
        foreach ($entities as $e) {
            $paths["/e/$e"] = ['get' => ['tags' => ['Records'], 'summary' => 'List ' . Registry::entities()[$e]['label'], 'parameters' => [['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'f', 'in' => 'query', 'style' => 'deepObject', 'schema' => ['type' => 'object']], ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => $ok('Rows')],
                'post' => ['tags' => ['Records'], 'summary' => 'Create ' . Registry::entities()[$e]['one'], 'requestBody' => $json(self::entitySchema($e)), 'responses' => $ok('Created')]];
            $paths["/e/$e/{id}"] = ['get' => ['tags' => ['Records'], 'parameters' => [$id], 'responses' => $ok('Row')], 'patch' => ['tags' => ['Records'], 'parameters' => [$id], 'requestBody' => $json(self::entitySchema($e)), 'responses' => $ok('Updated')], 'delete' => ['tags' => ['Records'], 'parameters' => [$id], 'responses' => $ok('Deleted')]];
        }
        $paths['/../hooks/canary'] = ['post' => ['tags' => ['Webhooks (inbound)'], 'summary' => 'Canary guest message or request. Sign the raw body with HMAC-SHA256 in X-Signature.', 'security' => [], 'requestBody' => $json(['type' => 'object', 'properties' => ['event' => ['type' => 'string'], 'data' => ['type' => 'object', 'properties' => ['conversation_id' => ['type' => 'string'], 'room_number' => ['type' => 'string'], 'guest_name' => ['type' => 'string'], 'message' => ['type' => 'string'], 'reservation_id' => ['type' => 'string']]]]]), 'responses' => $ok('Ticket number')]];
        $paths['/../hooks/opera'] = ['post' => ['tags' => ['Webhooks (inbound)'], 'summary' => 'Opera Cloud business events (CHECKIN, CHECKOUT, UPDATE_ROOM_STATUS, NEW_RESERVATION, ROOM_MOVE)', 'security' => [], 'responses' => $ok('Processed')]];
        $paths['/../hooks/sensor'] = ['post' => ['tags' => ['Webhooks (inbound)'], 'summary' => 'Sensor readings: {sensor_id, kind: temperature|leak|humidity|bms, value, unit}. Header X-Sensor-Token.', 'security' => [], 'responses' => $ok('Results')]];
        $paths['/../hooks/inbound'] = ['post' => ['tags' => ['Webhooks (inbound)'], 'summary' => 'Create a ticket from Zapier, Make or Power Automate (Bearer token, write scope)', 'requestBody' => $json($ref('TicketCreate')), 'responses' => $ok('Ticket')]];
        $paths['/../mcp'] = ['post' => ['tags' => ['MCP'], 'summary' => 'Model Context Protocol (Streamable HTTP, JSON-RPC 2.0). Token needs the mcp scope.', 'responses' => $ok('JSON-RPC response')]];

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => cfg('app_name') . ' API · ' . cfg('property_name'), 'version' => EXPTIX_VERSION, 'description' => "Hospitality operations and service-request platform. Authenticate with `Authorization: Bearer xtx_...` (create tokens under Admin > API and MCP). Scopes: read, write, mcp, admin. Times are UTC. Outbound webhooks are signed: `X-ExpTix-Signature: sha256=HMAC(secret, timestamp + '.' + body)` with `X-ExpTix-Timestamp`."],
            'servers' => [['url' => cfg('base_url') . '/api']],
            'security' => [['bearer' => []]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'xtx token']],
                'schemas' => [
                    'Ticket' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'number' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['ticket_type'])], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'],
                        'priority' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['priority'])], 'status' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['ticket_status'])], 'source' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['source'])],
                        'department_id' => ['type' => 'integer'], 'category_id' => ['type' => 'integer'], 'room_id' => ['type' => 'integer'], 'assigned_to' => ['type' => 'integer'], 'guest_name' => ['type' => 'string'], 'due_at' => ['type' => 'string'], 'sla_breached' => ['type' => 'boolean'], 'created_at' => ['type' => 'string']]],
                    'TicketCreate' => ['type' => 'object', 'required' => ['title'], 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'department_id' => ['type' => 'integer'], 'category_id' => ['type' => 'integer'], 'priority' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['priority'])],
                        'type' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['ticket_type'])], 'room' => ['type' => 'string', 'description' => 'Room number'], 'room_id' => ['type' => 'integer'], 'location_id' => ['type' => 'integer'], 'asset_id' => ['type' => 'integer'],
                        'guest_name' => ['type' => 'string'], 'guest_contact' => ['type' => 'string'], 'assigned_to' => ['type' => 'integer'], 'due_at' => ['type' => 'string'], 'scheduled_for' => ['type' => 'string'], 'external_ref' => ['type' => 'string'], 'external_system' => ['type' => 'string'], 'checklist' => ['type' => 'array', 'items' => ['type' => 'string']], 'set_room_ooo' => ['type' => 'boolean']]],
                    'TicketUpdate' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'enum' => array_keys(Registry::OPT['ticket_status'])], 'status_note' => ['type' => 'string'], 'assigned_to' => ['type' => ['integer', 'null']], 'priority' => ['type' => 'string'], 'resolution' => ['type' => 'string'],
                        'recovery_action' => ['type' => 'string'], 'recovery_value' => ['type' => 'number'], 'return_room_to_service' => ['type' => 'boolean'], 'force' => ['type' => 'boolean', 'description' => 'Resolve even with open checklist items']]],
                ],
            ],
        ];
    }

    private static function entitySchema(string $e): array {
        $props = [];
        foreach (Registry::entities()[$e]['fields'] as $k => $f) {
            if (in_array($f[1], ['readonly', 'readonly_ref'], true)) continue;
            $t = match ($f[1]) { 'number', 'ref' => ['type' => 'integer'], 'decimal' => ['type' => 'number'], 'bool' => ['type' => 'boolean'], 'multiref' => ['type' => 'array', 'items' => ['type' => 'integer']], default => ['type' => 'string'] };
            if (!empty($f['opts'])) $t['enum'] = array_map('strval', array_keys($f['opts']));
            $t['description'] = $f[0];
            $props[$k] = $t;
        }
        return ['type' => 'object', 'properties' => $props ?: (object)[]];
    }
}
