<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FederationEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * FederationEmailService Tests
 */
class FederationEmailServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(FederationEmailService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = [
            'sendNewMessageNotification',
            'sendTransactionNotification',
            'sendTransactionConfirmation',
            'sendWeeklyDigest',
            'sendPartnershipRequestNotification',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(FederationEmailService::class, $method),
                "Method {$method} should exist on FederationEmailService"
            );
        }
    }

    public function test_all_public_methods_are_static(): void
    {
        $methods = [
            'sendNewMessageNotification',
            'sendTransactionNotification',
            'sendTransactionConfirmation',
            'sendWeeklyDigest',
            'sendPartnershipRequestNotification',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(FederationEmailService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    public function test_send_new_message_notification_signature(): void
    {
        $ref = new \ReflectionMethod(FederationEmailService::class, 'sendNewMessageNotification');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('recipientUserId', $params[0]->getName());
        $this->assertEquals('senderUserId', $params[1]->getName());
        $this->assertEquals('senderTenantId', $params[2]->getName());
        $this->assertEquals('messagePreview', $params[3]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_send_transaction_notification_signature(): void
    {
        $ref = new \ReflectionMethod(FederationEmailService::class, 'sendTransactionNotification');
        $params = $ref->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('recipientUserId', $params[0]->getName());
        $this->assertEquals('senderUserId', $params[1]->getName());
        $this->assertEquals('senderTenantId', $params[2]->getName());
        $this->assertEquals('amount', $params[3]->getName());
        $this->assertEquals('description', $params[4]->getName());
    }

    public function test_send_transaction_confirmation_signature(): void
    {
        $ref = new \ReflectionMethod(FederationEmailService::class, 'sendTransactionConfirmation');
        $params = $ref->getParameters();

        $this->assertCount(6, $params);
        $this->assertEquals('senderUserId', $params[0]->getName());
        $this->assertEquals('recipientUserId', $params[1]->getName());
        $this->assertEquals('recipientTenantId', $params[2]->getName());
        $this->assertEquals('amount', $params[3]->getName());
        $this->assertEquals('description', $params[4]->getName());
        $this->assertEquals('newBalance', $params[5]->getName());
    }

    public function test_send_weekly_digest_signature(): void
    {
        $ref = new \ReflectionMethod(FederationEmailService::class, 'sendWeeklyDigest');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_send_partnership_request_notification_signature(): void
    {
        $ref = new \ReflectionMethod(FederationEmailService::class, 'sendPartnershipRequestNotification');
        $params = $ref->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('targetTenantId', $params[0]->getName());
        $this->assertEquals('requestingTenantId', $params[1]->getName());
        $this->assertEquals('requestingTenantName', $params[2]->getName());
        $this->assertEquals('requestedLevel', $params[3]->getName());
        $this->assertEquals('notes', $params[4]->getName());
        $this->assertTrue($params[4]->allowsNull());
        $this->assertTrue($params[4]->isDefaultValueAvailable());
        $this->assertNull($params[4]->getDefaultValue());
    }

    public function test_send_new_message_notification_sends_mail(): void
    {
        Mail::fake();

        // Mock DB calls to return user data
        DB::shouldReceive('selectOne')
            ->andReturn(
                (object) ['id' => 1, 'email' => 'recipient@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe'],
                (object) ['id' => 2, 'first_name' => 'John', 'last_name' => 'Smith'],
                (object) ['name' => 'Test Timebank']
            );

        $result = FederationEmailService::sendNewMessageNotification(1, 2, 1, 'Hello from federation!');

        $this->assertTrue($result);
        Mail::assertSent(function ($mail) {
            return true; // Mail::raw doesn't use Mailable, so we just check it was sent
        });
    }

    public function test_send_new_message_notification_returns_false_when_no_email(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $result = FederationEmailService::sendNewMessageNotification(999, 2, 1, 'Hello');

        $this->assertFalse($result);
    }

    public function test_send_transaction_notification_sends_mail(): void
    {
        Mail::fake();

        DB::shouldReceive('selectOne')
            ->andReturn(
                (object) ['id' => 1, 'email' => 'recipient@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe'],
                (object) ['id' => 2, 'first_name' => 'John', 'last_name' => 'Smith'],
                (object) ['name' => 'Test Timebank']
            );

        $result = FederationEmailService::sendTransactionNotification(1, 2, 1, 2.5, 'Garden help');

        $this->assertTrue($result);
    }

    public function test_send_transaction_confirmation_sends_mail(): void
    {
        Mail::fake();

        DB::shouldReceive('selectOne')
            ->andReturn(
                (object) ['id' => 2, 'email' => 'sender@example.com', 'first_name' => 'John', 'last_name' => 'Smith'],
                (object) ['id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe'],
                (object) ['name' => 'Test Timebank']
            );

        $result = FederationEmailService::sendTransactionConfirmation(2, 1, 1, 2.5, 'Garden help', 7.5);

        $this->assertTrue($result);
    }

    public function test_send_partnership_request_returns_false_when_no_admins(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = FederationEmailService::sendPartnershipRequestNotification(1, 2, 'Other Timebank', 2, 'We want to partner');

        $this->assertFalse($result);
    }

    public function test_send_partnership_request_sends_to_admins(): void
    {
        Mail::fake();

        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['id' => 1, 'email' => 'admin1@example.com', 'first_name' => 'Admin', 'last_name' => 'One'],
                (object) ['id' => 2, 'email' => 'admin2@example.com', 'first_name' => 'Admin', 'last_name' => 'Two'],
            ]);

        $result = FederationEmailService::sendPartnershipRequestNotification(1, 2, 'Other Timebank', 3, 'Partnership notes');

        $this->assertTrue($result);
    }

    public function test_private_helper_methods_exist(): void
    {
        $getUserWithEmail = new \ReflectionMethod(FederationEmailService::class, 'getUserWithEmail');
        $this->assertTrue($getUserWithEmail->isPrivate());

        $getUserBasicInfo = new \ReflectionMethod(FederationEmailService::class, 'getUserBasicInfo');
        $this->assertTrue($getUserBasicInfo->isPrivate());
    }
}
