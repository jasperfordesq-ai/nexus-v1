<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventNotificationDeliveryMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Transaction-local writer for Event domain and notification delivery claims.
 *
 * Call record() inside the same database transaction as the domain mutation.
 * Unique keys make retries return the original row without creating duplicates.
 */
final class EventDomainOutboxService
{
    private const CHANNELS = [
        'email',
        'push',
        'in_app',
        'web_push',
        'fcm',
        'realtime',
        'sms',
        'webhook',
    ];

    /** @return array<string,mixed> */
    public function record(
        int $tenantId,
        int $eventId,
        int $aggregateVersion,
        string $action,
        string $idempotencyKey,
        array $payload,
        ?EventNotificationDeliveryMode $mode = null,
        ?string $aggregateStream = null,
    ): array {
        if ($tenantId <= 0 || $eventId <= 0 || $aggregateVersion <= 0) {
            throw new InvalidArgumentException('Tenant, event, and aggregate version must be positive.');
        }

        $action = trim($action);
        $idempotencyKey = trim($idempotencyKey);
        if ($action === '' || mb_strlen($action) > 80) {
            throw new InvalidArgumentException('Event outbox action is invalid.');
        }
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            throw new InvalidArgumentException('Event outbox idempotency key is invalid.');
        }
        $aggregateStream = trim($aggregateStream ?? self::deriveAggregateStream(
            $eventId,
            $action,
            $payload,
        ));
        if ($aggregateStream === '' || mb_strlen($aggregateStream) > 191) {
            throw new InvalidArgumentException('Event outbox aggregate stream is invalid.');
        }
        if (!DB::table('events')->where('id', $eventId)->where('tenant_id', $tenantId)->exists()) {
            throw new InvalidArgumentException('Event does not belong to the supplied tenant.');
        }

        $mode ??= EventNotificationDeliveryModeResolver::resolve($tenantId);
        $now = now();
        DB::table('event_domain_outbox')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'aggregate_stream' => $aggregateStream,
            'aggregate_version' => $aggregateVersion,
            'action' => $action,
            'idempotency_key' => $idempotencyKey,
            'production_mode' => $mode->value,
            'status' => $mode->initialOutboxStatus(),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'available_at' => $mode->isClaimable() ? $now : null,
            'processed_at' => $mode === EventNotificationDeliveryMode::Direct ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('event_domain_outbox')
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($row === null) {
            throw new LogicException('Event outbox row could not be read after insert.');
        }
        $storedStream = (string) $row->aggregate_stream;
        if ((int) $row->event_id !== $eventId
            || ($storedStream !== $aggregateStream && $storedStream !== 'event')
            || (int) $row->aggregate_version !== $aggregateVersion
            || (string) $row->action !== $action) {
            throw new LogicException('Event outbox idempotency key was reused for a different mutation.');
        }

