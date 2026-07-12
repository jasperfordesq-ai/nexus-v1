<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** Allowlisted, stable recurrence capability projection. */
final class EventRecurrenceCapabilityResource
{
    /** @var list<string> */
    private const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var list<string> */
    private const END_TYPES = ['after_count', 'on_date', 'never'];

    /** @param array<string,mixed> $capabilities @return array<string,mixed> */
    public static function fromCapabilities(array $capabilities): array
    {
        $engine = in_array($capabilities['engine'] ?? null, ['legacy', 'v2'], true)
            ? (string) $capabilities['engine']
            : 'legacy';
        $schemaReady = ($capabilities['schema_ready'] ?? false) === true;
        $v2Ready = $engine === 'v2' && $schemaReady;
        $supportsRollingNever = $v2Ready
            && ($capabilities['supports_rolling_never'] ?? false) === true;
        $supportsEffectiveRevisions = $v2Ready
            && ($capabilities['supports_effective_revisions'] ?? false) === true;
        $supportsDefinitionBlueprints = $supportsRollingNever
            && ($capabilities['supports_definition_blueprints'] ?? false) === true;
        $rawRolloutState = (string) ($capabilities['rollout_state'] ?? '');
        $rolloutState = match (true) {
            $engine === 'legacy' => 'legacy',
            ! $schemaReady => 'v2_degraded',
            $supportsRollingNever => 'v2_rolling',
            $rawRolloutState === 'v2_degraded' => 'v2_degraded',
            default => 'v2_finite',
        };
        $endTypes = self::allowlistedList(
            $capabilities['supported_end_types'] ?? null,
            self::END_TYPES,
        );
        if (! $supportsRollingNever) {
            $endTypes = array_values(array_filter(
                $endTypes,
                static fn (string $endType): bool => $endType !== 'never',
            ));
        }
        $maxOccurrences = max(1, min(
            (int) ($capabilities['max_occurrences'] ?? 1),
            $engine === 'legacy' ? 52 : 5000,
        ));

        return [
            'contract_version' => 1,
            'engine' => $engine,
            'structured_input' => ($capabilities['structured_input'] ?? false) === true,
            'supported_frequencies' => self::allowlistedList(
                $capabilities['supported_frequencies'] ?? null,
                self::FREQUENCIES,
            ),
            'max_occurrences' => $maxOccurrences,
            'supported_end_types' => $endTypes,
            'supports_rolling_never' => $supportsRollingNever,
            'supports_effective_revisions' => $supportsEffectiveRevisions,
            'supports_definition_blueprints' => $supportsDefinitionBlueprints,
            'schema_ready' => $schemaReady,
            'rollout_state' => $rolloutState,
        ];
    }

    /** @param mixed $values @param list<string> $allowlist @return list<string> */
    private static function allowlistedList(mixed $values, array $allowlist): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            return [];
        }

        return array_values(array_filter(
            $allowlist,
            static fn (string $value): bool => in_array($value, $values, true),
        ));
    }
}
