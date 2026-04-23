<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class DB
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $default = config('database.default');
        $config  = config("database.connections.{$default}");

        if (!$config || empty($config['dsn'])) {
            throw new \Exception("Database configuration not found for connection: {$default}");
        }

        try {
            self::$pdo = new PDO(
                $config['dsn'],
                $config['username'] ?? '',
                $config['password'] ?? '',
                $config['options'] ?? [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            if (env('APP_DEBUG', false)) {
                die("Database connection failed: " . $e->getMessage());
            }
            die("Database connection failed. Please check your configuration.");
        }

        return self::$pdo;
    }

    public static function connection(): PDO
    {
        return self::connect();
    }

    /**
     * Execute a raw query with optional bindings
     */
    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /**
     * Get all records
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::query($sql, $bindings)->fetchAll();
    }

    /**
     * Get a single record
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $result = self::query($sql, $bindings)->fetch();
        return $result ?: null;
    }

    /**
     * Execute INSERT / UPDATE / DELETE and return affected rows
     */
    public static function statement(string $sql, array $bindings = []): int
    {
        return self::query($sql, $bindings)->rowCount();
    }

    /**
     * Insert a record and return last insert ID
     */
    public static function insert(string $sql, array $bindings = []): string|false
    {
        self::query($sql, $bindings);
        return self::connect()->lastInsertId();
    }

    /**
     * Convenience methods
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }


    /**
 * Run a database transaction with automatic commit/rollback.
 *
 * @template T
 * @param callable(PDO): T $callback
 * @return T
 * @throws \Throwable
 */
public static function transaction(callable $callback)
{
    $pdo = self::connect();

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $started = true;
        } else {
            $started = false;
        }

        $result = $callback($pdo);

        if ($started) {
            $pdo->commit();
        }

        return $result;

    } catch (\Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
    /**
     * Begin a transaction
     */
    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollBack(): bool
    {
        return self::connect()->rollBack();
    }
}