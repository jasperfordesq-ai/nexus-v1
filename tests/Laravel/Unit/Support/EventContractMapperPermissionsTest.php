<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Support\Events\EventContractMapper;
use PHPUnit\Framework\TestCase;

final class EventContractMapperPermissionsTest extends TestCase
{
    public function test_broad_manage_does_not_invent_staff_finance_or_ownership_permissions(): void
    {
        $permissions = EventContractMapper::permissions([
            'policy_abilities' => ['manage' => true],
        ]);

        self::assertTrue($permissions['edit']);
        self::assertTrue($permissions['cancel']);
        self::assertFalse($permissions['publish']);
        self::assertTrue($permissions['manage_agenda']);
        self::assertFalse($permissions['manage_staff']);
        self::assertFalse($permissions['manage_registration']);
        self::assertFalse($permissions['broadcast']);
        self::assertFalse($permissions['manage_finance']);
        self::assertFalse($permissions['reconcile_credits']);
        self::assertFalse($permissions['reconcile_tickets']);
        self::assertFalse($permissions['transfer_ownership']);
    }

    public function test_exact_enterprise_staff_capabilities_project_independently(): void
    {
        $permissions = EventContractMapper::permissions([
            'policy_abilities' => [
                'manageStaff' => true,
                'manageAgenda' => false,
                'manageRegistration' => true,
                'broadcast' => true,
                'manageFinance' => true,
                'reconcileCredits' => false,
                'reconcileTickets' => true,
                'transferOwnership' => false,
            ],
        ]);

        self::assertTrue($permissions['manage_staff']);
        self::assertFalse($permissions['manage_agenda']);
        self::assertTrue($permissions['manage_registration']);
        self::assertTrue($permissions['broadcast']);
        self::assertTrue($permissions['manage_finance']);
        self::assertFalse($permissions['reconcile_credits']);
        self::assertTrue($permissions['reconcile_tickets']);
        self::assertFalse($permissions['transfer_ownership']);
        self::assertFalse($permissions['edit']);
        self::assertFalse($permissions['manage_people']);
    }

    public function test_explicit_canonical_relationship_axes_override_stale_legacy_aliases(): void
    {
        $relationship = EventContractMapper::relationship([
            'max_attendees' => 10,
        ], [
            'legacy_status' => 'going',
            'engagement_state' => 'interested',
            'registration_state' => 'cancelled',
            'waitlist_state' => 'offered',
            'waitlist_position' => 4,
            'attendance' => [
                'state' => 'no_show',
                'checked_in_at' => null,
                'checked_out_at' => null,
            ],
            'confirmed_count' => 3,
            'capacity_occupied_count' => 10,
            'waitlist_count' => 5,
        ]);

        self::assertSame('interested', $relationship['engagement']['state']);
        self::assertSame('offered', $relationship['registration']['state']);
        self::assertSame(4, $relationship['registration']['waitlist_position']);
        self::assertSame('no_show', $relationship['attendance']['state']);
        self::assertSame(3, $relationship['capacity']['confirmed']);
        self::assertSame(0, $relationship['capacity']['remaining']);
        self::assertTrue($relationship['capacity']['is_full']);
        self::assertSame(5, $relationship['capacity']['waitlist_count']);
    }
}
