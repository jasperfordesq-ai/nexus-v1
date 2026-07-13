<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Support\StripeCurrency;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

final class StripeCurrencyTest extends TestCase
{
    public function test_two_decimal_currency_round_trip(): void
    {
        $this->assertSame(1234, StripeCurrency::toMinor(12.34, 'EUR'));
        $this->assertSame(12.34, StripeCurrency::fromMinor(1234, 'eur'));
    }

    public function test_zero_decimal_currency_uses_whole_units(): void
    {
        $this->assertSame(123, StripeCurrency::toMinor(123.0, 'JPY'));
        $this->assertSame(123.0, StripeCurrency::fromMinor(123, 'jpy'));
    }

    public function test_zero_decimal_currency_rejects_fractional_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StripeCurrency::toMinor(1.23, 'JPY');
    }

    public function test_non_zero_decimal_currency_uses_two_decimal_stripe_units(): void
    {
        $this->assertSame(123, StripeCurrency::toMinor(1.23, 'BHD'));
        $this->assertSame(1.23, StripeCurrency::fromMinor(123, 'bhd'));
    }

    public function test_non_zero_decimal_currency_rejects_third_decimal_place(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StripeCurrency::toMinor(1.234, 'KWD');
    }

    public function test_isk_and_ugx_use_two_decimal_api_units_but_require_whole_major_amounts(): void
    {
        $this->assertSame(500, StripeCurrency::toMinor(5.0, 'ISK'));
        $this->assertSame(500, StripeCurrency::toMinor(5.0, 'UGX'));

        foreach (['ISK', 'UGX'] as $currency) {
            try {
                StripeCurrency::toMinor(5.25, $currency);
                $this->fail("{$currency} must reject fractional major amounts.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unknown_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StripeCurrency::toMinor(1.00, 'ZZZ');
    }

    public function test_major_amount_formatting_uses_currency_exponent(): void
    {
        $this->assertSame('1,234.50', StripeCurrency::formatMajor(1234.5, 'EUR'));
        $this->assertSame('1,235', StripeCurrency::formatMajor(1234.6, 'JPY'));
        $this->assertSame('1.23', StripeCurrency::formatMajor(1.23, 'BHD'));
    }
}
