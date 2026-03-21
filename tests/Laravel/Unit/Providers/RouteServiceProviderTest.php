<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Providers;

use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Laravel\TestCase;

class RouteServiceProviderTest extends TestCase
{
    public function test_home_constant_is_root(): void
    {
        $this->assertEquals('/', RouteServiceProvider::HOME);
    }

    public function test_api_rate_limiter_is_registered(): void
    {
        // The RouteServiceProvider boot() registers rate limiters.
        // After app boot, the 'api' limiter should exist.
        $limiter = RateLimiter::limiter('api');
        $this->assertNotNull($limiter, 'The "api" rate limiter should be registered');
    }

    public function test_auth_rate_limiter_is_registered(): void
    {
        $limiter = RateLimiter::limiter('auth');
        $this->assertNotNull($limiter, 'The "auth" rate limiter should be registered');
    }

    public function test_uploads_rate_limiter_is_registered(): void
    {
        $limiter = RateLimiter::limiter('uploads');
        $this->assertNotNull($limiter, 'The "uploads" rate limiter should be registered');
    }

    public function test_api_routes_file_is_loaded(): void
    {
        // Verify that the API routes file exists at the expected location
        $this->assertFileExists(base_path('routes/api.php'));
    }
}
