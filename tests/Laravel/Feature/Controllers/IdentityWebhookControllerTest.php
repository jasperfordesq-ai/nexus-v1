<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationProviderInterface;
use App\Services\Identity\IdentityVerificationSessionService;
use App\Services\MemberVerificationBadgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Feature tests for IdentityWebhookController — identity verification provider webhooks (public).
 */
class IdentityWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // The mismatch tests register a throwaway provider into the static
        // registry; reset so it doesn't leak into other tests in the suite.
        IdentityProviderRegistry::reset();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    //  POST /v2/webhooks/identity/{provider_slug} (PUBLIC — no auth)
    // ------------------------------------------------------------------

    public function test_webhook_is_public(): void
    {
        $response = $this->apiPost('/v2/webhooks/identity/onfido', [
            'event' => 'check.completed',
            'resource_id' => 'abc123',
        ]);

        // Should NOT return 401 — webhooks are public
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_webhook_handles_unknown_provider(): void
    {
        $response = $this->apiPost('/v2/webhooks/identity/unknown-provider', [
            'event' => 'test',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 400, 404, 422]);
    }

    // ------------------------------------------------------------------
    //  H4 — a "passed" webhook must NOT grant the badge when the verified
    //  name/DOB does not match the user's profile.
    // ------------------------------------------------------------------

    public function test_verified_webhook_with_name_dob_mismatch_does_not_grant_id_verified_badge(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name'    => 'Alice',
            'last_name'     => 'Smith',
            'date_of_birth' => '1990-05-15',
        ]);
        TenantContext::setById($this->testTenantId);

        $slug = 'test_mismatch_idp';
        IdentityProviderRegistry::register($this->makeFakeProvider($slug, [
            'status'              => 'passed',
            'verified_first_name' => 'Mallory',           // mismatch
            'verified_last_name'  => 'Imposter',          // mismatch
            'verified_dob'        => ['year' => 1971, 'month' => 2, 'day' => 3],
        ]));

        $sessionId = IdentityVerificationSessionService::create(
            $this->testTenantId, (int) $user->id, $slug, 'document_only', ['provider_session_id' => 'sess_mismatch']
        );

        $response = $this->apiPost("/v2/webhooks/identity/{$slug}", [
            'session_id' => 'sess_mismatch',
            'result'     => 'passed',
        ]);

        $response->assertOk();

        // The trust badge must NOT have been granted.
        $badges = app(MemberVerificationBadgeService::class)->getUserBadges((int) $user->id);
        $hasIdBadge = collect($badges)->contains(fn ($b) => ($b['badge_type'] ?? null) === 'id_verified');
        $this->assertFalse($hasIdBadge, 'A name/DOB mismatch must NOT grant the id_verified badge');

        // The session is recorded as failed, consistent with the poll/cron paths.
        $status = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('status');
        $this->assertSame('failed', $status, 'A mismatched pass must be downgraded to failed');
    }

    public function test_verified_webhook_with_matching_name_dob_grants_id_verified_badge(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name'    => 'Alice',
            'last_name'     => 'Smith',
            'date_of_birth' => '1990-05-15',
        ]);
        TenantContext::setById($this->testTenantId);

        $slug = 'test_match_idp';
        IdentityProviderRegistry::register($this->makeFakeProvider($slug, [
            'status'              => 'passed',
            'verified_first_name' => 'Alice',
            'verified_last_name'  => 'Smith',
            'verified_dob'        => ['year' => 1990, 'month' => 5, 'day' => 15],
        ]));

        $sessionId = IdentityVerificationSessionService::create(
            $this->testTenantId, (int) $user->id, $slug, 'document_only', ['provider_session_id' => 'sess_match']
        );

        $response = $this->apiPost("/v2/webhooks/identity/{$slug}", [
            'session_id' => 'sess_match',
            'result'     => 'passed',
        ]);

        $response->assertOk();

        $badges = app(MemberVerificationBadgeService::class)->getUserBadges((int) $user->id);
        $hasIdBadge = collect($badges)->contains(fn ($b) => ($b['badge_type'] ?? null) === 'id_verified');
        $this->assertTrue($hasIdBadge, 'A matching name/DOB must grant the id_verified badge');
    }

    /**
     * Regression (tenant 11 / Time Banking UK, user 264): a UK driving licence
     * lists all given names ("SARAH JANE") in the first-name field while the
     * profile carries only "Sarah". Stripe verified the document; the name gate
     * must tolerate the extra middle name and still grant the badge.
     */
    public function test_verified_webhook_allows_document_with_extra_middle_name(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name'    => 'Sarah',
            'last_name'     => 'Bird',
            'date_of_birth' => '1990-05-15',
        ]);
        TenantContext::setById($this->testTenantId);

        $slug = 'test_middle_name_idp';
        IdentityProviderRegistry::register($this->makeFakeProvider($slug, [
            'status'              => 'passed',
            'verified_first_name' => 'SARAH JANE',
            'verified_last_name'  => 'BIRD',
            'verified_dob'        => ['year' => 1990, 'month' => 5, 'day' => 15],
        ]));

        $sessionId = IdentityVerificationSessionService::create(
            $this->testTenantId, (int) $user->id, $slug, 'document_only', ['provider_session_id' => 'sess_middle']
        );

        $this->apiPost("/v2/webhooks/identity/{$slug}", [
            'session_id' => 'sess_middle',
            'result'     => 'passed',
        ])->assertOk();

        $badges = app(MemberVerificationBadgeService::class)->getUserBadges((int) $user->id);
        $hasIdBadge = collect($badges)->contains(fn ($b) => ($b['badge_type'] ?? null) === 'id_verified');
        $this->assertTrue($hasIdBadge, 'A document with an extra middle name must still grant the badge');

        $status = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('status');
        $this->assertSame('passed', $status, 'An extra middle name is not a mismatch');
    }

    /** A leading honorific on the document ("Mrs Sarah Jane") must be ignored. */
    public function test_verified_webhook_ignores_honorific_prefix_on_document(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name'    => 'Sarah',
            'last_name'     => 'Bird',
            'date_of_birth' => '1990-05-15',
        ]);
        TenantContext::setById($this->testTenantId);

        $slug = 'test_honorific_idp';
        IdentityProviderRegistry::register($this->makeFakeProvider($slug, [
            'status'              => 'passed',
            'verified_first_name' => 'Mrs Sarah Jane',
            'verified_last_name'  => 'Bird',
            'verified_dob'        => ['year' => 1990, 'month' => 5, 'day' => 15],
        ]));

        $sessionId = IdentityVerificationSessionService::create(
            $this->testTenantId, (int) $user->id, $slug, 'document_only', ['provider_session_id' => 'sess_honorific']
        );

        $this->apiPost("/v2/webhooks/identity/{$slug}", [
            'session_id' => 'sess_honorific',
            'result'     => 'passed',
        ])->assertOk();

        $status = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('status');
        $this->assertSame('passed', $status, 'A leading honorific must not count as a mismatch');
    }

    /**
     * Guard against over-loosening: a document whose first name shares no token
     * with the profile (not merely an extra middle name) must still be rejected.
     */
    public function test_verified_webhook_still_fails_on_unrelated_first_name(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name'    => 'Sarah',
            'last_name'     => 'Bird',
            'date_of_birth' => '1990-05-15',
        ]);
        TenantContext::setById($this->testTenantId);

        $slug = 'test_unrelated_first_idp';
        IdentityProviderRegistry::register($this->makeFakeProvider($slug, [
            'status'              => 'passed',
            'verified_first_name' => 'Michael',
            'verified_last_name'  => 'Bird',
            'verified_dob'        => ['year' => 1990, 'month' => 5, 'day' => 15],
        ]));

        $sessionId = IdentityVerificationSessionService::create(
            $this->testTenantId, (int) $user->id, $slug, 'document_only', ['provider_session_id' => 'sess_unrelated']
        );

        $this->apiPost("/v2/webhooks/identity/{$slug}", [
            'session_id' => 'sess_unrelated',
            'result'     => 'passed',
        ])->assertOk();

        $badges = app(MemberVerificationBadgeService::class)->getUserBadges((int) $user->id);
        $hasIdBadge = collect($badges)->contains(fn ($b) => ($b['badge_type'] ?? null) === 'id_verified');
        $this->assertFalse($hasIdBadge, 'A genuinely different first name must NOT grant the badge');

        $status = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('status');
        $this->assertSame('failed', $status, 'An unrelated first name is still a mismatch');
    }
    private function makeFakeProvider(string $slug, array $verifiedOutputs): IdentityVerificationProviderInterface
    {
        return new class($slug, $verifiedOutputs) implements IdentityVerificationProviderInterface {
            public function __construct(private string $slug, private array $verifiedOutputs) {}
            public function getSlug(): string { return $this->slug; }
            public function getName(): string { return 'Test IDP'; }
            public function getSupportedLevels(): array { return ['document_only']; }
            public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
            {
                return ['provider_session_id' => 'sess_' . $this->slug];
            }
            public function getSessionStatus(string $providerSessionId): array { return $this->verifiedOutputs; }
            public function handleWebhook(array $payload, array $headers): array
            {
                return [
                    'provider_session_id' => $payload['session_id'] ?? ('sess_' . $this->slug),
                    'status'              => $payload['result'] ?? 'passed',
                ];
            }
            public function verifyWebhookSignature(string $rawBody, array $headers): bool { return true; }
            public function cancelSession(string $providerSessionId): bool { return true; }
            public function isAvailable(int $tenantId): bool { return true; }
        };
    }
}
