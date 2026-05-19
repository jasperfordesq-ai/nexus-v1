<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Http\Controllers\Api\PartnerApi\PartnerV1Controller;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

class PartnerApiWalletReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partner_wallet_balance_reads_live_user_balance(): void
    {
        $tenantId = $this->testTenantId;
        $user = User::factory()->forTenant($tenantId)->create([
            'balance' => 7.00,
        ]);

        $response = TenantContext::runForTenant($tenantId, fn () => (new PartnerV1Controller())->walletBalance((int) $user->id));
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(7.0, (float) $payload['data']['balance_hours']);
    }

    public function test_partner_wallet_credit_uses_live_wallet_and_partner_scoped_idempotency(): void
    {
        $tenantId = $this->testTenantId;
        $user = User::factory()->forTenant($tenantId)->create([
            'balance' => 1.00,
            'email' => 'partner-credit-' . uniqid('', true) . '@example.test',
        ]);
        $partnerId = $this->createPartner($tenantId, 'credit-partner');
        $otherPartnerId = $this->createPartner($tenantId, 'other-partner');
        $this->createWebhookSubscription($partnerId, 'https://partner.example.test/hook');
        $this->createWebhookSubscription($otherPartnerId, 'https://other.example.test/hook');

        Http::fake(fn () => Http::response([], 204));
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        $first = TenantContext::runForTenant($tenantId, fn () => (new PartnerV1Controller())->walletCredit($this->walletCreditRequest($partnerId, [
            'user_id' => $user->id,
            'hours' => 2,
            'reference' => 'bank-ref-1',
            'note' => 'Settled transfer',
        ])));
        $second = TenantContext::runForTenant($tenantId, fn () => (new PartnerV1Controller())->walletCredit($this->walletCreditRequest($partnerId, [
            'user_id' => $user->id,
            'hours' => 2,
            'reference' => 'bank-ref-1',
            'note' => 'Settled transfer',
        ])));

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertTrue($second->getData(true)['data']['replayed']);
        $this->assertSame(3.0, (float) DB::table('users')->where('id', $user->id)->value('balance'));
        $this->assertSame(1, DB::table('transactions')->where('tenant_id', $tenantId)->where('receiver_id', $user->id)->where('transaction_type', 'other')->count());
        $this->assertSame(1, DB::table('api_partner_wallet_credits')->where('tenant_id', $tenantId)->where('partner_id', $partnerId)->where('reference', 'bank-ref-1')->count());
        $this->assertCount(1, $mailer->calls);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://partner.example.test/hook');
    }

    public function test_partner_wallet_credit_reference_conflict_does_not_mutate_wallet(): void
    {
        $tenantId = $this->testTenantId;
        $user = User::factory()->forTenant($tenantId)->create(['balance' => 1.00]);
        $partnerId = $this->createPartner($tenantId, 'conflict-partner');
        app()->instance(EmailDispatchService::class, $this->fakeMailer());

        TenantContext::runForTenant($tenantId, fn () => (new PartnerV1Controller())->walletCredit($this->walletCreditRequest($partnerId, [
            'user_id' => $user->id,
            'hours' => 2,
            'reference' => 'same-ref',
        ])));

        $response = TenantContext::runForTenant($tenantId, fn () => (new PartnerV1Controller())->walletCredit($this->walletCreditRequest($partnerId, [
            'user_id' => $user->id,
            'hours' => 3,
            'reference' => 'same-ref',
        ])));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(3.0, (float) DB::table('users')->where('id', $user->id)->value('balance'));
        $this->assertSame(1, DB::table('transactions')->where('tenant_id', $tenantId)->where('receiver_id', $user->id)->count());
    }

    private function createPartner(int $tenantId, string $slug): int
    {
        return (int) DB::table('api_partners')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug . '-' . uniqid(),
            'status' => 'active',
            'is_sandbox' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWebhookSubscription(int $partnerId, string $url): void
    {
        DB::table('api_webhook_subscriptions')->insert([
            'tenant_id' => DB::table('api_partners')->where('id', $partnerId)->value('tenant_id'),
            'partner_id' => $partnerId,
            'event_types' => json_encode(['wallet.credited']),
            'target_url' => $url,
            'secret' => 'whsec_test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function walletCreditRequest(int $partnerId, array $payload): Request
    {
        $request = Request::create('/api/partner/v1/wallet/credit', 'POST', $payload);
        $partner = DB::table('api_partners')->where('id', $partnerId)->first();
        $request->attributes->set('partner', [
            'id' => $partnerId,
            'name' => (string) $partner->name,
            'slug' => (string) $partner->slug,
        ]);

        return $request;
    }

    private function fakeMailer(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}
