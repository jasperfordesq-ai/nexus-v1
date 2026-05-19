<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Listeners\NotifyTransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class TransactionNotificationReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transaction_completed_notifications_are_idempotent_per_tenant_transaction_user_channel(): void
    {
        $this->assertTrue(
            Schema::hasTable('transaction_notification_deliveries'),
            'transaction_notification_deliveries must exist as the durable idempotency ledger.'
        );

        $tenantId = 999;
        TenantContext::setById($tenantId);

        $sender = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Sender',
            'email' => 'tx-sender-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $receiver = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Receiver',
            'email' => 'tx-receiver-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $transaction = Transaction::factory()->forTenant($tenantId)->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 2,
            'description' => 'Reliability audit exchange',
            'status' => 'completed',
        ]);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($this->testTenantId);

        $listener = new NotifyTransactionCompleted();
        $event = new TransactionCompleted($transaction, $sender, $receiver, $tenantId);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertNull(TenantContext::currentId());
        $this->assertCount(4, $mailer->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $receiver->id)
            ->where('type', 'transaction')
            ->where('link', '/wallet')
            ->count());
        $this->assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('type', 'transaction')
            ->count());

        foreach ($mailer->calls as $call) {
            $this->assertSame($tenantId, $call['options']['tenant_id']);
        }

        $this->assertSame(5, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('status', 'delivered')
            ->count());
        $this->assertSame(1, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('user_id', $receiver->id)
            ->where('event', 'credit_received')
            ->where('channel', 'bell')
            ->whereNotNull('evidence_id')
            ->count());
        $this->assertSame(0, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_id', $transaction->id)
            ->count());
    }

    public function test_failed_transaction_bell_claim_can_be_retried_without_duplicate_successful_delivery(): void
    {
        $tenantId = 999;
        TenantContext::setById($tenantId);

        $sender = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Sender',
            'email' => 'tx-retry-sender-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $receiver = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Receiver',
            'email' => 'tx-retry-receiver-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $transaction = Transaction::factory()->forTenant($tenantId)->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 1,
            'status' => 'completed',
        ]);

        DB::table('transaction_notification_deliveries')->insert([
            'tenant_id' => $tenantId,
            'transaction_id' => $transaction->id,
            'user_id' => $receiver->id,
            'event' => 'credit_received',
            'channel' => 'bell',
            'status' => 'failed',
            'attempts' => 1,
            'claimed_at' => now()->subMinutes(30),
            'failed_at' => now()->subMinutes(30),
            'last_error' => 'previous bell failure',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        app()->instance(EmailDispatchService::class, $this->fakeMailer());

        (new NotifyTransactionCompleted())->handle(new TransactionCompleted($transaction, $sender, $receiver, $tenantId));

        $delivery = DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('user_id', $receiver->id)
            ->where('event', 'credit_received')
            ->where('channel', 'bell')
            ->first();

        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(2, (int) $delivery->attempts);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $receiver->id)
            ->where('type', 'transaction')
            ->count());
    }

    public function test_failed_transaction_email_claims_retry_without_duplicate_bell(): void
    {
        $tenantId = 999;
        TenantContext::setById($tenantId);

        $sender = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Sender',
            'email' => 'tx-email-retry-sender-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $receiver = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Receiver',
            'email' => 'tx-email-retry-receiver-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $transaction = Transaction::factory()->forTenant($tenantId)->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 3,
            'status' => 'completed',
        ]);
        $event = new TransactionCompleted($transaction, $sender, $receiver, $tenantId);

        $failingMailer = $this->fakeMailer(false);
        app()->instance(EmailDispatchService::class, $failingMailer);
        (new NotifyTransactionCompleted())->handle($event);

        $this->assertCount(4, $failingMailer->calls);
        $this->assertSame(4, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('channel', 'email')
            ->where('status', 'failed')
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $receiver->id)
            ->where('type', 'transaction')
            ->count());

        $retryMailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $retryMailer);
        (new NotifyTransactionCompleted())->handle($event);

        $this->assertCount(4, $retryMailer->calls);
        $this->assertSame(4, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('channel', 'email')
            ->where('status', 'delivered')
            ->where('attempts', 2)
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $receiver->id)
            ->where('type', 'transaction')
            ->count());
    }

    public function test_suppressed_transaction_email_claim_is_marked_skipped_not_delivered(): void
    {
        $tenantId = 999;
        TenantContext::setById($tenantId);

        $sender = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Sender',
            'email' => 'tx-skip-sender-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $receiver = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Receiver',
            'email' => 'tx-skip-receiver-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        DB::table('users')
            ->where('id', $receiver->id)
            ->where('tenant_id', $tenantId)
            ->update(['notification_preferences' => json_encode(['email_reviews' => 0])]);
        $transaction = Transaction::factory()->forTenant($tenantId)->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 3,
            'status' => 'completed',
        ]);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        (new NotifyTransactionCompleted())->handle(new TransactionCompleted($transaction, $sender, $receiver, $tenantId));

        $this->assertCount(3, $mailer->calls);
        $this->assertSame(1, DB::table('transaction_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('transaction_id', $transaction->id)
            ->where('user_id', $receiver->id)
            ->where('event', 'review_request')
            ->where('channel', 'email')
            ->where('status', 'skipped')
            ->whereNull('delivered_at')
            ->count());
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
