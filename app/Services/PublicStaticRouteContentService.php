<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;

class PublicStaticRouteContentService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const DEFINITIONS = [
        'developers' => [
            'route_key' => 'developers',
            'path' => '/developers',
            'locale_file' => 'common.json',
            'namespace' => 'developers',
            'title' => 'page_title',
            'lead' => 'hero_subtitle',
            'sections' => [
                [
                    'key' => 'features',
                    'items' => [
                        ['key' => 'oauth', 'title' => 'feature_oauth_title', 'description' => 'feature_oauth_body'],
                        ['key' => 'curated', 'title' => 'feature_curated_title', 'description' => 'feature_curated_body'],
                        ['key' => 'webhooks', 'title' => 'feature_webhooks_title', 'description' => 'feature_webhooks_body'],
                        ['key' => 'rate_limits', 'title' => 'feature_rate_limit_title', 'description' => 'feature_rate_limit_body'],
                    ],
                ],
                ['key' => 'request_access', 'title' => 'request_access_cta', 'body' => 'request_access_body'],
            ],
        ],
        'developers-auth' => [
            'route_key' => 'developersAuth',
            'path' => '/developers/auth',
            'locale_file' => 'common.json',
            'namespace' => 'developers',
            'title' => 'auth_meta_title',
            'lead' => 'auth_intro',
            'sections' => [
                [
                    'key' => 'steps',
                    'items' => [
                        ['key' => 'credentials', 'title' => 'auth_step1_title', 'description' => 'auth_step1_body'],
                        ['key' => 'token', 'title' => 'auth_step2_title', 'description' => 'auth_step2_body'],
                        ['key' => 'call_api', 'title' => 'auth_step3_title', 'description' => 'auth_step3_body'],
                    ],
                ],
            ],
        ],
        'developers-endpoints' => [
            'route_key' => 'developersEndpoints',
            'path' => '/developers/endpoints',
            'locale_file' => 'common.json',
            'namespace' => 'developers',
            'title' => 'endpoints_meta_title',
            'lead' => 'endpoints_intro',
            'sections' => [
                ['key' => 'endpoints', 'items_from' => 'endpoint_descriptions'],
            ],
        ],
        'developers-webhooks' => [
            'route_key' => 'developersWebhooks',
            'path' => '/developers/webhooks',
            'locale_file' => 'common.json',
            'namespace' => 'developers',
            'title' => 'webhooks_meta_title',
            'lead' => 'webhooks_intro',
            'sections' => [
                [
                    'key' => 'webhooks',
                    'items' => [
                        ['key' => 'events', 'title' => 'webhook_events_title', 'description' => 'webhook_event_wallet_credited'],
                        ['key' => 'signing', 'title' => 'webhook_signing_title', 'description' => 'webhook_signing_body'],
                        ['key' => 'create', 'title' => 'webhook_create_title', 'description' => 'webhook_create_body'],
                    ],
                ],
            ],
        ],
        'regional-analytics' => [
            'route_key' => 'regionalAnalytics',
            'path' => '/regional-analytics',
            'locale_file' => 'common.json',
            'namespace' => 'regional_analytics',
            'title' => 'page_title',
            'lead' => 'hero_subtitle',
            'sections' => [
                [
                    'key' => 'features',
                    'title' => 'features_heading',
                    'items' => [
                        ['key' => 'trends', 'title' => 'feature_trends_title', 'description' => 'feature_trends_body'],
                        ['key' => 'demand_supply', 'title' => 'feature_demand_supply_title', 'description' => 'feature_demand_supply_body'],
                        ['key' => 'demographics', 'title' => 'feature_demographics_title', 'description' => 'feature_demographics_body'],
                        ['key' => 'footfall', 'title' => 'feature_footfall_title', 'description' => 'feature_footfall_body'],
                    ],
                ],
                ['key' => 'pricing', 'title' => 'pricing_heading', 'items_from' => 'tiers'],
            ],
        ],
        'caring-community' => [
            'route_key' => 'caringCommunity',
            'path' => '/caring-community',
            'locale_file' => 'common.json',
            'namespace' => 'caring_community',
            'title' => 'title',
            'lead' => 'subtitle',
            'sections' => [
                [
                    'key' => 'how',
                    'title' => 'how.title',
                    'body' => 'how.subtitle',
                    'items' => [
                        ['key' => 'ask', 'title' => 'how.step1_title', 'description' => 'how.step1_desc'],
                        ['key' => 'offer', 'title' => 'how.step2_title', 'description' => 'how.step2_desc'],
                        ['key' => 'hours', 'title' => 'how.step3_title', 'description' => 'how.step3_desc'],
                    ],
                ],
                ['key' => 'operating_model', 'title' => 'operating_model.title', 'body' => 'operating_model.subtitle'],
            ],
        ],
        'partner' => [
            'route_key' => 'hourPartner',
            'path' => '/partner',
            'locale_file' => 'about.json',
            'namespace' => 'partner',
            'title' => 'page_title',
            'lead' => 'hero_subtitle',
            'sections' => [
                [
                    'key' => 'why_partner',
                    'title' => 'why_partner_heading',
                    'body' => 'why_partner_subtitle',
                    'items' => [
                        ['key' => 'social_value', 'title' => 'impact_card_0_title', 'description' => 'impact_card_0_description'],
                        ['key' => 'proof', 'title' => 'impact_card_1_title', 'description' => 'impact_card_1_description'],
                        ['key' => 'growth', 'title' => 'impact_card_2_title', 'description' => 'impact_card_2_description'],
                    ],
                ],
                [
                    'key' => 'opportunities',
                    'title' => 'partnership_opportunities_heading',
                    'body' => 'partnership_opportunities_subtitle',
                    'items' => [
                        ['key' => 'corporate', 'title' => 'partnership_type_0_title', 'description' => 'partnership_type_0_description'],
                        ['key' => 'sponsorship', 'title' => 'partnership_type_1_title', 'description' => 'partnership_type_1_description'],
                        ['key' => 'technology', 'title' => 'partnership_type_2_title', 'description' => 'partnership_type_2_description'],
                        ['key' => 'research', 'title' => 'partnership_type_3_title', 'description' => 'partnership_type_3_description'],
                    ],
                ],
            ],
        ],
        'social-prescribing' => [
            'route_key' => 'hourSocialPrescribing',
            'path' => '/social-prescribing',
            'locale_file' => 'about.json',
            'namespace' => 'social_prescribing',
            'title' => 'hero_title',
            'lead' => 'hero_subtitle',
            'sections' => [
                [
                    'key' => 'outcomes',
                    'title' => 'validated_outcomes_heading',
                    'body' => 'validated_outcomes_subtitle',
                    'items' => [
                        ['key' => 'wellbeing', 'title' => 'outcome_0_label', 'description' => 'outcome_0_description'],
                        ['key' => 'connection', 'title' => 'outcome_1_label', 'description' => 'outcome_1_description'],
                        ['key' => 'return', 'title' => 'outcome_2_label', 'description' => 'outcome_2_description'],
                    ],
                ],
                ['key' => 'referral_pathway', 'title' => 'referral_pathway_heading', 'body' => 'referral_pathway_subtitle'],
            ],
        ],
        'impact-summary' => [
            'route_key' => 'hourImpactSummary',
            'path' => '/impact-summary',
            'locale_file' => 'about.json',
            'namespace' => 'impact_summary',
            'title' => 'hero_headline',
            'lead' => 'hero_subtitle',
            'sections' => [
                [
                    'key' => 'highlights',
                    'items' => [
                        ['key' => 'return', 'title' => 'stat_return_label', 'description' => 'sroi_ratio_value'],
                        ['key' => 'wellbeing', 'title' => 'stat_wellbeing_label', 'description' => 'wellbeing_100_text'],
                        ['key' => 'connected', 'title' => 'stat_connected_label', 'description' => 'wellbeing_95_text'],
                    ],
                ],
                ['key' => 'public_health', 'title' => 'public_health_heading', 'body' => 'public_health_para1'],
            ],
        ],
        'impact-report' => [
            'route_key' => 'hourImpactReport',
            'path' => '/impact-report',
            'locale_file' => 'about.json',
            'namespace' => 'impact_report',
            'title' => 'hero_title',
            'lead' => 'hero_description',
            'sections' => [
                [
                    'key' => 'study_objectives',
                    'title' => 'study_objectives_heading',
                    'items' => [
                        ['key' => 'quantify', 'description' => 'study_objective_0'],
                        ['key' => 'wellbeing', 'description' => 'study_objective_1'],
                        ['key' => 'cost_effective', 'description' => 'study_objective_2'],
                        ['key' => 'referral', 'description' => 'study_objective_3'],
                        ['key' => 'recommendations', 'description' => 'study_objective_4'],
                    ],
                ],
                ['key' => 'sroi', 'title' => 'sroi_heading', 'body' => 'sroi_description'],
            ],
        ],
        'strategic-plan' => [
            'route_key' => 'hourStrategicPlan',
            'path' => '/strategic-plan',
            'locale_file' => 'about.json',
            'namespace' => 'strategic_plan',
            'title' => 'page_title',
            'lead' => 'hero_tagline',
            'sections' => [
                [
                    'key' => 'goals',
                    'items' => [
                        ['key' => 'growth', 'title' => 'goal_1_heading', 'description' => 'goal_1_text'],
                        ['key' => 'finance', 'title' => 'goal_2_heading', 'description' => 'goal_2_text'],
                    ],
                ],
                ['key' => 'mission', 'title' => 'mission_heading', 'body' => 'mission_text'],
                ['key' => 'vision', 'title' => 'vision_heading', 'body' => 'vision_text'],
            ],
        ],
        'platform-terms' => [
            'route_key' => 'platformTerms',
            'path' => '/platform/terms',
            'locale_file' => 'legal.json',
            'namespace' => 'platform_terms',
            'title' => 'title',
            'lead' => 'subtitle',
            'sections' => [
                ['key' => 'sections', 'title' => 'title', 'body' => 'subtitle', 'items_from' => 'sections'],
            ],
        ],
        'platform-privacy' => [
            'route_key' => 'platformPrivacy',
            'path' => '/platform/privacy',
            'locale_file' => 'legal.json',
            'namespace' => 'platform_privacy',
            'title' => 'title',
            'lead' => 'subtitle',
            'sections' => [
                ['key' => 'sections', 'title' => 'title', 'body' => 'subtitle', 'items_from' => 'sections'],
            ],
        ],
        'platform-disclaimer' => [
            'route_key' => 'platformDisclaimer',
            'path' => '/platform/disclaimer',
            'locale_file' => 'legal.json',
            'namespace' => 'platform_disclaimer',
            'title' => 'title',
            'lead' => 'subtitle',
            'sections' => [
                ['key' => 'sections', 'title' => 'title', 'body' => 'subtitle', 'items_from' => 'sections'],
            ],
        ],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $pageKey): ?array
    {
        $normalizedPageKey = strtolower(trim($pageKey));
        $definition = self::DEFINITIONS[$normalizedPageKey] ?? null;

        if (!is_array($definition)) {
            return null;
        }

        $localeFile = (string) $definition['locale_file'];
        $namespace = (string) $definition['namespace'];
        $localeData = $this->localeNamespace($localeFile, $namespace);

        if ($localeData === []) {
            return null;
        }

        $sections = $this->sections($localeData, $definition['sections'] ?? []);

        return [
            'route_key' => (string) $definition['route_key'],
            'page_key' => $normalizedPageKey,
            'path' => (string) $definition['path'],
            'content_source' => 'react_public_locale',
            'locale' => 'en',
            'locale_file' => $localeFile,
            'translation_namespace' => $this->translationNamespace($localeFile, $namespace),
            'tenant' => $this->tenantPayload(),
            'title' => $this->stringValue($localeData, (string) $definition['title']),
            'lead' => $this->stringValue($localeData, (string) $definition['lead']),
            'sections' => $sections,
            'items' => $this->summaryItems($sections),
        ];
    }

    /**
     * @return array{id: int, slug: string, name: string}
     */
    private function tenantPayload(): array
    {
        $tenant = TenantContext::get();

        return [
            'id' => (int) ($tenant['id'] ?? TenantContext::getId()),
            'slug' => (string) ($tenant['slug'] ?? ''),
            'name' => TenantContext::getName('Project NEXUS'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function localeNamespace(string $localeFile, string $namespace): array
    {
        $path = base_path('react-frontend/public/locales/en/' . $localeFile);

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return [];
        }

        $value = $this->valueAt($decoded, $namespace);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int, mixed>|mixed $sectionDefinitions
     * @return array<int, array<string, mixed>>
     */
    private function sections(array $localeData, mixed $sectionDefinitions): array
    {
        if (!is_array($sectionDefinitions)) {
            return [];
        }

        $sections = [];

        foreach ($sectionDefinitions as $definition) {
            if (!is_array($definition) || !is_string($definition['key'] ?? null)) {
                continue;
            }

            $section = [
                'key' => $definition['key'],
                'title' => isset($definition['title']) && is_string($definition['title'])
                    ? $this->stringValue($localeData, $definition['title'])
                    : '',
                'body' => isset($definition['body']) && is_string($definition['body'])
                    ? $this->stringValue($localeData, $definition['body'])
                    : '',
                'items' => $this->items($localeData, $definition),
            ];

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $sectionDefinition
     * @return array<int, array<string, string>>
     */
    private function items(array $localeData, array $sectionDefinition): array
    {
        if (isset($sectionDefinition['items_from']) && is_string($sectionDefinition['items_from'])) {
            return $this->itemsFromMap($localeData, $sectionDefinition['items_from']);
        }

        $itemDefinitions = $sectionDefinition['items'] ?? [];

        if (!is_array($itemDefinitions)) {
            return [];
        }

        $items = [];

        foreach ($itemDefinitions as $itemDefinition) {
            if (!is_array($itemDefinition) || !is_string($itemDefinition['key'] ?? null)) {
                continue;
            }

            $item = [
                'key' => $itemDefinition['key'],
                'id' => $itemDefinition['key'],
                'title' => isset($itemDefinition['title']) && is_string($itemDefinition['title'])
                    ? $this->stringValue($localeData, $itemDefinition['title'])
                    : $itemDefinition['key'],
                'description' => isset($itemDefinition['description']) && is_string($itemDefinition['description'])
                    ? $this->stringValue($localeData, $itemDefinition['description'])
                    : '',
            ];

            if ($item['title'] !== '' || $item['description'] !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function itemsFromMap(array $localeData, string $key): array
    {
        $map = $this->valueAt($localeData, $key);

        if (!is_array($map)) {
            return [];
        }

        $items = [];

        foreach ($map as $itemKey => $value) {
            if (is_array($value)) {
                $title = is_string($value['label'] ?? null) ? $value['label'] : (string) $itemKey;
                $description = is_string($value['description'] ?? null) ? $value['description'] : '';
            } elseif (is_string($value)) {
                $title = (string) $itemKey;
                $description = $value;
            } else {
                continue;
            }

            $items[] = [
                'key' => (string) $itemKey,
                'id' => (string) $itemKey,
                'title' => $title,
                'description' => $description,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function summaryItems(array $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            foreach (($section['items'] ?? []) as $item) {
                if (!is_array($item) || !is_string($item['id'] ?? null) || !is_string($item['title'] ?? null)) {
                    continue;
                }

                $items[] = [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'description' => is_string($item['description'] ?? null) ? $item['description'] : '',
                ];

                if (count($items) >= 8) {
                    return $items;
                }
            }
        }

        return $items;
    }

    private function stringValue(array $data, string $key): string
    {
        $value = $this->valueAt($data, $key);

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function valueAt(array $data, string $key): mixed
    {
        $value = $data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function translationNamespace(string $localeFile, string $namespace): string
    {
        return preg_replace('/\.json$/', '', $localeFile) . '.' . $namespace;
    }
}
