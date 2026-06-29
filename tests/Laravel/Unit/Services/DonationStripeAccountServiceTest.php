<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\DonationStripeAccountService;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * @covers \App\Services\DonationStripeAccountService
 */
class DonationStripeAccountServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', DonationStripeAccountService::SETTING_CONNECT_ACCOUNT_ID)
            ->delete();
        app(TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        parent::tearDown();
    }

    public function test_accountIdForTenant_returnsConfiguredConnectAccount(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            [
                'tenant_id' => $this->testTenantId,
                'setting_key' => DonationStripeAccountService::SETTING_CONNECT_ACCOUNT_ID,
            ],
            [
                'setting_value' => 'acct_test_123456',
                'setting_type' => 'string',
            ],
        );
        app(TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $this->assertSame(
            'acct_test_123456',
            DonationStripeAccountService::accountIdForTenant($this->testTenantId),
        );
    }

    public function test_accountIdForTenant_ignoresInvalidAccountIds(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            [
                'tenant_id' => $this->testTenantId,
                'setting_key' => DonationStripeAccountService::SETTING_CONNECT_ACCOUNT_ID,
            ],
            [
                'setting_value' => 'sk_live_not_an_account',
                'setting_type' => 'string',
            ],
        );
        app(TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $this->assertNull(DonationStripeAccountService::accountIdForTenant($this->testTenantId));
        $this->assertSame([], DonationStripeAccountService::stripeOptionsForTenant($this->testTenantId));
    }

    public function test_statusFromAccountObject_reportsReadyOnlyWhenChargesAndPayoutsAreEnabled(): void
    {
        $status = DonationStripeAccountService::statusFromAccountObject((object) [
            'id' => 'acct_ready_123',
            'details_submitted' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'requirements' => (object) [
                'currently_due' => [],
                'disabled_reason' => null,
            ],
        ]);

        $this->assertSame('ready', $status['state']);
        $this->assertTrue($status['charges_enabled']);
        $this->assertTrue($status['payouts_enabled']);
        $this->assertSame([], $status['requirements_due']);
    }

    public function test_statusFromAccountObject_reportsRestrictedWhenRequirementsAreDue(): void
    {
        $status = DonationStripeAccountService::statusFromAccountObject((object) [
            'id' => 'acct_due_123',
            'details_submitted' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'requirements' => (object) [
                'currently_due' => ['external_account'],
                'disabled_reason' => 'requirements.past_due',
            ],
        ]);

        $this->assertSame('restricted', $status['state']);
        $this->assertSame(['external_account'], $status['requirements_due']);
        $this->assertSame('requirements.past_due', $status['disabled_reason']);
    }

    public function test_onboarding_source_createsAccountLinkAndPersistsTenantAccount(): void
    {
        $source = file_get_contents(app_path('Services/DonationStripeAccountService.php'));

        $this->assertStringContainsString('$client->accounts->create', $source);
        $this->assertStringContainsString("'type' => 'express'", $source);
        $this->assertStringContainsString('$client->accountLinks->create', $source);
        $this->assertStringContainsString('self::SETTING_CONNECT_ACCOUNT_ID', $source);
        $this->assertStringContainsString("'type' => 'account_onboarding'", $source);
    }

    public function test_payment_routing_uses_connect_only_after_account_is_ready(): void
    {
        $source = file_get_contents(app_path('Services/DonationStripeAccountService.php'));

        $this->assertStringContainsString('accountIdForTenantReadyForCharges', $source);
        $this->assertStringContainsString("return (\$status['state'] ?? null) === 'ready' ? \$accountId : null", $source);
        $this->assertStringContainsString("'fallback_reason' => \$accountId && !\$activeAccountId ? 'stripe_connect_not_ready' : null", $source);
    }
}
