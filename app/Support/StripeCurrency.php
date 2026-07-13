<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Stripe presentment-currency validation and major/minor-unit conversion. */
final class StripeCurrency
{
    /** @var list<string> */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
        'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /** @var list<string> */
    private const SUPPORTED = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
        'BSD', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP',
        'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR',
        'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD',
        'HNL', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JOD', 'JPY',
        'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP',
        'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP',
        'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK',
        'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG',
        'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD',
        'SHP', 'SLE', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TND', 'TOP',
        'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VND',
        'VUV', 'WST', 'XAF', 'XCD', 'XCG', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW',
    ];

    public static function normalize(string $currency): string
    {
        $normalized = strtoupper(trim($currency));
        if (! in_array($normalized, self::SUPPORTED, true)) {
            throw new InvalidArgumentException(__('api.marketplace_payment_currency_unsupported'));
        }

        return $normalized;
    }

    public static function exponent(string $currency): int
    {
        $currency = self::normalize($currency);
        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }

        return 2;
    }

    public static function toMinor(float $amount, string $currency): int
    {
        $exponent = self::exponent($currency);
        $normalized = self::normalize($currency);
        if (in_array($normalized, ['ISK', 'UGX'], true)
            && abs($amount - round($amount)) > 0.0000001) {
            throw new InvalidArgumentException(__('api.marketplace_payment_currency_precision_invalid'));
        }
        $factor = 10 ** $exponent;
        $minor = (int) round($amount * $factor);
        if (abs($amount - ($minor / $factor)) > 0.0000001) {
            throw new InvalidArgumentException(__('api.marketplace_payment_currency_precision_invalid'));
        }

        return $minor;
    }

    public static function fromMinor(int $amount, string $currency): float
    {
        return $amount / (10 ** self::exponent($currency));
    }

    public static function roundMajor(float $amount, string $currency): float
    {
        return round($amount, self::exponent($currency));
    }

    /** Format a major-unit amount using the currency's supported precision. */
    public static function formatMajor(float $amount, string $currency): string
    {
        $currency = self::normalize($currency);
        return number_format(
            self::roundMajor($amount, $currency),
            self::exponent($currency),
            '.',
            ',',
        );
    }
}
