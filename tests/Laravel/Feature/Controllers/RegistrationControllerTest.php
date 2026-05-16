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
        RateLimiter::clear('register_success_ip:127.0.0.1');
        RateLimiter::clear('register_success_ip:::1');
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
            'terms_accepted' => true,
            // Backdate so the >= 5s min-form-time bot gate passes.
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    public function test_register_rejects_missing_terms_acceptance(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            // terms_accepted intentionally omitted
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('TERMS_REQUIRED', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('PASSWORD_MISMATCH', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_unverified_location(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => '555',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            // latitude / longitude intentionally omitted — simulates the
            // free-text bypass we've seen in the wild.
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('LOCATION_NOT_VERIFIED', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_disposable_email_domain(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'temp-' . uniqid() . '@mailinator.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('EMAIL_DISPOSABLE', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_disposable_subdomain(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'temp-' . uniqid() . '@foo.mailinator.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('EMAIL_DISPOSABLE', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_email_with_no_mail_servers(): void
    {
        // `.invalid` is reserved by RFC 6761 to NEVER resolve — no real
        // domain will ever exist there, so this assertion is stable across
        // every CI environment that has DNS.
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@nothing-here-' . uniqid() . '.invalid',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('EMAIL_DOMAIN_INVALID', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_when_daily_ip_cap_exceeded(): void
    {
        // Burn the default cap (5) of successful slots for this IP.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('register_success_ip:127.0.0.1', 86400);
            RateLimiter::hit('register_success_ip:::1', 86400);
        }

        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Toronto, Canada',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);

        $this->assertSame(429, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('REGISTRATION_DAILY_LIMIT', $body['errors'][0]['code'] ?? null);
    }

    public function test_register_rejects_when_tenant_breaker_tripped(): void
    {
        // Trip the breaker directly via the cache key the service reads.
        \Illuminate\Support\Facades\Cache::put('register_tenant_breaker:1', true, 3600);

        try {
            $response = $this->apiPost('/v2/auth/register', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'newuser-' . uniqid() . '@example.com',
                'location' => 'Toronto, Canada',
                'phone' => '+15551234567',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
                'terms_accepted' => true,
                'form_started_at' => (int) (microtime(true) * 1000) - 6000,
                'latitude' => 43.6532,
                'longitude' => -79.3832,
            ]);

            $this->assertSame(503, $response->getStatusCode());
            $body = json_decode((string) $response->getContent(), true);
            $this->assertSame('REGISTRATION_TENANT_PAUSED', $body['errors'][0]['code'] ?? null);
        } finally {
            \Illuminate\Support\Facades\Cache::forget('register_tenant_breaker:1');
            \Illuminate\Support\Facades\Cache::forget('register_tenant_breaker:1:ttl');
        }
    }

    public function test_register_rejects_null_island_coordinates(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser-' . uniqid() . '@example.com',
            'location' => 'Anywhere',
            'phone' => '+15551234567',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms_accepted' => true,
            'form_started_at' => (int) (microtime(true) * 1000) - 6000,
            'latitude' => 0,
            'longitude' => 0,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('LOCATION_NOT_VERIFIED', $body['errors'][0]['code'] ?? null);
    }
}