        return (array) $row;
    }

    /**
     * Keep independent aggregate versions from blocking each other. Event-wide
     * lifecycle/domain actions remain ordered together; participant records use
     * their immutable canonical identity where one is available.
     *
     * @param array<string,mixed> $payload
     */
    private static function deriveAggregateStream(int $eventId, string $action, array $payload): string
    {
        $prefix = "event:{$eventId}:";

        if (str_starts_with($action, 'event.lifecycle.')) {
            return $prefix . 'lifecycle';
        }
        if (str_starts_with($action, 'event.staff_role.')) {
            return $prefix . 'staff:' . self::streamIdentity($payload, ['assignment_id', 'user_id']);
        }
        if (str_starts_with($action, 'event.registration.')) {
            return $prefix . 'registration:' . self::streamIdentity($payload, ['registration_id', 'user_id']);
        }
        if (str_starts_with($action, 'event.waitlist.')) {
            return $prefix . 'waitlist:' . self::streamIdentity($payload, ['waitlist_entry_id', 'entry_id', 'user_id']);
        }
        if (str_starts_with($action, 'event.attendance.')) {
            return $prefix . 'attendance:' . self::streamIdentity($payload, ['attendance_id', 'attendee_user_id']);
        }
        if (str_starts_with($action, 'event.reminder.')) {
            $recipient = self::streamIdentity($payload, ['recipient_user_id']);
            $identity = (string) ($payload['reminder_identity'] ?? $action);

            return $prefix . 'reminder:' . $recipient . ':' . substr(hash('sha256', $identity), 0, 24);
        }

        return $prefix . 'domain';
    }

    /** @param array<string,mixed> $payload @param list<string> $keys */
    private static function streamIdentity(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if ((is_int($value) || is_string($value)) && trim((string) $value) !== '') {
                return substr(hash('sha256', $key . ':' . trim((string) $value)), 0, 24);
            }
        }

        return 'event';
    }

    /** @return array<string,mixed> */
    public function ensureDelivery(
        int $outboxId,
        int $recipientUserId,
        string $channel,
        string $deliveryKey,
        bool $allowIneligibleForSuppression = false,
    ): array {
        $channel = trim($channel);
        $deliveryKey = trim($deliveryKey);
        if ($recipientUserId <= 0 || !in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Event notification delivery recipient or channel is invalid.');
        }
        if ($deliveryKey === '' || mb_strlen($deliveryKey) > 191) {
            throw new InvalidArgumentException('Event notification delivery key is invalid.');
        }

        $outbox = DB::table('event_domain_outbox')->where('id', $outboxId)->first();
        if ($outbox === null) {
            throw new InvalidArgumentException('Event outbox row was not found.');
        }
        $recipientQuery = DB::table('users')
            ->where('id', $recipientUserId)
            ->where('tenant_id', (int) $outbox->tenant_id);
        if (! $allowIneligibleForSuppression) {
            $recipientQuery->where('status', 'active')->whereNull('deleted_at');
        }
        $recipientExists = $recipientQuery->exists();
        if (!$recipientExists) {
            throw new InvalidArgumentException('Event delivery recipient does not belong to the outbox tenant.');
        }

        $initialStatus = match ((string) $outbox->production_mode) {
            EventNotificationDeliveryMode::ShadowOutbox->value => 'shadow',
            EventNotificationDeliveryMode::Direct->value => 'direct',
            default => 'pending',
        };
        $now = now();
        DB::table('event_notification_deliveries')->insertOrIgnore([
            'tenant_id' => (int) $outbox->tenant_id,
            'outbox_id' => $outboxId,
            'recipient_user_id' => $recipientUserId,
            'external_recipient_hash' => null,
            'channel' => $channel,
            'delivery_key' => $deliveryKey,
            'status' => $initialStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('event_notification_deliveries')
            ->where('tenant_id', (int) $outbox->tenant_id)
            ->where('delivery_key', $deliveryKey)
            ->first();
        if ($row === null) {
            throw new LogicException('Event delivery row could not be read after insert.');
        }
        if ((int) $row->outbox_id !== $outboxId
            || (int) $row->recipient_user_id !== $recipientUserId
            || $row->external_recipient_hash !== null
            || (string) $row->channel !== $channel) {
            throw new LogicException('Event delivery key was reused for a different recipient or channel.');
        }

        return (array) $row;
    }

    /**
     * Create delivery evidence for a privacy-hashed external email recipient.
     * Internal user eligibility and preference semantics remain in ensureDelivery().
     *
     * @return array<string,mixed>
     */
    public function ensureExternalDelivery(
        int $outboxId,
        string $externalRecipientHash,
        string $channel,
        string $deliveryKey,
    ): array {
        $externalRecipientHash = strtolower(trim($externalRecipientHash));
        $channel = trim($channel);
        $deliveryKey = trim($deliveryKey);
        if (preg_match('/^[0-9a-f]{64}$/', $externalRecipientHash) !== 1
            || $channel !== 'email') {
            throw new InvalidArgumentException('External Event delivery recipient or channel is invalid.');
        }
        if ($deliveryKey === '' || mb_strlen($deliveryKey) > 191) {
            throw new InvalidArgumentException('Event delivery key is invalid.');
        }
        $outbox = DB::table('event_domain_outbox')->where('id', $outboxId)->first();
        if ($outbox === null
            || (string) $outbox->production_mode
                !== EventNotificationDeliveryMode::OutboxAuthoritative->value) {
            throw new InvalidArgumentException('Authoritative Event outbox row was not found.');
        }
        $now = now();
        DB::table('event_notification_deliveries')->insertOrIgnore([
            'tenant_id' => (int) $outbox->tenant_id,
            'outbox_id' => $outboxId,
            'recipient_user_id' => null,
            'external_recipient_hash' => $externalRecipientHash,
            'channel' => $channel,
            'delivery_key' => $deliveryKey,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $row = DB::table('event_notification_deliveries')
            ->where('tenant_id', (int) $outbox->tenant_id)
            ->where('delivery_key', $deliveryKey)
            ->first();
        if ($row === null) {
            throw new LogicException('External Event delivery row could not be read after insert.');
        }
        if ((int) $row->outbox_id !== $outboxId
            || $row->recipient_user_id !== null
            || ! is_string($row->external_recipient_hash)
            || ! hash_equals($externalRecipientHash, $row->external_recipient_hash)
            || (string) $row->channel !== $channel) {
            throw new LogicException('Event delivery key was reused for a different external recipient.');
        }

        return (array) $row;
    }

    public static function deliveryKey(
        int $tenantId,
        int $eventId,
        string $action,
        int $recipientUserId,
        string $channel,
        int $aggregateVersion,
    ): string {
        return hash('sha256', implode('|', [
            'event-delivery-v1',
            $tenantId,
            $eventId,
            trim($action),
            $recipientUserId,
            trim($channel),
            $aggregateVersion,
        ]));
    }

    public static function externalDeliveryKey(
        int $tenantId,
        int $eventId,
        string $action,
        string $externalRecipientHash,
        string $channel,
        int $aggregateVersion,
    ): string {
        $externalRecipientHash = strtolower(trim($externalRecipientHash));
        if ($tenantId <= 0
            || $eventId <= 0
            || $aggregateVersion <= 0
            || preg_match('/^[0-9a-f]{64}$/', $externalRecipientHash) !== 1) {
            throw new InvalidArgumentException('External Event delivery identity is invalid.');
        }

        return hash('sha256', implode('|', [
            'event-external-delivery-v1',
            $tenantId,
            $eventId,
            trim($action),
            $externalRecipientHash,
            trim($channel),
            $aggregateVersion,
        ]));
    }
}
