<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Core;

use App\Core\RateLimiter;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class RateLimiterTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_email_lockouts_and_clears_are_tenant_scoped(): void
    {
        $email = 'shared-lockout@example.test';
        $otherTenantId = $this->testTenantId === 1 ? 2 : 1;

        try {
            TenantContext::setById($this->testTenantId);
            for ($attempt = 0; $attempt < 10; $attempt++) {
                RateLimiter::recordAttempt($email, 'email', false);
            }
            self::assertTrue(RateLimiter::check($email, 'email')['limited']);
            self::assertFalse(DB::table('login_attempts')->where('identifier', $email)->exists());
            self::assertTrue(RateLimiter::check(strtoupper($email), 'email')['limited']);

            TenantContext::setById($otherTenantId);
            self::assertFalse(RateLimiter::check($email, 'email')['limited']);
            RateLimiter::clearAttempts($email, 'email');

            TenantContext::setById($this->testTenantId);
            self::assertTrue(RateLimiter::check($email, 'email')['limited']);
            RateLimiter::clearAttempts($email, 'email');
            self::assertFalse(RateLimiter::check($email, 'email')['limited']);
        } finally {
            TenantContext::reset();
        }
    }
}
