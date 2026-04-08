<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use App\Services\TimeOverflowAdapter;
use Illuminate\Console\Command;

/**
 * End-to-end federation test against a live TimeOverflow instance.
 *
 * Runs through all federation API endpoints to verify connectivity
 * and data translation.
 *
 * Usage:
 *   php artisan federation:test-timeoverflow --partner=1
 *   php artisan federation:test-timeoverflow --partner=1 --verbose
 */
class TestTimeOverflowFederation extends Command
{
    protected $signature = 'federation:test-timeoverflow
                            {--partner= : External partner ID to test against}
                            {--tenant= : Tenant ID (optional, for scoping)}
                            {--org= : TimeOverflow organization ID to query}';

    protected $description = 'Run end-to-end federation tests against a TimeOverflow instance';

    private int $passed = 0;
    private int $failed = 0;

    public function handle(): int
    {
        $partnerId = (int) $this->option('partner');
        $orgId = $this->option('org');

        if (!$partnerId) {
            $this->error('--partner is required');
            return self::FAILURE;
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║  TimeOverflow Federation E2E Test Suite      ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        // Test 1: Health Check
        $this->runTest('Health Check', function () use ($partnerId) {
            $result = FederationExternalApiClient::healthCheck($partnerId);
            $this->assertResult($result, 'Health check');

            $data = $result['data']['data'] ?? $result['data'] ?? [];
            $this->line("  Platform: " . ($data['platform'] ?? 'unknown'));
            $this->line("  Status: " . ($data['status'] ?? 'unknown'));
            $this->line("  Version: " . ($data['version'] ?? 'unknown'));
        });

        // Test 2: Fetch Organizations
        $this->runTest('Fetch Organizations', function () use ($partnerId, &$orgId) {
            $result = FederationExternalApiClient::get($partnerId, '/organizations');
            $this->assertResult($result, 'Fetch organizations');

            $data = $result['data']['data'] ?? [];
            $orgs = TimeOverflowAdapter::transformOrganizations($data);
            $this->line("  Found " . count($orgs) . " organization(s)");

            foreach (array_slice($orgs, 0, 3) as $org) {
                $this->line("    - {$org['name']} (ID: {$org['external_id']}, members: {$org['member_count']})");
            }

            // Auto-select first org if not specified
            if (!$orgId && !empty($data)) {
                $orgId = $data[0]['id'] ?? null;
                $this->line("  → Auto-selected org ID: {$orgId}");
            }
        });

        // Test 3: Fetch Members
        $this->runTest('Fetch Members', function () use ($partnerId, $orgId) {
            $params = $orgId ? ['organization_id' => $orgId] : [];
            $result = FederationExternalApiClient::fetchMembers($partnerId, $params);
            $this->assertResult($result, 'Fetch members');

            $data = $result['data']['data'] ?? [];
            $members = TimeOverflowAdapter::transformMembers($data);
            $this->line("  Found " . count($members) . " member(s)");

            foreach (array_slice($members, 0, 3) as $member) {
                $balance = $member['balance'] ?? 0;
                $this->line("    - {$member['name']} (balance: {$balance}h, skills: " . implode(', ', array_slice($member['skills'], 0, 3)) . ")");
            }
        });

        // Test 4: Fetch Listings
        $this->runTest('Fetch Listings', function () use ($partnerId, $orgId) {
            $params = $orgId ? ['organization_id' => $orgId] : [];
            $result = FederationExternalApiClient::fetchListings($partnerId, $params);
            $this->assertResult($result, 'Fetch listings');

            $data = $result['data']['data'] ?? [];
            $listings = TimeOverflowAdapter::transformListings($data);
            $this->line("  Found " . count($listings) . " listing(s)");

            $offers = array_filter($listings, fn($l) => $l['type'] === 'offer');
            $requests = array_filter($listings, fn($l) => $l['type'] === 'request');
            $this->line("    Offers: " . count($offers));
            $this->line("    Requests: " . count($requests));

            foreach (array_slice($listings, 0, 3) as $listing) {
                $this->line("    - [{$listing['type']}] {$listing['title']} (cat: {$listing['category_name']})");
            }
        });

        // Test 5: Fetch Single Organization
        if ($orgId) {
            $this->runTest('Fetch Single Organization', function () use ($partnerId, $orgId) {
                $result = FederationExternalApiClient::get($partnerId, "/organizations/{$orgId}");
                $this->assertResult($result, 'Fetch single org');

                $data = $result['data']['data'] ?? $result['data'] ?? [];
                $org = TimeOverflowAdapter::transformOrganization($data);
                $this->line("  Name: {$org['name']}");
                $this->line("  Location: " . ($org['location_name'] ?? 'N/A'));
                $this->line("  Members: {$org['member_count']}");
                $this->line("  Offers: " . ($org['active_offers_count'] ?? 'N/A'));
                $this->line("  Inquiries: " . ($org['active_inquiries_count'] ?? 'N/A'));
            });
        }

        // Test 6: Search Members
        $this->runTest('Search Members', function () use ($partnerId, $orgId) {
            $params = ['search' => 'a'];
            if ($orgId) {
                $params['organization_id'] = $orgId;
            }
            $result = FederationExternalApiClient::fetchMembers($partnerId, $params);
            $this->assertResult($result, 'Search members');

            $data = $result['data']['data'] ?? [];
            $this->line("  Search 'a' returned " . count($data) . " result(s)");
        });

        // Test 7: Unit Conversion
        $this->runTest('Unit Conversion (seconds ↔ hours)', function () {
            $this->assertTrue(TimeOverflowAdapter::secondsToHours(3600) === 1.0, '3600s = 1h');
            $this->assertTrue(TimeOverflowAdapter::secondsToHours(5400) === 1.5, '5400s = 1.5h');
            $this->assertTrue(TimeOverflowAdapter::hoursToSeconds(1.0) === 3600, '1h = 3600s');
            $this->assertTrue(TimeOverflowAdapter::hoursToSeconds(2.5) === 9000, '2.5h = 9000s');
            $this->line("  All conversions correct");
        });

        // Summary
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $total = $this->passed + $this->failed;
        $this->info("  Results: {$this->passed}/{$total} passed");
        if ($this->failed > 0) {
            $this->error("  {$this->failed} test(s) FAILED");
        } else {
            $this->info("  ✓ All tests passed!");
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('');

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runTest(string $name, \Closure $fn): void
    {
        $this->info("┌─ {$name}");
        try {
            $fn();
            $this->passed++;
            $this->info("└─ ✓ PASSED");
        } catch (\Exception $e) {
            $this->failed++;
            $this->error("└─ ✗ FAILED: {$e->getMessage()}");
        }
        $this->info('');
    }

    private function assertResult(array $result, string $context): void
    {
        if (!$result['success']) {
            throw new \RuntimeException("{$context} failed: " . ($result['error'] ?? 'Unknown error'));
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Assertion failed: {$message}");
        }
    }
}
