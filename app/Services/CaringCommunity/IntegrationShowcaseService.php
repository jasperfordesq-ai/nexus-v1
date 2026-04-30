<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

/**
 * AG93 — Open-Standards and Integration Showcase service.
 *
 * Purely composes a static showcase manifest of the platform's existing
 * federation, webhook, partner-API, OpenAPI, and OAuth surfaces so that
 * evaluators (Tom, Roland, integration partners) can see "what an
 * integration partner would receive" without reading source code.
 *
 * Reads NO database state — every value here is hardcoded from the live
 * routes, controllers, and contracts that already ship in the repo. The
 * URLs are tenant-prefixed at render time so each evaluator sees the path
 * they would actually call.
 */
class IntegrationShowcaseService
{
    /**
     * Composed showcase, structured for the admin page renderer.
     */
    public function showcase(): array
    {
        return [
            'updated_at' => now()->toIso8601String(),
            'sections'   => [
                $this->openApiSection(),
                $this->partnerApiSection(),
                $this->oauthSection(),
                $this->webhookSection(),
                $this->federationSection(),
                $this->signedPayloadSection(),
                $this->checklistSection(),
            ],
        ];
    }

    private function openApiSection(): array
    {
        return [
            'id'    => 'openapi',
            'title' => 'OpenAPI specification',
            'icon'  => 'FileJson',
            'body'  => 'Machine-readable spec for every public API surface. Generated from the same controllers that ship in this repo.',
            'items' => [
                ['label' => 'JSON', 'path' => '/api/v2/docs/openapi.json', 'method' => 'GET'],
                ['label' => 'YAML', 'path' => '/api/v2/docs/openapi.yaml', 'method' => 'GET'],
            ],
            'docs_link' => 'https://github.com/jasperfordesq-ai/nexus-v1/tree/main/docs',
        ];
    }

    private function partnerApiSection(): array
    {
        return [
            'id'    => 'partner_api',
            'title' => 'Partner API v1',
            'icon'  => 'Plug',
            'body'  => 'OAuth-secured external API for third-party integrators. Exposes users, listings, wallet credit, community aggregates, and webhook subscriptions.',
            'items' => [
                ['label' => 'List users',          'path' => '/api/partner/v1/users',                         'method' => 'GET'],
                ['label' => 'Show user',           'path' => '/api/partner/v1/users/{id}',                    'method' => 'GET'],
                ['label' => 'List listings',       'path' => '/api/partner/v1/listings',                      'method' => 'GET'],
                ['label' => 'Wallet balance',      'path' => '/api/partner/v1/wallet/balance/{userId}',       'method' => 'GET'],
                ['label' => 'Credit wallet',       'path' => '/api/partner/v1/wallet/credit',                 'method' => 'POST'],
                ['label' => 'Community aggregates','path' => '/api/partner/v1/aggregates/community',          'method' => 'GET'],
                ['label' => 'List subscriptions',  'path' => '/api/partner/v1/webhooks/subscriptions',        'method' => 'GET'],
                ['label' => 'Create subscription', 'path' => '/api/partner/v1/webhooks/subscriptions',        'method' => 'POST'],
            ],
            'docs_link' => 'https://github.com/jasperfordesq-ai/nexus-v1/blob/main/docs/API_REFERENCE.md',
        ];
    }

    private function oauthSection(): array
    {
        return [
            'id'    => 'oauth',
            'title' => 'OAuth / client credentials',
            'icon'  => 'KeyRound',
            'body'  => 'Standard OAuth 2.0 client-credentials flow for partner servers. Token revocation supported.',
            'items' => [
                ['label' => 'Token endpoint',  'path' => '/api/partner/v1/oauth/token',  'method' => 'POST'],
                ['label' => 'Revoke endpoint', 'path' => '/api/partner/v1/oauth/revoke', 'method' => 'POST'],
            ],
            'sample_request' => [
                'curl' => "curl -X POST https://app.project-nexus.ie/api/partner/v1/oauth/token \\\n"
                    . "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n"
                    . "  -d 'grant_type=client_credentials&client_id=<your-client-id>&client_secret=<your-client-secret>&scope=read:users read:listings'",
            ],
        ];
    }

