<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Closed vocabulary for explicitly delegated event staff roles. */
enum EventStaffRole: string
{
    case CoOrganizer = 'co_organizer';
    case RegistrationManager = 'registration_manager';
    case CommunicationsManager = 'communications_manager';
    case CheckInStaff = 'check_in_staff';
    case FinanceManager = 'finance_manager';

    /** @return list<EventStaffCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::CoOrganizer => [
                EventStaffCapability::View,
                EventStaffCapability::ViewMeetingLink,
                EventStaffCapability::ViewRoster,
                EventStaffCapability::ViewWaitlist,
                EventStaffCapability::Manage,
                EventStaffCapability::ManageStaff,
                EventStaffCapability::ManageAttendance,
                EventStaffCapability::MessagePeople,
                EventStaffCapability::ExportPeople,
                EventStaffCapability::LinkSeries,
                EventStaffCapability::ManageRegistration,
                EventStaffCapability::Broadcast,
            ],
            self::RegistrationManager => [
                EventStaffCapability::View,
                EventStaffCapability::ViewRoster,
                EventStaffCapability::ViewWaitlist,
                EventStaffCapability::ManageRegistration,
                EventStaffCapability::ExportPeople,
            ],
            self::CommunicationsManager => [
                EventStaffCapability::View,
                EventStaffCapability::MessagePeople,
                EventStaffCapability::Broadcast,
            ],
            self::CheckInStaff => [
                EventStaffCapability::View,
                EventStaffCapability::ViewRoster,
                EventStaffCapability::ManageAttendance,
            ],
            self::FinanceManager => [
                EventStaffCapability::View,
                EventStaffCapability::ManageFinance,
                EventStaffCapability::ReconcileCredits,
                EventStaffCapability::ReconcileTickets,
            ],
        };
    }

    public function grants(EventStaffCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
