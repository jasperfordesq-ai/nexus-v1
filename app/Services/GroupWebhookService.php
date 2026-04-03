<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;

/**
 * GroupWebhookService — Fire HTTP callbacks on group events.
 *
 * Supports signed payloads (HMAC-SHA256) for webhook verification.
 */
class GroupWebhookService
{
    const EVENT_MEMBER_JOINED = 'member.joined';
    const EVENT_MEMBER_LEFT = 'member.left';
    const EVENT_DISCUSSION_CREATED = 'discussion.created';
    const EVENT_POST_CREATED = 'post.created';
    const EVENT_GROUP_UPDATED = 'group.updated';
    const EVENT_MILESTONE_REACHED = 'milestone.reached';
    const EVENT_FILE_UPLOADED = 'file.uploaded';

    /**
     * Register a webhook for a group.
     */
    public static function register(int $groupId, string $url, array $events, ?string $secret = null): ?int
    {
        $tenantId = TenantContext::getId();

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return DB::table('group_webhooks')->insertGetId([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'url' => $url,
            'events' => json_encode($events),
            'secret' => $secret,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Fire webhooks for an event.
     */
    public static function fire(int $groupId, string $event, array $payload = []): void
    {
        $tenantId = TenantContext::getId();

        $webhooks = DB::table('group_webhooks')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook->events, true) ?: [];
            if (!in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }

            $body = [
                'event' => $event,
                'group_id' => $groupId,
                'tenant_id' => $tenantId,
                'timestamp' => now()->toIso8601String(),
                'data' => $payload,
            ];

            $headers = ['Content-Type' => 'application/json'];

            // Sign payload if secret exists
            if ($webhook->secret) {
                $signature = hash_hmac('sha256', json_encode($body), $webhook->secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            try {
                Http::timeout(10)->withHeaders($headers)->post($webhook->url, $body);

                DB::table('group_webhooks')
                    ->where('id', $webhook->id)
                    ->update(['last_fired_at' => now(), 'failure_count' => 0]);
            } catch (\Exception $e) {
                Log::warning('GroupWebhook: Failed to fire', [
                    'webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                    'error' => $e->getMessage(),
                ]);

                DB::table('group_webhooks')
                    ->where('id', $webhook->id)
                    ->increment('failure_count');

                // Auto-disable after 10 consecutive failures
                if (($webhook->failure_count ?? 0) >= 9) {
                    DB::table('group_webhooks')
                        ->where('id', $webhook->id)
                        ->update(['is_active' => false]);
                }
            }
        }
    }

    /**
     * List webhooks for a group.
     */
    public static function list(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_webhooks')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->select('id', 'url', 'events', 'is_active', 'last_fired_at', 'failure_count', 'created_at')
            ->get()
            ->map(function ($row) {
                $row->events = json_decode($row->events, true);
                return (array) $row;
            })
            ->toArray();
    }

    /**
     * Delete a webhook.
     */
    public static function delete(int $webhookId): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_webhooks')
            ->where('id', $webhookId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Toggle webhook active status.
     */
    public static function toggle(int $webhookId, bool $isActive): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_webhooks')
            ->where('id', $webhookId)
            ->where('tenant_id', $tenantId)
            ->update(['is_active' => $isActive, 'updated_at' => now()]) > 0;
    }
}
