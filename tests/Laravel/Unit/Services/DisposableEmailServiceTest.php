<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\DisposableEmailService;
use Tests\Laravel\TestCase;

class DisposableEmailServiceTest extends TestCase
{
    private DisposableEmailService $svc;

    /** A real entry pulled from the curated blocklist so positive assertions track the actual list. */
    private string $knownDisposable = '10minutemail.com';

    protected function setUp(): void
    {
        parent::setUp();
        DisposableEmailService::resetCache();
        $this->svc = new DisposableEmailService();

        $path = base_path('resources/security/disposable-email-domains.txt');
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && ! str_starts_with($line, '#')) {
                    $this->knownDisposable = strtolower($line);
                    break;
                }
            }
        }
    }

    public function test_empty_email_is_not_disposable(): void
    {
        $this->assertFalse($this->svc->isDisposable(''));
    }

    public function test_email_without_at_is_not_disposable(): void
    {
        $this->assertFalse($this->svc->isDisposable('not-an-email'));
    }

    public function test_email_with_trailing_at_is_not_disposable(): void
    {
        $this->assertFalse($this->svc->isDisposable('user@'));
    }

    public function test_legitimate_domain_is_not_disposable(): void
    {
        $this->assertFalse($this->svc->isDisposable('person@gmail.com'));
    }

    public function test_known_disposable_domain_is_flagged(): void
    {
        $this->assertTrue($this->svc->isDisposable('bot@' . $this->knownDisposable));
    }

    public function test_match_is_case_insensitive(): void
    {
        $this->assertTrue($this->svc->isDisposable('BOT@' . strtoupper($this->knownDisposable)));
    }

    public function test_subdomain_of_disposable_provider_is_flagged(): void
    {
        $this->assertTrue($this->svc->isDisposable('bot@inbox.' . $this->knownDisposable));
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $this->assertTrue($this->svc->isDisposable('  bot@' . $this->knownDisposable . '  '));
    }

    public function test_resetCache_reloads_blocklist(): void
    {
        $this->svc->isDisposable('x@' . $this->knownDisposable); // prime the static cache
        DisposableEmailService::resetCache();
        $this->assertTrue($this->svc->isDisposable('y@' . $this->knownDisposable));
    }
}
