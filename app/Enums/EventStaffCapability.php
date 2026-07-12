<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Canonical machine capabilities delegated by event staff assignments. */
enum EventStaffCapability: string
{
    case View = 'view';
    case ViewMeetingLink = 'viewMeetingLink';
    case ViewRoster = 'viewRoster';
    case ViewWaitlist = 'viewWaitlist';
    case Manage = 'manage';
    case ManageStaff = 'manageStaff';
    case ManageAttendance = 'manageAttendance';
    case MessagePeople = 'messagePeople';
    case ExportPeople = 'exportPeople';
    case LinkSeries = 'linkSeries';
    case ManageRegistration = 'manageRegistration';
    case Broadcast = 'broadcast';
    case ManageFinance = 'manageFinance';
    case ReconcileCredits = 'reconcileCredits';
    case ReconcileTickets = 'reconcileTickets';
    case TransferOwnership = 'transferOwnership';
}
