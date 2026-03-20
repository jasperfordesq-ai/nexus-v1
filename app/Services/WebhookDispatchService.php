<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WebhookDispatchService — Outbound webhook management and dispatch.
 *
 * Manages CRUD for outbound webhooks and dispatches events to registered URLs.
 * All queries are tenant-scoped via TenantContext::getId().
 */
class WebhookDispatchService
{
    public function __construct()
    {
    }

    /**
     * Dispatch an event to all matching webhooks.
     */
    public static function dispatch(string $eventType, array $payload): void
    {
        $tenantId = TenantContext::getId();

        try {
            $webhooks = DB::table('outbound_webhooks')
                ->where('tenant_id', $tenantId)
                ->where('is_active', 1)
                ->get();

            foreach ($webhooks as $webhook) {
                $events = json_decode($webhook->events ?? '[]', true);
                if (!is_array($events) || !in_array($eventType, $events, true)) {
                    continue;
                }

                try {
                    $body = json_encode([
                        'event' => $eventType,
                        'timestamp' => now()->toIso8601String(),
                        'tenant_id' => $tenantId,
                        'data' => $payload,
                    ]);

                    $signature = hash_hmac('sha256', $body, $webhook->secret ?? '');

                    $ch = curl_init($webhook->url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $body,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'X-Webhook-Signature: ' . $signature,
                            'X-Webhook-Event: ' . $eventType,
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    // Log the delivery attempt
                    DB::table('outbound_webhook_logs')->insert([
                        'tenant_id' => $tenantId,
                        'webhook_id' => $webhook->id,
                        'event_type' => $eventType,
                        'payload' => $body,
                        'response_code' => $httpCode,
                        'response_body' => substr($response ?: $error, 0, 1000),
                        'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed',
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    DB::table('outbound_webhook_logs')->insert([
                        'tenant_id' => $tenantId,
                        'webhook_id' => $webhook->id,
                        'event_type' => $eventType,
                        'payload' => json_encode($payload),
                        'response_code' => 0,
                        'response_body' => $e->getMessage(),
                        'status' => 'failed',
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('WebhookDispatchService::dispatch error: ' . $e->getMessage());
        }
    }

    /**
     * Get all webhooks for the current tenant.
     */
    public static function getWebhooks(): array
    {
        $tenantId = TenantContext::getId();

        try {
            return DB::table('outbound_webhooks')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($row) {
                    $row = (array) $row;
                    $row['events'] = json_decode($row['events'] ?? '[]', true);
                    return $row;
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('WebhookDispatchService::getWebhooks error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new webhook.
     *
     * @throws \InvalidArgumentException
     */
    public static function createWebhook(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        // Validate
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Webhook name is required.');
        }
        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('A valid webhook URL is required.');
        }
        if (empty($data['secret'])) {
            throw new \InvalidArgumentException('Webhook secret is required.');
        }
        if (empty($data['events']) || !is_array($data['events'])) {
            throw new \InvalidArgumentException('At least one event type is required.');
        }

        $id = DB::table('outbound_webhooks')->insertGetId([
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $data['secret'],
            'events' => json_encode($data['events']),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => (int) $id,
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'is_active' => 1,
        ];
    }

    /**
     * Update an existing webhook.
     *
     * @throws \InvalidArgumentException
     */
    public static function updateWebhook(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        // Validate URL if provided
        if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('A valid webhook URL is required.');
        }

        $updateData = [];
        $allowedFields = ['name', 'url', 'secret', 'is_active'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['events']) && is_array($data['events'])) {
            $updateData['events'] = json_encode($data['events']);
        }

        if (empty($updateData)) {
            return false;
        }

        $updateData['updated_at'] = now();

        $affected = DB::table('outbound_webhooks')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updateData);

        return $affected > 0;
    }

    /**
     * Delete a webhook.
     */
    public static function deleteWebhook(int $id): bool
    {
        $tenantId = TenantContext::getId();

        try {
            // Delete logs first
            DB::table('outbound_webhook_logs')
                ->where('webhook_id', $id)
                ->where('tenant_id', $tenantId)
                ->delete();

            $affected = DB::table('outbound_webhooks')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->delete();

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('WebhookDispatchService::deleteWebhook error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test a webhook by sending a test event.
     *
     * @throws \RuntimeException
     */
    public static function testWebhook(int $id): array
    {
        $tenantId = TenantContext::getId();

        $webhook = DB::table('outbound_webhooks')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$webhook) {
            throw new \RuntimeException("Webhook not found (id={$id}).");
        }

        $body = json_encode([
            'event' => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'tenant_id' => $tenantId,
            'data' => ['message' => 'This is a test webhook delivery.'],
        ]);

        $signature = hash_hmac('sha256', $body, $webhook->secret ?? '');

        $ch = curl_init($webhook->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: webhook.test',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response ?: $error,
        ];
    }

    /**
     * Get delivery logs for a webhook.
     */
    public static function getLogs(int $id, array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        try {
            $query = DB::table('outbound_webhook_logs')
                ->where('webhook_id', $id)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at');

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $limit = (int) ($filters['limit'] ?? 50);
            $query->limit($limit);

            return $query->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        } catch (\Exception $e) {
            Log::error('WebhookDispatchService::getLogs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retry failed webhook deliveries.
     *
     * @return int Number of retried deliveries
     */
    public static function retryFailed(): int
    {
        $tenantId = TenantContext::getId();
        $retried = 0;

        try {
            $failedLogs = DB::table('outbound_webhook_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at')
                ->limit(50)
                ->get();

            foreach ($failedLogs as $log) {
                $webhook = DB::table('outbound_webhooks')
                    ->where('id', $log->webhook_id)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', 1)
                    ->first();

                if (!$webhook) {
                    continue;
                }

                try {
                    $signature = hash_hmac('sha256', $log->payload, $webhook->secret ?? '');

                    $ch = curl_init($webhook->url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $log->payload,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'X-Webhook-Signature: ' . $signature,
                            'X-Webhook-Event: ' . ($log->event_type ?? 'retry'),
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $newStatus = ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed';

                    DB::table('outbound_webhook_logs')
                        ->where('id', $log->id)
                        ->update([
                            'status' => $newStatus,
                            'response_code' => $httpCode,
                            'response_body' => substr($response ?: '', 0, 1000),
                        ]);

                    $retried++;
                } catch (\Exception $e) {
                    // Skip failed retries
                }
            }
        } catch (\Exception $e) {
            Log::error('WebhookDispatchService::retryFailed error: ' . $e->getMessage());
        }

        return $retried;
    }
}
