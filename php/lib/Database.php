<?php
/**
 * Database – PDO wrapper with lazy connection.
 */
class Database {

    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_NAME);
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Convenience: prepare + execute + return all rows. */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Convenience: prepare + execute + return first row. */
    public static function queryOne(string $sql, array $params = []): ?array {
        $rows = self::query($sql, $params);
        return $rows[0] ?? null;
    }

    /** Convenience: prepare + execute, return lastInsertId. */
    public static function execute(string $sql, array $params = []): string {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return self::get()->lastInsertId();
    }
}
