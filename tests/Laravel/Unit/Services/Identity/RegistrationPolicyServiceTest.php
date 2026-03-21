<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\RegistrationPolicyService;
use Illuminate\Support\Facades\DB;

class RegistrationPolicyServiceTest extends TestCase
{
    public function test_modes_constant_has_expected_values(): void
    {
        $this->assertContains('open', RegistrationPolicyService::MODES);
        $this->assertContains('invite_only', RegistrationPolicyService::MODES);
        $this->assertContains('verified_identity', RegistrationPolicyService::MODES);
        $this->assertContains('government_id', RegistrationPolicyService::MODES);
    }

    public function test_verification_levels_constant(): void
    {
        $this->assertContains('none', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_only', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_selfie', RegistrationPolicyService::VERIFICATION_LEVELS);
    }

    public function test_post_verification_actions_constant(): void
    {
        $this->assertContains('activate', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('admin_approval', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
    }

    public function test_fallback_modes_constant(): void
    {
        $this->assertContains('none', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('admin_review', RegistrationPolicyService::FALLBACK_MODES);
    }

    public function test_getPolicy_returns_null_when_not_found(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $this->assertNull(RegistrationPolicyService::getPolicy(2));
    }

    public function test_getPolicy_returns_null_on_error(): void
    {
        DB::shouldReceive('statement')->andThrow(new \Exception('table missing'));

        $this->assertNull(RegistrationPolicyService::getPolicy(2));
    }

    public function test_upsertPolicy_throws_for_invalid_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid registration_mode');

        RegistrationPolicyService::upsertPolicy(2, ['registration_mode' => 'invalid_mode']);
    }

    public function test_upsertPolicy_throws_for_invalid_verification_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid verification_level');

        RegistrationPolicyService::upsertPolicy(2, [
            'registration_mode' => 'open',
            'verification_level' => 'invalid_level',
        ]);
    }

    public function test_upsertPolicy_throws_for_invalid_post_verification(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RegistrationPolicyService::upsertPolicy(2, [
            'registration_mode' => 'open',
            'verification_level' => 'none',
            'post_verification' => 'invalid_action',
        ]);
    }

    public function test_encryptConfig_and_decryptConfig_roundtrip(): void
    {
        // Set up APP_KEY for encryption
        putenv('APP_KEY=' . str_repeat('x', 32));

        $original = ['api_key' => 'sk-test-123', 'webhook_secret' => 'whsec_abc'];
        $encrypted = RegistrationPolicyService::encryptConfig($original);
        $decrypted = RegistrationPolicyService::decryptConfig($encrypted);

        $this->assertEquals($original, $decrypted);

        putenv('APP_KEY=');
    }

    public function test_decryptConfig_returns_empty_for_invalid_data(): void
    {
        putenv('APP_KEY=' . str_repeat('x', 32));

        $result = RegistrationPolicyService::decryptConfig('not-valid-base64!!!');
        $this->assertEmpty($result);

        putenv('APP_KEY=');
    }
}
