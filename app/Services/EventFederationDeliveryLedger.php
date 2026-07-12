<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationDeliveryStatus;
use App\Support\Events\EventFederationPayloadContract;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Durable, per-partner delivery state for event.federation.* facts.
 *
 * This ledger is intentionally independent from event_domain_outbox and
 * event_notification_deliveries. A notification parent or consumer state can
 * neither acknowledge nor dead-letter a federation delivery.
 */
final class EventFederationDeliveryLedger
{
    public const MAX_ATTEMPTS = 5;
    public const STALE_CLAIM_MINUTES = 10;
    public const MAX_STALE_RELEASE_BATCH = 500;
    private const BASE_RETRY_SECONDS = 60;
    private const MAX_RETRY_SECONDS = 3600;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function enqueue(
        int $tenantId,
        int $eventId,
        int $externalPartnerId,
        array $payload,
        ?DateTimeInterface $availableAt = null,
    ): array {
        if ($tenantId <= 0 || $eventId <= 0 || $externalPartnerId <= 0) {
            throw new InvalidArgumentException('event_federation_delivery_scope_invalid');
        }
        EventFederationPayloadContract::assertValid($payload, $tenantId, $eventId);
        $action = EventFederationAction::from((string) $payload['action']);

        $idempotencyKey = EventFederationPayloadContract::deliveryIdempotencyKey(
            $tenantId,
            $eventId,
            $externalPartnerId,
            $payload,
        );
        $payloadHash = EventFederationPayloadContract::hash($payload);
        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $externalPartnerId,
            $payload,
            $availableAt,
            $action,
            $idempotencyKey,
            $payloadHash,
        ): array {
            $this->assertPartner($tenantId, $externalPartnerId, $eventId, $action);
            $eventTenantId = DB::table('events')
                ->where('id', $eventId)
                ->lockForUpdate()
                ->value('tenant_id');
            if ($eventTenantId !== null && (int) $eventTenantId !== $tenantId) {
                throw new InvalidArgumentException('event_federation_delivery_event_tenant_mismatch');
            }
            if ($action === EventFederationAction::Upsert && $eventTenantId === null) {
                throw new InvalidArgumentException('event_federation_delivery_event_missing');
            }

            $aggregateVersion = (int) $payload['event_aggregate_version'];
            $calendarVersion = (int) $payload['event_calendar_version'];
            $stream = DB::table('event_federation_deliveries')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('external_partner_id', $externalPartnerId)
                ->orderBy('event_aggregate_version')
                ->orderBy('event_calendar_version')
                ->lockForUpdate()
                ->get(['event_aggregate_version', 'event_calendar_version', 'status']);
            foreach ($stream as $existing) {
                $aggregateComparison = $aggregateVersion <=> (int) $existing->event_aggregate_version;
                $calendarComparison = $calendarVersion <=> (int) $existing->event_calendar_version;
                $hasLowerAxis = $aggregateComparison < 0 || $calendarComparison < 0;
                $hasHigherAxis = $aggregateComparison > 0 || $calendarComparison > 0;
                if ($hasLowerAxis && $hasHigherAxis) {
                    throw new LogicException('event_federation_delivery_version_vector_conflict');
                }
                if ($hasLowerAxis
                    && ! in_array((string) $existing->status, [
                        EventFederationDeliveryStatus::Pending->value,
                        EventFederationDeliveryStatus::Retry->value,
                    ], true)) {
                    throw new LogicException('event_federation_delivery_stale_after_dispatch');
                }
            }

            $now = now();
            DB::table('event_federation_deliveries')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'external_partner_id' => $externalPartnerId,
                'payload_schema_version' => (int) $payload['payload_schema_version'],
                'event_aggregate_version' => $aggregateVersion,
                'event_calendar_version' => $calendarVersion,
                'action' => $action->value,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'payload' => EventFederationPayloadContract::canonicalJson($payload),
                'status' => EventFederationDeliveryStatus::Pending->value,
                'attempts' => 0,
                'available_at' => $availableAt ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table('event_federation_deliveries')
                ->where('tenant_id', $tenantId)
                ->where('external_partner_id', $externalPartnerId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($row === null) {
                throw new LogicException('event_federation_delivery_version_conflict');
            }
            if ((int) $row->event_id !== $eventId
                || (int) $row->payload_schema_version !== (int) $payload['payload_schema_version']
                || (int) $row->event_aggregate_version !== $aggregateVersion
                || (int) $row->event_calendar_version !== $calendarVersion
                || (string) $row->action !== $action->value
                || ! hash_equals((string) $row->payload_hash, $payloadHash)) {
                throw new LogicException('event_federation_delivery_idempotency_conflict');
            }

            return (array) $row;
        }, 3);
    }

    /**
     * Claim the earliest outstanding fact for each event/partner stream. A
     * dead-letter is an ordering barrier until an operator deliberately deals
     * with it; later retractions must never overtake an unseen upsert.
     *
     * @param list<EventFederationAction|string>|null $actions
     * @return list<array<string,mixed>>
     */
    public function claimBatch(
        int $limit = 50,
        ?int $tenantId = null,
        ?int $externalPartnerId = null,
        ?array $actions = null,
    ): array {
        $limit = max(1, min($limit, 100));
        if (($tenantId !== null && $tenantId <= 0)
            || ($externalPartnerId !== null && $externalPartnerId <= 0)) {
            return [];
        }
        $actionValues = null;
        if ($actions !== null) {
            $actionValues = [];
            foreach ($actions as $action) {
                $action = $action instanceof EventFederationAction ? $action : EventFederationAction::tryFrom((string) $action);
                if ($action === null) {
                    return [];
                }
                $actionValues[$action->value] = $action->value;
            }
            if ($actionValues === []) {
                return [];
            }
            $actionValues = array_values($actionValues);
        }
        $token = (string) Str::uuid();

        return DB::transaction(function () use (
            $limit,
            $tenantId,
            $externalPartnerId,
            $actionValues,
            $token,
        ): array {
            $now = now();
            $candidate = DB::table('event_federation_deliveries as candidate')
                ->whereIn('candidate.status', [
                    EventFederationDeliveryStatus::Pending->value,
                    EventFederationDeliveryStatus::Retry->value,
                ])
                ->where('candidate.attempts', '<', self::MAX_ATTEMPTS)
                ->when($tenantId !== null, static fn (Builder $query) => $query->where('candidate.tenant_id', $tenantId))
                ->when(
                    $externalPartnerId !== null,
                    static fn (Builder $query) => $query->where('candidate.external_partner_id', $externalPartnerId),
                )
                ->when(
                    $actionValues !== null,
                    static fn (Builder $query) => $query->whereIn('candidate.action', $actionValues),
                )
                ->where(static function (Builder $query) use ($now): void {
                    $query->whereNull('candidate.available_at')
                        ->orWhere('candidate.available_at', '<=', $now);
                })
                ->where(static function (Builder $query) use ($now): void {
                    $query->whereNull('candidate.next_attempt_at')
                        ->orWhere('candidate.next_attempt_at', '<=', $now);
                })
                ->whereNotExists(static function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('event_federation_deliveries as earlier')
                        ->whereColumn('earlier.tenant_id', 'candidate.tenant_id')
                        ->whereColumn('earlier.event_id', 'candidate.event_id')
                        ->whereColumn('earlier.external_partner_id', 'candidate.external_partner_id')
                        ->whereColumn('earlier.event_aggregate_version', '<=', 'candidate.event_aggregate_version')
                        ->whereColumn('earlier.event_calendar_version', '<=', 'candidate.event_calendar_version')
                        ->where(static function (Builder $version): void {
                            $version->whereColumn(
                                'earlier.event_aggregate_version',
                                '<',
                                'candidate.event_aggregate_version',
                            )->orWhereColumn(
                                'earlier.event_calendar_version',
                                '<',
                                'candidate.event_calendar_version',
                            );
                        })
                        ->whereIn('earlier.status', [
                            EventFederationDeliveryStatus::Pending->value,
                            EventFederationDeliveryStatus::Retry->value,
                            EventFederationDeliveryStatus::Processing->value,
                            EventFederationDeliveryStatus::DeadLetter->value,
                        ]);
                })
                ->orderBy('candidate.event_aggregate_version')
                ->orderBy('candidate.event_calendar_version')
                ->orderBy('candidate.id');

            $ids = $candidate->limit($limit)->lockForUpdate()->pluck('candidate.id')->all();
            if ($ids === []) {
                return [];
            }

            DB::table('event_federation_deliveries')
                ->whereIn('id', $ids)
                ->whereIn('status', [
                    EventFederationDeliveryStatus::Pending->value,
                    EventFederationDeliveryStatus::Retry->value,
                ])
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->update([
                    'status' => EventFederationDeliveryStatus::Processing->value,
                    'claim_token' => $token,
                    'claimed_at' => $now,
                    'last_attempt_at' => $now,
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => $now,
                ]);

            return DB::table('event_federation_deliveries')
                ->where('claim_token', $token)
                ->where('status', EventFederationDeliveryStatus::Processing->value)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        }, 3);
    }

    public function markDelivered(int $tenantId, int $deliveryId, string $claimToken): bool
    {
        if ($tenantId <= 0 || $deliveryId <= 0 || trim($claimToken) === '') {
            return false;
        }

        return DB::table('event_federation_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->where('status', EventFederationDeliveryStatus::Processing->value)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => EventFederationDeliveryStatus::Delivered->value,
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => null,
                'delivered_at' => now(),
                'last_error_code' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    public function markFailed(
        int $tenantId,
        int $deliveryId,
        string $claimToken,
        string $errorCode,
        string $error,
    ): bool {
        if ($tenantId <= 0 || $deliveryId <= 0 || trim($claimToken) === '') {
            return false;
        }

        return DB::transaction(function () use (
            $tenantId,
            $deliveryId,
            $claimToken,
            $errorCode,
            $error,
        ): bool {
            $row = DB::table('event_federation_deliveries')
                ->where('id', $deliveryId)
                ->where('tenant_id', $tenantId)
                ->where('status', EventFederationDeliveryStatus::Processing->value)
                ->where('claim_token', $claimToken)
                ->lockForUpdate()
                ->first(['attempts']);
            if ($row === null) {
                return false;
            }

            $attempts = (int) $row->attempts;
            $terminal = $attempts >= self::MAX_ATTEMPTS;
            $retrySeconds = min(
                self::MAX_RETRY_SECONDS,
                self::BASE_RETRY_SECONDS * (2 ** max(0, $attempts - 1)),
            );
            $now = now();

            return DB::table('event_federation_deliveries')
                ->where('id', $deliveryId)
                ->where('tenant_id', $tenantId)
                ->where('status', EventFederationDeliveryStatus::Processing->value)
                ->where('claim_token', $claimToken)
                ->update([
                    'status' => $terminal
                        ? EventFederationDeliveryStatus::DeadLetter->value
                        : EventFederationDeliveryStatus::Retry->value,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'next_attempt_at' => $terminal ? null : $now->copy()->addSeconds($retrySeconds),
                    'dead_lettered_at' => $terminal ? $now : null,
                    'last_error_code' => $this->sanitizeErrorCode($errorCode),
                    'last_error' => $this->sanitizeFailure($error),
                    'updated_at' => $now,
                ]) === 1;
        }, 3);
    }

    public function releaseStaleClaims(?int $tenantId = null, int $limit = 100): int
    {
        if ($tenantId !== null && $tenantId <= 0) {
            return 0;
        }
        $limit = max(1, min($limit, self::MAX_STALE_RELEASE_BATCH));

        return DB::transaction(function () use ($tenantId, $limit): int {
            $rows = DB::table('event_federation_deliveries')
                ->where('status', EventFederationDeliveryStatus::Processing->value)
                ->where('claimed_at', '<', now()->subMinutes(self::STALE_CLAIM_MINUTES))
                ->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId))
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id', 'tenant_id', 'claim_token', 'attempts']);
            $released = 0;
            foreach ($rows as $row) {
                $terminal = (int) $row->attempts >= self::MAX_ATTEMPTS;
                $updated = DB::table('event_federation_deliveries')
                    ->where('id', (int) $row->id)
                    ->where('tenant_id', (int) $row->tenant_id)
                    ->where('status', EventFederationDeliveryStatus::Processing->value)
                    ->where('claim_token', (string) $row->claim_token)
                    ->update([
                        'status' => $terminal
                            ? EventFederationDeliveryStatus::DeadLetter->value
                            : EventFederationDeliveryStatus::Retry->value,
                        'claim_token' => null,
                        'claimed_at' => null,
                        'next_attempt_at' => $terminal ? null : now(),
                        'dead_lettered_at' => $terminal ? now() : null,
                        'last_error_code' => 'STALE_CLAIM_RELEASED',
                        'last_error' => 'event_federation_stale_claim_released',
                        'updated_at' => now(),
                    ]);
                $released += $updated;
            }

            return $released;
        }, 3);
    }

    private function assertPartner(
        int $tenantId,
        int $externalPartnerId,
        int $eventId,
        EventFederationAction $action,
    ): void
    {
        $partner = DB::table('federation_external_partners')
            ->where('id', $externalPartnerId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first(['status', 'allow_events']);
        if ($partner === null) {
            throw new InvalidArgumentException('event_federation_delivery_partner_unavailable');
        }
        if ($action === EventFederationAction::Upsert) {
            if ((string) $partner->status !== 'active' || ! (bool) $partner->allow_events) {
                throw new InvalidArgumentException('event_federation_delivery_partner_unavailable');
            }

            return;
        }

        $hasPriorEvidence = DB::table('event_federation_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('external_partner_id', $externalPartnerId)
            ->lockForUpdate()
            ->first(['id']) !== null;
        if (((string) $partner->status !== 'active' || ! (bool) $partner->allow_events)
            && ! $hasPriorEvidence) {
            throw new InvalidArgumentException('event_federation_delivery_retraction_evidence_missing');
        }
    }

    private function sanitizeErrorCode(string $errorCode): string
    {
        $errorCode = strtoupper(trim($errorCode));
        $errorCode = (string) preg_replace('/[^A-Z0-9_\-]/', '_', $errorCode);

        return substr($errorCode !== '' ? $errorCode : 'DELIVERY_FAILED', 0, 64);
    }

    private function sanitizeFailure(string $error): string
    {
        $error = FederationLogRedactor::redactText($error) ?? '';
        $error = (string) preg_replace(
            '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
            '[REDACTED_EMAIL]',
            $error,
        );
        $error = (string) preg_replace(
            '/(?<!\w)(?:\+?\d[\s().-]*){8,}(?!\w)/',
            '[REDACTED_PHONE]',
            $error,
        );
        $error = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $error);

        return mb_substr(trim($error) !== '' ? trim($error) : 'event_federation_delivery_failed', 0, 1000);
    }
}
