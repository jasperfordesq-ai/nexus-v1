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
 * Feature tests for VoiceMessageController — voice message upload.
 */
class VoiceMessageControllerTest extends TestCase
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
    //  POST /messages/voice
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/messages/voice', []);

        $response->assertStatus(401);
    }

    public function test_store_requires_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/messages/voice', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_voice_messages_do_not_send_a_second_direct_email(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/VoiceMessageController.php'));

        $this->assertStringContainsString('MessageService::send', $source);
        $this->assertStringNotContainsString('EmailDispatchService::sendRaw', $source);
        $this->assertStringNotContainsString('voice_message.email_subject', $source);
    }
}
