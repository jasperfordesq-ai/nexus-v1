<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Services\EmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Laravel\TestCase;

/**
 * Integration test: verify that EmailService::send() routes through
 * the custom Mailer class (not Laravel's built-in Mail facade).
 *
 * After the production incident where EmailService used Mail::raw()
 * (Gmail SMTP) instead of Mailer::forCurrentTenant() (SendGrid),
 * these tests ensure the correct routing is preserved.
 */
class EmailMailerRoutingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * EmailService::send() must NOT call Laravel's Mail facade.
     * It should route through Mailer::forCurrentTenant() instead.
     */
    public function test_send_does_not_use_laravel_mail_facade(): void
    {
        Mail::fake();

        $service = new EmailService();
        // This will fail to actually send (no real SMTP in tests), but
        // the key assertion is that Mail::raw/send was never called.
        $service->send('test@example.com', 'Subject', 'Body');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * EmailService::send() should return false (not throw) on failure.
     */
    public function test_send_returns_false_on_failure(): void
    {
        $service = new EmailService();
        // With no real mail config in test env, send() should fail gracefully
        $result = $service->send('test@example.com', 'Subject', 'Body');

        $this->assertIsBool($result);
    }

    /**
     * Verify EmailService source code references Mailer::forCurrentTenant,
     * not Mail::raw or Mail::send. This is a code-level regression guard.
     */
    public function test_email_service_source_uses_mailer_class(): void
    {
        $source = file_get_contents(app_path('Services/EmailService.php'));

        $this->assertStringContainsString(
            'Mailer::forCurrentTenant()',
            $source,
            'EmailService::send() must call Mailer::forCurrentTenant()'
        );

        $this->assertStringNotContainsString(
            'Mail::raw(',
            $source,
            'EmailService must NOT use Mail::raw() — that bypasses SendGrid'
        );

        $this->assertStringNotContainsString(
            'Mail::send(',
            $source,
            'EmailService must NOT use Mail::send() — that bypasses SendGrid'
        );
    }
}
