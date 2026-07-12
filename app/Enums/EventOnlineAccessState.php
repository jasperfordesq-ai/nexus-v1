<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventOnlineAccessState: string
{
    case NotApplicable = 'not_applicable';
    case NotConfigured = 'not_configured';
    case Restricted = 'restricted';
    case Scheduled = 'scheduled';
    case Available = 'available';
    case Expired = 'expired';
}
