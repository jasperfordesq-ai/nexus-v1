<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    /**
     * Query performance tracking
     */
    private static $queryLog = [];
    private static $slowQueryThreshold = 0.1; // 100ms in seconds
    private static $enableProfiling = null;

    private function __construct()
    {
        $config = require __DIR__ . '/../Config/config.php';
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
            // Show a generic error to the user (Security Best Practice)
            header('HTTP/1.1 503 Service Unavailable');
            die("<h1>Service Unavailable</h1><p>The application is currently experiencing high load. Please try again later.</p>");
        }
    }

    public static function getInstance()
    {
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
        // Enable profiling if DEBUG mode is on (only evaluate env vars once)
        if (self::$enableProfiling === null) {
            self::$enableProfiling = getenv('DEBUG') === 'true' || getenv('DB_PROFILING') === 'true';
        }

        $start = microtime(true);

        try {
            // Validate parameters to prevent array-to-string conversion errors
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                    $caller = $backtrace[1] ?? [];
                    $location = isset($caller['file']) && isset($caller['line'])
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

            // Calculate execution time
            $duration = microtime(true) - $start;

            // Track query with performance monitor
            if (class_exists('\Nexus\Services\PerformanceMonitorService')) {
                \Nexus\Services\PerformanceMonitorService::trackQuery($sql, $params, $duration);
            }

            // Log slow queries
            if ($duration > self::$slowQueryThreshold) {
                self::logSlowQuery($sql, $params, $duration);
            }

            // Store in query log if profiling enabled
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
            // Log the error with query details
            error_log('Database Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    /**
     * Log slow queries to error log
     *
     * @param string $sql Query SQL
     * @param array $params Query parameters
     * @param float $duration Execution time in seconds
     */
    private static function logSlowQuery($sql, $params, $duration)
    {
        $milliseconds = round($duration * 1000, 2);

        // Get calling location
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[2] ?? $backtrace[1] ?? [];
        $location = isset($caller['file']) && isset($caller['line'])
            ? basename($caller['file']) . ':' . $caller['line']
            : 'unknown';

        // Build log message (redact param values to avoid exposing sensitive data)
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

    /**
     * Get query performance statistics
     *
     * @return array Query statistics
     */
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

        // Find slowest query
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

    /**
     * Reset query log
     */
    public static function resetQueryLog()
    {
        self::$queryLog = [];
    }

    /**
     * Set slow query threshold
     *
     * @param float $seconds Threshold in seconds
     */
    public static function setSlowQueryThreshold($seconds)
    {
        self::$slowQueryThreshold = (float) $seconds;
    }

    /**
     * Enable or disable query profiling
     *
     * @param bool $enabled
     */
    public static function setProfilingEnabled($enabled)
    {
        self::$enableProfiling = (bool) $enabled;
    }

    public static function lastInsertId()
    {
        return self::getInstance()->lastInsertId();
    }

    /**
     * Begin a database transaction
     */
    public static function beginTransaction()
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit the current transaction
     */
    public static function commit()
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback the current transaction
     */
    public static function rollback()
    {
        return self::getInstance()->rollBack();
    }
}
