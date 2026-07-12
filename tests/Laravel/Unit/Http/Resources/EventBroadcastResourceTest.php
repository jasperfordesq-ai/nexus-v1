<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Http\Resources;

use App\Http\Resources\EventBroadcastHistoryResource;
use App\Http\Resources\EventBroadcastResource;
use App\Models\EventBroadcast;
use App\Models\EventBroadcastHistory;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class EventBroadcastResourceTest extends TestCase
{
    public function test_manager_resource_exposes_aggregate_evidence_without_recipient_identity(): void
    {
        $broadcast = new EventBroadcast();
        $broadcast->setRawAttributes([
            'id' => 91,
            'tenant_id' => 2,
            'event_id' => 44,
            'variant' => 'announcement',
            'status' => 'scheduled',
            'broadcast_version' => 3,
            'audience_segments' => '["registration_confirmed","waitlist_active"]',
            'channels' => '["email","in_app"]',
            'body' => "Organizer prose\nkept intact.",
            'recipient_count' => 7,
            'delivery_count' => 14,
            'delivered_count' => 4,
            'suppressed_count' => 2,
            'dead_letter_count' => 1,
            'failure_code' => 'raw provider address user@example.test',
            'scheduled_at' => CarbonImmutable::parse('2026-07-12T10:00:00Z'),
            'created_at' => CarbonImmutable::parse('2026-07-11T09:00:00Z'),
            'updated_at' => CarbonImmutable::parse('2026-07-11T09:30:00Z'),
        ], true);

        $resource = EventBroadcastResource::fromModel($broadcast);

        self::assertSame(7, $resource['audience']['recipient_count']);
        self::assertSame(14, $resource['delivery']['total']);
        self::assertNull($resource['delivery']['failure_code']);
        self::assertSame("Organizer prose\nkept intact.", $resource['body']);
        self::assertArrayNotHasKey('recipient_user_id', $resource);
        self::assertArrayNotHasKey('claim_token', $resource);
        self::assertArrayNotHasKey('provider_evidence_id', $resource);
        self::assertArrayNotHasKey('created_by_user_id', $resource);
    }

    public function test_history_resource_allowlists_identity_free_metadata(): void
    {
        $history = new EventBroadcastHistory();
        $history->setRawAttributes([
            'id' => 3,
            'tenant_id' => 2,
            'event_id' => 44,
            'broadcast_id' => 91,
            'broadcast_version' => 4,
            'action' => 'sent',
            'from_status' => 'sending',
            'to_status' => 'sent',
            'actor_user_id' => 77,
            'metadata' => json_encode([
                'contract_version' => 1,
                'delivered_count' => 12,
                'recipient_user_id' => 501,
                'claim_token' => 'secret-claim',
                'raw_error' => 'user@example.test refused',
                'body' => 'private organizer body',
            ], JSON_THROW_ON_ERROR),
            'created_at' => CarbonImmutable::parse('2026-07-11T11:00:00Z'),
        ], true);

        $resource = EventBroadcastHistoryResource::fromModel($history);

        self::assertSame([
            'contract_version' => 1,
            'delivered_count' => 12,
        ], $resource['metadata']);
        self::assertArrayNotHasKey('actor_user_id', $resource);
        self::assertArrayNotHasKey('broadcast_id', $resource);
    }
}
