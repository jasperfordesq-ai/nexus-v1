<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

/**
 * BACK-COMPAT SHIM — TimeOverflowAdapter has moved to
 * App\Services\Protocols\TimeOverflowAdapter to match the other protocol
 * adapters. This subclass exists only to preserve legacy imports in console
 * commands and scripts.
 *
 * Prefer: use App\Services\Protocols\TimeOverflowAdapter;
 *
 * @deprecated Use App\Services\Protocols\TimeOverflowAdapter instead.
 */
class TimeOverflowAdapter extends \App\Services\Protocols\TimeOverflowAdapter
{
}
