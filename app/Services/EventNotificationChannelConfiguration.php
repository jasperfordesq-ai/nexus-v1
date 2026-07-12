<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Fail-closed parser and payload-free health view for outbox delivery channels. */
final class EventNotificationChannelConfiguration
{
    private const SUPPORTED = ['email', 'in_app', 'push'];

    /** @return non-empty-list<string> */
    public static function resolve(): array
    {
        $inspection = self::inspect();
        if (! $inspection['valid']) {
            throw new RuntimeException('event_notification_channel_configuration_invalid');
        }

        /** @var non-empty-list<string> $channels */
        $channels = $inspection['configured_supported'];
        return $channels;
    }

    /**
     * @return array{
     *   valid:bool,
     *   reason:?string,
     *   configured_supported:list<string>,
     *   invalid_entry_count:int,
     *   supported:list<string>
     * }
     */
    public static function inspect(): array
    {
        $configured = config('events.notification_delivery.channels', self::SUPPORTED);
        if (! is_array($configured)) {
            return self::invalid('not_array', 1);
        }

        $channels = [];
        $invalid = 0;
        foreach ($configured as $channel) {
            if (! is_string($channel) || ! in_array($channel, self::SUPPORTED, true)) {
                $invalid++;
                continue;
            }
            if (! in_array($channel, $channels, true)) {
                $channels[] = $channel;
            }
        }

        $reason = match (true) {
            $invalid > 0 => 'invalid_entries',
            $channels === [] => 'empty',
            default => null,
        };

        return [
            'valid' => $reason === null,
            'reason' => $reason,
            'configured_supported' => $channels,
            'invalid_entry_count' => $invalid,
            'supported' => self::SUPPORTED,
        ];
    }

    /**
     * @return array{
     *   valid:false,
     *   reason:string,
     *   configured_supported:list<string>,
     *   invalid_entry_count:int,
     *   supported:list<string>
     * }
     */
    private static function invalid(string $reason, int $invalid): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'configured_supported' => [],
            'invalid_entry_count' => $invalid,
            'supported' => self::SUPPORTED,
        ];
    }
}
