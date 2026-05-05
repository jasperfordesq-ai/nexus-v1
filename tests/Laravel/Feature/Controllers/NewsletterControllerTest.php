<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Feature tests for NewsletterController — newsletter unsubscribe.
 */
class NewsletterControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  POST /v2/newsletter/unsubscribe
    // ------------------------------------------------------------------

    public function test_unsubscribe_works(): void
    {
        $user = $this->authenticatedUser();
        $token = Str::random(64);

        DB::table('newsletter_subscribers')->insert([
            'tenant_id' => $this->testTenantId,
            'email' => $user->email,
            'user_id' => $user->id,
            'status' => 'active',
            'confirmation_token' => Str::random(64),
            'unsubscribe_token' => $token,
            'confirmed_at' => now(),
            'source' => 'signup',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost('/v2/newsletter/unsubscribe', [
            'token' => $token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'tenant_id' => $this->testTenantId,
            'email' => $user->email,
            'status' => 'unsubscribed',
            'is_active' => 0,
        ]);
    }

    public function test_unsubscribe_requires_token(): void
    {
        $response = $this->apiPost('/v2/newsletter/unsubscribe', []);

        $response->assertStatus(400);
    }

    public function test_unsubscribe_rejects_invalid_token(): void
    {
        $response = $this->apiPost('/v2/newsletter/unsubscribe', [
            'token' => Str::random(64),
        ]);

        $response->assertStatus(404);
    }

    public function test_click_tracking_does_not_redirect_unknown_signed_tokens(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $token = 'unknown-token';
        $url = 'https://evil.example.test/phishing';
        $signature = hash_hmac('sha256', $token . '|' . $url, (string) config('app.key'));

        $response = $this->get(
            '/api/v2/newsletter/click/' . $token . '?url=' . rawurlencode($url) . '&sig=' . rawurlencode($signature),
            $this->withTenantHeader()
        );

        $response->assertRedirect('https://app.example.test');
    }

    public function test_click_tracking_requires_signature(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $response = $this->get(
            '/api/v2/newsletter/click/missing-signature?url=' . rawurlencode('https://evil.example.test/phishing'),
            $this->withTenantHeader()
        );

        $response->assertRedirect('https://app.example.test');
    }

    public function test_legacy_unprefixed_tracking_pixel_route_works_for_sent_email_links(): void
    {
        $response = $this->get('/v2/newsletter/pixel/legacy-token', $this->withTenantHeader());

        $response->assertOk();
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
    }

    public function test_legacy_unprefixed_click_route_redirects_for_sent_email_links(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $response = $this->get(
            '/v2/newsletter/click/legacy-token?url=' . rawurlencode('https://hour-timebank.ie/'),
            $this->withTenantHeader()
        );

        $response->assertRedirect('https://app.example.test');
    }
}
