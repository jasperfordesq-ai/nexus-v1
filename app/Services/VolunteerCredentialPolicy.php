<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

/**
 * Controlled upload policy for volunteering credentials.
 *
 * Criminal-record and vetting evidence is outside this feature's data
 * contract. Historical aliases remain classified so they can be redacted and
 * removed without ever being offered for download again.
 */
final class VolunteerCredentialPolicy
{
    /** @var list<string> */
    public const ALLOWED_TYPES = [
        'first_aid',
        'safeguarding',
        'manual_handling',
        'food_hygiene',
        'driving_licence',
        'professional_registration',
        'other',
    ];

    /** @var list<string> */
    public const PROHIBITED_VETTING_TYPES = [
        'police_check',
        'police_clearance',
        'criminal_record_check',
        'background_check',
        'dbs',
        'dbs_check',
        'dbs_basic',
        'dbs_standard',
        'dbs_enhanced',
        'garda_vetting',
        'access_ni',
        'accessni',
        'pvg',
        'pvg_scotland',
    ];

    public static function isAllowed(string $type): bool
    {
        return in_array(self::normaliseType($type), self::ALLOWED_TYPES, true);
    }

    public static function isProhibitedVetting(string $type): bool
    {
        return in_array(self::normaliseType($type), self::PROHIBITED_VETTING_TYPES, true);
    }

    public static function isUnknown(string $type): bool
    {
        return ! self::isAllowed($type) && ! self::isProhibitedVetting($type);
    }

    public static function normaliseType(string $type): string
    {
        return strtolower(trim($type));
    }
}
