<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationInboundDecision;
use App\Support\Events\EventFederationInboundResult;
use App\Support\Events\EventFederationPayloadContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Version-arbitrated inbound boundary for federated event projections.
 *
 * Version vectors must move monotonically on both the aggregate and calendar
 * axes. Crossed vectors are conflicts rather than guesses. Tombstones remain
 * as durable rows, so delayed upserts cannot resurrect withdrawn events.
 */
final class EventFederationInboundProjectionService
{
    /** @param array<string,mixed> $payload */
    public function ingest(
        int $tenantId,
        int $externalPartnerId,
        array $payload,
    ): EventFederationInboundResult {
        if ($tenantId <= 0 || $externalPartnerId <= 0) {
            throw new InvalidArgumentException('event_federation_inbound_scope_invalid');
        }
        EventFederationPayloadContract::assertValid($payload);

        $externalId = (string) $payload['external_id'];
        if (mb_strlen($externalId) > 128) {
            throw new InvalidArgumentException('event_federation_inbound_external_id_invalid');
        }
        $action = EventFederationAction::from((string) $payload['action']);
        $aggregateVersion = (int) $payload['event_aggregate_version'];
        $calendarVersion = (int) $payload['event_calendar_version'];
        $payloadHash = EventFederationPayloadContract::hash($payload);

        return DB::transaction(function () use (
            $tenantId,
            $externalPartnerId,
            $payload,
            $externalId,
            $action,
            $aggregateVersion,
            $calendarVersion,
            $payloadHash,
        ): EventFederationInboundResult {
            $this->assertPartner($tenantId, $externalPartnerId, $externalId, $action);
            $query = static fn () => DB::table('federation_events')
                ->where('tenant_id', $tenantId)
                ->where('external_partner_id', $externalPartnerId)
                ->where('external_id', $externalId);
            $existing = $query()->lockForUpdate()->first();

            if ($existing === null) {
                $now = now();
                $inserted = DB::table('federation_events')->insertOrIgnore([
                    ...$this->projectionValues($payload, $action),
                    'tenant_id' => $tenantId,
                    'external_partner_id' => $externalPartnerId,
                    'external_id' => $externalId,
                    'replay_count' => 0,
                    'stale_count' => 0,
                    'conflict_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existing = $query()->lockForUpdate()->first();
                if ($existing === null) {
                    throw new LogicException('event_federation_inbound_projection_insert_failed');
                }
                if ($inserted === 1) {
                    return $this->result(
                        EventFederationInboundDecision::Accepted,
                        (int) $existing->id,
                        $action,
                        $aggregateVersion,
                        $calendarVersion,
                        $payloadHash,
                    );
                }
            }

            $storedAggregate = (int) $existing->source_aggregate_version;
            $storedCalendar = (int) $existing->source_calendar_version;
            $aggregateComparison = $aggregateVersion <=> $storedAggregate;
            $calendarComparison = $calendarVersion <=> $storedCalendar;
            $hasLowerAxis = $aggregateComparison < 0 || $calendarComparison < 0;
            $hasHigherAxis = $aggregateComparison > 0 || $calendarComparison > 0;

            if (! $hasLowerAxis && ! $hasHigherAxis) {
                $sameHash = is_string($existing->source_payload_hash)
                    && strlen($existing->source_payload_hash) === 64
                    && hash_equals($existing->source_payload_hash, $payloadHash);
                $sameAction = (string) $existing->source_action === $action->value;
                if ($sameHash && $sameAction) {
                    $query()->where('id', (int) $existing->id)->update([
                        'last_received_at' => now(),
                        'last_replayed_at' => now(),
                        'replay_count' => DB::raw('replay_count + 1'),
                    ]);

                    return $this->result(
                        EventFederationInboundDecision::Replay,
                        (int) $existing->id,
                        $action,
                        $aggregateVersion,
                        $calendarVersion,
                        $payloadHash,
                    );
                }

                return $this->recordConflict(
                    $query,
                    (int) $existing->id,
                    $action,
                    $aggregateVersion,
                    $calendarVersion,
                    $payloadHash,
                );
            }

            if ($hasLowerAxis && $hasHigherAxis) {
                return $this->recordConflict(
                    $query,
                    (int) $existing->id,
                    $action,
                    $aggregateVersion,
                    $calendarVersion,
                    $payloadHash,
                );
            }

            if ($hasLowerAxis) {
                $query()->where('id', (int) $existing->id)->update([
                    'last_received_at' => now(),
                    'last_stale_at' => now(),
                    'last_stale_hash' => $payloadHash,
                    'stale_count' => DB::raw('stale_count + 1'),
                ]);

                return $this->result(
                    EventFederationInboundDecision::Stale,
                    (int) $existing->id,
                    $action,
                    $aggregateVersion,
                    $calendarVersion,
                    $payloadHash,
                );
            }

            $updated = $query()->where('id', (int) $existing->id)->update([
                ...$this->projectionValues($payload, $action),
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw new LogicException('event_federation_inbound_projection_update_failed');
            }

            return $this->result(
                EventFederationInboundDecision::Accepted,
                (int) $existing->id,
                $action,
                $aggregateVersion,
                $calendarVersion,
                $payloadHash,
            );
        }, 3);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function projectionValues(
        array $payload,
        EventFederationAction $action,
    ): array {
        $receivedAt = now();
        $occurredAt = CarbonImmutable::parse((string) $payload['occurred_at'])->utc();
        $sourceMetadata = [
            'contract' => EventFederationPayloadContract::SCHEMA,
            'schema_version' => (int) $payload['payload_schema_version'],
            'source_identity' => (string) $payload['source_identity'],
            'source_platform' => (string) $payload['source_platform'],
            'source_tenant_id' => (int) $payload['source_tenant_id'],
            'publication_status' => $payload['publication_status'] ?? null,
            'operational_status' => $payload['operational_status'] ?? null,
            'visibility' => $payload['visibility'] ?? null,
        ];
        if ($action === EventFederationAction::Upsert) {
            $sourceMetadata = [
                ...$sourceMetadata,
                'timezone' => (string) $payload['timezone'],
                'all_day' => (bool) $payload['all_day'],
                'latitude' => $payload['latitude'],
                'longitude' => $payload['longitude'],
                'is_online' => (bool) $payload['is_online'],
                'created_at' => (string) $payload['created_at'],
                'updated_at' => (string) $payload['updated_at'],
            ];
        }
        $common = [
            'payload_schema_version' => (int) $payload['payload_schema_version'],
            'source_aggregate_version' => (int) $payload['event_aggregate_version'],
            'source_calendar_version' => (int) $payload['event_calendar_version'],
            'source_action' => $action->value,
            'source_payload_hash' => EventFederationPayloadContract::hash($payload),
            'source_occurred_at' => $occurredAt,
            'is_tombstone' => $action === EventFederationAction::Tombstone,
            'last_received_at' => $receivedAt,
            'metadata' => json_encode(
                $sourceMetadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ];

        if ($action === EventFederationAction::Tombstone) {
            return [
                ...$common,
                // Keep the identity row while minimising withdrawn public data.
                'title' => mb_substr((string) $payload['source_identity'], 0, 500),
                'description' => null,
                'starts_at' => null,
                'ends_at' => null,
                'location' => null,
                'tombstoned_at' => $occurredAt,
                'tombstone_reason' => (string) $payload['tombstone_reason'],
            ];
        }

        $values = [
            ...$common,
            'title' => (string) $payload['title'],
            'description' => null,
            'starts_at' => CarbonImmutable::parse((string) $payload['starts_at'])->utc(),
            'ends_at' => CarbonImmutable::parse((string) $payload['ends_at'])->utc(),
            'location' => $payload['location'],
            'tombstoned_at' => null,
            'tombstone_reason' => null,
        ];

        return $values;
    }

    /**
     * @param callable():\Illuminate\Database\Query\Builder $query
     */
    private function recordConflict(
        callable $query,
        int $projectionId,
        EventFederationAction $action,
        int $aggregateVersion,
        int $calendarVersion,
        string $payloadHash,
    ): EventFederationInboundResult {
        $query()->where('id', $projectionId)->update([
            'last_received_at' => now(),
            'last_conflict_at' => now(),
            'last_conflict_hash' => $payloadHash,
            'conflict_count' => DB::raw('conflict_count + 1'),
        ]);

        return $this->result(
            EventFederationInboundDecision::Conflict,
            $projectionId,
            $action,
            $aggregateVersion,
            $calendarVersion,
            $payloadHash,
        );
    }

    private function result(
        EventFederationInboundDecision $decision,
        int $projectionId,
        EventFederationAction $action,
        int $aggregateVersion,
        int $calendarVersion,
        string $payloadHash,
    ): EventFederationInboundResult {
        return new EventFederationInboundResult(
            $decision,
            $projectionId,
            $action,
            $aggregateVersion,
            $calendarVersion,
            $payloadHash,
        );
    }

    private function assertPartner(
        int $tenantId,
        int $externalPartnerId,
        string $externalId,
        EventFederationAction $action,
    ): void
    {
        $partner = DB::table('federation_external_partners')
            ->where('id', $externalPartnerId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first(['status', 'allow_events']);
        if ($partner === null || (string) $partner->status !== 'active') {
            throw new InvalidArgumentException('event_federation_inbound_partner_unavailable');
        }
        if ((bool) $partner->allow_events) {
            return;
        }
        if ($action === EventFederationAction::Tombstone
            && DB::table('federation_events')
                ->where('tenant_id', $tenantId)
                ->where('external_partner_id', $externalPartnerId)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first(['id']) !== null) {
            return;
        }

        throw new InvalidArgumentException(
            $action === EventFederationAction::Tombstone
                ? 'event_federation_inbound_retraction_evidence_missing'
                : 'event_federation_inbound_partner_unavailable',
        );
    }
}
