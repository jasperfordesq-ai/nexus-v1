<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Sentry\State\Scope;
use Nexus\Core\TenantContext;

/**
 * Sentry Error Tracking Service
 *
 * Provides centralized error tracking and performance monitoring for Project NEXUS.
 * Integrates with Sentry.io for real-time error alerts, stack traces, and performance insights.
 *
 * Features:
 * - Automatic exception capture
 * - User context tracking (user ID, tenant ID)
 * - Performance monitoring (transactions, spans)
 * - Breadcrumb logging for debugging
 * - Environment-aware sampling
 *
 * @see https://docs.sentry.io/platforms/php/
 */
class SentryService
{
    private static bool $initialized = false;
    private static bool $enabled = false;

    /**
     * Initialize Sentry SDK
     *
     * This should be called early in the application bootstrap (index.php)
     * before any errors can occur.
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $dsn = getenv('SENTRY_DSN_PHP') ?: null;
        $environment = getenv('SENTRY_ENVIRONMENT') ?: getenv('APP_ENV') ?: 'production';
        $appEnv = getenv('APP_ENV') ?: 'production';

        // Don't initialize Sentry in development or if DSN is not set
        if ($appEnv === 'development' || $appEnv === 'local' || empty($dsn)) {
            self::$initialized = true;
            self::$enabled = false;
            return;
        }

        try {
            \Sentry\init([
                'dsn' => $dsn,
                'environment' => $environment,
                'release' => self::getRelease(),

                // Sample rates
                'sample_rate' => 1.0, // 100% of errors
                'traces_sample_rate' => (float)(getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.1), // 10% of transactions

                // Performance
                'enable_tracing' => true,

                // Context
                'attach_stacktrace' => true,
                'max_breadcrumbs' => 50,
                'max_value_length' => 2048,

                // Privacy
                'send_default_pii' => false, // Don't send PII by default (we'll add it manually)

                // Server name
                'server_name' => gethostname() ?: 'unknown',

                // Error reporting
                'error_types' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,

                // Integrations
                'default_integrations' => true,

                // Before send callback - filter sensitive data
                'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
                    return self::beforeSend($event);
                },

                // Before breadcrumb callback - filter sensitive breadcrumbs
                'before_breadcrumb' => function (\Sentry\Breadcrumb $breadcrumb): ?\Sentry\Breadcrumb {
                    return self::beforeBreadcrumb($breadcrumb);
                },
            ]);

            self::$initialized = true;
            self::$enabled = true;

            // Set global tags
            \Sentry\configureScope(function (Scope $scope): void {
                $scope->setTag('platform', 'php');
                $scope->setTag('app_component', 'backend');
            });

        } catch (\Throwable $e) {
            // Silently fail - don't break the app if Sentry fails
            error_log('Sentry initialization failed: ' . $e->getMessage());
            self::$initialized = true;
            self::$enabled = false;
        }
    }

    /**
     * Check if Sentry is enabled
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Set user context
     *
     * @param int $userId User ID
     * @param string|null $email User email (optional)
     * @param string|null $username Username (optional)
     * @return void
     */
    public static function setUser(int $userId, ?string $email = null, ?string $username = null): void
    {
        if (!self::$enabled) {
            return;
        }

        \Sentry\configureScope(function (Scope $scope) use ($userId, $email, $username): void {
            $scope->setUser([
                'id' => (string)$userId,
                'email' => $email,
                'username' => $username,
            ]);
        });
    }

    /**
     * Set tenant context
     *
     * @param int $tenantId Tenant ID
     * @param string|null $tenantName Tenant name (optional)
     * @return void
     */
    public static function setTenant(int $tenantId, ?string $tenantName = null): void
    {
        if (!self::$enabled) {
            return;
        }

        \Sentry\configureScope(function (Scope $scope) use ($tenantId, $tenantName): void {
            $scope->setTag('tenant_id', (string)$tenantId);
            if ($tenantName) {
                $scope->setTag('tenant_name', $tenantName);
            }

            $scope->setContext('tenant', [
                'id' => $tenantId,
                'name' => $tenantName,
            ]);
        });
    }

