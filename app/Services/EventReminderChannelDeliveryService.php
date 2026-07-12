<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventNotificationDeliveryMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Per-channel idempotency and retry state for Event notification producers.
 *
 * The event outbox remains in direct mode for these compatibility paths, while
 * event_notification_deliveries provides a durable row for each requested
 * channel. A retry can therefore resume only the failed channel without
 * recreating a bell or re-sending a successful push/email. Passing the stable
 * email idempotency key improves provider evidence and crash recovery, but it
 * does not by itself promise provider-level exactly-once delivery.
 */
final class EventReminderChannelDeliveryService
{
    private const CHANNELS = ['email', 'in_app', 'push', 'web_push', 'fcm', 'realtime'];
    private const TERMINAL_STATUSES = ['delivered', 'suppressed', 'failed_terminal'];

    public function __construct(private readonly ?EventDomainOutboxService $outbox = null)
    {
    }

    /**
     * @param list<string> $channels
     * @param array<string,mixed> $payload
     * @return array<string,array<string,mixed>> keyed by channel
     */
    public function ensureChannels(
        int $tenantId,
        int $eventId,
        int $userId,
        string $reminderIdentity,
        array $channels,
        array $payload = [],
    ): array {
        $identityHash = hash('sha256', $reminderIdentity);
        $action = 'event.reminder.due';
        $outboxKey = 'event-reminder:' . $tenantId . ':' . $eventId . ':' . $userId . ':' . $identityHash;
        return $this->ensureCanonicalChannels(
            $tenantId,
            $eventId,
            $userId,
            $action,
            $outboxKey,
            $channels,
            array_merge($payload, [
                'recipient_user_id' => $userId,
                'reminder_identity' => $reminderIdentity,
            ]),
        );
    }

    /**
     * Create per-channel delivery evidence for a non-reminder Event action.
     *
     * @param list<string> $channels
     * @param array<string,mixed> $payload
     * @return array<string,array<string,mixed>> keyed by channel
     */
    public function ensureChannelsForAction(
        int $tenantId,
        int $eventId,
        int $userId,
        string $action,
        string $deliveryIdentity,
        array $channels,
        array $payload = [],
    ): array {
        $action = trim($action);
        $deliveryIdentity = trim($deliveryIdentity);
        if (mb_strlen($action) > 80
            || preg_match('/^event(?:\.[a-z0-9_]+)+$/', $action) !== 1) {
            throw new InvalidArgumentException('Event delivery action is invalid.');
        }
        if ($deliveryIdentity === '' || mb_strlen($deliveryIdentity) > 191) {
            throw new InvalidArgumentException('Event delivery identity is invalid.');
        }

        $identityHash = hash('sha256', $deliveryIdentity);
        $actionHash = hash('sha256', $action);
        $outboxKey = 'event-action:' . $tenantId . ':' . $eventId . ':' . $userId
            . ':' . $actionHash . ':' . $identityHash;

        return $this->ensureCanonicalChannels(
            $tenantId,
            $eventId,
            $userId,
            $action,
            $outboxKey,
            $channels,
            array_merge($payload, [
                'recipient_user_id' => $userId,
                'delivery_identity' => $deliveryIdentity,
            ]),
        );
    }

    /**
     * @param list<string> $channels
     * @param array<string,mixed> $payload
     * @return array<string,array<string,mixed>> keyed by channel
     */
    private function ensureCanonicalChannels(
        int $tenantId,
        int $eventId,
        int $userId,
        string $action,
        string $outboxKey,
        array $channels,
        array $payload,
    ): array {
        $channels = array_values(array_unique(array_filter(
            $channels,
            static fn (string $channel): bool => in_array($channel, self::CHANNELS, true),
        )));
        if ($channels === []) {
            return [];
        }

        $outbox = ($this->outbox ?? new EventDomainOutboxService())->record(
            $tenantId,
            $eventId,
            1,
            $action,
            $outboxKey,
            $payload,
            EventNotificationDeliveryMode::Direct,
        );

        $deliveries = [];
        $deliveryAction = $action;
        if ($action === 'event.reminder.due'
            && is_string($payload['reminder_identity'] ?? null)
            && trim((string) $payload['reminder_identity']) !== '') {
            $deliveryAction .= '.' . substr(
                hash('sha256', (string) $payload['reminder_identity']),
                0,
                24,
            );
        }
        foreach ($channels as $channel) {
            $deliveryKey = EventDomainOutboxService::deliveryKey(
                $tenantId,
                $eventId,
                $deliveryAction,
                $userId,
                $channel,
                1,
            );
            $delivery = ($this->outbox ?? new EventDomainOutboxService())->ensureDelivery(
                (int) $outbox['id'],
                $userId,
                $channel,
                $deliveryKey,
            );

            if ((string) $delivery['status'] === 'direct') {
                DB::table('event_notification_deliveries')
                    ->where('id', (int) $delivery['id'])
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'direct')
                    ->update(['status' => 'pending', 'updated_at' => now()]);
                $delivery['status'] = 'pending';
            }

            $deliveries[$channel] = $delivery;
        }

