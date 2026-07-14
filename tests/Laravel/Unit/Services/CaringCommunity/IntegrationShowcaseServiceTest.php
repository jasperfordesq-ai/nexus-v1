<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Services\CaringCommunity\IntegrationShowcaseService;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for IntegrationShowcaseService.
 *
 * The service is stateless / pure-PHP — it reads no DB and hits no external
 * HTTP. No DatabaseTransactions or Http::fake() are required.
 */
class IntegrationShowcaseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function service(): IntegrationShowcaseService
    {
        return app(IntegrationShowcaseService::class);
    }

    // -------------------------------------------------------------------------
    // showcase() top-level structure
    // -------------------------------------------------------------------------

    public function test_showcase_returns_array_with_updated_at_and_sections(): void
    {
        $result = $this->service()->showcase();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertIsString($result['updated_at']);
        $this->assertIsArray($result['sections']);
    }

    public function test_showcase_updated_at_is_valid_iso8601(): void
    {
        $result = $this->service()->showcase();

        // ISO 8601: "2026-06-24T10:30:00+00:00" — must parse via DateTime without throwing.
        $parsed = \DateTime::createFromFormat(\DateTimeInterface::ISO8601_EXPANDED, $result['updated_at'])
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:sP', $result['updated_at'])
            ?: \DateTime::createFromFormat(\DateTimeInterface::ATOM, $result['updated_at']);

        $this->assertNotFalse($parsed, "updated_at '{$result['updated_at']}' is not a valid ISO 8601 timestamp.");
    }

    public function test_showcase_contains_exactly_seven_sections(): void
    {
        $result = $this->service()->showcase();
        $this->assertCount(7, $result['sections']);
    }

    public function test_showcase_section_ids_are_unique_and_strings(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $ids = array_column($sections, 'id');

        $this->assertCount(count($ids), array_unique($ids), 'Section ids must be unique.');
        foreach ($ids as $id) {
            $this->assertIsString($id);
            $this->assertNotEmpty($id);
        }
    }

    public function test_all_sections_have_language_neutral_id_and_icon(): void
    {
        $sections = $this->service()->showcase()['sections'];

        foreach ($sections as $section) {
            $this->assertArrayHasKey('id', $section, "Section missing 'id'");
            $this->assertArrayHasKey('icon', $section, "Section '{$section['id']}' missing 'icon'");
            $this->assertNotEmpty($section['icon']);
            $this->assertArrayNotHasKey('title', $section);
        }
    }

    // -------------------------------------------------------------------------
    // openapi section
    // -------------------------------------------------------------------------

    public function test_openapi_section_has_expected_id_and_two_items(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $openapi  = $this->findSection($sections, 'openapi');

        $this->assertNotNull($openapi, 'openapi section not found');
        $this->assertArrayHasKey('items', $openapi);
        $this->assertCount(2, $openapi['items']);

        $methods = array_column($openapi['items'], 'method');
        $this->assertSame(['GET', 'GET'], $methods);

        $codes = array_column($openapi['items'], 'code');
        $this->assertSame(['openapi_json', 'openapi_yaml'], $codes);
    }

    public function test_openapi_section_has_docs_link(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $openapi  = $this->findSection($sections, 'openapi');

        $this->assertArrayHasKey('docs_link', $openapi);
        $this->assertStringStartsWith('https://', $openapi['docs_link']);
    }

    // -------------------------------------------------------------------------
    // partner_api section
    // -------------------------------------------------------------------------

    public function test_partner_api_section_contains_wallet_credit_endpoint(): void
    {
        $sections   = $this->service()->showcase()['sections'];
        $partnerApi = $this->findSection($sections, 'partner_api');

        $this->assertNotNull($partnerApi, 'partner_api section not found');
        $paths = array_column($partnerApi['items'], 'path');
        $this->assertContains('/api/partner/v1/wallet/credit', $paths);
    }

    public function test_partner_api_section_has_docs_link(): void
    {
        $sections   = $this->service()->showcase()['sections'];
        $partnerApi = $this->findSection($sections, 'partner_api');

        $this->assertArrayHasKey('docs_link', $partnerApi);
        $this->assertNotEmpty($partnerApi['docs_link']);
    }

    // -------------------------------------------------------------------------
    // oauth section
    // -------------------------------------------------------------------------

    public function test_oauth_section_has_token_and_revoke_endpoints(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $oauth    = $this->findSection($sections, 'oauth');

        $this->assertNotNull($oauth, 'oauth section not found');
        $paths = array_column($oauth['items'], 'path');
        $this->assertContains('/api/partner/v1/oauth/token', $paths);
        $this->assertContains('/api/partner/v1/oauth/revoke', $paths);
    }

    public function test_oauth_section_has_sample_curl_request(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $oauth    = $this->findSection($sections, 'oauth');

        $this->assertArrayHasKey('sample_request', $oauth);
        $this->assertArrayHasKey('curl', $oauth['sample_request']);
        $this->assertStringContainsString('client_credentials', $oauth['sample_request']['curl']);
    }

    // -------------------------------------------------------------------------
    // webhooks section
    // -------------------------------------------------------------------------

    public function test_webhooks_section_has_all_four_crud_operations(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $webhooks = $this->findSection($sections, 'webhooks');

        $this->assertNotNull($webhooks, 'webhooks section not found');
        $methods = array_column($webhooks['items'], 'method');
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
    }

    public function test_webhooks_section_has_semantic_verification_note_code(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $webhooks = $this->findSection($sections, 'webhooks');

        $this->assertSame('webhook_signature', $webhooks['verification_note_code']);
    }

    // -------------------------------------------------------------------------
    // federation section
    // -------------------------------------------------------------------------

    public function test_federation_section_exposes_aggregate_endpoint(): void
    {
        $sections   = $this->service()->showcase()['sections'];
        $federation = $this->findSection($sections, 'federation');

        $this->assertNotNull($federation, 'federation section not found');
        $paths = array_column($federation['items'], 'path');
        $this->assertContains('/api/v2/federation/aggregates', $paths);
    }

    // -------------------------------------------------------------------------
    // sample_payloads section
    // -------------------------------------------------------------------------

    public function test_sample_payloads_section_has_three_samples(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $payloads = $this->findSection($sections, 'sample_payloads');

        $this->assertNotNull($payloads, 'sample_payloads section not found');
        $this->assertArrayHasKey('samples', $payloads);
        $this->assertCount(3, $payloads['samples']);
    }

    public function test_sample_payloads_bodies_are_valid_json(): void
    {
        $sections = $this->service()->showcase()['sections'];
        $payloads = $this->findSection($sections, 'sample_payloads');

        foreach ($payloads['samples'] as $i => $sample) {
            $this->assertArrayHasKey('body', $sample, "Sample {$i} missing 'body'");
            $decoded = json_decode($sample['body'], true);
            $this->assertNotNull($decoded, "Sample {$i} body is not valid JSON: {$sample['body']}");
        }
    }

    // -------------------------------------------------------------------------
    // partner_checklist section
    // -------------------------------------------------------------------------

    public function test_checklist_section_has_at_least_five_items(): void
    {
        $sections  = $this->service()->showcase()['sections'];
        $checklist = $this->findSection($sections, 'partner_checklist');

        $this->assertNotNull($checklist, 'partner_checklist section not found');
        $this->assertArrayHasKey('checklist_codes', $checklist);
        $this->assertGreaterThanOrEqual(5, count($checklist['checklist_codes']));
    }

    public function test_checklist_items_are_non_empty_strings(): void
    {
        $sections  = $this->service()->showcase()['sections'];
        $checklist = $this->findSection($sections, 'partner_checklist');

        foreach ($checklist['checklist_codes'] as $i => $item) {
            $this->assertIsString($item, "Checklist item {$i} is not a string.");
            $this->assertNotEmpty($item, "Checklist item {$i} is empty.");
        }
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /** @param array<int, array<string, mixed>> $sections */
    private function findSection(array $sections, string $id): ?array
    {
        foreach ($sections as $section) {
            if (($section['id'] ?? null) === $id) {
                return $section;
            }
        }
        return null;
    }
}
