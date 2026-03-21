<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

/**
 * Database — thin wrapper around Laravel's DB facade.
 *
 * All production code should use Eloquent or DB:: directly. This class
 * exists solely for backward compatibility with tests, migrations, and
 * legacy scripts that call Database::query().
 *
 * Internally delegates to Laravel's PDO connection — no separate
 * connection is created.
 */
class Database
{
    /** Query performance tracking */
    private static array $queryLog = [];
    private static float $slowQueryThreshold = 0.1; // 100ms in seconds
    private static ?bool $enableProfiling = null;

    /**
     * Get the PDO instance from Laravel's database connection.
     */
    public static function getInstance(): PDO
    {
        return DB::connection()->getPdo();
    }

    /**
     * Alias for getInstance().
     */
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }

    /**
     * Set Laravel's PDO connection for the DB bridge.
     *
     * @deprecated No longer needed — getInstance() pulls from DB:: directly.
     */
    public static function setLaravelConnection(PDO $pdo): void
    {
        // No-op: kept for backward compatibility with bootstrap/app.php
    }

    /**
     * Execute a prepared statement and return the PDOStatement.
     *
     * @param string $sql    SQL query with ? placeholders
     * @param array  $params Bind parameters
     * @return \PDOStatement
     */
    public static function query($sql, $params = [])
    {
        if (self::$enableProfiling === null) {
            self::$enableProfiling = config('app.debug', false)
                || config('database.profiling', false);
        }

        $start = microtime(true);

        try {
            // Validate — PDO cannot bind array values
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                    $caller = $backtrace[1] ?? [];
                    $location = isset($caller['file'], $caller['line'])
                        ? $caller['file'] . ':' . $caller['line']
                        : 'unknown';

                    throw new \InvalidArgumentException(
                        "Array parameter detected at key '$key'. " .
                        "PDO cannot bind array values directly. " .
                        "Use IN (?, ?, ...) with flattened parameters instead. " .
                        "Called from: $location"
                    );
                }
            }

            $pdo = self::getInstance();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $duration = microtime(true) - $start;

            if ($duration > self::$slowQueryThreshold) {
                self::logSlowQuery($sql, $params, $duration);
            }

            if (self::$enableProfiling) {
                self::$queryLog[] = [
                    'sql' => $sql,
                    'params' => $params,
                    'duration' => $duration,
                    'timestamp' => microtime(true),
                    'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                ];
            }

            return $stmt;
        } catch (PDOException $e) {
            error_log('Database Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    private static function logSlowQuery($sql, $params, $duration): void
    {
        $milliseconds = round($duration * 1000, 2);

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[2] ?? $backtrace[1] ?? [];
        $location = isset($caller['file'], $caller['line'])
            ? basename($caller['file']) . ':' . $caller['line']
            : 'unknown';

        $paramCount = count($params);
        $message = sprintf(
            "SLOW QUERY [%sms] at %s\nSQL: %s\nParam count: %d",
            $milliseconds,
            $location,
            trim(preg_replace('/\s+/', ' ', $sql)),
            $paramCount
        );

        error_log($message);
    }

    public static function getQueryStats(): array
    {
        if (empty(self::$queryLog)) {
            return [
                'total_queries' => 0,
                'total_duration' => 0,
                'avg_duration' => 0,
                'slowest_query' => null,
                'profiling_enabled' => self::$enableProfiling
            ];
        }

        $totalDuration = array_sum(array_column(self::$queryLog, 'duration'));
        $queryCount = count(self::$queryLog);

        $slowest = null;
        $slowestDuration = 0;
        foreach (self::$queryLog as $query) {
            if ($query['duration'] > $slowestDuration) {
                $slowestDuration = $query['duration'];
                $slowest = [
                    'sql' => $query['sql'],
                    'duration' => round($query['duration'] * 1000, 2) . 'ms',
                    'params' => $query['params']
                ];
            }
        }

        return [
            'total_queries' => $queryCount,
            'total_duration' => round($totalDuration * 1000, 2) . 'ms',
            'avg_duration' => round(($totalDuration / $queryCount) * 1000, 2) . 'ms',
            'slowest_query' => $slowest,
            'slow_query_threshold' => (self::$slowQueryThreshold * 1000) . 'ms',
            'profiling_enabled' => self::$enableProfiling,
            'queries' => self::$queryLog
        ];
    }

    public static function resetQueryLog(): void
    {
        self::$queryLog = [];
    }

    public static function setSlowQueryThreshold($seconds): void
    {
        self::$slowQueryThreshold = (float) $seconds;
    }

    public static function setProfilingEnabled($enabled): void
    {
        self::$enableProfiling = (bool) $enabled;
    }

    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
}
