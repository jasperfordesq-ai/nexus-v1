<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationV2Controller;
use App\Models\User;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

final class FederationV2ExternalTransferTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    private const SOURCE_TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableFederationForTenant(self::SOURCE_TENANT_ID);
        $this->app->make(FederationFeatureService::class)->clearCache();
        Cache::flush();
        TenantContext::setById(self::SOURCE_TENANT_ID);
    }

    public function test_external_transfer_fails_closed_without_partner_safeguarding_contract(): void
    {
        $senderId = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 25);
        $partner = $this->setupPartner('nexus', self::SOURCE_TENANT_ID);
        $remoteMemberId = 'member-uuid-abc-123';

        Http::fake(fn () => Http::response([
            'success' => true,
            'data' => [
                'transaction_id' => 'remote-tx-123',
                'status' => 'accepted',
            ],
        ], 200));

        $first = $this->callSendTransaction(
            $senderId,
            'ext-' . $partner->id . '-' . $remoteMemberId,
            'ext-' . $partner->id,
            10,
            'Remote garden help',
            'external-transfer-idem-1'
        );
        $second = $this->callSendTransaction(
            $senderId,
            'ext-' . $partner->id . '-' . $remoteMemberId,
            'ext-' . $partner->id,
            10,
            'Remote garden help',
            'external-transfer-idem-1'
        );

        $this->assertSame(503, $first->getStatusCode(), (string) $first->getContent());
        $this->assertSame(503, $second->getStatusCode(), (string) $second->getContent());
        $this->assertSame('SAFEGUARDING_POLICY_UNAVAILABLE', $first->getData(true)['errors'][0]['code']);

        $this->assertEqualsWithDelta(25, (float) DB::table('users')->where('id', $senderId)->value('balance'), 0.001);
        $this->assertSame(0, (int) DB::table('transactions')
            ->where('tenant_id', self::SOURCE_TENANT_ID)
            ->where('sender_id', $senderId)
            ->where('is_federated', 1)
            ->count());
        Http::assertNothingSent();
    }

    public function test_external_transfer_disabled_by_partner_allow_flag_does_not_post_or_debit(): void
    {
        $senderId = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 25);
        $partner = $this->setupPartner('nexus', self::SOURCE_TENANT_ID);
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['allow_transactions' => 0]);

        Http::fake();

        $response = $this->callSendTransaction(
            $senderId,
            'ext-' . $partner->id . '-member-123',
            'ext-' . $partner->id,
            10,
            'Should be blocked',
            'external-transfer-disabled-1'
        );

        $this->assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        $this->assertEqualsWithDelta(25, (float) DB::table('users')->where('id', $senderId)->value('balance'), 0.001);
        $this->assertSame(0, (int) DB::table('transactions')
            ->where('tenant_id', self::SOURCE_TENANT_ID)
            ->where('sender_id', $senderId)
            ->where('is_federated', 1)
            ->count());
        Http::assertNothingSent();
    }

    private function makeFederatedUser(int $tenantId, float $balance): int
    {
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'first_name' => 'External',
            'last_name' => 'Sender',
            'email' => 'external.sender.' . uniqid('', true) . '@example.com',
            'username' => 'ext_sender_' . substr(md5(uniqid('', true)), 0, 10),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'balance' => $balance,
            'status' => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->optInUserToFederation($userId);

        return $userId;
    }

    private function callSendTransaction(
        int $senderId,
        string $receiverId,
        string $receiverTenantId,
        int $amount,
        string $description,
        string $idempotencyKey
    ): JsonResponse {
        TenantContext::setById(self::SOURCE_TENANT_ID);
        $sender = User::query()->find($senderId);
        $this->assertNotNull($sender, 'Sender user must exist for the test.');
        $this->actingAs($sender);

        $this->app->instance('request', Request::create(
            '/api/v2/federation/transactions',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            ],
            json_encode([
                'receiver_id' => $receiverId,
                'receiver_tenant_id' => $receiverTenantId,
                'amount' => $amount,
                'description' => $description,
            ])
        ));

        TenantContext::setById(self::SOURCE_TENANT_ID);

        return $this->app->make(FederationV2Controller::class)->sendTransaction();
    }
}
