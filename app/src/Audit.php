<?php
declare(strict_types=1);

/**
 * Append-only, hash-chained audit trail (NIST 800-53 AU-2, AU-3, AU-9, AU-10).
 * Each record stores the SHA-256 of the previous record, so any edit or deletion breaks the chain
 * and shows up in verify().
 */
final class Audit {
    public static function log(string $action, ?string $entity = null, $entityId = null, array $detail = [], ?array $user = null): void {
        $user ??= Auth::$user;
        $prev = (string)(Db::val('SELECT hash FROM audit_log ORDER BY id DESC LIMIT 1') ?? '');
        $row = [
            'at' => U::now(),
            'user_id' => $user['id'] ?? null,
            'username' => $user['username'] ?? (Auth::$via ?: 'system'),
            'ip' => U::ip(),
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? (PHP_SAPI === 'cli' ? 'cli' : ''), 0, 255),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (int)$entityId,
            'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'prev_hash' => $prev,
        ];
        $row['hash'] = self::hashRow($row);
        Db::insert('audit_log', $row);
    }

    private static function hashRow(array $r): string {
        return hash('sha256', implode('|', [$r['prev_hash'], $r['at'], $r['user_id'], $r['username'], $r['ip'], $r['action'], $r['entity'], $r['entity_id'], $r['detail']]));
    }

    public static function verify(): array {
        $prev = ''; $n = 0; $bad = [];
        $st = Db::q('SELECT * FROM audit_log ORDER BY id');
        while ($r = $st->fetch()) {
            $n++;
            if ($r['prev_hash'] !== $prev || self::hashRow($r) !== $r['hash']) $bad[] = (int)$r['id'];
            $prev = $r['hash'];
            if (count($bad) > 20) break;
        }
        return ['records' => $n, 'intact' => !$bad, 'broken_at' => $bad];
    }

    /** Field-level diff for change records (AU-3 content of audit records). */
    public static function diff(array $before, array $after): array {
        $d = [];
        foreach ($after as $k => $v) {
            if (in_array($k, ['password_hash', 'mfa_secret', 'token_hash', 'updated_at'], true)) continue;
            $old = $before[$k] ?? null;
            if ((string)$old !== (string)$v) $d[$k] = [$old, $v];
        }
        return $d;
    }
}
