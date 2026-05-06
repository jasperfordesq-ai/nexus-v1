<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Feature tests for RegistrationController — user registration (public, rate-limited).
 */
class RegistrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        RateLimiter::clear('api:registration:ip:127.0.0.1');
        RateLimiter::clear('api:registration:ip:::1');
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/register (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_register_requires_fields(): void
    {
        $response = $this->apiPost('/v2/auth/register', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_requires_email(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_requires_password(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser@example.com',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_requires_phone(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_requires_location(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_rejects_invalid_phone(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'phone' => 'not-a-phone',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_register_happy_path(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }
}
