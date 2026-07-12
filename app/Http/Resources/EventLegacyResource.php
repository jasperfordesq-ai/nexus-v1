<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** One-release compatibility projection for absent/header-v1 clients. */
final class EventLegacyResource
{
    /** @return array<string, mixed> */
    public static function fromArray(array $event): array
    {
        // The service has already policy-redacted the legacy URL aliases. The
        // canonical group is removed so v1 retains its established shape.
        unset($event['online_access'], $event['_event_contract']);

        return $event;
    }
}
