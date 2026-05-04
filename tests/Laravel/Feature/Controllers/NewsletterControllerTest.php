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
}
