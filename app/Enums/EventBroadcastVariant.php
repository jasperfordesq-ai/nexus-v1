<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Explicit rendering and eligibility contract for organizer communications. */
enum EventBroadcastVariant: string
{
    case Announcement = 'announcement';
    case FollowUp = 'follow_up';
    case ReviewRequest = 'review_request';

    public function requiresCompletedEvent(): bool
    {
        return $this !== self::Announcement;
    }

    public function subjectTranslationKey(): string
    {
        return match ($this) {
            self::Announcement => 'emails.events.broadcast_subject',
            self::FollowUp => 'emails.events.follow_up_subject',
            self::ReviewRequest => 'emails.events.review_request_subject',
        };
    }

    public function headingTranslationKey(): string
    {
        return match ($this) {
            self::Announcement => 'emails.events.broadcast_heading',
            self::FollowUp => 'emails.events.follow_up_heading',
            self::ReviewRequest => 'emails.events.review_request_heading',
        };
    }

    public function notificationType(): string
    {
        return match ($this) {
            self::Announcement => 'event_broadcast',
            self::FollowUp => 'event_follow_up',
            self::ReviewRequest => 'event_review_request',
        };
    }
}
