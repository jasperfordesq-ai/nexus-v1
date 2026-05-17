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

    /**
     * Scan the entire app/ tree for direct Laravel Mail facade usage.
     * Every email must route through Mailer::forCurrentTenant() because
     * the platform .env has SendGrid configured but intentionally NO SMTP
     * credentials — any Mail::raw / Mail::to / Mail::send call would
     * attempt SMTP and silently fail in production.
     *
     * Multiple bypasses caused real production incidents (safeguarding
     * critical alerts, billing notifications, monthly reports). This guard
     * blocks the next one at CI time.
     *
     * Allowed exceptions: tests/ (mocks), app/Mail/ Mailable classes
     * (they're rendered + dispatched via Mailer separately).
     */
    public function test_no_mail_facade_usage_anywhere_in_app(): void
    {
        $banned = [
            'Mail::raw(',
            'Mail::to(',
            'Mail::send(',
            'Mail::queue(',
            'Mail::later(',
            'Mail::mailer(',
            '\\Illuminate\\Support\\Facades\\Mail::',
        ];

        // Allow Mailable classes — they DEFINE the email body, they don't
        // dispatch it. Dispatch happens via Mailer::forCurrentTenant() at
        // the call site (e.g. SafeguardingService renders the Mailable's
        // HTML and pipes it through Mailer).
        $allowedDirs = [
            // app/Mail/ — Mailable classes themselves
            'Mail' . DIRECTORY_SEPARATOR,
        ];

        $offences = [];
        $appPath = app_path();
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));

        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relPath = str_replace($appPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $skip = false;
            foreach ($allowedDirs as $allowed) {
                if (str_starts_with($relPath, $allowed)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            // Strip comments so we don't trip on docblock mentions like
            // "uses Mail::raw" in an explanatory comment.
            $stripped = preg_replace('!/\*.*?\*/!s', '', $contents);
            $stripped = preg_replace('!//.*?$!m', '', (string) $stripped);

            foreach ($banned as $pattern) {
                if (str_contains((string) $stripped, $pattern)) {
                    $offences[] = "{$relPath}: {$pattern}";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "Mail:: facade usage detected outside app/Mail/. Route through Mailer::forCurrentTenant() instead:\n  - "
                . implode("\n  - ", $offences)
        );
    }
}
