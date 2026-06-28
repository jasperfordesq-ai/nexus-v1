<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use App\Core\TenantContext;
use App\Listeners\NotifyAdminOfNewRegistration;
use App\Listeners\SendWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * H5 regression lock — activation-email queue resilience.
 *
 * Registration emails must not be queue-only. A dead worker would silently
 * lock out new signups and hide them from admins, so the registration email
 * listeners run inline while the scheduled `emails:resend-stuck-activations`
 * command remains a recovery path for users who still have no activation log.
 */
class ActivationEmailQueueResilienceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registration_email_listeners_run_inline_not_on_the_queue(): void
    {
        $this->assertFalse(
            in_array(ShouldQueue::class, class_implements(SendWelcomeNotification::class), true),
            'Activation/welcome email must run inline so signup is not dependent on a queue worker.'
        );

        $this->assertFalse(
            in_array(ShouldQueue::class, class_implements(NotifyAdminOfNewRegistration::class), true),
            'Admin new-registration email must run inline so admins are not dependent on a queue worker.'
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
