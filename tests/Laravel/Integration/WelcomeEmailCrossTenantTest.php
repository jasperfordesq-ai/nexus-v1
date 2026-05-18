<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\Listeners\SendWelcomeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Regression guard for the cross-tenant welcome email leak that caused the
 * original incident: users registering on any tenant except tenant 2 never
 * received their activation email because TenantContext leaked between
 * Horizon queue jobs and the SerializesModels deserializer poisoned the
 * User::findOrFail lookup.
 *
 * This test:
 *   1. Seeds two users — one on tenant 2 (the "always works" baseline) and
 *      one on a different tenant (the failure case).
 *   2. Pre-populates `TenantContext` with the tenant-2 id to simulate a
 *      Horizon worker that just finished a tenant-2 job.
 *   3. Dispatches UserRegistered for the OTHER tenant's user and runs the
 *      SendWelcomeNotification listener handler.
 *   4. Asserts the listener completed successfully AND wrote an
 *      email_verification_tokens row for the other-tenant user — both of
 *      which would fail under the original bug because the User lookup
 *      filtered by leaked tenant-2 context and returned null.
 *   5. Asserts TenantContext was reset to null after the handler returned
 *      (the finally-reset structural defence).
 */
class WelcomeEmailCrossTenantTest extends TestCase
{
    use DatabaseTransactions;

    public function test_welcome_email_listener_runs_on_a_non_tenant_2_user_even_when_context_is_leaked(): void
    {
        // Pre-leak: simulate a worker that just finished a tenant-2 job.
        TenantContext::setById(2);

        // Seed a user on a DIFFERENT tenant (the failure case).
        $otherTenantId = 999;
        $userId = $this->seedActiveUserOnTenant($otherTenantId);

        // Resolve via TenantContext on the OTHER tenant so the model load
        // matches the way RegistrationService::register actually constructs
        // the User before dispatching UserRegistered.
        TenantContext::setById($otherTenantId);
        $user = User::query()->where('id', $userId)->where('tenant_id', $otherTenantId)->first();
        $this->assertNotNull($user, 'precondition: user must load on the other tenant before dispatch');

        // Re-leak the context to tenant 2 to maximally stress the listener.
        TenantContext::setById(2);

        // Fire the listener directly (skipping the queue) so the test runs
        // synchronously. The handler is the production handler — same
        // try / finally { TenantContext::reset(); } we deployed.
        $event = new UserRegistered($user, $otherTenantId);
        $listener = new SendWelcomeNotification();
        $listener->handle($event);

        // Assertion 1: the verification token was written for the OTHER
        // tenant. If the leak had poisoned User::findOrFail (the original
        // bug), the listener would never have reached generateVerificationToken
        // and this row would not exist.
        $this->assertTrue(
            DB::table('email_verification_tokens')
                ->where('user_id', $userId)
                ->where('tenant_id', $otherTenantId)
                ->exists(),
            'SendWelcomeNotification did not write a verification token for the non-tenant-2 user — the cross-tenant leak is back.'
        );

        // Assertion 2: TenantContext is reset to null after handle() — proves
        // the finally-reset is in place. Without it the next job picked up
        // by the same worker would inherit this listener's context.
        $this->assertNull(
            TenantContext::currentId(),
            'SendWelcomeNotification did not reset TenantContext in finally — risk of cross-tenant leak into next job.'
        );

        // Assertion 3: email_log row was written (we passed through Mailer).
        // The audit trail is what makes operational triage possible.
        if (Schema::hasTable('email_log')) {
            $this->assertTrue(
                DB::table('email_log')
                    ->where('tenant_id', $otherTenantId)
                    ->where('user_id', $userId)
                    ->exists(),
                'email_log row not written — observability for the welcome email is broken.'
            );
        }
    }

    private function seedActiveUserOnTenant(int $tenantId): int
    {
        $email = 'welcome-test-' . uniqid() . '@example.test';
        return (int) DB::table('users')->insertGetId([
            'tenant_id'          => $tenantId,
            'email'              => $email,
            'first_name'         => 'WelcomeTest',
            'name'               => 'WelcomeTest User',
            'password_hash'      => password_hash('x', PASSWORD_DEFAULT),
            'password'           => 'unused',
            'status'             => 'pending',
            'role'               => 'member',
            'is_approved'        => 0,
            'email_verified_at'  => null,
            'preferred_language' => 'en',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }
}
