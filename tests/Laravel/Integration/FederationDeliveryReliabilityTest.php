<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Events\FederatedReviewReceived;
use App\Http\Controllers\Api\FederationExternalWebhookController;
use App\Listeners\HandleFederatedReviewReceived;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class FederationDeliveryReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_federated_review_email_failure_remains_retryable(): void
    {
        foreach (['external_partner_id', 'external_id', 'email_sent_at', 'email_claimed_at', 'email_failed_at', 'email_last_error'] as $column) {
            if (!Schema::hasColumn('reviews', $column)) {
                $this->markTestSkipped("reviews.{$column} is not available.");
            }
        }

        $tenantId = $this->testTenantId;
        TenantContext::reset();
        $this->assertTrue(TenantContext::setById($tenantId));
        $this->assertSame($tenantId, TenantContext::getId());

        $receiver = User::factory()->forTenant($tenantId)->create([
            'email' => 'fed-review-retry-' . uniqid('', true) . '@example.test',
            'status' => 'active',
            'preferred_language' => 'en',
            'federation_notifications_enabled' => 1,
        ]);

        DB::table('users')
            ->where('id', (int) $receiver->id)
            ->where('tenant_id', $tenantId)
            ->update(['notification_preferences' => json_encode(['email_reviews' => 1])]);

        $reviewId = (int) DB::table('reviews')->insertGetId([
            'tenant_id' => $tenantId,
            'reviewer_id' => (int) $receiver->id,
            'receiver_id' => (int) $receiver->id,
            'rating' => 5,
            'comment' => 'Federated review delivery regression',
            'review_type' => 'federated',
            'external_partner_id' => 12345,
            'external_id' => 'review-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app()->instance(EmailDispatchService::class, $this->fakeDispatcher(false));

        $listener = new HandleFederatedReviewReceived();

        try {
            $listener->handle(new FederatedReviewReceived(
                $tenantId,
                12345,
                $reviewId,
                [
                    'receiver_id' => (int) $receiver->id,
                    'rating' => 5,
                    'comment' => 'Federated review delivery regression',
                ]
            ));

            $this->fail('Expected failed federated review email delivery to throw for queue retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Federated review email dispatch returned false', $e->getMessage());
        }

        $review = DB::table('reviews')->where('id', $reviewId)->where('tenant_id', $tenantId)->first();
        $this->assertNull($review->email_sent_at);
        $this->assertNull($review->email_claimed_at);
        $this->assertNotNull($review->email_failed_at);
    }

    public function test_duplicate_external_transaction_repairs_missing_email_delivery_before_ack(): void
    {
        foreach (['notification_sent_at', 'email_sent_at', 'email_failed_at', 'email_last_error', 'external_idempotency_key'] as $column) {
            if (!Schema::hasColumn('federation_transactions', $column)) {
                $this->markTestSkipped("federation_transactions.{$column} is not available.");
            }
        }

        $tenantId = $this->testTenantId;
        TenantContext::reset();
        $this->assertTrue(TenantContext::setById($tenantId));
        $this->assertSame($tenantId, TenantContext::getId());

        $receiver = User::factory()->forTenant($tenantId)->create([
            'email' => 'fed-tx-repair-' . uniqid('', true) . '@example.test',
            'status' => 'active',
            'preferred_language' => 'en',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => (int) $receiver->id,
            'tenant_id' => $tenantId,
            'status' => 'active',
        ]);

        $partner = (object) [
            'id' => 45678,
            'tenant_id' => $tenantId,
            'name' => 'Reliability Partner',
            'allow_transactions' => 1,
        ];
        $externalTxId = 'ext-tx-' . uniqid();
        $idempotencyKey = 'external-partner:' . $partner->id . ':transaction:' . $externalTxId;

        DB::table('federation_transactions')->insert([
            'sender_tenant_id' => 0,
            'sender_user_id' => 987,
            'receiver_tenant_id' => $tenantId,
            'receiver_user_id' => (int) $receiver->id,
            'amount' => 2.5,
            'description' => 'Duplicate repair transaction',
            'status' => 'completed',
            'external_partner_id' => $partner->id,
            'external_receiver_name' => 'External Sender',
            'external_transaction_id' => $externalTxId,
            'external_idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);

        $mailer = $this->fakeDispatcher(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $controller = app(FederationExternalWebhookController::class);
        $method = new \ReflectionMethod($controller, 'handleTransactionCompleted');
        $method->setAccessible(true);
        TenantContext::reset();
        $this->assertTrue(TenantContext::setById($tenantId));

        $result = $method->invoke($controller, [
            'external_transaction_id' => $externalTxId,
            'recipient_id' => (int) $receiver->id,
            'sender_id' => 987,
            'sender_name' => 'External Sender',
            'amount' => 2.5,
            'description' => 'Duplicate repair transaction',
        ], $partner);

        $this->assertSame('duplicate', $result['status'], json_encode($result));
        $this->assertCount(1, $mailer->calls);

        $transaction = DB::table('federation_transactions')
            ->where('external_idempotency_key', $idempotencyKey)
            ->where('receiver_tenant_id', $tenantId)
            ->first();
        $this->assertNotNull($transaction->email_sent_at);
        $this->assertNull($transaction->email_failed_at);
    }

    private function fakeDispatcher(bool $result): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            public array $calls = [];

            public function __construct(private readonly bool $result)
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
