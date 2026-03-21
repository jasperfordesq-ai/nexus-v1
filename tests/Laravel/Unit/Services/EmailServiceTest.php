<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailServiceTest extends TestCase
{
    private EmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailService();
    }

    // =========================================================================
    // send()
    // =========================================================================

    public function test_send_returns_true_on_success(): void
    {
        Mail::shouldReceive('raw')->once();

        $result = $this->service->send('test@example.com', 'Hello', 'Body text');
        $this->assertTrue($result);
    }

    public function test_send_returns_false_on_mail_failure(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('SMTP error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->send('test@example.com', 'Hello', 'Body text');
        $this->assertFalse($result);
    }

    // =========================================================================
    // getSettings()
    // =========================================================================

    public function test_getSettings_returns_defaults_when_no_settings_stored(): void
    {
        DB::shouldReceive('table->where->whereIn->pluck->all')->andReturn([]);

        $result = $this->service->getSettings(2);

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('reply_to', $result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('footer', $result);
    }

    public function test_getSettings_returns_stored_values(): void
    {
        DB::shouldReceive('table->where->whereIn->pluck->all')->andReturn([
            'email_from' => 'custom@example.com',
            'email_footer' => 'Custom footer',
        ]);

        $result = $this->service->getSettings(2);

        $this->assertEquals('custom@example.com', $result['from']);
        $this->assertEquals('Custom footer', $result['footer']);
    }

    // =========================================================================
    // updateSettings()
    // =========================================================================

    public function test_updateSettings_upserts_allowed_keys(): void
    {
        DB::shouldReceive('table->updateOrInsert')->twice();

        $result = $this->service->updateSettings(2, [
            'email_from' => 'new@example.com',
            'email_footer' => 'New footer',
        ]);

        $this->assertTrue($result);
    }

    public function test_updateSettings_ignores_disallowed_keys(): void
    {
        // Should not call updateOrInsert for disallowed keys
        DB::shouldReceive('table->updateOrInsert')->never();

        $result = $this->service->updateSettings(2, [
            'evil_key' => 'bad value',
        ]);

        $this->assertTrue($result);
    }
}
