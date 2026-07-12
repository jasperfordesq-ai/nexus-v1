<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventParticipationDenialReason: string
{
    case SafeguardingPolicy = 'safeguarding_policy';
    case MinimumAge = 'minimum_age';
    case GuardianConsent = 'guardian_consent';
    case CodeOfConduct = 'code_of_conduct';
    case ConductViolation = 'conduct_violation';
    case SafetyReview = 'safety_review';
    case UserBlock = 'user_block';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
