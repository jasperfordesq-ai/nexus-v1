<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Enums;

use App\Enums\EventBroadcastAudienceSegment;
use App\Enums\EventBroadcastStatus;
use App\Enums\EventBroadcastVariant;
use PHPUnit\Framework\TestCase;

final class EventBroadcastEnumsTest extends TestCase
{
    public function test_lifecycle_requires_audited_pre_send_and_terminal_transitions(): void
    {
        self::assertTrue(EventBroadcastStatus::Draft->canTransitionTo(EventBroadcastStatus::Scheduled));
        self::assertTrue(EventBroadcastStatus::Draft->canTransitionTo(EventBroadcastStatus::Cancelled));
        self::assertTrue(EventBroadcastStatus::Scheduled->canTransitionTo(EventBroadcastStatus::Sending));
        self::assertTrue(EventBroadcastStatus::Scheduled->canTransitionTo(EventBroadcastStatus::Cancelled));
        self::assertTrue(EventBroadcastStatus::Sending->canTransitionTo(EventBroadcastStatus::Sent));
        self::assertTrue(EventBroadcastStatus::Sending->canTransitionTo(EventBroadcastStatus::Failed));
        self::assertTrue(EventBroadcastStatus::Failed->canTransitionTo(EventBroadcastStatus::Scheduled));
        self::assertFalse(EventBroadcastStatus::Sending->canTransitionTo(EventBroadcastStatus::Cancelled));
        self::assertFalse(EventBroadcastStatus::Sent->canTransitionTo(EventBroadcastStatus::Draft));
        self::assertTrue(EventBroadcastStatus::Sent->isTerminal());
        self::assertTrue(EventBroadcastStatus::Cancelled->isTerminal());
    }

    public function test_post_event_variants_and_canonical_segments_are_explicit(): void
    {
        self::assertFalse(EventBroadcastVariant::Announcement->requiresCompletedEvent());
        self::assertTrue(EventBroadcastVariant::FollowUp->requiresCompletedEvent());
        self::assertTrue(EventBroadcastVariant::ReviewRequest->requiresCompletedEvent());
        self::assertSame('event_review_request', EventBroadcastVariant::ReviewRequest->notificationType());

        self::assertSame([
            'registration_confirmed',
            'waitlist_active',
            'attendance_attended',
            'attendance_no_show',
        ], array_map(
            static fn (EventBroadcastAudienceSegment $segment): string => $segment->value,
            EventBroadcastAudienceSegment::cases(),
        ));
        self::assertFalse(EventBroadcastAudienceSegment::RegistrationConfirmed->isAttendanceSegment());
        self::assertTrue(EventBroadcastAudienceSegment::AttendanceAttended->isAttendanceSegment());
    }
}
