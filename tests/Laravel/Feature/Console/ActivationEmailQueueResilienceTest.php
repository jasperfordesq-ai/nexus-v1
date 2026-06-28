<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Console;

use App\Core\TenantContext;
use App\Listeners\NotifyAdminOfNewRegistration;
use App\Listeners\SendWelcomeNotification;
use App\Models\User;
use App\Services\DisposableEmailService;
use App\Services\EmailDispatchService;
use App\Services\MxRecordValidator;
use App\Services\PwnedPasswordService;
use App\Services\RegistrationService;
use App\Services\TenantSettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

    public function test_successful_registration_sends_activation_and_admin_emails_without_queue_worker(): void
    {
        Queue::fake();

        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name'       => 'Inline Registration Tenant',
            'slug'       => 'inline-registration-' . uniqid(),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantContext::setById($tenantId);

        User::factory()->forTenant($tenantId)->create([
            'role'               => 'admin',
            'status'             => 'active',
            'email'              => 'admin-inline-' . uniqid() . '@project-nexus.testmail',
            'preferred_language' => 'en',
        ]);

        $mailer = new RegistrationInlineEmailDispatchService();
        app()->instance(EmailDispatchService::class, $mailer);

        $service = new RegistrationService(
            new User(),
            app(TenantSettingsService::class),
            new RegistrationInlinePwnedPasswordService(),
            new RegistrationInlineDisposableEmailService(),
            new RegistrationInlineMxRecordValidator(),
        );

        $result = $service->register([
            'first_name'            => 'Inline',
            'last_name'             => 'Signup',
            'email'                 => 'inline-signup-' . uniqid() . '@project-nexus.testmail',
            'location'              => 'Toronto, Canada',
            'phone'                 => '+15551234567',
            'password'              => 'A uniquely long registration passphrase 2026',
            'password_confirmation' => 'A uniquely long registration passphrase 2026',
            'terms_accepted'        => true,
        ], $tenantId);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertTrue($result['requires_verification'] ?? false);

        $userId = (int) ($result['user']['id'] ?? 0);
        $this->assertGreaterThan(0, $userId);
        $this->assertTrue(DB::table('email_verification_tokens')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists());

        $categories = array_column($mailer->calls, 'category');
        $this->assertContains('activation', $categories);
        $this->assertContains('admin_new_registration', $categories);

        Queue::assertNotPushed(
            CallQueuedListener::class,
            fn ($job) => in_array($job->class, [
                SendWelcomeNotification::class,
                NotifyAdminOfNewRegistration::class,
            ], true)
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

class RegistrationInlineEmailDispatchService extends EmailDispatchService
{
    /** @var list<array{to:string, subject:string, category:string|null, tenant_id:int|null}> */
    public array $calls = [];

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $this->calls[] = [
            'to'        => $to,
            'subject'   => $subject,
            'category'  => $options['category'] ?? null,
            'tenant_id' => isset($options['tenant_id']) ? (int) $options['tenant_id'] : null,
        ];

        return true;
    }
}

class RegistrationInlinePwnedPasswordService extends PwnedPasswordService
{
    public function isPwned(string $password): bool
    {
        return false;
    }
}

class RegistrationInlineDisposableEmailService extends DisposableEmailService
{
    public function isDisposable(string $email): bool
    {
        return false;
    }
}

class RegistrationInlineMxRecordValidator extends MxRecordValidator
{
    public function isResolvable(string $email): bool
    {
        return true;
    }
}
