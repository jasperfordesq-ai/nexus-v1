<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Support\Events\EventSessionContractMapper;
use PHPUnit\Framework\TestCase;

final class EventSessionContractMapperTest extends TestCase
{
    public function test_shared_agenda_fixture_is_frozen(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/contracts/events/v2/event-agenda.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $actual = EventSessionContractMapper::agenda([
            'id' => 101,
            'agenda_version' => 3,
            'timezone' => 'Europe/Dublin',
        ], [[
            'id' => 501,
            'version' => 2,
            'title' => 'Repair skills workshop',
            'description' => 'Learn safe, practical repair techniques.',
            'session_type' => 'workshop',
            'visibility' => 'registered',
            'status' => 'scheduled',
            'starts_at_utc' => '2030-05-01 09:30:00',
            'ends_at_utc' => '2030-05-01 10:15:00',
            'timezone' => 'Europe/Dublin',
            'track_name' => 'Practical skills',
            'room_name' => 'Workshop room',
            'position' => 1,
            'cancellation_reason' => null,
            'capacity' => 24,
            'capacity_registered' => 12,
            'viewer_registration_state' => 'registered',
            'viewer_registration_version' => 1,
            'viewer_can_view_registered' => true,
            'viewer_can_register' => false,
            'viewer_can_withdraw' => true,
            'speakers' => [[
                'user_id' => 7,
                'display_name' => null,
                'role_label' => 'Facilitator',
                'position' => 1,
                'user' => [
                    'id' => 7,
                    'name' => 'Alex Morgan',
                ],
            ]],
            'resources' => [
                [
                    'id' => 701,
                    'type' => 'slides',
                    'title' => 'Workshop slides',
                    'visibility' => 'registered',
                    'position' => 1,
                    'url' => 'https://events.example.test/resources/workshop-slides',
                ],
                [
                    'id' => 702,
                    'type' => 'stream',
                    'title' => 'Live workshop stream',
                    'visibility' => 'registered',
                    'position' => 2,
                    'url' => 'https://events.example.test/live/workshop',
                ],
            ],
        ]]);

        self::assertSame($fixture, $actual);
    }

    public function test_projection_strips_html_private_fields_and_manager_only_reason(): void
    {
        $session = [
            'id' => 9,
            'tenant_id' => 2,
            'version' => 4,
            'title' => '<strong>Closing panel</strong>',
            'description' => '<p>Questions &amp; answers.</p>',
            'session_type' => 'panel',
            'visibility' => 'staff',
            'status' => 'cancelled',
            'starts_at_utc' => '2030-05-01 11:00:00',
            'ends_at_utc' => '2030-05-01 12:00:00',
            'timezone' => 'UTC',
            'track_name' => '<em>Main</em>',
            'room_name' => '<span>Room A</span>',
            'room_key' => str_repeat('a', 64),
            'position' => 2,
            'cancellation_reason' => '<b>Facilitator unavailable</b>',
            'created_by' => 44,
            'speakers' => [[
                'user_id' => 44,
                'display_name' => null,
                'role_label' => '<i>Chair</i>',
                'position' => 1,
                'user' => [
                    'name' => 'Taylor Reed',
                    'email' => 'private@example.test',
                ],
            ]],
        ];

        $public = EventSessionContractMapper::session($session, false);
        self::assertSame('Closing panel', $public['title']);
        self::assertSame('Questions & answers.', $public['description']);
        self::assertSame('Main', $public['track']);
        self::assertSame('Room A', $public['room']);
        self::assertNull($public['cancellation_reason']);
        self::assertSame('Taylor Reed', $public['speakers'][0]['display_name']);
        self::assertArrayNotHasKey('tenant_id', $public);
        self::assertArrayNotHasKey('created_by', $public);
        self::assertArrayNotHasKey('room_key', $public);
        self::assertArrayNotHasKey('email', $public['speakers'][0]);

        $manager = EventSessionContractMapper::session($session, true);
        self::assertSame('Facilitator unavailable', $manager['cancellation_reason']);
    }
}
