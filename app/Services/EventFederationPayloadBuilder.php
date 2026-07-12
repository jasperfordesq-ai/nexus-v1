<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationTombstoneReason;
use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Models\Event;
use App\Support\Events\EventFederationPayloadContract;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Builds the strict, versioned public snapshot used by event federation. */
final class EventFederationPayloadBuilder
{
    /** @return array<string,mixed> */
    public function build(
        Event $event,
        ?EventFederationTombstoneReason $forcedTombstone = null,
    ): array {
        $tenantId = (int) $event->getAttribute('tenant_id');
        $eventId = (int) $event->getKey();
        if ($tenantId <= 0 || $eventId <= 0 || ! $event->exists) {
            throw new InvalidArgumentException('event_federation_payload_requires_persisted_event');
        }

        $publication = $this->publicationState($event);
        $operational = $this->operationalState($event);
        $visibility = strtolower(trim((string) ($event->getAttribute('federated_visibility') ?? 'none')));
        if (! in_array($visibility, ['none', 'listed', 'joinable'], true)) {
            $visibility = 'none';
        }
        $reason = $forcedTombstone ?? $this->tombstoneReason($publication, $operational, $visibility);
        $federationVersion = (int) ($event->getAttribute('federation_version') ?? 0);
        $aggregateVersion = $federationVersion > 0
            ? $federationVersion
            : max(
                1,
                (int) ($event->getAttribute('lifecycle_version') ?? 0),
                (int) ($event->getAttribute('calendar_sequence') ?? 0),
            );
        $calendarVersion = max(0, (int) ($event->getAttribute('calendar_sequence') ?? 0));
        $updatedAt = $event->getAttribute('updated_at');
        $createdAt = $event->getAttribute('created_at');
        if (! $updatedAt instanceof DateTimeInterface || ! $createdAt instanceof DateTimeInterface) {
            throw new InvalidArgumentException('event_federation_payload_timestamps_missing');
        }

        $base = $this->basePayload(
            $tenantId,
            $eventId,
            $aggregateVersion,
            $calendarVersion,
            $reason === null ? EventFederationAction::Upsert : EventFederationAction::Tombstone,
            $updatedAt,
        );

        if ($reason !== null) {
            $payload = [
                ...$base,
                'tombstone_reason' => $reason->value,
                'publication_status' => $publication->value,
                'operational_status' => $operational->value,
                'visibility' => $visibility,
            ];
            EventFederationPayloadContract::assertValid($payload, $tenantId, $eventId);

            return $payload;
        }

        $startsAt = $event->getAttribute('start_time');
        $endsAt = $event->getAttribute('end_time');
        if (! $startsAt instanceof DateTimeInterface || ! $endsAt instanceof DateTimeInterface) {
            throw new InvalidArgumentException('event_federation_payload_event_time_missing');
        }
        $timezone = trim((string) ($event->getAttribute('timezone') ?? 'UTC'));
        if (! in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
            && $timezone !== 'UTC') {
            throw new InvalidArgumentException('event_federation_payload_timezone_invalid');
        }
        $location = $this->publicLocation($event->getAttribute('location'));

        $payload = [
            ...$base,
            'title' => trim((string) $event->getAttribute('title')),
            'starts_at' => $this->timestamp($startsAt),
            'ends_at' => $this->timestamp($endsAt),
            'timezone' => $timezone,
            'all_day' => (bool) ($event->getAttribute('all_day') ?? false),
            'location' => $location,
            'latitude' => $this->coordinate($event->getAttribute('latitude')),
            'longitude' => $this->coordinate($event->getAttribute('longitude')),
            'is_online' => (bool) ($event->getAttribute('is_online') ?? false),
            'publication_status' => $publication->value,
            'operational_status' => $operational->value,
            'visibility' => $visibility,
            'created_at' => $this->timestamp($createdAt),
            'updated_at' => $this->timestamp($updatedAt),
        ];
        EventFederationPayloadContract::assertValid($payload, $tenantId, $eventId);

        return $payload;
    }

