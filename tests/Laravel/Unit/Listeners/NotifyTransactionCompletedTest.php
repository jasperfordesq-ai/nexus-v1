<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Events\TransactionCompleted;
use App\Listeners\NotifyTransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyTransactionCompleted listener.
 *
 * NotificationDispatcher is alias-mocked so static calls (sendCreditEmail,
 * sendCreditSentEmail, sendReviewRequestEmail, fanOutPush) are interceptable
 * without actually sending SMTP or push payloads. Bell notifications ARE
 * written to the DB via Notification::createNotification(), so we can assert
 * durable in-app delivery with raw DB queries.
 *
 * Tests run in separate processes so the alias mock can shadow the real class
 * before Laravel's service container autoloads it. Manual tearDown handles DB
 * cleanup instead of DatabaseTransactions (incompatible with separate processes).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyTransactionCompletedTest extends TestCase
{
    /** @var \Mockery\MockInterface */
    private $dispatcherAlias;

    private int $senderId;
    private int $receiverId;
    private int $transactionId;

    /** User IDs seeded in this test — deleted in tearDown. */
    private array $userIds = [];

    protected function setUp(): void
    {
        // Alias mock MUST be created before parent::setUp() so it shadows the
        // class before Laravel's service container can autoload the real one.
        // shouldIgnoreMissing() silences unexpected calls (fanOutPush, etc.);
        // per-test shouldReceive() expectations layer on top of this.
        $this->dispatcherAlias = Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldIgnoreMissing();

        parent::setUp();

        // Seed two real users in tenant 2 (hour-timebank).
        $this->senderId = (int) DB::table('users')->insertGetId([
            'name'                     => 'TX Sender',
            'first_name'               => 'Alice',
            'last_name'                => 'Sender',
            'email'                    => 'tx-sender-' . uniqid() . '@example.com',
            'tenant_id'                => 2,
            'status'                   => 'active',
            'role'                     => 'member',
            'xp'                       => 0,
            'preferred_language'       => 'en',
            'notification_preferences' => json_encode(['email_transactions' => true]),
            'created_at'               => now(),
        ]);
        $this->userIds[] = $this->senderId;

        $this->receiverId = (int) DB::table('users')->insertGetId([
            'name'                     => 'TX Receiver',
            'first_name'               => 'Bob',
            'last_name'                => 'Receiver',
            'email'                    => 'tx-receiver-' . uniqid() . '@example.com',
            'tenant_id'                => 2,
            'status'                   => 'active',
            'role'                     => 'member',
            'xp'                       => 0,
            'preferred_language'       => 'en',
            'notification_preferences' => json_encode(['email_transactions' => true]),
            'created_at'               => now(),
        ]);
        $this->userIds[] = $this->receiverId;

        // Synthetic transaction ID — real enough for idempotency rows, but the
        // listener never looks it up in the transactions table.
        $this->transactionId = 9990000 + random_int(1, 9999);
    }

    protected function tearDown(): void
    {
        // Separate process = no DatabaseTransactions rollback; delete manually.
        DB::table('transaction_notification_deliveries')
            ->where('transaction_id', $this->transactionId)
            ->delete();
        DB::table('notifications')
            ->whereIn('user_id', $this->userIds)
            ->delete();
        DB::table('users')
            ->whereIn('id', $this->userIds)
            ->delete();

        Mockery::close();
        parent::tearDown();
    }

    // ── helper ────────────────────────────────────────────────────────────

    /**
     * Build a TransactionCompleted event using the seeded sender/receiver users.
     */
    private function makeEvent(float $amount = 1.0, string $description = ''): TransactionCompleted
    {
        $transaction              = new Transaction();
        $transaction->id          = $this->transactionId;
        $transaction->amount      = $amount;
        $transaction->description = $description;
        $transaction->tenant_id   = 2;

        $sender              = new User();
        $sender->id          = $this->senderId;
        $sender->first_name  = 'Alice';
        $sender->preferred_language = 'en';

        $receiver              = new User();
        $receiver->id          = $this->receiverId;
        $receiver->first_name  = 'Bob';
        $receiver->preferred_language = 'en';

        return new TransactionCompleted($transaction, $sender, $receiver, 2);
    }

    // ── 1. Structural ─────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyTransactionCompleted::class)),
            'NotifyTransactionCompleted must implement ShouldQueue'
        );
    }

    // ── 2. Bell notification written to DB ───────────────────────────────

    public function test_creates_bell_notification_for_receiver(): void
    {
        (new NotifyTransactionCompleted())->handle($this->makeEvent(1.0));

        $bell = DB::table('notifications')
            ->where('user_id', $this->receiverId)
            ->where('type', 'transaction')
            ->first();

        $this->assertNotNull($bell, 'Receiver must get a bell notification of type=transaction');
        $this->assertSame('/wallet', $bell->link);
    }

    public function test_bell_notification_message_contains_sender_name(): void
    {
        (new NotifyTransactionCompleted())->handle($this->makeEvent(1.0));

        $bell = DB::table('notifications')
            ->where('user_id', $this->receiverId)
            ->where('type', 'transaction')
            ->first();

        $this->assertNotNull($bell);
        // The listener builds: __('notifications.credit_received', ['name'=>'Alice',...])
        // → "Alice sent you 1 hour(s)"
        $this->assertStringContainsString('Alice', (string) $bell->message);
    }

    public function test_bell_message_includes_description_when_present(): void
    {
        (new NotifyTransactionCompleted())->handle($this->makeEvent(2.0, 'Garden help'));

        $bell = DB::table('notifications')
            ->where('user_id', $this->receiverId)
            ->where('type', 'transaction')
            ->first();

        $this->assertNotNull($bell);
        // When description is non-empty the listener appends:
        //   __('notifications.credit_received_for', ['description' => 'Garden help'])
        $this->assertStringContainsString('Garden help', (string) $bell->message);
    }

    public function test_no_bell_notification_created_for_sender(): void
    {
        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        $senderBell = DB::table('notifications')
            ->where('user_id', $this->senderId)
            ->where('type', 'transaction')
            ->first();

        // Sender gets an email only, not a bell notification.
        $this->assertNull($senderBell, 'Sender must NOT receive a bell notification');
    }

    // ── 3. Email dispatch calls ───────────────────────────────────────────

    public function test_sends_credit_received_email_to_receiver(): void
    {
        // sendCreditEmail($receiverId, $senderName, $amount, $description)
        $this->dispatcherAlias
            ->shouldReceive('sendCreditEmail')
            ->once()
            ->with($this->receiverId, 'Alice', 1.0, Mockery::any())
            ->andReturn(true);

        (new NotifyTransactionCompleted())->handle($this->makeEvent(1.0));

        // Mockery assertion verified at tearDown; also assert the delivery row
        // to confirm the email path was exercised and tracked.
        $row = DB::table('transaction_notification_deliveries')
            ->where('transaction_id', $this->transactionId)
            ->where('user_id', $this->receiverId)
            ->where('event', 'credit_received')
            ->where('channel', 'email')
            ->first();
        $this->assertNotNull($row, 'Email delivery row must exist for receiver after sendCreditEmail');
    }

    public function test_sends_credit_sent_email_to_sender(): void
    {
        // sendCreditSentEmail($senderId, $recipientName, $amount, $description)
        $this->dispatcherAlias
            ->shouldReceive('sendCreditSentEmail')
            ->once()
            ->with($this->senderId, 'Bob', 1.0, Mockery::any())
            ->andReturn(true);

        (new NotifyTransactionCompleted())->handle($this->makeEvent(1.0));

        // Confirm the sender-side email delivery row was written.
        $row = DB::table('transaction_notification_deliveries')
            ->where('transaction_id', $this->transactionId)
            ->where('user_id', $this->senderId)
            ->where('event', 'credit_sent')
            ->where('channel', 'email')
            ->first();
        $this->assertNotNull($row, 'Email delivery row must exist for sender after sendCreditSentEmail');
    }

    public function test_sends_review_request_to_both_parties(): void
    {
        // sendReviewRequestEmail($userId, $otherPartyName, $transactionId)
        // Called once for receiver and once for sender.
        $this->dispatcherAlias
            ->shouldReceive('sendReviewRequestEmail')
            ->twice()
            ->andReturn(true);

        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        // Confirm delivery rows were written for both parties.
        $reviewRows = DB::table('transaction_notification_deliveries')
            ->where('transaction_id', $this->transactionId)
            ->where('event', 'review_request')
            ->where('channel', 'email')
            ->count();
        $this->assertSame(2, $reviewRows, 'Review request email delivery rows must exist for both sender and receiver');
    }

    // ── 4. Email preference gate ──────────────────────────────────────────

    public function test_skips_credit_email_for_receiver_when_preference_disabled(): void
    {
        DB::table('users')->where('id', $this->receiverId)->update([
            'notification_preferences' => json_encode(['email_transactions' => false]),
        ]);

        $this->dispatcherAlias->shouldNotReceive('sendCreditEmail');

        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        // Bell notification must still fire regardless of email pref.
        $bell = DB::table('notifications')
            ->where('user_id', $this->receiverId)
            ->where('type', 'transaction')
            ->first();
        $this->assertNotNull($bell, 'Bell must still be created even when email is disabled');
    }

    public function test_skips_credit_sent_email_for_sender_when_preference_disabled(): void
    {
        DB::table('users')->where('id', $this->senderId)->update([
            'notification_preferences' => json_encode(['email_transactions' => false]),
        ]);

        $this->dispatcherAlias->shouldNotReceive('sendCreditSentEmail');

        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        // Mockery verifies shouldNotReceive in tearDown via Mockery::close().
        $this->assertTrue(true);
    }

    // ── 5. Idempotency via transaction_notification_deliveries ────────────

    public function test_bell_notification_delivered_exactly_once_on_replay(): void
    {
        $event    = $this->makeEvent();
        $listener = new NotifyTransactionCompleted();

        $listener->handle($event); // first delivery → inserts bell + delivery row
        $listener->handle($event); // replay → claimDelivery finds DELIVERED, skips

        $bellCount = DB::table('notifications')
            ->where('user_id', $this->receiverId)
            ->where('type', 'transaction')
            ->count();

        $this->assertSame(1, $bellCount, 'Bell notification must be created exactly once per transaction');
    }

    public function test_delivery_row_marked_delivered_after_successful_bell(): void
    {
        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        $row = DB::table('transaction_notification_deliveries')
            ->where('tenant_id', 2)
            ->where('transaction_id', $this->transactionId)
            ->where('user_id', $this->receiverId)
            ->where('event', 'credit_received')
            ->where('channel', 'bell')
            ->first();

        $this->assertNotNull($row, 'Delivery tracking row must exist for receiver bell');
        $this->assertSame('delivered', $row->status);
    }

    // ── 6. Graceful handling of invalid tenant ────────────────────────────

    public function test_skips_all_work_when_tenant_id_is_invalid(): void
    {
        $transaction              = new Transaction();
        $transaction->id          = $this->transactionId;
        $transaction->amount      = 1.0;
        $transaction->description = '';

        $sender             = new User();
        $sender->id         = $this->senderId;
        $sender->first_name = 'Alice';

        $receiver             = new User();
        $receiver->id         = $this->receiverId;
        $receiver->first_name = 'Bob';

        // TenantContext::setById(0) returns false → listener logs warning + returns.
        $event = new TransactionCompleted($transaction, $sender, $receiver, 0);

        // Must not throw.
        (new NotifyTransactionCompleted())->handle($event);

        // No bell notification should have been written.
        $bellCount = DB::table('notifications')
            ->whereIn('user_id', $this->userIds)
            ->where('type', 'transaction')
            ->count();
        $this->assertSame(0, $bellCount, 'Invalid tenant must cause a no-op — no bell created');
    }

    // ── 7. Exception resilience ───────────────────────────────────────────

    public function test_does_not_propagate_exception_on_dispatcher_failure(): void
    {
        // sendCreditEmail throws → deliverTransactionEmail re-throws →
        // outer handle() catch block swallows it and logs error.
        // The test verifies the exception does NOT escape handle().
        $this->dispatcherAlias
            ->shouldReceive('sendCreditEmail')
            ->andThrow(new \RuntimeException('SMTP unavailable'));

        // Must not propagate.
        (new NotifyTransactionCompleted())->handle($this->makeEvent());

        $this->assertTrue(true, 'Listener must absorb all exceptions via outer try/catch');
    }
}
