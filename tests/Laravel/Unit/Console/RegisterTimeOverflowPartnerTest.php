<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for `federation:register-timeoverflow` Artisan command.
 *
 * Uses unique tenant id 99722 for isolation.
 *
 * The command:
 *   1. Validates required options (--name, --url, --api-key, --tenant).
 *   2. Builds a registration payload via TimeOverflowAdapter::buildRegistrationPayload().
 *   3. Calls FederationExternalPartnerService::create() which encrypts the api_key
 *      via Crypt::encryptString() and inserts into federation_external_partners.
 *   4. --dry-run exits SUCCESS without writing a row.
 *   5. Interactive confirmation is driven with expectsConfirmation().
 *
 * OutboundUrlGuard::isSafeHttpUrl() does real DNS resolution for hostnames but
 * short-circuits to a public-IP check for literal IPv4 addresses. We therefore
 * use IPs from 93.184.216.0/24 (example.com's public block) as base_url values;
 * these pass the SSRF guard without requiring live DNS.
 *
 * The encrypter is rebound to the known test APP_KEY so Crypt works correctly.
 */
class RegisterTimeOverflowPartnerTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99722;

    /**
     * Monotonic IP octet counter so each test gets a distinct base_url and
     * avoids the UNIQUE (tenant_id, base_url) constraint violation.
     */
    private static int $ipSeq = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Rebind encrypter to the known test APP_KEY.
        $this->app->instance(
            'encrypter',
            new \Illuminate\Encryption\Encrypter(
                base64_decode('HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls='),
                'AES-256-CBC'
            )
        );

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Register TimeOverflow Test Tenant',
            'slug'       => 'reg-to-test-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Clean stale rows for this isolated tenant so the unique-url constraint
        // only sees rows created by the current test run.
        DB::table('federation_external_partners')
            ->where('tenant_id', self::TENANT_ID)
            ->delete();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Return a distinct public-IP base URL from the 93.184.216.x block
     * (example.com's public /24). OutboundUrlGuard resolves IP literals
     * directly without DNS, so these always pass the SSRF check.
     */
    private function freshIpUrl(): string
    {
        $octet = 1 + (self::$ipSeq++ % 253);
        return 'https://93.184.216.' . $octet;
    }

    /**
     * Return all federation_external_partners rows for our test tenant.
     */
    private function partnerRows(): \Illuminate\Support\Collection
    {
        return DB::table('federation_external_partners')
            ->where('tenant_id', self::TENANT_ID)
            ->get();
    }

    /**
     * Run the command with sensible defaults and expect the interactive
     * confirmation to be answered "yes". Each call gets a fresh IP URL.
     *
     * @param array $overrides Extra option overrides.
     */
    private function runWithConfirm(array $overrides = []): \Illuminate\Testing\PendingCommand
    {
        $defaults = [
            '--name'    => 'TimeOverflow Test Bank',
            '--url'     => $this->freshIpUrl(),
            '--api-key' => 'to_key_test_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ];

        $cmd = $this->artisan('federation:register-timeoverflow', array_merge($defaults, $overrides));

        return $cmd->expectsConfirmation('Proceed with registration?', 'yes');
    }

    // ----------------------------------------------------------------
    // Tests
    // ----------------------------------------------------------------

    public function test_exits_failure_when_all_required_options_missing_and_empty_answers(): void
    {
        // Without any options the command prompts for name, url, api-key (secret), tenant.
        // Answering empty strings triggers the validation failure.
        $this->artisan('federation:register-timeoverflow')
            ->expectsQuestion('Enter a display name for the TimeOverflow instance', '')
            ->expectsQuestion('Enter the base URL (e.g., https://timeoverflow.example.com)', '')
            ->expectsQuestion('Enter the raw API key from TimeOverflow', '')
            ->expectsQuestion('Enter the Nexus tenant ID to register under', '0')
            ->expectsOutput('All fields are required: --name, --url, --api-key, --tenant')
            ->assertExitCode(1);
    }

    public function test_dry_run_exits_success_without_creating_row(): void
    {
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'Dry Run Bank',
            '--url'     => $this->freshIpUrl(),
            '--api-key' => 'to_key_dry_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertCount(0, $this->partnerRows());
    }

    public function test_registration_creates_partner_row_with_correct_fields(): void
    {
        $uniqueKey = 'to_key_create_' . uniqid();
        $url       = $this->freshIpUrl();

        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'TimeOverflow Create Bank',
            '--url'     => $url,
            '--api-key' => $uniqueKey,
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ])
            ->expectsConfirmation('Proceed with registration?', 'yes')
            ->assertExitCode(0);

        $rows = $this->partnerRows();
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame('TimeOverflow Create Bank', $row->name);
        $this->assertSame($url, $row->base_url);
        $this->assertSame('pending', $row->status);
        $this->assertSame('/api/v1', $row->api_path);
        $this->assertSame('api_key', $row->auth_method);
        // api_key must be stored encrypted (not plaintext).
        $this->assertNotSame($uniqueKey, $row->api_key,
            'api_key must be encrypted at rest');
        $this->assertSame((string) self::TENANT_ID, (string) $row->tenant_id);
    }

    public function test_registration_outputs_partner_id_and_next_steps(): void
    {
        $this->runWithConfirm()
            ->expectsOutputToContain('TimeOverflow partner registered successfully')
            ->expectsOutputToContain('Partner ID:')
            ->assertExitCode(0);
    }

    public function test_cancelling_confirmation_exits_success_without_row(): void
    {
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'Cancelled Bank',
            '--url'     => $this->freshIpUrl(),
            '--api-key' => 'to_key_cancel_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ])
            ->expectsConfirmation('Proceed with registration?', 'no')
            ->assertExitCode(0);

        $this->assertCount(0, $this->partnerRows());
    }

    public function test_allow_transactions_flag_enables_transactions_in_row(): void
    {
        $this->artisan('federation:register-timeoverflow', [
            '--name'               => 'Transaction Bank',
            '--url'                => $this->freshIpUrl(),
            '--api-key'            => 'to_key_txn_' . uniqid(),
            '--tenant'             => self::TENANT_ID,
            '--user'               => 1,
            '--allow-transactions' => true,
        ])
            ->expectsConfirmation('Proceed with registration?', 'yes')
            ->assertExitCode(0);

        $row = $this->partnerRows()->first();
        $this->assertNotNull($row, 'Partner row must be created');
        $this->assertSame(1, (int) $row->allow_transactions);
    }

    public function test_duplicate_url_for_same_tenant_returns_failure(): void
    {
        $sharedUrl = $this->freshIpUrl();

        // Register once successfully.
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'First Bank',
            '--url'     => $sharedUrl,
            '--api-key' => 'to_key_first_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ])
            ->expectsConfirmation('Proceed with registration?', 'yes')
            ->assertExitCode(0);

        // Re-run with the same URL — must fail (unique constraint).
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'Duplicate Bank',
            '--url'     => $sharedUrl,
            '--api-key' => 'to_key_dup_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ])
            ->expectsConfirmation('Proceed with registration?', 'yes')
            ->assertExitCode(1);

        // Must still have exactly one row.
        $this->assertCount(1, $this->partnerRows());
    }

    public function test_registration_table_is_displayed_before_confirmation(): void
    {
        // The command calls $this->table() to print a preview before confirming.
        // We verify it outputs 'Name' (a column header) as a proxy for the table render.
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'Table Bank',
            '--url'     => $this->freshIpUrl(),
            '--api-key' => 'to_key_table_' . uniqid(),
            '--tenant'  => self::TENANT_ID,
            '--user'    => 1,
        ])
            ->expectsOutputToContain('TimeOverflow Partner Registration')
            ->expectsConfirmation('Proceed with registration?', 'no')
            ->assertExitCode(0);
    }

    public function test_missing_tenant_option_prompts_and_exits_failure_on_zero_tenant(): void
    {
        // Supply name/url/api-key but not --tenant; answer 0 to the prompt.
        $this->artisan('federation:register-timeoverflow', [
            '--name'    => 'No Tenant Bank',
            '--url'     => $this->freshIpUrl(),
            '--api-key' => 'to_key_noten_' . uniqid(),
        ])
            ->expectsQuestion('Enter the Nexus tenant ID to register under', '0')
            ->expectsOutput('All fields are required: --name, --url, --api-key, --tenant')
            ->assertExitCode(1);
    }

    public function test_allow_member_search_defaults_to_enabled(): void
    {
        $this->runWithConfirm()
            ->assertExitCode(0);

        $row = $this->partnerRows()->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->allow_member_search,
            'allow_member_search must default to 1 per TimeOverflowAdapter::buildRegistrationPayload()');
    }

    public function test_allow_listing_search_defaults_to_enabled(): void
    {
        $this->runWithConfirm()
            ->assertExitCode(0);

        $row = $this->partnerRows()->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->allow_listing_search,
            'allow_listing_search must default to 1 per TimeOverflowAdapter::buildRegistrationPayload()');
    }
}
