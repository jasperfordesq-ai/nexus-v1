<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * WebhookDispatchService
 *
 * Manages outbound webhook registrations and dispatches platform events to
 * external HTTP endpoints. Payloads are signed with HMAC-SHA256 and delivery
 * attempts are logged for auditability and retry.
 *
 * Tables: outbound_webhooks, outbound_webhook_logs
 */
class WebhookDispatchService
{
    /** Maximum consecutive failures before a webhook is auto-disabled */
    private const MAX_FAILURE_COUNT = 10;

    /** HTTP request timeout in seconds */
    private const CURL_TIMEOUT = 10;

    /** Connection timeout in seconds */
    private const CURL_CONNECT_TIMEOUT = 5;

    /**
     * Dispatch an event to all matching active webhooks for the current tenant.
     *
     * For each active webhook whose events JSON array includes $eventType:
     *  - Builds a payload envelope with metadata
     *  - Signs it with HMAC-SHA256 using the webhook's secret
     *  - Sends a synchronous HTTP POST (timeout 10 s)
     *  - Logs the result to outbound_webhook_logs
     *  - On failure, increments failure_count; disables webhook if > 10
     *
     * @param string $eventType Event identifier (e.g. 'exchange.completed')
     * @param array  $payload   Event-specific data
     */
    public static function dispatch(string $eventType, array $payload): void
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT id, url, secret, events
             FROM outbound_webhooks
             WHERE tenant_id = ? AND is_active = 1"
        );
        $stmt->execute([$tenantId]);
        $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($webhooks as $webhook) {
            $subscribedEvents = json_decode($webhook['events'], true);
            if (!is_array($subscribedEvents) || !in_array($eventType, $subscribedEvents, true)) {
                continue;
            }

            self::deliverToWebhook(
                (int) $webhook['id'],
                $webhook['url'],
                $webhook['secret'],
                $eventType,
                $payload
            );
        }
    }

    /**
     * List all webhooks for the current tenant.
     *
     * @return array List of webhook records with events decoded to array
     */
    public static function getWebhooks(): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT id, name, url, events, is_active, last_triggered_at,
                    failure_count, created_at
             FROM outbound_webhooks
             WHERE tenant_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['events'] = json_decode($row['events'], true) ?? [];
        }

        return $rows;
    }

    /**
     * Create a new webhook registration.
     *
     * @param int   $userId User ID of the creator (admin)
     * @param array $data   Required keys: name, url, secret, events (string[])
     * @return array The created webhook record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function createWebhook(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $name   = trim($data['name'] ?? '');
        $url    = trim($data['url'] ?? '');
        $secret = trim($data['secret'] ?? '');
        $events = $data['events'] ?? [];

        if ($name === '') {
            throw new \InvalidArgumentException('Webhook name is required.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('A valid HTTP or HTTPS URL is required.');
        }
        if ($secret === '') {
            throw new \InvalidArgumentException('Webhook secret is required.');
        }
        if (!is_array($events) || empty($events)) {
            throw new \InvalidArgumentException('At least one event type is required.');
        }

        $eventsJson = json_encode(array_values($events));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO outbound_webhooks
             (tenant_id, name, url, secret, events, is_active, failure_count, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)"
        );
        $stmt->execute([$tenantId, $name, $url, $secret, $eventsJson, $userId, $now]);

        $id = (int) $pdo->lastInsertId();

        return [
            'id'                => $id,
            'name'              => $name,
            'url'               => $url,
            'events'            => array_values($events),
            'is_active'         => 1,
            'failure_count'     => 0,
            'last_triggered_at' => null,
            'created_at'        => $now,
        ];
    }

    /**
     * Update an existing webhook.
     *
     * Accepts any subset of: name, url, secret, events, is_active.
     * Re-enabling a webhook resets its failure_count to 0.
     *
     * @param int   $id   Webhook ID (tenant-scoped)
     * @param array $data Fields to update
     * @return bool True if a row was updated
     * @throws \InvalidArgumentException On invalid URL
     */
    public static function updateWebhook(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }

        if (isset($data['url'])) {
            $url = trim($data['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                throw new \InvalidArgumentException('A valid HTTP or HTTPS URL is required.');
            }
            $fields[] = 'url = ?';
            $params[] = $url;
        }

        if (isset($data['secret'])) {
            $fields[] = 'secret = ?';
            $params[] = trim($data['secret']);
        }

        if (isset($data['events']) && is_array($data['events'])) {
            $fields[] = 'events = ?';
            $params[] = json_encode(array_values($data['events']));
        }

        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
            if ($data['is_active']) {
                $fields[] = 'failure_count = 0';
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE outbound_webhooks SET " . implode(', ', $fields)
             . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a webhook and all its delivery logs.
     *
     * @param int $id Webhook ID (tenant-scoped)
     * @return bool True if the webhook was deleted
     */
    public static function deleteWebhook(int $id): bool
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        // Verify webhook belongs to current tenant before deleting logs
        $stmt = $pdo->prepare(
            "DELETE FROM outbound_webhook_logs
             WHERE webhook_id IN (
                 SELECT id FROM outbound_webhooks WHERE id = ? AND tenant_id = ?
             )"
        );
        $stmt->execute([$id, $tenantId]);

        // Delete the webhook itself
        $stmt = $pdo->prepare(
            "DELETE FROM outbound_webhooks WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Send a test event to a webhook and return response details.
     *
     * The test delivery is logged like any other delivery but uses the
     * special event type 'webhook.test'.
     *
     * @param int $id Webhook ID (tenant-scoped)
     * @return array Keys: success, response_code, response_body
     * @throws \RuntimeException If the webhook is not found
     */
    public static function testWebhook(int $id): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT url, secret FROM outbound_webhooks WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        $webhook = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$webhook) {
            throw new \RuntimeException('Webhook not found.');
        }

        $envelope = [
            'event'     => 'webhook.test',
            'tenant_id' => $tenantId,
            'timestamp' => date('c'),
            'payload'   => [
                'message'    => 'This is a test event from Project NEXUS.',
                'webhook_id' => $id,
            ],
        ];

        $jsonPayload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature   = hash_hmac('sha256', $jsonPayload, $webhook['secret']);
        $result      = self::sendHttpPost($webhook['url'], $jsonPayload, $signature);

        self::logDelivery($id, 'webhook.test', $envelope, $result);

        return [
            'success'       => $result['success'],
            'response_code' => $result['response_code'],
            'response_body' => $result['response_body'],
        ];
    }

    /**
     * Get recent delivery logs for a specific webhook.
     *
     * @param int          $webhookId Webhook ID (tenant-scoped)
     * @param array|int    $filters   Filters array with 'limit' and 'cursor' keys, or legacy int limit
     * @return array Log records with payload decoded
     */
    public static function getLogs(int $webhookId, array|int $filters = 50): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        // Support both legacy int $limit and new array $filters
        if (is_array($filters)) {
            $limit  = max(1, min((int) ($filters['limit'] ?? 50), 200));
            $cursor = $filters['cursor'] ?? null;
        } else {
            $limit  = max(1, min($filters, 200));
            $cursor = null;
        }

        $params = [$tenantId, $webhookId];

        $cursorClause = '';
        if ($cursor !== null) {
            $cursorClause = 'AND l.id < ?';
            $params[] = (int) $cursor;
        }

        $params[] = $limit;

        $stmt = $pdo->prepare(
            "SELECT l.id, l.event_type, l.payload, l.response_code, l.response_body,
                    l.status, l.attempted_at
             FROM outbound_webhook_logs l
             JOIN outbound_webhooks w ON l.webhook_id = w.id AND w.tenant_id = ?
             WHERE l.webhook_id = ?
             {$cursorClause}
             ORDER BY l.attempted_at DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['payload'] = json_decode($row['payload'], true);
        }

        return $rows;
    }

    /**
     * Retry failed webhook deliveries from the last 24 hours (cron).
     *
     * Only retries logs whose parent webhook is still active. On success,
     * decrements the webhook's failure_count (minimum 0). Updates each log
     * entry in place.
     *
     * @return int Number of deliveries retried
     */
    public static function retryFailed(): int
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $stmt = $pdo->prepare(
            "SELECT l.id, l.webhook_id, l.event_type, l.payload,
                    w.url, w.secret
             FROM outbound_webhook_logs l
             JOIN outbound_webhooks w ON w.id = l.webhook_id
             WHERE w.tenant_id = ?
               AND l.status = 'failed'
               AND l.attempted_at >= ?
               AND w.is_active = 1
             ORDER BY l.attempted_at ASC
             LIMIT 100"
        );
        $stmt->execute([$tenantId, $cutoff]);
        $failedLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $retriedCount = 0;

        foreach ($failedLogs as $log) {
            $payload = json_decode($log['payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            // Refresh timestamp for retry
            $payload['timestamp'] = date('c');

            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature   = hash_hmac('sha256', $jsonPayload, $log['secret']);
            $result      = self::sendHttpPost($log['url'], $jsonPayload, $signature);

            $status = $result['success'] ? 'success' : 'failed';

            // Update the log entry in place
            $updateStmt = $pdo->prepare(
                "UPDATE outbound_webhook_logs
                 SET response_code = ?, response_body = ?, status = ?, attempted_at = NOW()
                 WHERE id = ?"
            );
            $updateStmt->execute([
                $result['response_code'],
                $result['response_body'],
                $status,
                $log['id'],
            ]);

            // Adjust webhook failure_count on success
            if ($result['success']) {
                $pdo->prepare(
                    "UPDATE outbound_webhooks
                     SET failure_count = GREATEST(failure_count - 1, 0)
                     WHERE id = ? AND tenant_id = ?"
                )->execute([$log['webhook_id'], $tenantId]);
            }

            $retriedCount++;
        }

        return $retriedCount;
    }

    // ========================================================================
    // Private helpers
    // ========================================================================

    /**
     * Deliver an event to a single webhook, log the result, and manage
     * failure tracking.
     */
    private static function deliverToWebhook(
        int $webhookId,
        string $url,
        string $secret,
        string $eventType,
        array $payload
    ): void {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $envelope = [
            'event'     => $eventType,
            'tenant_id' => $tenantId,
            'timestamp' => date('c'),
            'payload'   => $payload,
        ];

        $jsonPayload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature   = hash_hmac('sha256', $jsonPayload, $secret);
        $result      = self::sendHttpPost($url, $jsonPayload, $signature);

        // Persist delivery log
        self::logDelivery($webhookId, $eventType, $envelope, $result);

        // Update webhook metadata
        if ($result['success']) {
            $pdo->prepare(
                "UPDATE outbound_webhooks
                 SET last_triggered_at = NOW(), failure_count = 0
                 WHERE id = ? AND tenant_id = ?"
            )->execute([$webhookId, $tenantId]);
        } else {
            $pdo->prepare(
                "UPDATE outbound_webhooks
                 SET last_triggered_at = NOW(), failure_count = failure_count + 1
                 WHERE id = ? AND tenant_id = ?"
            )->execute([$webhookId, $tenantId]);

            // Auto-disable after too many consecutive failures
            $pdo->prepare(
                "UPDATE outbound_webhooks
                 SET is_active = 0
                 WHERE id = ? AND tenant_id = ? AND failure_count > ?"
            )->execute([$webhookId, $tenantId, self::MAX_FAILURE_COUNT]);
        }
    }

    /**
     * Send an HTTP POST request via cURL.
     *
     * @return array{success: bool, response_code: int|null, response_body: string|null}
     */
    private static function sendHttpPost(string $url, string $jsonPayload, string $signature): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Nexus-Signature: ' . $signature,
                'User-Agent: ProjectNEXUS-Webhooks/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseBody = curl_exec($ch);
        $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success'       => false,
                'response_code' => null,
                'response_body' => 'cURL error: ' . $curlError,
            ];
        }

        // Truncate response body for storage (first 4 KB)
        $storedBody = mb_substr((string) $responseBody, 0, 4096);

        return [
            'success'       => $responseCode >= 200 && $responseCode < 300,
            'response_code' => $responseCode,
            'response_body' => $storedBody,
        ];
    }

    /**
     * Insert a delivery log record into outbound_webhook_logs.
     */
    private static function logDelivery(int $webhookId, string $eventType, array $envelope, array $result): void
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $status = $result['success'] ? 'success' : 'failed';

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO outbound_webhook_logs
                 (tenant_id, webhook_id, event_type, payload, response_code,
                  response_body, status, attempted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $tenantId,
                $webhookId,
                $eventType,
                json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $result['response_code'],
                $result['response_body'],
                $status,
            ]);
        } catch (\PDOException $e) {
            error_log('WebhookDispatchService::logDelivery failed: ' . $e->getMessage());
        }
    }
}
