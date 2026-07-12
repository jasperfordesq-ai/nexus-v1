<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

/** Spreadsheet-safe scalar formatting for registration exports. */
final class EventRegistrationCsv
{
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            $value = array_is_list($value)
                ? implode('; ', array_map(static fn (mixed $item): string => self::plain($item), $value))
                : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $text = (string) $value;

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $text) === 1 ? "'{$text}" : $text;
    }

    private static function plain(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return $value === null ? '' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
