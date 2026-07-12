<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Enums\EventFederationTombstoneReason;
use App\Models\Event;
use App\Services\EventFederationPayloadBuilder;
use App\Support\Events\EventFederationPayloadContract;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventFederationPayloadBuilderTest extends TestCase
{
    private EventFederationPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new EventFederationPayloadBuilder();
    }

    public function test_upsert_payload_is_versioned_and_excludes_private_event_material(): void
    {
        $event = $this->event([
            'description' => 'Email host@example.test and use token=top-secret.',
            'online_link' => 'https://meet.example.test/private?token=top-secret',
            'video_url' => 'https://video.example.test/private',
            'user_id' => 991,
            'organizer_email' => 'host@example.test',
            'organizer_phone' => '+1 555 111 2222',
            'private_notes' => 'Do not distribute.',
            'registration_roster' => [['email' => 'attendee@example.test']],
        ]);

        $payload = $this->builder->build($event);

        self::assertSame(EventFederationPayloadContract::SCHEMA, $payload['payload_schema']);
        self::assertSame(1, $payload['payload_schema_version']);
        self::assertSame('upsert', $payload['action']);
        self::assertSame('urn:nexus:event:41:902', $payload['source_identity']);
        self::assertSame(21, $payload['event_aggregate_version']);
        self::assertSame(13, $payload['event_calendar_version']);
        self::assertSame('Europe/Dublin', $payload['timezone']);
        self::assertSame('Community Hall', $payload['location']);

        foreach ([
            'description', 'online_link', 'video_url', 'user_id', 'organizer_email',
            'organizer_phone', 'private_notes', 'registration_roster', 'meeting_url',
            'join_url', 'token',
        ] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload);
        }
        $encoded = EventFederationPayloadContract::canonicalJson($payload);
        self::assertStringNotContainsString('host@example.test', $encoded);
        self::assertStringNotContainsString('top-secret', $encoded);
        self::assertStringNotContainsString('attendee@example.test', $encoded);
        EventFederationPayloadContract::assertValid($payload, 41, 902);
    }

    public function test_join_or_meeting_url_in_location_is_not_federated(): void
    {
        $payload = $this->builder->build($this->event([
            'is_online' => true,
            'location' => 'https://meet.example.test/room?token=secret',
        ]));

        self::assertNull($payload['location']);
        self::assertStringNotContainsString('meet.example.test', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_phone_number_in_location_is_not_federated(): void
    {
        $payload = $this->builder->build($this->event([
            'location' => 'Call +353 87 123 4567 for the private address',
        ]));

        self::assertNull($payload['location']);
        self::assertStringNotContainsString('+353 87 123 4567', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function tombstoneStates(): iterable
    {
        yield 'visibility withdrawn' => [
            ['federated_visibility' => 'none'],
            EventFederationTombstoneReason::VisibilityWithdrawn->value,
        ];
        yield 'cancelled' => [
            ['operational_status' => 'cancelled'],
            EventFederationTombstoneReason::Cancelled->value,
        ];
        yield 'archived' => [
            ['publication_status' => 'archived'],
            EventFederationTombstoneReason::Archived->value,
        ];
        yield 'draft' => [
            ['publication_status' => 'draft'],
            EventFederationTombstoneReason::Unpublished->value,
        ];
    }

    /** @param array<string,mixed> $overrides */
    #[DataProvider('tombstoneStates')]
    public function test_non_public_lifecycle_states_emit_minimal_tombstones(
        array $overrides,
        string $reason,
    ): void {
        $event = $this->event([
            ...$overrides,
            'description' => 'Private after withdrawal',
            'location' => 'Sensitive Venue',
            'online_link' => 'https://meet.example.test/private',
        ]);

        $payload = $this->builder->build($event);

        self::assertSame('tombstone', $payload['action']);
        self::assertSame($reason, $payload['tombstone_reason']);
        foreach (['title', 'description', 'starts_at', 'ends_at', 'location', 'online_link'] as $field) {
            self::assertArrayNotHasKey($field, $payload);
        }
        EventFederationPayloadContract::assertValid($payload, 41, 902);
    }

    public function test_explicit_delete_tombstone_needs_no_live_event_row(): void
    {
        $payload = $this->builder->buildDeletion(
            41,
            902,
            9,
            14,
            CarbonImmutable::parse('2026-07-11T13:00:00Z'),
        );

        self::assertSame('tombstone', $payload['action']);
        self::assertSame('deleted', $payload['tombstone_reason']);
        self::assertSame('urn:nexus:event:41:902', $payload['source_identity']);
        self::assertCount(11, $payload);
        EventFederationPayloadContract::assertValid($payload, 41, 902);
    }

    public function test_payload_hash_is_stable_across_object_key_order(): void
    {
        $payload = $this->builder->build($this->event());
        $reordered = array_reverse($payload, true);

        self::assertSame(
            EventFederationPayloadContract::hash($payload),
            EventFederationPayloadContract::hash($reordered),
        );
    }

    public function test_federation_visible_mutations_require_a_new_federation_version(): void
    {
        $legacy = $this->builder->build($this->event(['federation_version' => 0]));
        self::assertSame(13, $legacy['event_aggregate_version']);

        $event = $this->event();
        $initial = $this->builder->build($event);

        $event->setAttribute('latitude', 53.5);
        $coordinateChangeWithoutVersion = $this->builder->build($event);
        self::assertSame(21, $coordinateChangeWithoutVersion['event_aggregate_version']);
        self::assertNotSame(
            EventFederationPayloadContract::hash($initial),
            EventFederationPayloadContract::hash($coordinateChangeWithoutVersion),
        );

        $event->setAttribute('federation_version', 22);
        $coordinateChange = $this->builder->build($event);
        self::assertSame(22, $coordinateChange['event_aggregate_version']);

        $event->setAttribute('federated_visibility', 'none');
        $withdrawalWithoutVersion = $this->builder->build($event);
        self::assertSame(22, $withdrawalWithoutVersion['event_aggregate_version']);
        self::assertSame('tombstone', $withdrawalWithoutVersion['action']);

        $event->setAttribute('federation_version', 23);
        $withdrawal = $this->builder->build($event);
        self::assertSame(23, $withdrawal['event_aggregate_version']);
        self::assertSame('visibility_withdrawn', $withdrawal['tombstone_reason']);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidRfc3339Dates(): iterable
    {
        yield 'relative' => ['tomorrow'];
        yield 'local without offset' => ['2026-08-01T10:00:00'];
        yield 'database datetime' => ['2026-08-01 10:00:00'];
    }

    #[DataProvider('invalidRfc3339Dates')]
    public function test_contract_rejects_datetime_without_explicit_rfc3339_offset(string $invalid): void
    {
        $payload = $this->builder->build($this->event());
        $payload['occurred_at'] = $invalid;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event_federation_payload_occurred_at_invalid');
        EventFederationPayloadContract::assertValid($payload);
    }

    /** @return iterable<string,array{string,string}> */
    public static function invalidUpsertLifecycle(): iterable
    {
        yield 'not published' => ['publication_status', 'draft'];
        yield 'cancelled' => ['operational_status', 'cancelled'];
        yield 'not shared' => ['visibility', 'none'];
    }

    #[DataProvider('invalidUpsertLifecycle')]
    public function test_contract_rejects_upsert_that_is_not_publicly_federatable(
        string $field,
        string $value,
    ): void {
        $payload = $this->builder->build($this->event());
        $payload[$field] = $value;

        $this->expectException(\InvalidArgumentException::class);
        EventFederationPayloadContract::assertValid($payload);
    }

    /** @param array<string,mixed> $overrides */
    private function event(array $overrides = []): Event
    {
        $event = new Event();
        $event->setRawAttributes([
            'id' => 902,
            'tenant_id' => 41,
            'user_id' => 991,
            'title' => 'Federated repair workshop',
            'description' => 'Public description is deliberately not federated in v1.',
            'start_time' => CarbonImmutable::parse('2026-08-01T10:00:00Z'),
            'end_time' => CarbonImmutable::parse('2026-08-01T12:00:00Z'),
            'timezone' => 'Europe/Dublin',
            'all_day' => false,
            'location' => 'Community Hall',
            'latitude' => 53.3498,
            'longitude' => -6.2603,
            'is_online' => false,
            'online_link' => null,
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'federated_visibility' => 'listed',
            'lifecycle_version' => 8,
            'calendar_sequence' => 13,
            'federation_version' => 21,
            'created_at' => CarbonImmutable::parse('2026-06-01T09:00:00Z'),
            'updated_at' => CarbonImmutable::parse('2026-07-11T12:00:00Z'),
            ...$overrides,
        ]);
        $event->exists = true;

        return $event;
    }
}
