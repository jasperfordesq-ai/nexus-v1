<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Enums;

use App\Enums\EventStaffCapability;
use App\Enums\EventStaffRole;
use App\Services\EventRoleService;
use PHPUnit\Framework\TestCase;

final class EventStaffRoleTest extends TestCase
{
    public function test_role_vocabulary_is_closed_and_exact(): void
    {
        self::assertSame([
            'co_organizer',
            'registration_manager',
            'communications_manager',
            'check_in_staff',
            'finance_manager',
        ], array_map(static fn (EventStaffRole $role): string => $role->value, EventStaffRole::cases()));
        self::assertNull(EventStaffRole::tryFrom('owner'));
        self::assertNull(EventStaffRole::tryFrom('moderator'));
        self::assertNull(EventStaffRole::tryFrom('co-organizer'));
    }

    public function test_capability_map_matches_the_delegation_contract(): void
    {
        self::assertSame([
            'co_organizer' => [
                'view',
                'viewMeetingLink',
                'viewRoster',
                'viewWaitlist',
                'manage',
                'manageStaff',
                'manageAttendance',
                'messagePeople',
                'exportPeople',
                'linkSeries',
                'manageRegistration',
                'broadcast',
            ],
            'registration_manager' => [
                'view',
                'viewRoster',
                'viewWaitlist',
                'manageRegistration',
                'exportPeople',
            ],
            'communications_manager' => ['view', 'messagePeople', 'broadcast'],
            'check_in_staff' => ['view', 'viewRoster', 'manageAttendance'],
            'finance_manager' => ['view', 'manageFinance', 'reconcileCredits', 'reconcileTickets'],
        ], EventRoleService::capabilityMap());
    }

    public function test_co_organizer_never_inherits_ownership_or_finance(): void
    {
        $role = EventStaffRole::CoOrganizer;

        self::assertTrue($role->grants(EventStaffCapability::Manage));
        self::assertTrue($role->grants(EventStaffCapability::ManageStaff));
        self::assertFalse($role->grants(EventStaffCapability::TransferOwnership));
        self::assertFalse($role->grants(EventStaffCapability::ManageFinance));
        self::assertFalse($role->grants(EventStaffCapability::ReconcileCredits));
        self::assertFalse($role->grants(EventStaffCapability::ReconcileTickets));
    }
}
