<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventOfflineSyncOperation: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case NoShow = 'no_show';
    case Undo = 'undo';
}
