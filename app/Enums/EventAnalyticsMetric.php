<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/**
 * Event analytics metric dictionary.
 *
 * Operational metrics are derived from their authoritative ledgers. Only the
 * two optional funnel metrics may be persisted as analytics facts.
 */
enum EventAnalyticsMetric: string
{
    case EventViewed = 'event_viewed';
    case InterestChanged = 'interest_changed';
    case RegistrationStarted = 'registration_started';
    case RegistrationCompleted = 'registration_completed';
    case InvitationConverted = 'invitation_converted';
    case WaitlistMoved = 'waitlist_moved';
    case RegistrationCancelled = 'registration_cancelled';
    case CheckedIn = 'checked_in';
    case AttendanceRecorded = 'attendance_recorded';
    case NoShowRecorded = 'no_show_recorded';
    case CreditSettled = 'credit_settled';
    case CommunicationDelivered = 'communication_delivered';

    public function isOptional(): bool
    {
        return match ($this) {
            self::EventViewed, self::RegistrationStarted => true,
            default => false,
        };
    }

    /** @return array{owner:string,purpose:string,source:string,deduplication:string,late_event_rule:string,consent_required:bool} */
    public function definition(): array
    {
        return match ($this) {
            self::EventViewed => $this->optionalDefinition(
                'Measure consented event-detail reach.',
                'event_analytics_optional_facts',
                'One fact per caller-supplied view impression identifier.',
            ),
            self::RegistrationStarted => $this->optionalDefinition(
                'Measure consented registration-funnel starts.',
                'event_analytics_optional_facts',
                'One fact per caller-supplied registration-attempt identifier.',
            ),
            self::InterestChanged => $this->operationalDefinition(
                'Measure current and historical interest.',
                'event_rsvps and event registration history',
                'Authoritative state/history identity; never copy into analytics facts.',
            ),
            self::RegistrationCompleted => $this->operationalDefinition(
                'Measure completed registrations and capacity use.',
                'event_registrations and event_registration_history',
                'Canonical registration and history identifiers.',
            ),
            self::InvitationConverted => $this->operationalDefinition(
                'Measure invitation acceptance conversion.',
                'event_invitations and event_invitation_history',
                'Canonical invitation and acceptance revision identifiers.',
            ),
            self::WaitlistMoved => $this->operationalDefinition(
                'Measure waitlist offers, expiry, and promotion.',
                'event_waitlist_entries and event_waitlist_entry_history',
                'Canonical queue entry and history revision identifiers.',
            ),
            self::RegistrationCancelled => $this->operationalDefinition(
                'Measure registration cancellation.',
                'event_registrations and event_registration_history',
                'Canonical cancellation history revision identifier.',
            ),
            self::CheckedIn => $this->operationalDefinition(
                'Measure check-in operations.',
                'event_attendance and event_attendance_activity',
                'Canonical attendance activity identifier and version.',
            ),
            self::AttendanceRecorded => $this->operationalDefinition(
                'Measure final attendance.',
                'event_attendance and event_attendance_activity',
                'Canonical attendance subject and final version.',
            ),
            self::NoShowRecorded => $this->operationalDefinition(
                'Measure no-show outcomes.',
                'event_attendance and event_attendance_activity',
                'Canonical no-show activity identifier and version.',
            ),
            self::CreditSettled => $this->operationalDefinition(
                'Reconcile approved Event credit effects.',
                'event_attendance_credit_claims and wallet transactions',
                'Immutable claim and canonical wallet transaction identifiers.',
            ),
            self::CommunicationDelivered => $this->operationalDefinition(
                'Measure eligible communication delivery by channel.',
                'event_notification_deliveries',
                'Unique recipient/channel delivery identity.',
            ),
        };
    }

    /** @return array{owner:string,purpose:string,source:string,deduplication:string,late_event_rule:string,consent_required:bool} */
    private function optionalDefinition(
        string $purpose,
        string $source,
        string $deduplication,
    ): array {
        return [
            'owner' => 'Events product and privacy owners',
            'purpose' => $purpose,
            'source' => $source,
            'deduplication' => $deduplication,
            'late_event_rule' => 'Accept up to 30 days late; flag after 24 hours; reject future events beyond five minutes.',
            'consent_required' => true,
        ];
    }

    /** @return array{owner:string,purpose:string,source:string,deduplication:string,late_event_rule:string,consent_required:bool} */
    private function operationalDefinition(
        string $purpose,
        string $source,
        string $deduplication,
    ): array {
        return [
            'owner' => 'Events operations owner',
            'purpose' => $purpose,
            'source' => $source,
            'deduplication' => $deduplication,
            'late_event_rule' => 'Use the authoritative record timestamp and revision; late projection does not create another domain fact.',
            'consent_required' => false,
        ];
    }
}