    private function webhookSection(): array
    {
        return [
            'id'    => 'webhooks',
            'title' => 'Webhook subscriptions',
            'icon'  => 'Webhook',
            'body'  => 'Partners subscribe to event topics; payloads are signed with HMAC-SHA256 over the full body using the per-subscription secret.',
            'items' => [
                ['label' => 'List subscriptions',   'path' => '/api/partner/v1/webhooks/subscriptions',      'method' => 'GET'],
                ['label' => 'Create subscription',  'path' => '/api/partner/v1/webhooks/subscriptions',      'method' => 'POST'],
                ['label' => 'Update subscription',  'path' => '/api/partner/v1/webhooks/subscriptions/{id}', 'method' => 'PUT'],
                ['label' => 'Delete subscription',  'path' => '/api/partner/v1/webhooks/subscriptions/{id}', 'method' => 'DELETE'],
            ],
            'verification_note' => 'Verify the X-NEXUS-Signature header by computing HMAC-SHA256(body, secret) and comparing with constant-time equality. Reject any payload older than the configured replay window (default 5 minutes).',
        ];
    }

    private function federationSection(): array
    {
        return [
            'id'    => 'federation',
            'title' => 'Federation aggregates',
            'icon'  => 'Network',
            'body'  => 'Each tenant exposes a read-only signed federation aggregate endpoint. Aggregate payloads expose counts, brackets, top categories, and locales — never raw PII. See FEDERATION_API_MANUAL.md for the full protocol.',
            'items' => [
                ['label' => 'Tenant aggregate', 'path' => '/api/v2/federation/aggregates', 'method' => 'GET'],
            ],
            'docs_link' => 'https://github.com/jasperfordesq-ai/nexus-v1/blob/main/docs/FEDERATION.md',
        ];
    }

    private function signedPayloadSection(): array
    {
        return [
            'id'    => 'sample_payloads',
            'title' => 'Sample payloads',
            'icon'  => 'FileCode',
            'body'  => 'Representative payloads for an integration partner — illustrative only, not from a live tenant.',
            'samples' => [
                [
                    'label' => 'Federation aggregate (signed JSON, illustrative)',
                    'kind'  => 'json',
                    'body'  => json_encode([
                        'tenant_id'      => 42,
                        'period'         => ['from' => '2026-01-01', 'to' => '2026-03-31'],
                        'schema_version' => 1,
                        'aggregates'     => [
                            'total_approved_hours' => 1234.5,
                            'member_count_bracket' => '200-1000',
                            'top_categories'       => [
                                ['name' => 'Companionship', 'count' => 87],
                                ['name' => 'Transport',     'count' => 64],
                            ],
                            'partner_org_count'    => 12,
                            'supported_locales'    => ['de', 'fr', 'it'],
                        ],
                        'signature' => 'ed25519:HASH_VERIFIED_OUT_OF_BAND',
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                [
                    'label' => 'Webhook event (HMAC signed, illustrative)',
                    'kind'  => 'json',
                    'body'  => json_encode([
                        'id'      => 'evt_2YxK1bn7q3aQ',
                        'type'    => 'listing.created',
                        'created' => '2026-04-30T12:34:56Z',
                        'tenant'  => 'agoris',
                        'data'    => [
                            'listing_id' => 9921,
                            'category'   => 'transport',
                            'sub_region' => 'cham_quartier_eichmatt',
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'headers' => [
                        'X-NEXUS-Signature: t=1714478096,v1=8a7e1c64...redacted...e2',
                        'X-NEXUS-Event-Id: evt_2YxK1bn7q3aQ',
                        'Content-Type: application/json',
                    ],
                ],
                [
                    'label' => 'Partner API community aggregates (illustrative)',
                    'kind'  => 'json',
                    'body'  => json_encode([
                        'tenant'                  => 'agoris',
                        'as_of'                   => '2026-04-30T00:00:00Z',
                        'active_members'          => 248,
                        'approved_hours_90d'      => 1421,
                        'recurring_relationships' => 73,
                        'cost_offset_chf_90d'     => 99470,
                        'methodology'             => [
                            'window_days'           => 90,
                            'hourly_rate_chf'       => 35,
                            'prevention_multiplier' => 2,
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    private function checklistSection(): array
    {
        return [
            'id'    => 'partner_checklist',
            'title' => 'What an integration partner receives',
            'icon'  => 'ClipboardList',
            'body'  => 'Hand this checklist to a prospective integration partner before kickoff.',
            'checklist' => [
                'OAuth client_id and client_secret (one pair per partner)',
                'Allowed scopes list (e.g. read:users, read:listings, write:wallet)',
                'Webhook subscription with HMAC secret + endpoint URL allowlist',
                'OpenAPI spec URL (JSON or YAML)',
                'Sandbox tenant slug for integration testing',
                'Federation aggregate signing public key (for federation partners only)',
                'Rate-limit headers documentation (X-RateLimit-Limit, X-RateLimit-Remaining)',
                'Data-sharing agreement (DSA) draft + named DPO contact',
                'Incident-response runbook URL (from the AG80 disclosure pack)',
            ],
        ];
    }
}
