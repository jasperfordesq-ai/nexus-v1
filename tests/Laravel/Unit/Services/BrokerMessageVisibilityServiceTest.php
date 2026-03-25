<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BrokerMessageVisibilityService;
use App\Services\BrokerControlConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;

class BrokerMessageVisibilityServiceTest extends TestCase
{
    use DatabaseTransactions;
    private BrokerMessageVisibilityService $service;
    private $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig = Mockery::mock(BrokerControlConfigService::class);
        $this->service = new BrokerMessageVisibilityService($this->mockConfig);
    }

    public function test_constants_defined(): void
    {
        $this->assertSame('first_contact', BrokerMessageVisibilityService::REASON_FIRST_CONTACT);
        $this->assertSame('high_risk_listing', BrokerMessageVisibilityService::REASON_HIGH_RISK_LISTING);
        $this->assertSame('new_member', BrokerMessageVisibilityService::REASON_NEW_MEMBER);
        $this->assertSame('flagged_user', BrokerMessageVisibilityService::REASON_FLAGGED_USER);
        $this->assertSame('random_sample', BrokerMessageVisibilityService::REASON_MONITORING);
    }

    public function test_shouldCopyMessage_returns_null_when_visibility_disabled(): void
    {
        $this->mockConfig->shouldReceive('isBrokerVisibilityEnabled')->andReturn(false);

        $result = $this->service->shouldCopyMessage(1, 2);
        $this->assertNull($result);
    }

    public function test_shouldCopyMessage_returns_flagged_user_when_under_monitoring(): void
    {
        $this->mockConfig->shouldReceive('isBrokerVisibilityEnabled')->andReturn(true);

        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'under_monitoring' => true,
            'monitoring_expires_at' => null,
        ]);

        $result = $this->service->shouldCopyMessage(1, 2);
        $this->assertSame('flagged_user', $result);
    }

    public function test_copyMessageForBroker_returns_null_when_message_not_found(): void
    {
        // Message::find() with a non-existent ID returns null, so the method should return null
        $result = $this->service->copyMessageForBroker(999999, 'first_contact');
        $this->assertNull($result);
    }

    public function test_copyMessageForBroker_returns_null_when_already_copied(): void
    {
        // Create a real message in the DB, then a broker copy, and verify null is returned
        DB::table('users')->insertOrIgnore([
            'id' => 9001, 'tenant_id' => 2, 'name' => 'Sender Test',
            'email' => 'sender-bmv@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insertOrIgnore([
            'id' => 9002, 'tenant_id' => 2, 'name' => 'Receiver Test',
            'email' => 'receiver-bmv@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => 2, 'sender_id' => 9001, 'receiver_id' => 9002,
            'body' => 'Test message', 'created_at' => now(),
        ]);

        DB::table('broker_message_copies')->insert([
            'tenant_id' => 2, 'original_message_id' => $messageId,
            'conversation_key' => md5('9001-9002'),
            'sender_id' => 9001, 'receiver_id' => 9002,
            'message_body' => 'Test message', 'sent_at' => now(),
            'copy_reason' => 'first_contact', 'created_at' => now(),
        ]);

        $result = $this->service->copyMessageForBroker($messageId, 'first_contact');
        $this->assertNull($result);
    }

    public function test_markAsReviewed_returns_false_when_not_found(): void
    {
        // BrokerMessageCopy::find(999) should return null for a non-existent ID
        $result = $this->service->markAsReviewed(999999, 1);
        $this->assertFalse($result);
    }

    public function test_markAsReviewed_returns_true_on_success(): void
    {
        // Seed users and a message for the broker copy foreign key
        DB::table('users')->insertOrIgnore([
            'id' => 9001, 'tenant_id' => 2, 'name' => 'Sender Test',
            'email' => 'sender-bmv2@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insertOrIgnore([
            'id' => 9002, 'tenant_id' => 2, 'name' => 'Receiver Test',
            'email' => 'receiver-bmv2@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insertOrIgnore([
            'id' => 9003, 'tenant_id' => 2, 'name' => 'Broker Admin',
            'email' => 'broker-admin-bmv@test.com', 'role' => 'admin', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => 2, 'sender_id' => 9001, 'receiver_id' => 9002,
            'body' => 'Review test message', 'created_at' => now(),
        ]);

        $copyId = DB::table('broker_message_copies')->insertGetId([
            'tenant_id' => 2, 'original_message_id' => $messageId,
            'conversation_key' => md5('test-review'),
            'sender_id' => 9001, 'receiver_id' => 9002,
            'message_body' => 'Message to review', 'sent_at' => now(),
            'copy_reason' => 'new_member', 'created_at' => now(),
        ]);

        $result = $this->service->markAsReviewed($copyId, 9003);
        $this->assertTrue($result);

        // Verify the record was updated
        $updated = DB::table('broker_message_copies')->where('id', $copyId)->first();
        $this->assertNotNull($updated, 'Broker message copy should exist after markAsReviewed');
        $this->assertSame(9003, (int) $updated->reviewed_by);
        $this->assertNotNull($updated->reviewed_at);
    }

    public function test_countUnreviewed_returns_integer(): void
    {
        // Seed users and messages for foreign keys
        DB::table('users')->insertOrIgnore([
            'id' => 9001, 'tenant_id' => 2, 'name' => 'Sender Test',
            'email' => 'sender-bmv3@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insertOrIgnore([
            'id' => 9002, 'tenant_id' => 2, 'name' => 'Receiver Test',
            'email' => 'receiver-bmv3@test.com', 'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Clear existing copies for a clean count
        DB::table('broker_message_copies')->where('tenant_id', 2)->delete();

        // Create messages to reference
        $messageIds = [];
        for ($i = 0; $i < 4; $i++) {
            $messageIds[] = DB::table('messages')->insertGetId([
                'tenant_id' => 2, 'sender_id' => 9001, 'receiver_id' => 9002,
                'body' => "Count test message {$i}", 'created_at' => now(),
            ]);
        }

        // Insert 3 unreviewed copies
        for ($i = 0; $i < 3; $i++) {
            DB::table('broker_message_copies')->insert([
                'tenant_id' => 2, 'original_message_id' => $messageIds[$i],
                'conversation_key' => md5("count-test-{$i}"),
                'sender_id' => 9001, 'receiver_id' => 9002,
                'message_body' => "Unreviewed message {$i}", 'sent_at' => now(),
                'copy_reason' => 'random_sample',
                'reviewed_at' => null,
                'created_at' => now(),
            ]);
        }

        // Insert 1 reviewed copy
        DB::table('broker_message_copies')->insert([
            'tenant_id' => 2, 'original_message_id' => $messageIds[3],
            'conversation_key' => md5('count-test-reviewed'),
            'sender_id' => 9001, 'receiver_id' => 9002,
            'message_body' => 'Reviewed message', 'sent_at' => now(),
            'copy_reason' => 'random_sample',
            'reviewed_at' => now(), 'reviewed_by' => 9001,
            'created_at' => now(),
        ]);

        $result = $this->service->countUnreviewed();
        $this->assertIsInt($result);
        $this->assertSame(3, $result);
    }

    public function test_isMessagingDisabledForUser_returns_false_when_no_restriction(): void
    {
        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->isMessagingDisabledForUser(1);
        $this->assertFalse($result);
    }

    public function test_getUserRestrictionStatus_returns_defaults_when_no_record(): void
    {
        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getUserRestrictionStatus(1);
        $this->assertFalse($result['messaging_disabled']);
        $this->assertFalse($result['under_monitoring']);
        $this->assertNull($result['restriction_reason']);
    }
}
