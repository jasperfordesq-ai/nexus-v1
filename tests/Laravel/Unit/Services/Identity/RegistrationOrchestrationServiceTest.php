<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\RegistrationOrchestrationService;
use Illuminate\Support\Facades\DB;

class RegistrationOrchestrationServiceTest extends TestCase
{
    /**
     * Set up a DB mock that returns an 'open' policy, which causes
     * processRegistration to activate the user immediately. This is
     * the simplest path through the orchestration logic and requires
     * no external provider calls.
     */
    private function mockOpenPolicyDb(): void
    {
        // getEffectivePolicy calls DB twice: getPolicy row + fallback construction
        // We mock statement to chain-return self for fetch calls
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'registration_mode' => 'open',
            'verification_level' => 'none',
            'verification_provider' => null,
            'post_verification' => 'activate',
            'fallback_mode' => 'none',
            'invite_code_required' => 0,
            'email_verification_required' => 0,
            'waitlist_enabled' => 0,
            'provider_config' => null,
        ]);

        // The 'open' path calls UPDATE users SET is_approved = 1
        // and IdentityVerificationEventService::log — both use DB::statement
        // We allow any number of statement calls
    }

    public function test_process_registration_returns_array_with_required_keys_for_open_mode(): void
    {
        $this->mockOpenPolicyDb();

        $result = RegistrationOrchestrationService::processRegistration(1, 2);

        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('requires_verification', $result);
        $this->assertArrayHasKey('requires_approval', $result);
        $this->assertArrayHasKey('next_steps', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_process_registration_open_mode_returns_activated_action(): void
    {
        $this->mockOpenPolicyDb();

        $result = RegistrationOrchestrationService::processRegistration(1, 2);

        $this->assertSame('activated', $result['action']);
        $this->assertFalse($result['requires_verification']);
        $this->assertFalse($result['requires_approval']);
        $this->assertIsArray($result['next_steps']);
        $this->assertIsString($result['message']);
    }

    public function test_process_registration_invite_only_requires_approval(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'registration_mode' => 'invite_only',
            'verification_level' => 'none',
            'verification_provider' => null,
            'post_verification' => 'activate',
            'fallback_mode' => 'none',
            'invite_code_required' => 1,
            'email_verification_required' => 0,
            'waitlist_enabled' => 0,
            'provider_config' => null,
        ]);

        $result = RegistrationOrchestrationService::processRegistration(1, 2);

        $this->assertSame('pending_approval', $result['action']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_process_registration_open_with_approval_requires_approval(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'registration_mode' => 'open_with_approval',
            'verification_level' => 'none',
            'verification_provider' => null,
            'post_verification' => 'activate',
            'fallback_mode' => 'none',
            'invite_code_required' => 0,
            'email_verification_required' => 0,
            'waitlist_enabled' => 0,
            'provider_config' => null,
        ]);

        $result = RegistrationOrchestrationService::processRegistration(1, 2);

        $this->assertSame('pending_approval', $result['action']);
        $this->assertFalse($result['requires_verification']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_process_registration_verified_identity_no_provider_falls_back_to_approval(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'registration_mode' => 'verified_identity',
            'verification_level' => 'document_selfie',
            'verification_provider' => null, // No provider configured
            'post_verification' => 'activate',
            'fallback_mode' => 'none',
            'invite_code_required' => 0,
            'email_verification_required' => 0,
            'waitlist_enabled' => 0,
            'provider_config' => null,
        ]);

        $result = RegistrationOrchestrationService::processRegistration(1, 2);

        // With no provider and no fallback, it falls through to handleOpenWithApproval
        $this->assertSame('pending_approval', $result['action']);
    }

    public function test_admin_review_throws_for_invalid_decision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision must be "approve" or "reject".');

        RegistrationOrchestrationService::adminReview(1, 99, 'maybe');
    }

    public function test_admin_review_throws_when_session_not_found(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Verification session not found.');

        RegistrationOrchestrationService::adminReview(999, 1, 'approve');
    }

    public function test_trigger_fallback_admin_review_returns_pending_approval(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn([
            'id' => 1,
            'tenant_id' => 2,
            'registration_mode' => 'verified_identity',
            'verification_level' => 'document_selfie',
            'verification_provider' => 'stripe_identity',
            'post_verification' => 'activate',
            'fallback_mode' => 'admin_review',
            'invite_code_required' => 0,
            'email_verification_required' => 0,
            'waitlist_enabled' => 0,
            'provider_config' => null,
        ]);

        $result = RegistrationOrchestrationService::triggerFallback(1, 2, 'provider_unavailable');

        $this->assertSame('pending_approval', $result['action']);
        $this->assertTrue($result['requires_approval']);
        $this->assertFalse($result['requires_verification']);
    }

    public function test_get_registration_status_returns_not_found_for_unknown_user(): void
    {
        DB::shouldReceive('statement')->andReturnSelf();
        DB::shouldReceive('fetch')->andReturn(false);

        $result = RegistrationOrchestrationService::getRegistrationStatus(99999, 2);

        $this->assertSame('not_found', $result['status']);
    }
}
