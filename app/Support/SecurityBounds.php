<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

final class SecurityBounds
{
    public const MAX_SINGLE_EXTERNAL_CREDIT_HOURS = 24.0;
    public const MAX_EVENT_ATTENDANCE_HOURS = 24.0;
    public const MIN_PAID_PUSH_COST_PER_SEND_CENTS = 1;
    public const MAX_PAID_PUSH_COST_PER_SEND_CENTS = 1000;
    public const MIN_REPORTABLE_AUDIENCE_COUNT = 10;

    public static function isAcceptableHourAmount(float $hours, float $max = self::MAX_SINGLE_EXTERNAL_CREDIT_HOURS): bool
    {
        return is_finite($hours) && $hours > 0 && $hours <= $max && round($hours, 2) == $hours;
    }

    public static function paidPushCostPerSend(mixed $value, int $default = 5): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $cost = (int) $value;

        return max(
            self::MIN_PAID_PUSH_COST_PER_SEND_CENTS,
            min(self::MAX_PAID_PUSH_COST_PER_SEND_CENTS, $cost)
        );
    }

    public static function bucketAudienceCount(int $count): int
    {
        if ($count < self::MIN_REPORTABLE_AUDIENCE_COUNT) {
            return 0;
        }

        if ($count < 100) {
            return (int) (floor($count / 10) * 10);
        }

        return (int) (floor($count / 100) * 100);
    }
}
