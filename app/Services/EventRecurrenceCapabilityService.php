<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fail-safe runtime recurrence contract for maintained clients.
 *
 * Only bounded capabilities are exposed. Raw configuration, schema names,
 * probe errors, tenant identifiers and rollout secrets never leave this class.
 */
class EventRecurrenceCapabilityService
{
    public const CONTRACT_VERSION = 1;
    public const LEGACY_MAX_OCCURRENCES = 52;

    /** @var list<string> */
    private const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var list<string> */
    private const FINITE_END_TYPES = ['after_count', 'on_date'];

    /**
     * @return array{
     *   contract_version:int,
     *   engine:string,
     *   structured_input:bool,
     *   supported_frequencies:list<string>,
     *   max_occurrences:int,
     *   supported_end_types:list<string>,
     *   supports_rolling_never:bool,
     *   supports_effective_revisions:bool,
     *   supports_definition_blueprints:bool,
     *   schema_ready:bool,
     *   rollout_state:string
     * }
     */
    public function capabilities(): array
    {
        $v2Flag = config('events.recurrence.engine_v2_enabled', false);
        $v2Enabled = is_bool($v2Flag) && $v2Flag;
        $rawMaxOccurrences = config('events.recurrence.max_occurrences', 366);
        $maxConfigurationValid = is_int($rawMaxOccurrences)
            && $rawMaxOccurrences >= 1
            && $rawMaxOccurrences <= 5000;

        if (! $v2Enabled) {
            return $this->result(
                'legacy',
                self::LEGACY_MAX_OCCURRENCES,
                false,
                false,
                false,
                true,
                'legacy',
            );
        }

        $maxOccurrences = $maxConfigurationValid ? $rawMaxOccurrences : 1;
        $materialization = $this->materializationState();
        $schemaReady = $materialization['schema_available']
            && $this->revisionSchemaAvailable();
        $finiteReady = $schemaReady && $maxConfigurationValid;
        $supportsRollingNever = $finiteReady
            && $materialization['configuration']['enabled']
            && $materialization['configuration']['engine_v2_writer_enabled']
            && $materialization['configuration']['valid'];
        $supportsEffectiveRevisions = $finiteReady;
        $blueprintFlag = config('events.recurrence.definition_blueprints.enabled', false);
        $supportsDefinitionBlueprints = $supportsRollingNever
            && is_bool($blueprintFlag)
            && $blueprintFlag
            && $this->definitionBlueprintSchemaAvailable();
        $rolloutState = ! $finiteReady
            ? 'v2_degraded'
            : ($supportsRollingNever ? 'v2_rolling' : 'v2_finite');

        return $this->result(
            'v2',
            $maxOccurrences,
            $supportsRollingNever,
            $supportsEffectiveRevisions,
            $supportsDefinitionBlueprints,
            $schemaReady,
            $rolloutState,
        );
    }

    /**
     * @return array{
     *   schema_available:bool,
     *   configuration:array{enabled:bool,engine_v2_writer_enabled:bool,valid:bool}
     * }
     */
    protected function materializationState(): array
    {
        try {
            $materializer = app(EventRecurrenceMaterializationService::class);
            $configuration = $materializer->configuration();

            return [
                'schema_available' => $materializer->schemaAvailable(),
                'configuration' => [
                    'enabled' => ($configuration['enabled'] ?? false) === true,
                    'engine_v2_writer_enabled' => (
                        $configuration['engine_v2_writer_enabled'] ?? false
                    ) === true,
                    'valid' => ($configuration['valid'] ?? false) === true,
                ],
            ];
        } catch (Throwable) {
            return $this->unavailableMaterializationState();
        }
    }

    protected function revisionSchemaAvailable(): bool
    {
        try {
            if (! Schema::hasTable('event_recurrence_revisions')
                || ! Schema::hasTable('event_recurrence_occurrence_ledger')
                || ! Schema::hasTable('event_recurrence_rules')) {
                return false;
            }
            foreach (['effective_revision_version', 'materialized_set_version'] as $column) {
                if (! Schema::hasColumn('event_recurrence_rules', $column)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function definitionBlueprintSchemaAvailable(): bool
    {
        try {
            return app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *   contract_version:int,
     *   engine:string,
     *   structured_input:bool,
     *   supported_frequencies:list<string>,
     *   max_occurrences:int,
     *   supported_end_types:list<string>,
     *   supports_rolling_never:bool,
     *   supports_effective_revisions:bool,
     *   supports_definition_blueprints:bool,
     *   schema_ready:bool,
     *   rollout_state:string
     * }
     */
    private function result(
        string $engine,
        int $maxOccurrences,
        bool $supportsRollingNever,
        bool $supportsEffectiveRevisions,
        bool $supportsDefinitionBlueprints,
        bool $schemaReady,
        string $rolloutState,
    ): array {
        $endTypes = self::FINITE_END_TYPES;
        if ($supportsRollingNever) {
            $endTypes[] = 'never';
        }

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'engine' => $engine,
            'structured_input' => true,
            'supported_frequencies' => self::FREQUENCIES,
            'max_occurrences' => $maxOccurrences,
            'supported_end_types' => $endTypes,
            'supports_rolling_never' => $supportsRollingNever,
            'supports_effective_revisions' => $supportsEffectiveRevisions,
            'supports_definition_blueprints' => $supportsDefinitionBlueprints,
            'schema_ready' => $schemaReady,
            'rollout_state' => $rolloutState,
        ];
    }

    /**
     * @return array{
     *   schema_available:bool,
     *   configuration:array{enabled:bool,engine_v2_writer_enabled:bool,valid:bool}
     * }
     */
    private function unavailableMaterializationState(): array
    {
        return [
            'schema_available' => false,
            'configuration' => [
                'enabled' => false,
                'engine_v2_writer_enabled' => false,
                'valid' => false,
            ],
        ];
    }
}
