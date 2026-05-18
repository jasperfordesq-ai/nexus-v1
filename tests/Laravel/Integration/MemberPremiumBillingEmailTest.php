<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\MemberPremiumService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MemberPremiumBillingEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_payment_failed_notifies_member_under_subscription_tenant(): void
    {
        $tenantId = 999;
        [$user, $subscriptionId, $stripeSubId] = $this->createMemberSubscription($tenantId);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        MemberPremiumService::applyWebhookEvent((object) [
            'id' => 'evt_member_failed_' . uniqid(),
            'type' => 'invoice.payment_failed',
            'data' => (object) [
                'object' => (object) [
                    'subscription' => $stripeSubId,
                ],
            ],
        ]);

        $row = DB::table('member_subscriptions')->where('id', $subscriptionId)->first();
        $this->assertSame('past_due', $row->status);
        $this->assertNotNull($row->grace_period_ends_at);
        $this->assertSame(2, TenantContext::currentId());

        $this->assertCount(1, $mailer->calls);
        $this->assertSame($user->email, $mailer->calls[0]['to']);
        $this->assertSame('billing', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'type' => 'member_premium_billing',
        ]);
    }

    public function test_invoice_paid_recovery_notifies_member_and_clears_grace(): void
    {
        $tenantId = 999;
        [$user, $subscriptionId, $stripeSubId] = $this->createMemberSubscription($tenantId, [
            'status' => 'past_due',
            'grace_period_ends_at' => now()->addDays(3),
        ]);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById(2);

        MemberPremiumService::applyWebhookEvent((object) [
            'id' => 'evt_member_paid_' . uniqid(),
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'subscription' => $stripeSubId,
                ],
            ],
        ]);

        $row = DB::table('member_subscriptions')->where('id', $subscriptionId)->first();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->grace_period_ends_at);
        $this->assertSame(2, TenantContext::currentId());

        $this->assertCount(1, $mailer->calls);
        $this->assertSame($user->email, $mailer->calls[0]['to']);
        $this->assertSame('billing', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'type' => 'member_premium_billing',
        ]);
    }

    public function test_replayed_member_premium_event_does_not_duplicate_email_or_bell(): void
    {
        $tenantId = 999;
        [$user, , $stripeSubId] = $this->createMemberSubscription($tenantId);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        $event = (object) [
            'id' => 'evt_member_replay_' . uniqid(),
            'type' => 'invoice.payment_failed',
            'data' => (object) [
                'object' => (object) [
                    'subscription' => $stripeSubId,
                ],
            ],
        ];

        MemberPremiumService::applyWebhookEvent($event);
        MemberPremiumService::applyWebhookEvent($event);

        $this->assertCount(1, $mailer->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('type', 'member_premium_billing')
            ->count());
    }

    /**
     * @return array{0:User,1:int,2:string}
     */
    private function createMemberSubscription(int $tenantId, array $overrides = []): array
    {
        $user = User::factory()->forTenant($tenantId)->create([
            'email' => 'premium-member-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);

        $tierId = (int) DB::table('member_premium_tiers')->insertGetId([
            'tenant_id' => $tenantId,
            'slug' => 'premium-' . uniqid(),
            'name' => 'Premium Plus',
            'description' => 'Test tier',
            'monthly_price_cents' => 500,
            'yearly_price_cents' => 5000,
            'features' => json_encode(['verified_badge']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stripeSubId = 'sub_member_' . uniqid();
        $subscriptionId = (int) DB::table('member_subscriptions')->insertGetId(array_merge([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'tier_id' => $tierId,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_customer_id' => 'cus_member_' . uniqid(),
            'status' => 'active',
            'billing_interval' => 'monthly',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return [$user, $subscriptionId, $stripeSubId];
    }

    private function fakeMailer(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}
