<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Typed resource attached to an ordered event-agenda session. */
enum EventSessionResourceType: string
{
    case Link = 'link';
    case Document = 'document';
    case Slides = 'slides';
    case Download = 'download';
    case Stream = 'stream';
    case Recording = 'recording';

    public function isProtectedMedia(): bool
    {
        return $this === self::Stream || $this === self::Recording;
    }
}
