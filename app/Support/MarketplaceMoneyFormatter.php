<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support;

/** Consistent marketplace money labels using Stripe presentment exponents. */
final class MarketplaceMoneyFormatter
{
    public static function format(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        try {
            $precision = StripeCurrency::exponent($currency);
        } catch (\InvalidArgumentException) {
            // Historical/imported records can pre-date the supported-currency
            // guard. Their pages must remain renderable while clearly showing
            // the stored code; new checkout paths still reject that currency.
            $precision = 2;
        }

        return trim($currency . ' ' . number_format($amount, $precision));
    }
}
