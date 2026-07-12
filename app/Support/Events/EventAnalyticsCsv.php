<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

/** Formula-safe, identity-free CSV projection for the Event analytics contract. */
final class EventAnalyticsCsv
{
    /** @param array<string,mixed> $summary @return list<array{string,string,string}> */
    public static function rows(array $summary): array
    {
        $rows = [];
        $walk = function (array $value, string $prefix = '') use (&$walk, &$rows): void {
            foreach ($value as $key => $item) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                if (is_array($item)) {
                    if (array_key_exists('value', $item) && array_key_exists('suppressed', $item)) {
                        $rows[] = [
                            self::cell($path),
                            self::cell($item['value'] ?? null),
                            ($item['suppressed'] ?? false) ? '1' : '0',
                        ];
                        continue;
                    }
                    $walk($item, $path);
                    continue;
                }
                $rows[] = [self::cell($path), self::cell($item), '0'];
            }
        };
        $walk($summary);

        return $rows;
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $cell = is_scalar($value) ? (string) $value : '';
        $candidate = ltrim($cell, " \t\r\n");
        if ($candidate !== '' && str_contains('=+-@', $candidate[0])) {
            return "'" . $cell;
        }

        return $cell;
    }
}
