<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Events\EventFederationInboundResult;
use App\Support\Events\EventFederationReceiptContract;

/** Public partner receipt with no local projection or payload identifiers. */
final class EventFederationReceiptResource
{
    /** @return array<string,int|string> */
    public static function fromResult(EventFederationInboundResult $result): array
    {
        return EventFederationReceiptContract::fromResult($result);
    }
}
