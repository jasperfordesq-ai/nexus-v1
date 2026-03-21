<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FederationEmailServiceTest extends TestCase
{
    public function test_sendNewMessageNotification_returns_false_when_recipient_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertFalse(FederationEmailService::sendNewMessageNotification(999, 1, 2, 'Preview'));
    }

    public function test_sendNewMessageNotification_returns_false_when_no_email(): void
    {
        $recipient = (object) ['id' => 1, 'email' => null, 'first_name' => 'Test', 'last_name' => 'User'];
        DB::shouldReceive('selectOne')->andReturn($recipient);

        $this->assertFalse(FederationEmailService::sendNewMessageNotification(1, 2, 3, 'Preview'));
    }

    public function test_sendNewMessageNotification_sends_email(): void
    {
        $recipient = (object) ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'User'];
        $sender = (object) ['id' => 2, 'first_name' => 'Sender', 'last_name' => 'Name'];
        $tenant = (object) ['name' => 'Partner Community'];

        DB::shouldReceive('selectOne')->andReturn($recipient, $sender, $tenant);
        Mail::shouldReceive('raw')->once();
        Log::shouldReceive('info')->once();

        $this->assertTrue(FederationEmailService::sendNewMessageNotification(1, 2, 3, 'Hello!'));
    }

    public function test_sendTransactionNotification_returns_false_on_mail_error(): void
    {
        $recipient = (object) ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'T', 'last_name' => 'U'];
        $sender = (object) ['id' => 2, 'first_name' => 'S', 'last_name' => 'N'];
        $tenant = (object) ['name' => 'Community'];

        DB::shouldReceive('selectOne')->andReturn($recipient, $sender, $tenant);
        Mail::shouldReceive('raw')->andThrow(new \Exception('SMTP error'));
        Log::shouldReceive('error')->once();

        $this->assertFalse(FederationEmailService::sendTransactionNotification(1, 2, 3, 1.5, 'Exchange'));
    }

    public function test_sendPartnershipRequestNotification_returns_false_when_no_admins(): void
    {
        DB::shouldReceive('select')->andReturn([]);
        Log::shouldReceive('warning')->once();

        $this->assertFalse(FederationEmailService::sendPartnershipRequestNotification(1, 2, 'Test Community', 1));
    }

    public function test_sendPartnershipRequestNotification_sends_to_admins(): void
    {
        $admins = [
            (object) ['id' => 1, 'email' => 'admin@example.com', 'first_name' => 'Admin', 'last_name' => 'One'],
        ];
        DB::shouldReceive('select')->andReturn($admins);
        Mail::shouldReceive('raw')->once();
        Log::shouldReceive('info')->once();

        $this->assertTrue(FederationEmailService::sendPartnershipRequestNotification(1, 2, 'Test', 2, 'Please accept'));
    }

    public function test_sendWeeklyDigest_returns_false_when_no_email(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertFalse(FederationEmailService::sendWeeklyDigest(999, 1));
    }
}