    /**
     * Set request context (automatically called for API requests)
     *
     * @param array $requestData Request data to include
     * @return void
     */
    public static function setRequestContext(array $requestData): void
    {
        if (!self::$enabled) {
            return;
        }

        \Sentry\configureScope(function (Scope $scope) use ($requestData): void {
            $scope->setContext('request', $requestData);
        });
    }

    /**
     * Add breadcrumb for debugging
     *
     * @param string $message Breadcrumb message
     * @param string $category Category (e.g., 'auth', 'db', 'api')
     * @param array $data Additional data
     * @param string $level Level (debug, info, warning, error)
     * @return void
     */
    public static function addBreadcrumb(
        string $message,
        string $category = 'default',
        array $data = [],
        string $level = 'info'
    ): void {
        if (!self::$enabled) {
            return;
        }

        \Sentry\addBreadcrumb(
            new \Sentry\Breadcrumb(
                $level,
                \Sentry\Breadcrumb::TYPE_DEFAULT,
                $category,
                $message,
                $data
            )
        );
    }

    /**
     * Capture an exception
     *
     * @param \Throwable $exception Exception to capture
     * @param array $context Additional context
     * @return string|null Event ID
     */
    public static function captureException(\Throwable $exception, array $context = []): ?string
    {
        if (!self::$enabled) {
            return null;
        }

        // Add context if provided
        if (!empty($context)) {
            \Sentry\configureScope(function (Scope $scope) use ($context): void {
                foreach ($context as $key => $value) {
                    $scope->setContext($key, $value);
                }
            });
        }

        return \Sentry\captureException($exception);
    }

    /**
     * Capture a message (for non-exception errors)
     *
     * @param string $message Message to capture
     * @param string $level Level (debug, info, warning, error, fatal)
     * @param array $context Additional context
     * @return string|null Event ID
     */
    public static function captureMessage(
        string $message,
        string $level = 'error',
        array $context = []
    ): ?string {
        if (!self::$enabled) {
            return null;
        }

        // Convert string level to Sentry severity
        $severity = match ($level) {
            'debug' => \Sentry\Severity::debug(),
            'info' => \Sentry\Severity::info(),
            'warning' => \Sentry\Severity::warning(),
            'error' => \Sentry\Severity::error(),
            'fatal' => \Sentry\Severity::fatal(),
            default => \Sentry\Severity::error(),
        };

        // Add context if provided
        if (!empty($context)) {
            \Sentry\configureScope(function (Scope $scope) use ($context): void {
                foreach ($context as $key => $value) {
                    $scope->setContext($key, $value);
                }
            });
        }

        return \Sentry\captureMessage($message, $severity);
    }

    /**
     * Start a performance transaction
     *
     * @param string $name Transaction name (e.g., 'POST /api/v2/listings')
     * @param string $op Operation (e.g., 'http.server', 'db.query')
     * @return \Sentry\Tracing\Transaction|null
     */
    public static function startTransaction(string $name, string $op = 'http.server'): ?\Sentry\Tracing\Transaction
    {
        if (!self::$enabled) {
            return null;
        }

        $transactionContext = new \Sentry\Tracing\TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setOp($op);

        return \Sentry\startTransaction($transactionContext);
    }

    /**
     * Get current transaction
     *
     * @return \Sentry\Tracing\Transaction|null
     */
    public static function getCurrentTransaction(): ?\Sentry\Tracing\Transaction
    {
        if (!self::$enabled) {
            return null;
        }

        return \Sentry\SentrySdk::getCurrentHub()->getTransaction();
    }

