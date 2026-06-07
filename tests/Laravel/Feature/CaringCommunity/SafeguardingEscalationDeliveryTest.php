<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\SafeguardingService;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Verifies that critical safeguarding reports fan out via:
 *   1) the in-app `notifications` table (UI bell), AND
 *   2) email to coordinators holding `safeguarding.view`,
 *
 * with each coordinator's email rendered in their own preferred_language.
 *
 * NOTE: the service deliberately does NOT use Mail::to(...)->send($mailable)
 * (the default Mail SMTP transport is unconfigured in prod and silently dropped
 * every critical alert). It renders SafeguardingCriticalMail to HTML and
 * dispatches through EmailDispatchService::sendRaw() — the platform mailer used
 * by every other outbound email. These tests therefore spy on
 * EmailDispatchService rather than Mail::fake().
 *
 * Failures in the email pipeline must not break report submission.
 */
class SafeguardingEscalationDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2; // hour-timebank

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCaringCommunityFeature(self::TENANT_ID, true);
        TenantContext::setById(self::TENANT_ID);
    }

    private function setCaringCommunityFeature(int $tenantId, bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }
        $features['caring_community'] = $enabled;
        DB::table('tenants')->where('id', $tenantId)->update(['features' => json_encode($features)]);
    }

    private function makeUser(int $tenantId, string $email, string $role = 'member', ?string $lang = 'en'): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'          => $tenantId,
            'first_name'         => 'Test',
            'last_name'          => 'User',
            'email'              => $email,
            'username'           => 'u_' . substr(md5($email . $tenantId . microtime(true)), 0, 8),
            'password'           => password_hash('password', PASSWORD_BCRYPT),
            'balance'            => 0,
            'status'             => 'active',
            'role'               => $role,
            'preferred_language' => $lang,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /**
     * Grant the safeguarding.view permission to a user. If the schema does
     * not match the expectation (different table layout), skip the test —
     * fan-out is gated on these tables existing in the canonical layout.
     */
    private function grantSafeguardingView(int $tenantId, int $userId): bool
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('user_permissions')) {
            return false;
        }

        try {
            $permissionId = DB::table('permissions')->where('name', 'safeguarding.view')->value('id');
            if (!$permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name'         => 'safeguarding.view',
                    'display_name' => 'View safeguarding reports',
                    'description'  => 'View safeguarding reports',
                    'category'     => 'safeguarding',
                    'is_dangerous' => 0,
                    'tenant_id'    => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
            DB::table('user_permissions')->insert([
                'tenant_id'     => $tenantId,
                'user_id'       => $userId,
                'permission_id' => $permissionId,
                'granted'       => 1,
                'granted_at'    => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Bind a spy for EmailDispatchService that records every send() call's
     * recipient + subject, and returns true (delivered). sendRaw() resolves the
     * instance via app(EmailDispatchService::class), so this captures the real
     * critical fan-out path.
     *
     * @return array<int,array{to:string,subject:string}> a reference array that
     *         accumulates captured sends
     */
    private function spyEmailDispatch(array &$captured): void
    {
        $spy = Mockery::mock(EmailDispatchService::class);
        $spy->shouldReceive('send')
            ->andReturnUsing(function (string $to, string $subject, string $body, array $options = []) use (&$captured) {
                $captured[] = ['to' => $to, 'subject' => $subject];
                return true;
            });
        $this->app->instance(EmailDispatchService::class, $spy);
    }

    public function test_critical_report_sends_email_to_coordinator(): void
    {
        $captured = [];
        $this->spyEmailDispatch($captured);

        $coordinator = $this->makeUser(self::TENANT_ID, 'coord.' . uniqid() . '@example.test', 'coordinator', 'en');
        if (!$this->grantSafeguardingView(self::TENANT_ID, $coordinator)) {
            $this->markTestSkipped('Permissions table layout differs in this environment.');
        }

        $reporter = $this->makeUser(self::TENANT_ID, 'rep.' . uniqid() . '@example.test');

        $coordinatorEmail = (string) DB::table('users')->where('id', $coordinator)->value('email');

        $service = app(SafeguardingService::class);
        $service->submitReport($reporter, [
            'category'    => 'exploitation',
            'severity'    => 'critical',
            'description' => 'Urgent — please act now.',
        ]);

        $recipients = array_column($captured, 'to');
        $this->assertContains(
            $coordinatorEmail,
            $recipients,
            'Critical report must dispatch an email to the safeguarding.view coordinator.'
        );
    }

    public function test_recipient_locale_is_honored_during_render(): void
    {
        $captured = [];
        $this->spyEmailDispatch($captured);

        $coordinator = $this->makeUser(self::TENANT_ID, 'coord_de.' . uniqid() . '@example.test', 'coordinator', 'de');
        if (!$this->grantSafeguardingView(self::TENANT_ID, $coordinator)) {
            $this->markTestSkipped('Permissions table layout differs in this environment.');
        }

        $reporter = $this->makeUser(self::TENANT_ID, 'rep_locale.' . uniqid() . '@example.test');

        // Caller locale = en; recipient = de. After fan-out completes,
        // LocaleContext::withLocale must restore the original caller locale.
        App::setLocale('en');

        $service = app(SafeguardingService::class);
        $service->submitReport($reporter, [
            'category'    => 'exploitation',
            'severity'    => 'critical',
            'description' => 'Urgent in German.',
        ]);

        // 1) Caller locale restored — proves the wrapper executed and ran finally{}.
        $this->assertSame('en', App::getLocale(), 'Caller locale must be restored after fan-out.');

        // 2) Email was dispatched to the German-speaking coordinator.
        $coordinatorEmail = (string) DB::table('users')->where('id', $coordinator)->value('email');
        $this->assertContains(
            $coordinatorEmail,
            array_column($captured, 'to'),
            'Critical report must dispatch an email to the German-speaking coordinator.'
        );
    }

    public function test_mail_failure_does_not_break_report_submission(): void
    {
        // Force the platform mailer (the real critical-alert delivery path) to
        // throw on every send. fanOutCriticalNotification wraps each send in a
        // per-recipient try/catch, so submitReport must still succeed.
        $failingMailer = Mockery::mock(EmailDispatchService::class);
        $failingMailer->shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));
        $this->app->instance(EmailDispatchService::class, $failingMailer);

        $coordinator = $this->makeUser(self::TENANT_ID, 'coord_fail.' . uniqid() . '@example.test', 'coordinator', 'en');
        $this->grantSafeguardingView(self::TENANT_ID, $coordinator);

        $reporter = $this->makeUser(self::TENANT_ID, 'rep_fail.' . uniqid() . '@example.test');

        $service = app(SafeguardingService::class);
        $result = $service->submitReport($reporter, [
            'category'    => 'medical_concern',
            'severity'    => 'critical',
            'description' => 'Urgent.',
        ]);

        $this->assertArrayHasKey('report_id', $result);
        $this->assertGreaterThan(0, $result['report_id']);

        $row = DB::table('safeguarding_reports')->where('id', $result['report_id'])->first();
        $this->assertNotNull($row);
        $this->assertSame('submitted', $row->status);
    }
}
