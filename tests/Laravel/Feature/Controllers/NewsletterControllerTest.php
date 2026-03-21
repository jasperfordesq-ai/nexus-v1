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

    public function test_unsubscribe_requires_auth(): void
    {
        $response = $this->apiPost('/v2/newsletter/unsubscribe', ['email' => 'test@example.com']);

        $response->assertStatus(401);
    }

    public function test_unsubscribe_works(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiPost('/v2/newsletter/unsubscribe', [
            'email' => $user->email,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }
}
