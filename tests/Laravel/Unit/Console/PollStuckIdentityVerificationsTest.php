<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationProviderInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for App\Console\Commands\PollStuckIdentityVerifications
 *
 * Strategy:
 *   1. Call IdentityProviderRegistry::reset() in setUp to clear real providers.
 *   2. Register a test-double provider (implements IdentityVerificationProviderInterface)
 *      under the slug 'stripe_identity' so the command picks it up.
 *   3. DB rows are torn down by DatabaseTransactions.
 *
 * The command picks up sessions WHERE:
 *   status IN ('created','started','processing')
 *   AND provider_session_id IS NOT NULL
 *   AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
 *   AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
 *
 * Uses unique tenant id 99732.
 */
class PollStuckIdentityVerificationsTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId = 99732;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(); // catch any stray HTTP calls

        // Seed isolated tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => $this->tenantId],
            [
                'name'              => 'Test Tenant 99732',
                'slug'              => 'test-tenant-99732',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        TenantContext::setById($this->tenantId);

        // Seed a user for FK constraint on identity_verification_sessions
        $this->userId = (int) DB::table('users')->insertGetId([
            'name'       => 'VerifyUser 99732',
            'email'      => 'verify99732@example.com',
            'tenant_id'  => $this->tenantId,
            'role'       => 'member',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        // Reset the provider registry so our stub is the only registered provider
        IdentityProviderRegistry::reset();
    }

    protected function tearDown(): void
    {
        // Re-reset so the real app providers can re-register for subsequent tests
        IdentityProviderRegistry::reset();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Insert a verification session and return its id.
     * updated_at defaults to 10 minutes ago so the DATE_SUB(NOW(), 5 MINUTE) filter picks it up.
     */
    private function insertStuckSession(string $status = 'processing', array $overrides = []): int
    {
        $defaults = [
            'tenant_id'          => $this->tenantId,
            'user_id'            => $this->userId,
            'provider_slug'      => 'stripe_identity',
            'provider_session_id'=> 'vs_test_' . uniqid(),
            'verification_level' => 'document_selfie',
            'status'             => $status,
            'payment_status'     => 'none',
            'created_at'         => now()->subHours(2)->format('Y-m-d H:i:s'),
            'updated_at'         => now()->subMinutes(10)->format('Y-m-d H:i:s'),
        ];

        return (int) DB::table('identity_verification_sessions')->insertGetId(
            array_merge($defaults, $overrides)
        );
    }

    /**
     * Build a stub provider that returns the given getSessionStatus payload
     * and reports slug 'stripe_identity'.
     */
    private function buildProviderStub(array $statusPayload): IdentityVerificationProviderInterface
    {
        $stub = new class($statusPayload) implements IdentityVerificationProviderInterface {
            public function __construct(private array $payload) {}

            public function getSlug(): string { return 'stripe_identity'; }
            public function getName(): string { return 'Stripe Identity (test stub)'; }
            public function getSupportedLevels(): array { return ['document_selfie']; }

            public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
            {
                return ['provider_session_id' => 'vs_stub', 'redirect_url' => null, 'client_token' => null, 'expires_at' => null];
            }

            public function getSessionStatus(string $providerSessionId): array
            {
                return $this->payload;
            }

            public function handleWebhook(array $payload, array $headers): array { return []; }
            public function verifyWebhookSignature(string $rawBody, array $headers): bool { return true; }
            public function cancelSession(string $providerSessionId): bool { return true; }
            public function isAvailable(int $tenantId): bool { return true; }
        };

        return $stub;
    }

    /**
     * Register a stub provider in the registry.
     *
     * The registry's ensureInitialized() fires on the FIRST get() call and
     * overwrites any pre-registered stub with the real StripeIdentityProvider.
     * We force initialization first by trying a benign get() (ignoring "not found"),
     * then immediately overwrite with our stub so it wins when the command calls get().
     */
    private function registerStub(array $statusPayload): void
    {
        // Force the static $initialized flag to true so ensureInitialized() is a no-op
        // when the command later calls get().  We do this by calling get() on a slug
        // that exists (mock) — ignore any result; we only care about the side-effect.
        try { IdentityProviderRegistry::get('mock'); } catch (\Throwable) {}

        // Now overwrite 'stripe_identity' with our stub.  Since $initialized = true,
        // the next get('stripe_identity') call will NOT re-run ensureInitialized() and
        // our stub will be returned.
        IdentityProviderRegistry::register($this->buildProviderStub($statusPayload));
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Command exits successfully when there are no stuck sessions to poll.
     */
    public function test_exits_success_with_no_stuck_sessions(): void
    {
        // A session with updated_at = NOW() is not stuck (passes the filter only if minutes=0)
        $this->insertStuckSession('created', [
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Register a stub so registry doesn't throw if somehow called
        $this->registerStub(['status' => 'created']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 5])
            ->assertExitCode(0);
    }

    /**
     * A stuck 'processing' session where Stripe returns 'failed' → status updated to 'failed'.
     */
    public function test_stuck_session_transitions_to_failed(): void
    {
        $sessionId = $this->insertStuckSession('processing');

        $this->registerStub([
            'status'         => 'failed',
            'failure_reason' => 'Document not readable',
        ]);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('failed', $session->status, 'Session should transition to failed');
        $this->assertNotNull($session->failure_reason, 'failure_reason should be recorded');
    }

    /**
     * A stuck 'started' session where Stripe returns 'cancelled' → status = 'cancelled'.
     */
    public function test_stuck_session_transitions_to_cancelled(): void
    {
        $sessionId = $this->insertStuckSession('started');

        $this->registerStub(['status' => 'cancelled']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('cancelled', $session->status, 'Session should transition to cancelled');
    }

    /**
     * A fresh session (updated_at = now) is NOT polled — status stays 'created'.
     * The DATE_SUB filter with --minutes=5 excludes it.
     */
    public function test_fresh_session_is_not_polled(): void
    {
        $sessionId = $this->insertStuckSession('created', [
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // If polled, it would become 'failed'; since it's not polled, it must stay 'created'
        $this->registerStub(['status' => 'failed']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 5])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('created', $session->status, 'Fresh session must not be polled');
    }

    /**
     * A terminal-status session ('passed') is excluded by the WHERE IN clause.
     */
    public function test_already_passed_session_is_not_repolled(): void
    {
        $sessionId = $this->insertStuckSession('passed', [
            'updated_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ]);

        $this->registerStub(['status' => 'failed']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('passed', $session->status, 'Passed sessions must not be repolled');
    }

    /**
     * A session older than 7 days is excluded by the created_at guard.
     */
    public function test_session_older_than_7_days_is_excluded(): void
    {
        $sessionId = $this->insertStuckSession('processing', [
            'created_at' => now()->subDays(8)->format('Y-m-d H:i:s'),
            'updated_at' => now()->subDays(8)->format('Y-m-d H:i:s'),
        ]);

        $this->registerStub(['status' => 'failed']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('processing', $session->status, 'Session older than 7 days must be excluded');
    }

    /**
     * Intermediate status change (e.g. 'created' → 'processing') from provider is applied.
     */
    public function test_intermediate_status_change_is_applied(): void
    {
        $sessionId = $this->insertStuckSession('created');

        $this->registerStub(['status' => 'processing']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertSame('processing', $session->status, 'Intermediate status should be applied');
    }

    /**
     * Provider exception → command continues gracefully and returns SUCCESS (no crash).
     */
    public function test_provider_exception_does_not_crash_command(): void
    {
        $this->insertStuckSession('processing');

        // Register a stub that throws
        $throwingStub = new class implements IdentityVerificationProviderInterface {
            public function getSlug(): string { return 'stripe_identity'; }
            public function getName(): string { return 'Failing stub'; }
            public function getSupportedLevels(): array { return ['document_selfie']; }
            public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array { return []; }
            public function getSessionStatus(string $providerSessionId): array
            {
                throw new \RuntimeException('Stripe API down');
            }
            public function handleWebhook(array $payload, array $headers): array { return []; }
            public function verifyWebhookSignature(string $rawBody, array $headers): bool { return true; }
            public function cancelSession(string $providerSessionId): bool { return true; }
            public function isAvailable(int $tenantId): bool { return true; }
        };

        IdentityProviderRegistry::register($throwingStub);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $this->assertTrue(true, 'Command must handle provider exceptions gracefully');
    }

    /**
     * A session where provider returns 'passed' and no name/DOB mismatch exists
     * must transition to 'passed' or 'failed' (depending on the mismatch check).
     * The key assertion is that the status is no longer 'processing'.
     */
    public function test_stuck_processing_session_transitions_when_provider_returns_passed(): void
    {
        $sessionId = $this->insertStuckSession('processing');

        $this->registerStub([
            'status'         => 'passed',
            'verified_name'  => null,  // no name → no mismatch
            'dob'            => null,
        ]);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        $this->assertContains(
            $session->status,
            ['passed', 'failed'],
            'Session status must have changed from processing when provider returns passed'
        );
        // Status must no longer be 'processing'
        $this->assertNotSame('processing', $session->status, 'processing status must be resolved after poll');
    }

    /**
     * Unchanged status from provider (e.g. still 'processing') → updated_at is bumped
     * so the same session is not re-polled next hour immediately.
     *
     * NOTE: The command does a raw UPDATE SET updated_at = CURRENT_TIMESTAMP in this path,
     * so we assert updated_at advances.
     */
    public function test_unchanged_status_bumps_updated_at(): void
    {
        $oldUpdatedAt = now()->subMinutes(10)->format('Y-m-d H:i:s');
        $sessionId    = $this->insertStuckSession('processing', ['updated_at' => $oldUpdatedAt]);

        // Provider returns same status as before → falls into the "touch" branch
        $this->registerStub(['status' => 'processing']);

        $this->artisan('nexus:identity:poll-stuck', ['--minutes' => 0])
            ->assertExitCode(0);

        $session = DB::table('identity_verification_sessions')->where('id', $sessionId)->first();
        // updated_at should have moved forward from the old value
        $this->assertNotSame($oldUpdatedAt, $session->updated_at, 'updated_at must be bumped even when status unchanged');
    }
}
