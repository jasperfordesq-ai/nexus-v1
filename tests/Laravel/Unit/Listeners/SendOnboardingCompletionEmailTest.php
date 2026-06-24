<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\OnboardingCompleted;
use App\Listeners\SendOnboardingCompletionEmail;
use App\Services\EmailDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for SendOnboardingCompletionEmail listener.
 *
 * Uses tenant 2 (the default hour-timebank test tenant) — a real tenant row
 * is required because the listener calls TenantContext::setById() which looks up
 * the tenants table.  All rows are rolled back via DatabaseTransactions.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SendOnboardingCompletionEmailTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /** @var \Mockery\MockInterface */
    private $emailAlias;

    protected function setUp(): void
    {
        // Alias mock MUST be created before parent::setUp() — the class may already
        // be autoloaded. shouldIgnoreMissing() silences unexpected static calls.
        $this->emailAlias = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();

        parent::setUp();

        // Flush cache so idempotency keys from earlier tests cannot short-circuit handle().
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(SendOnboardingCompletionEmail::class), true),
            'SendOnboardingCompletionEmail must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new SendOnboardingCompletionEmail();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — email is dispatched to the completing user
    // -------------------------------------------------------------------------

    public function test_handle_sends_completion_email_to_user(): void
    {
        $user  = $this->seedUser();
        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->with(
                $user->email,
                Mockery::type('string'),
                Mockery::type('string'),
                null, null, null,
                'onboarding_completed',
                Mockery::type('array')
            )
            ->andReturn(true);

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Done cache key is written after a successful send
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_send(): void
    {
        $user       = $this->seedUser();
        $event      = new OnboardingCompleted($user->id, $this->testTenantId);
        $handledKey = 'send_onboarding_completion:done:' . $this->testTenantId . ':' . $user->id;

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must be set after a successful send');
    }

    // -------------------------------------------------------------------------
    // Done cache key is NOT written when send returns false
    // -------------------------------------------------------------------------

    public function test_handle_does_not_write_done_key_when_send_fails(): void
    {
        $user       = $this->seedUser();
        $event      = new OnboardingCompleted($user->id, $this->testTenantId);
        $handledKey = 'send_onboarding_completion:done:' . $this->testTenantId . ':' . $user->id;

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertFalse(Cache::has($handledKey), 'Done cache key must NOT be set when email send failed');
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery suppressed via done key
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery_via_done_key(): void
    {
        $user       = $this->seedUser();
        $handledKey = 'send_onboarding_completion:done:' . $this->testTenantId . ':' . $user->id;

        Cache::put($handledKey, 1, now()->addHour());

        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('SendOnboardingCompletionEmail: duplicate delivery suppressed', Mockery::type('array'));

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(Cache::has($handledKey));
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery suppressed via claim key
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery_via_claim_key(): void
    {
        $user     = $this->seedUser();
        $claimKey = 'send_onboarding_completion:claim:' . $this->testTenantId . ':' . $user->id;

        // Simulate another worker holding the claim
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('SendOnboardingCompletionEmail: concurrent delivery suppressed', Mockery::type('array'));

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Claim key is released after successful send
    // -------------------------------------------------------------------------

    public function test_handle_releases_claim_key_after_successful_send(): void
    {
        $user     = $this->seedUser();
        $claimKey = 'send_onboarding_completion:claim:' . $this->testTenantId . ':' . $user->id;

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertFalse(Cache::has($claimKey), 'Claim key must be released from cache after successful handle');
    }

    // -------------------------------------------------------------------------
    // Claim key is released even when email layer throws
    // -------------------------------------------------------------------------

    public function test_handle_releases_claim_key_after_exception(): void
    {
        $user     = $this->seedUser();
        $claimKey = 'send_onboarding_completion:claim:' . $this->testTenantId . ':' . $user->id;

        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->andThrow(new \RuntimeException('SMTP timeout'));

        Log::shouldReceive('error')->atLeast()->once();

        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertFalse(Cache::has($claimKey), 'Claim key must be released in finally block after exception');
    }

    // -------------------------------------------------------------------------
    // Unknown tenant — listener skips silently
    // -------------------------------------------------------------------------

    public function test_handle_skips_when_tenant_not_found(): void
    {
        $user  = $this->seedUser();
        // tenant_id 88888 does not exist in tenants table.
        // TenantContext::setById() will log its own warning then return false;
        // the listener then logs 'SendOnboardingCompletionEmail: tenant not found, skipping'.
        // We accept one or more warnings (both messages match type 'string') and
        // assert that sendRaw is never called — the key assertion for this guard.
        $event = new OnboardingCompleted($user->id, 88888);

        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('warning')->atLeast()->once()->withAnyArgs();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Unknown user — listener exits without sending
    // -------------------------------------------------------------------------

    public function test_handle_skips_when_user_not_found(): void
    {
        $event = new OnboardingCompleted(99999999, $this->testTenantId);

        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // email_log dedup guard — skips when a prior sent row exists
    // -------------------------------------------------------------------------

    public function test_handle_skips_when_email_log_shows_already_sent(): void
    {
        $user = $this->seedUser();

        // Insert a pre-existing email_log row for onboarding_completed (same tenant+user)
        DB::table('email_log')->insert([
            'tenant_id'       => $this->testTenantId,
            'user_id'         => $user->id,
            'recipient_email' => $user->email,
            'category'        => 'onboarding_completed',
            'status'          => 'sent',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('SendOnboardingCompletionEmail: already sent, skipping duplicate event', Mockery::type('array'));
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Locale wrapping — email send is called with user's preferred locale in context
    // -------------------------------------------------------------------------

    public function test_handle_sends_email_for_non_english_locale_user(): void
    {
        // Seed a user with a non-English preferred language.
        // The listener wraps the render in LocaleContext::withLocale($user, ...)
        // which swaps App locale to the user's language before calling __().
        $user  = $this->seedUser(['preferred_language' => 'fr']);
        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        // sendRaw must still be called once regardless of locale
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->andReturn(true);

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Warning is logged when email send returns false
    // -------------------------------------------------------------------------

    public function test_handle_logs_warning_when_send_returns_false(): void
    {
        $user  = $this->seedUser();
        $event = new OnboardingCompleted($user->id, $this->testTenantId);

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        Log::shouldReceive('warning')
            ->once()
            ->with('SendOnboardingCompletionEmail: email returned false', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new SendOnboardingCompletionEmail())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedUser(array $overrides = []): object
    {
        $unique = uniqid('obc_', true);

        $data = array_merge([
            'tenant_id'          => $this->testTenantId,
            'name'               => 'Onboard User ' . $unique,
            'first_name'         => 'Onboard',
            'last_name'          => 'User',
            'email'              => $unique . '@example.com',
            'role'               => 'member',
            'status'             => 'active',
            'preferred_language' => 'en',
            'is_approved'        => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $overrides);

        $id = DB::table('users')->insertGetId($data);

        return (object) array_merge($data, ['id' => $id]);
    }
}
