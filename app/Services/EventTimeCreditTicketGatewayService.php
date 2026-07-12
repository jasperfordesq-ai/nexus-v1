<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventTicketingException;

/** Explicit fail-closed boundary until an approved canonical escrow adapter exists. */
final class EventTimeCreditTicketGatewayService
{
    public function supportsActivation(): bool
    {
        return false;
    }

    public function materialize(mixed ...$context): never
    {
        $this->unavailable();
    }

    public function hold(mixed ...$context): never
    {
        $this->unavailable();
    }

    public function settle(mixed ...$context): never
    {
        $this->unavailable();
    }

    public function release(mixed ...$context): never
    {
        $this->unavailable();
    }

    public function refund(mixed ...$context): never
    {
        $this->unavailable();
    }

    private function unavailable(): never
    {
        throw new EventTicketingException('event_ticket_time_credit_gateway_unavailable');
    }
}
