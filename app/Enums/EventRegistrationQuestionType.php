<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventRegistrationQuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Dietary = 'dietary';
    case Accessibility = 'accessibility';
    case Consent = 'consent';
    case Waiver = 'waiver';

    public function isChoice(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }

    public function isConsent(): bool
    {
        return $this === self::Consent || $this === self::Waiver;
    }
}
