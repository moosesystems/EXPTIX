<?php
declare(strict_types=1);

/** Thin PDO wrapper. All SQL uses backtick identifiers, which both MySQL and SQLite accept. */
final class Db {
    public static PDO $pdo;
    public static string $driver = 'sqlite';

    public static function connect(array $c): void {
        self::$driver = $c['driver'] ?? 'sqlite';
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        if (self::$driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
            self::$pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        } else {
            self::$pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
            self::$pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = OFF; PRAGMA busy_timeout = 5000;');
        }
    }

    public static function q(string $sql, array $p = []): PDOStatement {
        $st = self::$pdo->prepare($sql);
        $st->execute(array_values($p) === $p ? $p : $p);
        return $st;
    }
    public static function all(string $sql, array $p = []): array { return self::q($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { $r = self::q($sql, $p)->fetch(); return $r === false ? null : $r; }
    public static function val(string $sql, array $p = []) { $r = self::q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

    public static function insert(string $table, array $data): int {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int)self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, int $id): void {
        if (!$data) return;
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        self::q("UPDATE `$table` SET $set WHERE id = ?", [...array_values($data), $id]);
    }

    public static function tx(callable $fn) {
        self::$pdo->beginTransaction();
        try { $r = $fn(); self::$pdo->commit(); return $r; }
        catch (Throwable $e) { self::$pdo->rollBack(); throw $e; }
    }

    /** Date difference in minutes between two DATETIME columns, portable. */
    public static function minutesBetween(string $a, string $b): string {
        return self::$driver === 'mysql'
            ? "TIMESTAMPDIFF(MINUTE, $a, $b)"
            : "((julianday($b) - julianday($a)) * 1440)";
    }
    public static function rand(): string { return self::$driver === 'mysql' ? 'RAND()' : 'RANDOM()'; }
    public static function dateOf(string $col): string {
        return self::$driver === 'mysql' ? "DATE($col)" : "substr($col,1,10)";
    }
}