        return $deliveries;
    }

    /** @return array<string,mixed>|null */
    public function claim(int $tenantId, int $deliveryId): ?array
    {
        $now = now();
        $staleMinutes = max(1, (int) config('events.notification_delivery.stale_claim_minutes', 10));
        DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'claimed')
            ->where('claimed_at', '<', $now->copy()->subMinutes($staleMinutes))
            ->update([
                'status' => 'retrying',
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => $now,
                'last_error' => 'event_notification_stale_channel_claim_released',
                'updated_at' => $now,
            ]);

        $token = (string) Str::uuid();
        $claimed = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'retrying'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
            })
            ->update([
                'status' => 'claimed',
                'claim_token' => $token,
                'claimed_at' => $now,
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => null,
                'updated_at' => $now,
            ]);
        if ($claimed !== 1) {
            return null;
        }

        $row = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('claim_token', $token)
            ->first();

        return $row === null ? null : (array) $row;
    }

    public function markDelivered(int $tenantId, int $deliveryId, string $claimToken, ?string $provider = null): bool
    {
        $updated = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'claimed')
            ->where('claim_token', $claimToken)
            ->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'suppressed_at' => null,
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => null,
                'preference_reason' => null,
                'suppression_reason' => null,
                'provider' => $provider,
                'last_error' => null,
                'updated_at' => now(),
            ]) === 1;

        return $updated;
    }

    public function markSuppressed(
        int $tenantId,
        int $deliveryId,
        string $reason,
        ?string $preferenceReason = null,
    ): bool {
        $updated = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            // Never steal an active provider claim. Suppression is decided
            // before claim(), so a claimed row belongs to another worker and
            // must be allowed to finish or become stale.
            ->whereIn('status', ['direct', 'pending', 'retrying', 'suppressed'])
            ->update([
                'status' => 'suppressed',
                'delivered_at' => null,
                'suppressed_at' => now(),
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => null,
                'preference_reason' => $preferenceReason,
                'suppression_reason' => mb_substr($reason, 0, 191),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        if ($updated === 1) {
            return true;
        }

        return DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'suppressed')
            ->exists();
    }

    /** Close a channel whose canonical participant fact has already advanced. */
    public function markSuperseded(int $tenantId, int $deliveryId): bool
    {
        $staleBefore = now()->subMinutes(
            max(1, (int) config('events.notification_delivery.stale_claim_minutes', 10)),
        );
        $updated = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where(static function ($eligible) use ($staleBefore): void {
                $eligible->whereIn('status', ['direct', 'pending', 'retrying', 'failed_terminal'])
                    ->orWhere(static function ($claimed) use ($staleBefore): void {
                        $claimed->where('status', 'claimed')
                            ->where('claimed_at', '<', $staleBefore);
                    });
            })
            ->update([
                'status' => 'suppressed',
                'delivered_at' => null,
                'suppressed_at' => now(),
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => null,
                'dead_lettered_at' => null,
                'preference_reason' => null,
                'suppression_reason' => 'superseded',
                'last_error' => null,
                'updated_at' => now(),
            ]);
        if ($updated === 1) {
            return true;
        }

        return DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['delivered', 'suppressed'])
            ->exists();
    }

    public function markRetrying(int $tenantId, int $deliveryId, string $claimToken, string $error): bool
    {
        $delivery = DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'claimed')
            ->where('claim_token', $claimToken)
            ->first(['attempts']);
        if ($delivery === null) {
            return false;
        }

        $attempts = (int) $delivery->attempts;
        $maxAttempts = max(1, (int) config('events.notification_delivery.max_attempts', 5));
        $terminal = $attempts >= $maxAttempts;
        $baseSeconds = max(1, (int) config('events.notification_delivery.base_retry_seconds', 60));
        $maxSeconds = max($baseSeconds, (int) config('events.notification_delivery.max_retry_seconds', 3600));
        $retrySeconds = min($maxSeconds, $baseSeconds * (2 ** max(0, $attempts - 1)));

        return DB::table('event_notification_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'claimed')
            ->where('claim_token', $claimToken)
            ->update([
                'status' => $terminal ? 'failed_terminal' : 'retrying',
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => $terminal ? null : now()->addSeconds($retrySeconds),
                'dead_lettered_at' => $terminal ? now() : null,
                'last_error' => EventNotificationErrorSanitizer::sanitize($error),
                'updated_at' => now(),
            ]) === 1;
    }

    /**
     * @param array<string,array<string,mixed>> $deliveries
     * @return array<string,string>
     */
    public function statuses(int $tenantId, array $deliveries): array
    {
        $idsByChannel = [];
        foreach ($deliveries as $channel => $delivery) {
            $idsByChannel[$channel] = (int) $delivery['id'];
        }
        if ($idsByChannel === []) {
            return [];
        }

        $rows = DB::table('event_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values($idsByChannel))
            ->get(['id', 'status'])
            ->keyBy('id');

        $statuses = [];
        foreach ($idsByChannel as $channel => $id) {
            $statuses[$channel] = (string) ($rows->get($id)->status ?? 'pending');
        }

        return $statuses;
    }

    /** @param array<string,string> $statuses */
    public function allTerminal(array $statuses): bool
    {
        return $statuses !== [] && collect($statuses)
            ->every(static fn (string $status): bool => in_array($status, self::TERMINAL_STATUSES, true));
    }
}
