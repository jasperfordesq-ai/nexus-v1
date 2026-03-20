<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database singleton — provides raw PDO access with query profiling,
 * slow-query logging, and Laravel PDO bridge support.
 *
 * Prefer Eloquent or DB:: facade for new code. This class exists for
 * backward compatibility with legacy code that calls Database::query().
 */
class Database
{
    private static $instance = null;
    private $pdo;

    /** Query performance tracking */
    private static $queryLog = [];
    private static $slowQueryThreshold = 0.1; // 100ms in seconds
    private static $enableProfiling = null;

    /**
     * Laravel DB bridge — when set, getInstance() returns Laravel's PDO
     * instead of creating a separate connection.
     */
    private static ?PDO $laravelPdo = null;

    private function __construct()
    {
        // Prefer Laravel's PDO if the application has booted
        try {
            if (function_exists('app') && app()->bound('db')) {
                $this->pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
                return;
            }
        } catch (\Throwable $e) {
            // Laravel not available, fall back to manual connection
        }

        $config = require __DIR__ . '/../../src/Config/config.php';
        $dbConfig = $config['db'];

        try {
            if ($dbConfig['type'] === 'sqlite') {
                $this->pdo = new PDO('sqlite:' . $dbConfig['file']);
            } elseif ($dbConfig['type'] === 'pgsql' || $dbConfig['type'] === 'postgresql') {
                $port = $dbConfig['port'] ?? 5432;
                $dsn = "pgsql:host={$dbConfig['host']};port={$port};dbname={$dbConfig['name']}";
                $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
            } else {
                $port = $dbConfig['port'] ?? 3306;
                $dsn = "mysql:host={$dbConfig['host']};port={$port};dbname={$dbConfig['name']};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log('Database Connection Failed: ' . $e->getMessage());
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Set Laravel's PDO connection for the DB bridge.
     */
    public static function setLaravelConnection(PDO $pdo): void
    {
        self::$laravelPdo = $pdo;
    }

    public static function getInstance()
    {
        if (self::$laravelPdo !== null) {
            return self::$laravelPdo;
        }

        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    public static function getConnection()
    {
        return self::getInstance();
    }

    public static function query($sql, $params = [])
    {
        if (self::$enableProfiling === null) {
            self::$enableProfiling = getenv('DEBUG') === 'true' || getenv('DB_PROFILING') === 'true';
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

            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);

            $duration = microtime(true) - $start;

            // Track with performance monitor if available
            if (class_exists(\App\Services\PerformanceMonitorService::class)) {
                \App\Services\PerformanceMonitorService::trackQueryStatic($sql, $params, $duration);
            }

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

    private static function logSlowQuery($sql, $params, $duration)
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

    public static function getQueryStats()
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

    public static function resetQueryLog()
    {
        self::$queryLog = [];
    }

    public static function setSlowQueryThreshold($seconds)
    {
        self::$slowQueryThreshold = (float) $seconds;
    }

    public static function setProfilingEnabled($enabled)
    {
        self::$enableProfiling = (bool) $enabled;
    }

    public static function lastInsertId()
    {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction()
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit()
    {
        return self::getInstance()->commit();
    }

    public static function rollback()
    {
        return self::getInstance()->rollBack();
    }
}
