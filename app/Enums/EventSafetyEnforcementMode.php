<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Controlled rollout for event-safety participation enforcement. */
enum EventSafetyEnforcementMode: string
{
    case Off = 'off';
    case Shadow = 'shadow';
    case Enforce = 'enforce';

    public function blocksParticipation(): bool
    {
        return $this === self::Enforce;
    }

    public function evaluatesParticipation(): bool
    {
        return $this !== self::Off;
    }
}
