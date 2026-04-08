<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\FederationExternalPartnerService;
use App\Services\TimeOverflowAdapter;
use Illuminate\Console\Command;

/**
 * Register a TimeOverflow instance as a Nexus external federation partner.
 *
 * Usage:
 *   php artisan federation:register-timeoverflow \
 *     --name="TimeOverflow Ireland" \
 *     --url=https://timeoverflow.example.com \
 *     --api-key=to_fed_xxxxx... \
 *     --tenant=2
 */
class RegisterTimeOverflowPartner extends Command
{
    protected $signature = 'federation:register-timeoverflow
                            {--name= : Display name for the TimeOverflow instance}
                            {--url= : Base URL of the TimeOverflow instance}
                            {--api-key= : Raw API key from TimeOverflow}
                            {--tenant= : Nexus tenant ID to register under}
                            {--user=1 : Admin user ID performing the registration}
                            {--allow-transactions : Enable cross-platform transfers}
                            {--dry-run : Show what would be created without actually creating}';

    protected $description = 'Register a TimeOverflow instance as an external federation partner';

    public function handle(): int
    {
        $name = $this->option('name');
        $url = $this->option('url');
        $apiKey = $this->option('api-key');
        $tenantId = (int) $this->option('tenant');
        $userId = (int) $this->option('user');
        $dryRun = $this->option('dry-run');

        // Validate required fields
        if (empty($name)) {
            $name = $this->ask('Enter a display name for the TimeOverflow instance');
        }
        if (empty($url)) {
            $url = $this->ask('Enter the base URL (e.g., https://timeoverflow.example.com)');
        }
        if (empty($apiKey)) {
            $apiKey = $this->secret('Enter the raw API key from TimeOverflow');
        }
        if (!$tenantId) {
            $tenantId = (int) $this->ask('Enter the Nexus tenant ID to register under');
        }

        if (empty($name) || empty($url) || empty($apiKey) || !$tenantId) {
            $this->error('All fields are required: --name, --url, --api-key, --tenant');
            return self::FAILURE;
        }

        // Build the registration payload using the adapter
        $payload = TimeOverflowAdapter::buildRegistrationPayload(
            $name,
            $url,
            $apiKey,
            [
                'allow_transactions' => $this->option('allow-transactions'),
            ]
        );

        $this->info('');
        $this->info('=== TimeOverflow Partner Registration ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $payload['name']],
                ['Base URL', $payload['base_url']],
                ['API Path', $payload['api_path']],
                ['Auth Method', $payload['auth_method']],
                ['Member Search', $payload['allow_member_search'] ? 'Yes' : 'No'],
                ['Listing Search', $payload['allow_listing_search'] ? 'Yes' : 'No'],
                ['Messaging', $payload['allow_messaging'] ? 'Yes' : 'No'],
                ['Transactions', $payload['allow_transactions'] ? 'Yes' : 'No'],
                ['Tenant ID', $tenantId],
                ['Registered By', "User #{$userId}"],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN — no changes made.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Proceed with registration?', true)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        // Create the external partner
        $result = FederationExternalPartnerService::create($payload, $tenantId, $userId);

        if ($result['success']) {
            $this->info('');
            $this->info("✓ TimeOverflow partner registered successfully!");
            $this->info("  Partner ID: {$result['id']}");
            $this->info("  Status: pending (activate via admin panel or API)");
            $this->info('');
            $this->info('Next steps:');
            $this->info("  1. Activate: php artisan federation:activate-partner {$result['id']} --tenant={$tenantId}");
            $this->info("  2. Health check: POST /api/v2/admin/federation/external-partners/{$result['id']}/health-check");
            $this->info("  3. Browse listings: FederationExternalApiClient::fetchListings({$result['id']})");
            return self::SUCCESS;
        }

        $this->error("Registration failed: " . ($result['error'] ?? 'Unknown error'));
        return self::FAILURE;
    }
}
