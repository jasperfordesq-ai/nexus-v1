<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\BalanceAlertService;
use App\Services\CreditDonationService;
use App\Services\DonationEmailService;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class CreditDonationTenantEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credit_donation_rejects_recipient_outside_requested_tenant(): void
    {
        $tenantId = 999;
        $donor = User::factory()->forTenant($tenantId)->create([
            'balance' => 25,
            'email' => 'credit-donor-' . uniqid('', true) . '@example.test',
        ]);
        $foreignRecipient = User::factory()->forTenant(2)->create([
            'balance' => 0,
            'email' => 'credit-foreign-recipient-' . uniqid('', true) . '@example.test',
        ]);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        $result = app(CreditDonationService::class)->donate($tenantId, (int) $donor->id, (int) $foreignRecipient->id, 5, 'Thanks');

        $this->assertFalse($result);
        $this->assertCount(0, $mailer->calls);
        $this->assertSame(0, DB::table('credit_donations')->where('tenant_id', $tenantId)->where('donor_id', $donor->id)->count());
        $this->assertSame(25, (int) DB::table('users')->where('id', $donor->id)->value('balance'));
    }

    public function test_donation_emails_build_wallet_links_from_explicit_tenant_and_restore_context(): void
    {
        $tenantId = 999;
        $donor = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Donor',
            'email' => 'credit-donor-email-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $recipient = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Recipient',
            'email' => 'credit-recipient-email-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        DonationEmailService::sendDonationEmails($tenantId, $donor, $recipient, 3, null);

        $this->assertCount(2, $mailer->calls);
        foreach ($mailer->calls as $call) {
            $this->assertSame($tenantId, $call['options']['tenant_id']);
            $this->assertStringContainsString('/test-999/wallet', $call['body']);
            $this->assertStringNotContainsString('/hour-timebank/wallet', $call['body']);
        }
        $this->assertSame(2, TenantContext::currentId());
    }

    public function test_balance_alert_does_not_record_daily_alert_when_email_send_fails(): void
    {
        $tenantId = 999;
        [$owner, $orgId] = $this->createFundedOrgWallet($tenantId, 5.0);
        $mailer = $this->fakeMailer(false);
        app()->instance(EmailDispatchService::class, $mailer);
        TenantContext::setById($tenantId);

        $result = app(BalanceAlertService::class)->checkBalance($orgId, 5.0, 'Low Wallet Org');

        $this->assertSame('critical', $result['alert_type']);
        $this->assertFalse($result['alert_sent']);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($owner->email, $mailer->calls[0]['to']);
        $this->assertSame(0, DB::table('org_balance_alerts')->where('tenant_id', $tenantId)->where('organization_id', $orgId)->count());
    }

    public function test_balance_alert_records_daily_alert_only_after_successful_email(): void
    {
        $tenantId = 999;
        [, $orgId] = $this->createFundedOrgWallet($tenantId, 25.0);
        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);
        TenantContext::setById($tenantId);

        $result = app(BalanceAlertService::class)->checkBalance($orgId, 25.0, 'Low Wallet Org');

        $this->assertSame('low', $result['alert_type']);
        $this->assertTrue($result['alert_sent']);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame(1, DB::table('org_balance_alerts')
            ->where('tenant_id', $tenantId)
            ->where('organization_id', $orgId)
            ->where('alert_type', 'low')
            ->count());
    }

    /**
     * @return array{0:User,1:int}
     */
    private function createFundedOrgWallet(int $tenantId, float $balance): array
    {
        $owner = User::factory()->forTenant($tenantId)->create([
            'email' => 'org-wallet-owner-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'name' => 'Low Wallet Org',
            'status' => 'approved',
            'balance' => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('org_wallets')->insert([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'balance' => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('org_transactions')->insert([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'sender_type' => 'user',
            'sender_id' => $owner->id,
            'receiver_type' => 'organization',
            'receiver_id' => $orgId,
            'amount' => 100,
            'description' => 'Initial funding',
            'created_at' => now(),
        ]);

        return [$owner, $orgId];
    }

    private function fakeMailer(bool $result = true): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            public array $calls = [];

            public function __construct(private bool $result)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return $this->result;
            }
        };
    }
}