    /**
     * Build a durable deletion retraction after only the source identity and
     * final versions remain. Integration must capture these values before the
     * event row is physically removed.
     *
     * @return array<string,mixed>
     */
    public function buildDeletion(
        int $tenantId,
        int $eventId,
        int $aggregateVersion,
        int $calendarVersion,
        DateTimeInterface $occurredAt,
    ): array {
        if ($tenantId <= 0 || $eventId <= 0 || $aggregateVersion <= 0 || $calendarVersion < 0) {
            throw new InvalidArgumentException('event_federation_deletion_identity_invalid');
        }
        $payload = [
            ...$this->basePayload(
                $tenantId,
                $eventId,
                $aggregateVersion,
                $calendarVersion,
                EventFederationAction::Tombstone,
                $occurredAt,
            ),
            'tombstone_reason' => EventFederationTombstoneReason::Deleted->value,
        ];
        EventFederationPayloadContract::assertValid($payload, $tenantId, $eventId);

        return $payload;
    }

    /** @return array<string,int|string> */
    private function basePayload(
        int $tenantId,
        int $eventId,
        int $aggregateVersion,
        int $calendarVersion,
        EventFederationAction $action,
        DateTimeInterface $occurredAt,
    ): array {
        return [
            'payload_schema' => EventFederationPayloadContract::SCHEMA,
            'payload_schema_version' => EventFederationPayloadContract::SCHEMA_VERSION,
            'action' => $action->value,
            'source_identity' => 'urn:nexus:event:' . $tenantId . ':' . $eventId,
            'source_platform' => 'nexus',
            'source_tenant_id' => $tenantId,
            'external_id' => (string) $eventId,
            'event_aggregate_version' => $aggregateVersion,
            'event_calendar_version' => $calendarVersion,
            'occurred_at' => $this->timestamp($occurredAt),
        ];
    }

    private function publicationState(Event $event): EventPublicationState
    {
        $canonical = $event->getAttribute('publication_status');
        if ($canonical instanceof EventPublicationState) {
            return $canonical;
        }
        if ($canonical instanceof BackedEnum) {
            $canonical = $canonical->value;
        }
        if (is_string($canonical) && EventPublicationState::tryFrom($canonical) !== null) {
            return EventPublicationState::from($canonical);
        }

        return EventPublicationState::fromLegacyStatus(
            is_string($event->getAttribute('status')) ? $event->getAttribute('status') : null,
        );
    }

    private function operationalState(Event $event): EventOperationalState
    {
        $canonical = $event->getAttribute('operational_status');
        if ($canonical instanceof EventOperationalState) {
            return $canonical;
        }
        if ($canonical instanceof BackedEnum) {
            $canonical = $canonical->value;
        }
        if (is_string($canonical) && EventOperationalState::tryFrom($canonical) !== null) {
            return EventOperationalState::from($canonical);
        }

        return EventOperationalState::fromLegacyStatus(
            is_string($event->getAttribute('status')) ? $event->getAttribute('status') : null,
        );
    }

    private function tombstoneReason(
        EventPublicationState $publication,
        EventOperationalState $operational,
        string $visibility,
    ): ?EventFederationTombstoneReason {
        if ($publication === EventPublicationState::Archived) {
            return EventFederationTombstoneReason::Archived;
        }
        if ($publication !== EventPublicationState::Published) {
            return EventFederationTombstoneReason::Unpublished;
        }
        if ($operational === EventOperationalState::Cancelled) {
            return EventFederationTombstoneReason::Cancelled;
        }
        if (! in_array($visibility, ['listed', 'joinable'], true)) {
            return EventFederationTombstoneReason::VisibilityWithdrawn;
        }

        return null;
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return CarbonImmutable::instance($value)
            ->utc()
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private function coordinate(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
    }

    private function publicLocation(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/\b(?:https?:\/\/|www\.)\S+/i', $value) === 1
            || preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $value) === 1
            || preg_match('/\b(?:token|password|secret|credential|meeting[_ -]?id|passcode)\s*[:=]/i', $value) === 1
            || preg_match('/(?<!\w)(?:\+?\d[\s().-]*){8,}(?!\w)/', $value) === 1
            || preg_match('/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/', $value) === 1) {
            return null;
        }

        return $value;
    }
}
