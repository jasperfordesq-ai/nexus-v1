<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Events\EventContractMapper;

final class EventSeriesResource
{
    /** @return array<string, mixed> */
    public static function fromArray(array $series, array $occurrences = []): array
    {
        return EventContractMapper::seriesResource($series, $occurrences);
    }
}
