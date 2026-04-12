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
        $this->markTestIncomplete(
            'FederationEmailService uses App\\Core\\Mailer::forCurrentTenant()->send(), not Mail::raw(). '
            . 'Needs a dedicated mailer mock or Mail::fake() integration — TODO rewrite.'
        );
    }

    public function test_sendTransactionNotification_returns_false_on_mail_error(): void
    {
        $this->markTestIncomplete(
            'FederationEmailService uses App\\Core\\Mailer not Mail::raw; rewrite with mailer mock.'
        );
    }

    public function test_sendPartnershipRequestNotification_returns_false_when_no_admins(): void
    {
        DB::shouldReceive('select')->andReturn([]);
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $this->assertFalse(FederationEmailService::sendPartnershipRequestNotification(1, 2, 'Test Community', 1));
    }

    public function test_sendPartnershipRequestNotification_sends_to_admins(): void
    {
        $this->markTestIncomplete(
            'FederationEmailService uses App\\Core\\Mailer not Mail::raw; rewrite with mailer mock.'
        );
    }

    public function test_sendWeeklyDigest_returns_false_when_no_email(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertFalse(FederationEmailService::sendWeeklyDigest(999, 1));
    }
}
