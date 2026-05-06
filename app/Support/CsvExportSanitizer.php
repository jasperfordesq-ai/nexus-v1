<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support;

/**
 * Neutralizes values that spreadsheet tools may interpret as executable formulas.
 */
final class CsvExportSanitizer
{
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $cell = (string) $value;

        if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell) === 1) {
            return "'" . $cell;
        }

        return $cell;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }
}
