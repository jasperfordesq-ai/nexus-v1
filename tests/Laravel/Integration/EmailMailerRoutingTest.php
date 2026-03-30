<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\Mailer;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Integration test: verify that EmailService::send() routes through
 * the custom Mailer class (not Laravel's built-in Mail facade).
 */
class EmailMailerRoutingTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // EmailService → Mailer routing
    // =========================================================================

    public function test_send_uses_mailer_forCurrentTenant(): void
    {
        // Create a mock of the Mailer class
        $mockMailer = Mockery::mock(Mailer::class);
        $mockMailer->shouldReceive('send')
            ->once()
            ->with('test@example.com', 'Subject', 'Body')
            ->andReturn(true);

        // Mock the static forCurrentTenant() to return our mock mailer
        Mockery::mock('alias:' . Mailer::class)
            ->shouldReceive('forCurrentTenant')
            ->once()
            ->andReturn($mockMailer);

        $service = new EmailService();
        $result = $service->send('test@example.com', 'Subject', 'Body');

        $this->assertTrue($result);
    }

    public function test_send_returns_false_when_mailer_throws(): void
    {
        // Mock Mailer::forCurrentTenant() to throw an exception
        Mockery::mock('alias:' . Mailer::class)
            ->shouldReceive('forCurrentTenant')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection failed'));

        $service = new EmailService();
        $result = $service->send('test@example.com', 'Subject', 'Body');

        $this->assertFalse($result,
            'send() should return false when Mailer::forCurrentTenant() throws');
    }

    public function test_send_returns_false_when_mailer_send_fails(): void
    {
        $mockMailer = Mockery::mock(Mailer::class);
        $mockMailer->shouldReceive('send')
            ->once()
            ->with('user@example.com', 'Test Subject', 'Test Body')
            ->andReturn(false);

        Mockery::mock('alias:' . Mailer::class)
            ->shouldReceive('forCurrentTenant')
            ->once()
            ->andReturn($mockMailer);

        $service = new EmailService();
        $result = $service->send('user@example.com', 'Test Subject', 'Test Body');

        $this->assertFalse($result,
            'send() should return false when the underlying mailer send() returns false');
    }

    public function test_send_passes_correct_arguments_to_mailer(): void
    {
        $capturedTo = null;
        $capturedSubject = null;
        $capturedBody = null;

        $mockMailer = Mockery::mock(Mailer::class);
        $mockMailer->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $subject, $body) use (&$capturedTo, &$capturedSubject, &$capturedBody) {
                $capturedTo = $to;
                $capturedSubject = $subject;
                $capturedBody = $body;
                return true;
            })
            ->andReturn(true);

        Mockery::mock('alias:' . Mailer::class)
            ->shouldReceive('forCurrentTenant')
            ->once()
            ->andReturn($mockMailer);

        $service = new EmailService();
        $service->send('recipient@example.com', 'Weekly Digest', '<h1>Hello</h1>');

        $this->assertEquals('recipient@example.com', $capturedTo);
        $this->assertEquals('Weekly Digest', $capturedSubject);
        $this->assertEquals('<h1>Hello</h1>', $capturedBody);
    }
}
