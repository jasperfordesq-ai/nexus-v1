<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;

/**
 * EmailService Tests
 *
 * Tests email sending, settings retrieval, and settings update.
 */
class EmailServiceTest extends TestCase
{
    private function svc(): EmailService
    {
        return new EmailService();
    }

    public function test_send_returns_bool(): void
    {
        Mail::fake();

        $result = $this->svc()->send('test@example.com', 'Test Subject', 'Test body');
        $this->assertIsBool($result);
    }

    public function test_send_with_options(): void
    {
        Mail::fake();

        $result = $this->svc()->send('test@example.com', 'Subject', 'Body', [
            'from'     => 'sender@example.com',
            'reply_to' => 'reply@example.com',
        ]);
        $this->assertTrue($result);
    }

    public function test_get_settings_returns_expected_keys(): void
    {
        $result = $this->svc()->getSettings(2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('reply_to', $result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('footer', $result);
    }

    public function test_get_settings_returns_defaults_for_missing_tenant(): void
    {
        $result = $this->svc()->getSettings(999999);

        $this->assertIsArray($result);
        // from and driver should have defaults from config
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('driver', $result);
    }

    public function test_update_settings_returns_true(): void
    {
        $result = $this->svc()->updateSettings(2, [
            'email_footer' => 'Test footer',
        ]);
        $this->assertTrue($result);
    }

    public function test_update_settings_ignores_disallowed_keys(): void
    {
        // Should succeed but ignore the bad key
        $result = $this->svc()->updateSettings(2, [
            'evil_setting' => 'should be ignored',
        ]);
        $this->assertTrue($result);
    }
}