    /**
     * Get release version (for Sentry releases)
     *
     * @return string
     */
    private static function getRelease(): string
    {
        // Try to get from git
        $gitHash = trim((string)shell_exec('git rev-parse --short HEAD 2>/dev/null'));
        if (!empty($gitHash)) {
            return 'nexus@' . $gitHash;
        }

        // Fallback to version file or timestamp
        $versionFile = __DIR__ . '/../../VERSION';
        if (file_exists($versionFile)) {
            return 'nexus@' . trim((string)file_get_contents($versionFile));
        }

        return 'nexus@unknown';
    }

    /**
     * Before send callback - filter sensitive data
     *
     * @param \Sentry\Event $event
     * @return \Sentry\Event|null
     */
    private static function beforeSend(\Sentry\Event $event): ?\Sentry\Event
    {
        // Filter sensitive data from request body
        $request = $event->getRequest();
        if ($request) {
            $data = $request['data'] ?? [];
            if (is_array($data)) {
                // Remove sensitive fields
                $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'csrf_token'];
                foreach ($sensitiveFields as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = '[FILTERED]';
                    }
                }
                $request['data'] = $data;
            }
        }

        return $event;
    }

    /**
     * Before breadcrumb callback - filter sensitive breadcrumbs
     *
     * @param \Sentry\Breadcrumb $breadcrumb
     * @return \Sentry\Breadcrumb|null
     */
    private static function beforeBreadcrumb(\Sentry\Breadcrumb $breadcrumb): ?\Sentry\Breadcrumb
    {
        // Filter sensitive data from breadcrumb data
        $data = $breadcrumb->getMetadata();
        if (is_array($data)) {
            $sensitiveFields = ['password', 'token', 'api_key', 'secret'];
            foreach ($sensitiveFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = '[FILTERED]';
                }
            }
        }

        return $breadcrumb;
    }

    /**
     * Capture database query breadcrumb
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param float $duration Query duration in seconds
     * @return void
     */
    public static function captureQuery(string $query, array $params = [], float $duration = 0): void
    {
        if (!self::$enabled) {
            return;
        }

        self::addBreadcrumb(
            'Database Query',
            'db',
            [
                'query' => $query,
                'params' => $params,
                'duration_ms' => round($duration * 1000, 2),
            ]
        );
    }

    /**
     * Capture API call breadcrumb
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param int $statusCode Response status code
     * @param float $duration Request duration in seconds
     * @return void
     */
    public static function captureApiCall(
        string $method,
        string $endpoint,
        int $statusCode,
        float $duration = 0
    ): void {
        if (!self::$enabled) {
            return;
        }

        self::addBreadcrumb(
            "API: $method $endpoint",
            'api',
            [
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => round($duration * 1000, 2),
            ],
            $statusCode >= 400 ? 'error' : 'info'
        );
    }

    /**
     * Capture authentication event
     *
     * @param string $event Event type (login, logout, 2fa, failed_login)
     * @param int|null $userId User ID
     * @param array $data Additional data
     * @return void
     */
    public static function captureAuthEvent(string $event, ?int $userId = null, array $data = []): void
    {
        if (!self::$enabled) {
            return;
        }

        $breadcrumbData = array_merge($data, [
            'event' => $event,
            'user_id' => $userId,
        ]);

        self::addBreadcrumb(
            "Auth: $event",
            'auth',
            $breadcrumbData,
            in_array($event, ['failed_login', 'failed_2fa']) ? 'warning' : 'info'
        );
    }

    /**
     * Flush events (useful before script termination)
     *
     * @param int $timeout Timeout in seconds
     * @return bool Success
     */
    public static function flush(int $timeout = 2): bool
    {
        if (!self::$enabled) {
            return false;
        }

        try {
            $client = \Sentry\SentrySdk::getCurrentHub()->getClient();
            if ($client) {
                return $client->flush($timeout);
            }
        } catch (\Throwable $e) {
            error_log('Sentry flush failed: ' . $e->getMessage());
        }

        return false;
    }
}
