<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventFederationTombstoneReason: string
{
    case VisibilityWithdrawn = 'visibility_withdrawn';
    case Unpublished = 'unpublished';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
    case Deleted = 'deleted';
}
