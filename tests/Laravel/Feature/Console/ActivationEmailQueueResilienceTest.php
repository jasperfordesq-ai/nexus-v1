<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\Listeners\SendWelcomeNotification;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * H5 regression lock — activation-email queue resilience.
 *
 * The welcome/activation email is queue-only (SendWelcomeNotification is
 * ShouldQueue, deliberately tries=1). A dead worker would silently lock out
 * every new signup. The safety net is the scheduled `emails:resend-stuck-activations`
 * command, which re-sends ONLY to users who have NO activation email on record —
 * so a healthy system sends nothing, and a worker outage self-heals.
 */
class ActivationEmailQueueResilienceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_registered_queues_the_welcome_notification(): void
    {
        Queue::fake();
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'            => 'pending',
            'email_verified_at' => null,
        ]);
        TenantContext::setById($this->testTenantId);

        event(new UserRegistered($user, $this->testTenantId));

        Queue::assertPushed(
            CallQueuedListener::class,
            fn ($job) => $job->class === SendWelcomeNotification::class
        );
    }

    public function test_resend_stuck_activations_command_is_scheduled(): void
    {
        // schedule:list reflects the real registered schedule from bootstrap/app.php.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('emails:resend-stuck-activations')
            ->assertExitCode(0);
    }

    public function test_resend_targets_users_with_no_activation_email_and_skips_those_who_got_one(): void
    {
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name'       => 'Activation Drill Tenant',
            'slug'       => 'activation-drill-' . uniqid(),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantContext::setById($tenantId);

        // A: already received the welcome/activation email — must be SKIPPED.
        $aEmail = 'got.activation.' . uniqid() . '@example.com';
        DB::table('users')->insert([
            'tenant_id' => $tenantId, 'name' => 'A', 'first_name' => 'A', 'last_name' => 'Got',
            'email' => $aEmail, 'username' => 'a_' . substr(md5(uniqid()), 0, 10),
            'status' => 'pending', 'email_verified_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('email_log')->insert([
            'tenant_id' => $tenantId, 'recipient_email' => $aEmail,
            'category' => 'activation', 'status' => 'sent', 'created_at' => now(),
        ]);

        // B: never received any activation email (dead worker) — must be TARGETED.
        $bEmail = 'no.email.' . uniqid() . '@example.com';
        DB::table('users')->insert([
            'tenant_id' => $tenantId, 'name' => 'B', 'first_name' => 'B', 'last_name' => 'Stuck',
            'email' => $bEmail, 'username' => 'b_' . substr(md5(uniqid()), 0, 10),
            'status' => 'pending', 'email_verified_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('emails:resend-stuck-activations', ['--dry-run' => true, '--tenant' => $tenantId])
            ->expectsOutputToContain($bEmail)
            ->doesntExpectOutputToContain($aEmail)
            ->assertExitCode(0);
    }
}
