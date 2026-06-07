<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederatedMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class FederatedMessageServiceTest extends TestCase
{
    /**
     * Build a fluent query-builder mock whose chainable methods all return
     * self, and whose terminal methods (first/count/exists/insertGetId/etc.)
     * return supplied defaults. This mirrors the actual Eloquent query-builder
     * chains used by FederatedMessageService without coupling the test to the
     * exact number/order of where()/whereIn() calls.
     *
     * @param array<string,mixed> $terminals
     */
    private function makeBuilder(array $terminals = []): Mockery\MockInterface
    {
        $builder = Mockery::mock();
        foreach (['where', 'orWhere', 'whereIn', 'whereNull', 'whereNotNull', 'select'] as $chain) {
            $builder->shouldReceive($chain)->andReturnSelf();
        }
        $builder->shouldReceive('first')->andReturn($terminals['first'] ?? null);
        $builder->shouldReceive('count')->andReturn($terminals['count'] ?? 0);
        $builder->shouldReceive('exists')->andReturn($terminals['exists'] ?? false);
        $builder->shouldReceive('insertGetId')->andReturn($terminals['insertGetId'] ?? 0);
        $builder->shouldReceive('insertOrIgnore')->andReturn($terminals['insertOrIgnore'] ?? 0);
        $builder->shouldReceive('update')->andReturn($terminals['update'] ?? 1);

        return $builder;
    }

    // =========================================================================
    // sendMessage()
    // =========================================================================

    public function test_sendMessage_fails_when_sender_not_found(): void
    {
        // First DB::table() lookup (sender) returns null → early return.
        DB::shouldReceive('table')->andReturn($this->makeBuilder(['first' => null]));

        $result = FederatedMessageService::sendMessage(999, 1, 2, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertEquals('Sender not found', $result['error']);
    }

    public function test_sendMessage_fails_when_receiver_not_found(): void
    {
        $sender = (object) ['id' => 1, 'tenant_id' => 2];

        // sender lookup → $sender; receiver lookup → null (early return).
        DB::shouldReceive('table')->andReturn(
            $this->makeBuilder(['first' => $sender]),
            $this->makeBuilder(['first' => null])
        );

        $result = FederatedMessageService::sendMessage(1, 999, 3, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Receiver not found', $result['error']);
    }

    public function test_sendMessage_fails_when_sender_not_opted_in(): void
    {
        $sender = (object) ['id' => 1, 'tenant_id' => 2];
        $receiver = (object) ['id' => 2, 'tenant_id' => 3];
        // senderSettings present but flags falsy → "has not enabled federated messaging".
        $settings = (object) ['federation_optin' => false, 'messaging_enabled_federated' => false];

        DB::shouldReceive('table')->andReturn(
            $this->makeBuilder(['first' => $sender]),   // users (sender)
            $this->makeBuilder(['first' => $receiver]), // users (receiver)
            $this->makeBuilder(['first' => $settings])  // federation_user_settings (sender)
        );

        $result = FederatedMessageService::sendMessage(1, 2, 3, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('federated messaging', $result['error']);
    }

    // =========================================================================
    // getUnreadCount()
    // =========================================================================

    public function test_getUnreadCount_returns_integer(): void
    {
        DB::shouldReceive('table')->andReturn($this->makeBuilder(['count' => 7]));

        $this->assertEquals(7, FederatedMessageService::getUnreadCount(1));
    }

    public function test_getUnreadCount_returns_zero_on_error(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('count')->andThrow(new \Exception('error'));
        DB::shouldReceive('table')->andReturn($builder);

        $this->assertEquals(0, FederatedMessageService::getUnreadCount(1));
    }

    // =========================================================================
    // storeExternalMessage()
    // =========================================================================

    public function test_storeExternalMessage_fails_when_receiver_not_found(): void
    {
        DB::shouldReceive('table')->andReturn($this->makeBuilder(['first' => null]));

        $result = FederatedMessageService::storeExternalMessage(999, 1, 2, 'Sender', 'Partner', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_storeExternalMessage_succeeds(): void
    {
        $receiver = (object) ['id' => 1, 'tenant_id' => 2];
        // Message row fetched after insert (used by ensureExternalMessageDelivery).
        $message = (object) [
            'id' => 10,
            'receiver_user_id' => 1,
            'receiver_tenant_id' => 2,
            'notification_sent_at' => now(),  // skip notification side effect
            'email_sent_at' => now(),         // skip email side effect → delivery success
        ];

        DB::shouldReceive('table')->andReturn(
            $this->makeBuilder(['first' => $receiver]),         // users (receiver)
            $this->makeBuilder(['exists' => true]),             // federation_user_settings opt-in
            $this->makeBuilder(['insertGetId' => 10]),          // insertExternalMessage (no external id)
            $this->makeBuilder(['first' => $message])           // fetch federation_messages row
        );

        $result = FederatedMessageService::storeExternalMessage(1, 5, 100, 'Sender Name', 'Partner Name', 'Subject', 'Body');
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['message_id']);
    }

    public function test_duplicate_external_message_returns_before_notification_and_email(): void
    {
        $source = file_get_contents(app_path('Services/FederatedMessageService.php'));

        $duplicateReturn = strpos($source, "if (!\$messageInsert['created'])");
        $bellDispatch = strpos($source, 'Notification::createNotification');
        $emailDispatch = strpos($source, 'FederationEmailService::sendExternalMessageNotification');

        $this->assertNotFalse($duplicateReturn);
        $this->assertNotFalse($bellDispatch);
        $this->assertNotFalse($emailDispatch);
        $this->assertLessThan($bellDispatch, $duplicateReturn);
        $this->assertLessThan($emailDispatch, $duplicateReturn);
    }
}
