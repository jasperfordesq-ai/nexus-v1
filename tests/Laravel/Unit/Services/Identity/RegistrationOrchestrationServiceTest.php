<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use App\Services\Identity\RegistrationOrchestrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * RegistrationOrchestrationService drives its decisions off a real
 * `tenant_registration_policies` row read via DB::selectOne (through
 * RegistrationPolicyService) and persists via DB::statement. The previous
 * version of this test mocked a non-existent DB::fetch()/DB::statement()->self
 * custom layer that the Laravel service never used. We now seed a real policy
 * row for the test tenant and exercise the service against nexus_ci.
 */
class RegistrationOrchestrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seed a registration policy row for the test tenant with the given mode.
     *
     * @param array<string,mixed> $overrides
     */
    private function seedPolicy(string $mode, array $overrides = []): void
    {
        $tenantId = $this->testTenantId;

        DB::table('tenant_registration_policies')->updateOrInsert(
            ['tenant_id' => $tenantId],
            array_merge([
                'registration_mode' => $mode,
                'verification_provider' => null,
                'verification_level' => 'none',
                'post_verification' => 'activate',
                'fallback_mode' => 'none',
                'require_email_verify' => 0,
                'provider_config' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );

        // Factory/observer side effects can re-pin TenantContext to tenant 1.
        TenantContext::setById($tenantId);
    }

    public function test_process_registration_returns_array_with_required_keys_for_open_mode(): void
    {
        $this->seedPolicy('open');

        $result = RegistrationOrchestrationService::processRegistration(1, $this->testTenantId);

        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('requires_verification', $result);
        $this->assertArrayHasKey('requires_approval', $result);
        $this->assertArrayHasKey('next_steps', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_process_registration_open_mode_returns_activated_action(): void
    {
        $this->seedPolicy('open');

        $result = RegistrationOrchestrationService::processRegistration(1, $this->testTenantId);

        $this->assertSame('activated', $result['action']);
        $this->assertFalse($result['requires_verification']);
        $this->assertFalse($result['requires_approval']);
        $this->assertIsArray($result['next_steps']);
        $this->assertIsString($result['message']);
    }

    public function test_process_registration_invite_only_requires_approval(): void
    {
        $this->seedPolicy('invite_only');

        $result = RegistrationOrchestrationService::processRegistration(1, $this->testTenantId);

        $this->assertSame('pending_approval', $result['action']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_process_registration_open_with_approval_requires_approval(): void
    {
        $this->seedPolicy('open_with_approval');

        $result = RegistrationOrchestrationService::processRegistration(1, $this->testTenantId);

        $this->assertSame('pending_approval', $result['action']);
        $this->assertFalse($result['requires_verification']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_process_registration_verified_identity_no_provider_falls_back_to_approval(): void
    {
        // verified_identity with no provider AND fallback_mode 'none'
        // falls through to handleOpenWithApproval.
        $this->seedPolicy('verified_identity', [
            'verification_level' => 'document_selfie',
            'verification_provider' => null,
            'fallback_mode' => 'none',
        ]);

        $result = RegistrationOrchestrationService::processRegistration(1, $this->testTenantId);

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
        // No identity_verification_sessions row with this id → getById() returns
        // null → service throws InvalidArgumentException.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Verification session not found.');

        RegistrationOrchestrationService::adminReview(999999, 1, 'approve');
    }

    public function test_trigger_fallback_admin_review_returns_pending_approval(): void
    {
        $this->seedPolicy('verified_identity', [
            'verification_level' => 'document_selfie',
            'verification_provider' => 'stripe_identity',
            'fallback_mode' => 'admin_review',
        ]);

        $result = RegistrationOrchestrationService::triggerFallback(1, $this->testTenantId, 'provider_unavailable');

        $this->assertSame('pending_approval', $result['action']);
        $this->assertTrue($result['requires_approval']);
        $this->assertFalse($result['requires_verification']);
    }

    public function test_get_registration_status_returns_not_found_for_unknown_user(): void
    {
        $result = RegistrationOrchestrationService::getRegistrationStatus(99999999, $this->testTenantId);

        $this->assertSame('not_found', $result['status']);
    }
}
