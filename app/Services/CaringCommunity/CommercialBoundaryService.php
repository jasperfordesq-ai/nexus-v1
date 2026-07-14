<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG82 — Commercial Boundary Map.
 *
 * The service owns stable capability, category, and classification codes only.
 * User-facing names, descriptions, and export copy belong to the React locale
 * catalogue so the same response can be rendered in any administrator locale.
 */
class CommercialBoundaryService
{
    public const SETTING_KEY = 'caring.commercial_boundary';

    public const CLASSIFICATIONS = [
        'agpl_public',
        'tenant_config',
        'private_deployment',
        'commercial',
    ];

    /**
     * Build the full matrix for a tenant, with overrides applied.
     *
     * @return array{
     *   categories: list<array{key: string}>,
     *   classifications: list<array{key: string}>,
     *   capabilities: list<array<string, mixed>>,
     *   overrides_count: int,
     *   last_updated_at: ?string,
     * }
     */
    public function matrix(int $tenantId): array
    {
        $overrides = $this->loadOverrides($tenantId);
        $capabilities = [];

        foreach ($this->canonicalCapabilities() as $capability) {
            $key = $capability['key'];
            $hasOverride = array_key_exists($key, $overrides);
            $effective = $hasOverride
                ? (string) $overrides[$key]
                : (string) $capability['default_classification'];

            $capabilities[] = [
                'key' => $key,
                'category' => $capability['category'],
                'default_classification' => $capability['default_classification'],
                'effective_classification' => $effective,
                'is_overridden' => $hasOverride,
                'agpl_module' => $capability['agpl_module'],
            ];
        }

        return [
            'categories' => $this->categories(),
            'classifications' => $this->classificationDefinitions(),
            'capabilities' => $capabilities,
            'overrides_count' => count($overrides),
            'last_updated_at' => $this->latestUpdatedAt($tenantId),
        ];
    }

    /**
     * Set or clear an override for a single capability.
     *
     * @return array{
     *   matrix?: array<string, mixed>,
     *   errors?: list<array{field: string, code: string, params: array<string, mixed>}>,
     * }
     */
    public function setOverride(int $tenantId, string $capabilityKey, ?string $classification): array
    {
        $errors = [];

        if (!$this->isValidCapabilityKey($capabilityKey)) {
            $errors[] = [
                'field' => 'capability_key',
                'code' => 'UNKNOWN_CAPABILITY',
                'params' => ['capability_key' => $capabilityKey],
            ];
        }

        if ($classification !== null && !in_array($classification, self::CLASSIFICATIONS, true)) {
            $errors[] = [
                'field' => 'classification',
                'code' => 'INVALID_CLASSIFICATION',
                'params' => ['classifications' => self::CLASSIFICATIONS],
            ];
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $overrides = $this->loadOverrides($tenantId);
        if ($classification === null) {
            unset($overrides[$capabilityKey]);
        } else {
            $overrides[$capabilityKey] = $classification;
        }

        $this->persistOverrides($tenantId, $overrides);

        return ['matrix' => $this->matrix($tenantId)];
    }

    /** @return list<array{key: string}> */
    private function categories(): array
    {
        return array_map(
            static fn (string $key): array => ['key' => $key],
            [
                'core_caring',
                'community_governance',
                'gamification_engagement',
                'commercial_layer',
                'advanced_ai',
                'mobile_native',
                'regional_intelligence',
            ],
        );
    }

    /** @return list<array{key: string}> */
    private function classificationDefinitions(): array
    {
        return array_map(
            static fn (string $key): array => ['key' => $key],
            self::CLASSIFICATIONS,
        );
    }

    /**
     * Canonical capability metadata. All prose is translated by the consumer.
     *
     * @return list<array{
     *   key: string,
     *   category: string,
     *   default_classification: string,
     *   agpl_module: bool,
     * }>
     */
    private function canonicalCapabilities(): array
    {
        return [
            ['key' => 'caring_community_module', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_help_requests', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_support_relationships', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_caregiver_links', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_substitute_cover', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_warmth_pass', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_emergency_alerts', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_sub_regions', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_research_consent', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_municipal_roi', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_pilot_scoreboard', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_disclosure_pack', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'caring_operating_policy', 'category' => 'core_caring', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'safeguarding_reports', 'category' => 'community_governance', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'trust_tier_system', 'category' => 'community_governance', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'municipal_verification', 'category' => 'community_governance', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'xp_badges_journeys', 'category' => 'gamification_engagement', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'appreciation_messages', 'category' => 'gamification_engagement', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'local_advertising_campaigns', 'category' => 'commercial_layer', 'default_classification' => 'tenant_config', 'agpl_module' => true],
            ['key' => 'paid_push_campaigns', 'category' => 'commercial_layer', 'default_classification' => 'tenant_config', 'agpl_module' => true],
            ['key' => 'premium_member_tier', 'category' => 'commercial_layer', 'default_classification' => 'tenant_config', 'agpl_module' => true],
            ['key' => 'merchant_loyalty_coupons', 'category' => 'commercial_layer', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'verein_dues_collection', 'category' => 'commercial_layer', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'smart_matching_engine', 'category' => 'advanced_ai', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'ki_agenten_framework', 'category' => 'advanced_ai', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'ai_chat_assistant', 'category' => 'advanced_ai', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'embedding_recommendations', 'category' => 'advanced_ai', 'default_classification' => 'agpl_public', 'agpl_module' => true],
            ['key' => 'tenant_branded_native_app', 'category' => 'mobile_native', 'default_classification' => 'private_deployment', 'agpl_module' => true],
            ['key' => 'paid_regional_analytics', 'category' => 'regional_intelligence', 'default_classification' => 'commercial', 'agpl_module' => false],
            ['key' => 'partner_api_access', 'category' => 'regional_intelligence', 'default_classification' => 'commercial', 'agpl_module' => false],
        ];
    }

    /** @return array<string, string> */
    private function loadOverrides(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return [];
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if (!$row || empty($row->setting_value)) {
            return [];
        }

        $decoded = json_decode((string) $row->setting_value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $overrides = $decoded['overrides'] ?? [];
        if (!is_array($overrides)) {
            return [];
        }

        $clean = [];
        foreach ($overrides as $key => $classification) {
            if (!is_string($key) || !is_string($classification)) {
                continue;
            }
            if (!$this->isValidCapabilityKey($key)) {
                continue;
            }
            if (!in_array($classification, self::CLASSIFICATIONS, true)) {
                continue;
            }
            $clean[$key] = $classification;
        }

        return $clean;
    }

    /** @param array<string, string> $overrides */
    private function persistOverrides(int $tenantId, array $overrides): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            return;
        }

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode(['overrides' => $overrides], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type' => 'json',
                'category' => 'caring_community',
                'description' => 'AG82 commercial-boundary overrides',
                'updated_at' => now(),
            ],
        );
    }

    private function latestUpdatedAt(int $tenantId): ?string
    {
        if (!Schema::hasTable('tenant_settings')) {
            return null;
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        return $row?->updated_at ? (string) $row->updated_at : null;
    }

    private function isValidCapabilityKey(string $key): bool
    {
        foreach ($this->canonicalCapabilities() as $capability) {
            if ($capability['key'] === $key) {
                return true;
            }
        }

        return false;
    }
}
