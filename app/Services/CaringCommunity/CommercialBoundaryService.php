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
 * Classifies every Caring Community-adjacent capability into one of four
 * commercial categories so an admin can answer the question "is X part of the
 * open-source AGPL package, a tenant config knob, a private deployment layer,
 * or a commercial add-on?" without re-deriving the answer each time.
 *
 *   - agpl_public        AGPL public code in the open-source repo. Anyone may
 *                        deploy this for free.
 *   - tenant_config      Feature toggle in tenants.features. AGPL code; the
 *                        tenant chooses whether to enable.
 *   - private_deployment Operational deployment layer (build accounts,
 *                        infrastructure ownership). Not a code license issue.
 *   - commercial         Requires a separate commercial agreement with the
 *                        platform operator. Not part of the AGPL package.
 *
 * Storage: the canonical matrix lives in code (see canonicalCapabilities()).
 * Per-tenant overrides are stored as a single JSON envelope in
 * tenant_settings under `caring.commercial_boundary` with the shape:
 *
 *     { "overrides": { "<capability_key>": "<classification>" } }
 *
 * Overrides let a tenant deploying privately re-classify capabilities for
 * their own internal map without changing the canonical AGPL view.
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
     *   categories: list<array{key: string, label: string}>,
     *   classifications: list<array{key: string, label: string, description: string}>,
     *   capabilities: list<array<string, mixed>>,
     *   overrides_count: int,
     *   last_updated_at: ?string,
     * }
     */
    public function matrix(int $tenantId): array
    {
        $overrides = $this->loadOverrides($tenantId);
        $capabilities = [];

        foreach ($this->canonicalCapabilities() as $cap) {
            $key = $cap['key'];
            $hasOverride = array_key_exists($key, $overrides);
            $effective = $hasOverride
                ? (string) $overrides[$key]
                : (string) $cap['default_classification'];

            $capabilities[] = [
                'key'                    => $key,
                'label'                  => $cap['label'],
                'description'            => $cap['description'],
                'category'               => $cap['category'],
                'default_classification' => $cap['default_classification'],
                'effective_classification' => $effective,
                'is_overridden'          => $hasOverride,
                'agpl_module'            => $cap['agpl_module'],
                'notes'                  => $cap['notes'],
            ];
        }

        return [
            'categories'      => $this->categories(),
            'classifications' => $this->classificationDefinitions(),
            'capabilities'    => $capabilities,
            'overrides_count' => count($overrides),
            'last_updated_at' => $this->latestUpdatedAt($tenantId),
        ];
    }

    /**
     * Set or clear an override for a single capability.
     *
     * Pass $classification = null to clear an override and revert the
     * capability to its canonical default.
     *
     * @return array{
     *   matrix?: array<string, mixed>,
     *   errors?: list<array{field: string, message: string}>,
     * }
     */
    public function setOverride(int $tenantId, string $capabilityKey, ?string $classification): array
    {
        $errors = [];

        if (!$this->isValidCapabilityKey($capabilityKey)) {
            $errors[] = ['field' => 'capability_key', 'message' => 'unknown capability'];
        }

        if ($classification !== null && !in_array($classification, self::CLASSIFICATIONS, true)) {
            $errors[] = ['field' => 'classification', 'message' => 'must be one of: ' . implode(', ', self::CLASSIFICATIONS)];
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

    // -----------------------------------------------------------------------
    // Canonical data
    // -----------------------------------------------------------------------

    /**
     * @return list<array{key: string, label: string}>
     */
    private function categories(): array
    {
        return [
            ['key' => 'core_caring',             'label' => 'Core caring community'],
            ['key' => 'community_governance',    'label' => 'Community governance'],
            ['key' => 'gamification_engagement', 'label' => 'Gamification & engagement'],
            ['key' => 'commercial_layer',        'label' => 'Commercial layer'],
            ['key' => 'advanced_ai',             'label' => 'Advanced AI'],
            ['key' => 'mobile_native',           'label' => 'Mobile native'],
            ['key' => 'regional_intelligence',   'label' => 'Regional intelligence'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    private function classificationDefinitions(): array
    {
        return [
            [
                'key'         => 'agpl_public',
                'label'       => 'AGPL public',
                'description' => 'AGPL public code in the open-source repo. Anyone may deploy this for free.',
            ],
            [
                'key'         => 'tenant_config',
                'label'       => 'Tenant config',
                'description' => 'Feature toggle in `tenants.features`. AGPL code; tenant chooses whether to enable.',
            ],
            [
                'key'         => 'private_deployment',
                'label'       => 'Private deployment',
                'description' => 'Operational deployment layer (build accounts, infrastructure ownership). Not a code license issue.',
            ],
            [
                'key'         => 'commercial',
                'label'       => 'Commercial',
                'description' => 'Requires a separate commercial agreement with the platform operator. Not part of the AGPL package.',
            ],
        ];
    }

    /**
     * The hardcoded canonical capability matrix. Keep this list ordered by
     * category, then by importance, so the admin UI groups read sensibly.
     *
     * @return list<array{
     *   key: string,
     *   label: string,
     *   description: string,
     *   category: string,
     *   default_classification: string,
     *   agpl_module: bool,
     *   notes: string,
     * }>
     */
    private function canonicalCapabilities(): array
    {
        return [
            // ---- core_caring -----------------------------------------------
            [
                'key' => 'caring_community_module',
                'label' => 'Caring Community module',
                'description' => 'The umbrella feature flag that activates the full Caring Community workflow inside a tenant.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => 'Master gate that other caring features hang off.',
            ],
            [
                'key' => 'caring_help_requests',
                'label' => 'Help requests',
                'description' => 'Caring help-request flow with on-behalf-of caregiver requests, matching, and acceptance lifecycle.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_support_relationships',
                'label' => 'Support relationships',
                'description' => 'Long-running 1:1 caring relationships between a recipient and one or more supporters with weekly check-ins.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_caregiver_links',
                'label' => 'Caregiver links',
                'description' => 'Verified link between an informal caregiver and the person they care for, allowing the caregiver to request help on behalf.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_substitute_cover',
                'label' => 'Substitute / cover scheduling',
                'description' => 'Find substitute caregivers when the primary supporter is unavailable, with conflict detection and confirmation.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_warmth_pass',
                'label' => 'Warmth Pass',
                'description' => 'Recipient-side dignity layer that controls who can see their needs, with consent-based introduction flows.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_emergency_alerts',
                'label' => 'Emergency alerts',
                'description' => 'Recipient or caregiver-triggered emergency broadcast to a vetted radius of trusted neighbours.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_sub_regions',
                'label' => 'Caring sub-regions',
                'description' => 'Sub-regional cells inside a tenant (canton, district, neighbourhood) with their own coordinator and KPI roll-up.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_research_consent',
                'label' => 'Research consent flag',
                'description' => 'Member opt-in flag for inclusion in anonymised research datasets shared with academic or municipal partners.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_municipal_roi',
                'label' => 'Municipal ROI report',
                'description' => 'CHF-denominated formal-care-cost-offset and prevention-value report for B2G procurement conversations.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_pilot_scoreboard',
                'label' => 'Pilot scoreboard',
                'description' => 'Live KPI tile pack used during pilot stand-ups with funder-facing momentum metrics.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_disclosure_pack',
                'label' => 'FADP / nDSG disclosure pack',
                'description' => 'Editable Swiss data-protection disclosure pack a pilot can hand to legal counsel before resident onboarding.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'caring_operating_policy',
                'label' => 'Operating policy workshop',
                'description' => 'Schema-driven policy form covering approval authority, SLA windows, legacy-hour settlement, CHF rates and cadence.',
                'category' => 'core_caring',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],

            // ---- community_governance --------------------------------------
            [
                'key' => 'safeguarding_reports',
                'label' => 'Safeguarding reports',
                'description' => 'Member-to-coordinator safeguarding flag flow with audit trail and escalation routing.',
                'category' => 'community_governance',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'trust_tier_system',
                'label' => 'Trust tier system',
                'description' => 'Member trust progression (new, trusted, vetted) gating which caring actions a member can perform.',
                'category' => 'community_governance',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'municipal_verification',
                'label' => 'Municipal verification badge',
                'description' => 'Optional municipal partner-issued verification stamp on a member profile (residence or background-check based).',
                'category' => 'community_governance',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],

            // ---- gamification_engagement -----------------------------------
            [
                'key' => 'xp_badges_journeys',
                'label' => 'XP, badges & journeys',
                'description' => 'Engagement layer with XP, badges, journeys and challenges that reward caring participation.',
                'category' => 'gamification_engagement',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'appreciation_messages',
                'label' => 'Appreciation messages',
                'description' => 'Lightweight thank-you / kudos flow letting recipients publicly acknowledge supporters.',
                'category' => 'gamification_engagement',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],

            // ---- commercial_layer ------------------------------------------
            [
                'key' => 'local_advertising_campaigns',
                'label' => 'Local advertising campaigns',
                'description' => 'In-app placements sold to local merchants. AGPL code, opt-in per tenant, revenue stays with the deployer.',
                'category' => 'commercial_layer',
                'default_classification' => 'tenant_config',
                'agpl_module' => true,
                'notes' => 'Opt-in per tenant. AGPL code, no revenue share back to platform operator.',
            ],
            [
                'key' => 'paid_push_campaigns',
                'label' => 'Paid push campaigns',
                'description' => 'Targeted push-notification campaigns merchants can purchase to reach opted-in members.',
                'category' => 'commercial_layer',
                'default_classification' => 'tenant_config',
                'agpl_module' => true,
                'notes' => 'Tenant must enable + accept FCM cost responsibility.',
            ],
            [
                'key' => 'premium_member_tier',
                'label' => 'Premium member tier',
                'description' => 'Optional paid member tier with extra perks — tenant decides whether to operate one.',
                'category' => 'commercial_layer',
                'default_classification' => 'tenant_config',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'merchant_loyalty_coupons',
                'label' => 'Merchant loyalty & coupons',
                'description' => 'Local merchant loyalty stamp cards and coupon redemption tied to time-credit balances.',
                'category' => 'commercial_layer',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'verein_dues_collection',
                'label' => 'Verein / association dues',
                'description' => 'Recurring association dues collection via Stripe with member statements and reconciliation.',
                'category' => 'commercial_layer',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => 'Stripe account is operational ownership; the code is AGPL.',
            ],

            // ---- advanced_ai -----------------------------------------------
            [
                'key' => 'smart_matching_engine',
                'label' => 'Smart matching engine',
                'description' => 'Heuristic matcher that pairs help requests with likely supporters using skills, distance and history.',
                'category' => 'advanced_ai',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => '',
            ],
            [
                'key' => 'ki_agenten_framework',
                'label' => 'KI-Agenten framework',
                'description' => 'Per-tenant agent runtime that lets coordinators define structured assistants for repeatable workflows.',
                'category' => 'advanced_ai',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => 'Code is AGPL; tenant supplies its own LLM API key (separate cost).',
            ],
            [
                'key' => 'ai_chat_assistant',
                'label' => 'AI chat assistant',
                'description' => 'Member-facing chat assistant grounded in the tenant knowledge base for help and onboarding.',
                'category' => 'advanced_ai',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => 'Tenant supplies its own OpenAI key.',
            ],
            [
                'key' => 'embedding_recommendations',
                'label' => 'Embedding-based recommendations',
                'description' => 'OpenAI embeddings power listing, member, and group recommendations across the platform.',
                'category' => 'advanced_ai',
                'default_classification' => 'agpl_public',
                'agpl_module' => true,
                'notes' => 'Tenant pays the embedding API bill.',
            ],

            // ---- mobile_native ---------------------------------------------
            [
                'key' => 'tenant_branded_native_app',
                'label' => 'Tenant-branded native app',
                'description' => 'iOS / Android Capacitor app published under the tenant brand. The code is AGPL; the build pipeline and store accounts are not.',
                'category' => 'mobile_native',
                'default_classification' => 'private_deployment',
                'agpl_module' => true,
                'notes' => 'Source is AGPL. Apple / Google developer accounts, signing keys, build pipeline and review workflow are operational ownership and not part of the package.',
            ],

            // ---- regional_intelligence -------------------------------------
            [
                'key' => 'paid_regional_analytics',
                'label' => 'Paid regional analytics (B2G)',
                'description' => 'Cross-tenant aggregate analytics for cantonal / municipal procurement — sold separately to public-sector buyers.',
                'category' => 'regional_intelligence',
                'default_classification' => 'commercial',
                'agpl_module' => false,
                'notes' => 'Separate B2G product. Requires a commercial agreement with the platform operator.',
            ],
            [
                'key' => 'partner_api_access',
                'label' => 'Partner API access',
                'description' => 'Outbound API for research, government and integration partners with rate-limited cross-tenant queries.',
                'category' => 'regional_intelligence',
                'default_classification' => 'commercial',
                'agpl_module' => false,
                'notes' => 'Commercial agreement required. Not exposed to AGPL deployers by default.',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Persistence helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
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

        // Sanitise: only known capability keys + valid classifications survive.
        $clean = [];
        foreach ($overrides as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                continue;
            }
            if (!$this->isValidCapabilityKey($k)) {
                continue;
            }
            if (!in_array($v, self::CLASSIFICATIONS, true)) {
                continue;
            }
            $clean[$k] = $v;
        }

        return $clean;
    }

    /**
     * @param array<string, string> $overrides
     */
    private function persistOverrides(int $tenantId, array $overrides): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            return;
        }

        $payload = ['overrides' => $overrides];

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'description'   => 'AG82 Commercial Boundary Map per-tenant overrides',
                'updated_at'    => now(),
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
        foreach ($this->canonicalCapabilities() as $cap) {
            if ($cap['key'] === $key) {
                return true;
            }
        }
        return false;
    }
}
