<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\EventNotificationService;
use App\Services\Identity\RegistrationOrchestrationService;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class NotificationQueueTenantIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_queue_notification_resolves_tenant_from_recipient_when_context_is_missing(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        TenantContext::reset();

        try {
            $method = new \ReflectionMethod(NotificationDispatcher::class, 'queueNotification');
            $method->setAccessible(true);
            $method->invoke(null, $user->id, 'new_message', 'Message received', '/messages', 'instant', '<p>Message received</p>');

            $row = DB::table('notification_queue')
                ->where('user_id', $user->id)
                ->where('activity_type', 'new_message')
                ->orderByDesc('id')
                ->first();

            $this->assertNotNull($row);
            $this->assertSame($this->testTenantId, (int) $row->tenant_id);
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_notification_model_resolves_tenant_from_recipient_when_context_is_missing(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        TenantContext::reset();

        try {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => 'system',
                'message' => 'Tenant fallback check',
                'link' => '/notifications',
                'is_read' => 0,
                'created_at' => now(),
            ]);

            $rowTenantId = DB::table('notifications')
                ->where('id', $notification->id)
                ->value('tenant_id');

            $this->assertSame($this->testTenantId, (int) $rowTenantId);

            $id = Notification::createNotification($user->id, 'Tenant fallback check two', '/notifications', 'system');
            $helperTenantId = DB::table('notifications')
                ->where('id', $id)
                ->value('tenant_id');

            $this->assertSame($this->testTenantId, (int) $helperTenantId);
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_email_dispatcher_infers_tenant_from_unique_recipient_when_context_is_missing(): void
    {
        $email = 'tenant-infer-' . uniqid('', true) . '@example.test';
        User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => $email,
        ]);

        TenantContext::reset();

        try {
            $method = new \ReflectionMethod(EmailDispatchService::class, 'resolveTenantId');
            $method->setAccessible(true);

            $tenantId = $method->invoke(app(EmailDispatchService::class), [], $email);

            $this->assertSame($this->testTenantId, $tenantId);
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_email_dispatcher_prefers_unique_recipient_tenant_over_leaked_context(): void
    {
        $otherTenantId = 999;
        $email = 'tenant-mismatch-' . uniqid('', true) . '@example.test';
        User::factory()->forTenant($otherTenantId)->create([
            'status' => 'active',
            'email' => $email,
        ]);

        TenantContext::setById($this->testTenantId);

        $method = new \ReflectionMethod(EmailDispatchService::class, 'resolveTenantId');
        $method->setAccessible(true);

        $tenantId = $method->invoke(app(EmailDispatchService::class), [], $email);

        $this->assertSame($otherTenantId, $tenantId);
        $this->assertSame($this->testTenantId, TenantContext::currentId());
    }

    public function test_email_dispatcher_allows_intentional_platform_send_without_leaked_context(): void
    {
        $email = 'external-provisioning-' . uniqid('', true) . '@example.test';

        TenantContext::setById($this->testTenantId);

        $method = new \ReflectionMethod(EmailDispatchService::class, 'resolveTenantId');
        $method->setAccessible(true);

        $tenantId = $method->invoke(app(EmailDispatchService::class), [
            'tenant_id' => null,
            'allow_missing_tenant' => true,
        ], $email);

        $this->assertNull($tenantId);
        $this->assertSame($this->testTenantId, TenantContext::currentId());
    }

    public function test_email_dispatcher_clears_context_for_null_tenant_sends(): void
    {
        $source = file_get_contents(app_path('Services/EmailDispatchService.php'));

        $this->assertStringContainsString('runWithResolvedTenant', $source);
        $this->assertStringContainsString('TenantContext::reset();', $source);
        $this->assertStringContainsString("'allow_missing_tenant'", $source);
    }

    public function test_tenant_provisioning_rejection_is_explicit_platform_email(): void
    {
        $source = file_get_contents(app_path('Services/TenantProvisioning/TenantProvisioningMailer.php'));

        $this->assertStringContainsString("'tenant_id' => null", $source);
        $this->assertStringContainsString("'allow_missing_tenant' => true", $source);
        $this->assertStringNotContainsString("'tenant_id' => TenantContext::currentId()", $source);
    }

    public function test_activity_email_helpers_select_recipient_tenant_for_dispatch(): void
    {
        $ideationSource = file_get_contents(app_path('Services/IdeationChallengeService.php'));
        $balanceSource = file_get_contents(app_path('Services/BalanceAlertService.php'));
        $brokerSource = file_get_contents(app_path('Services/BrokerMessageVisibilityService.php'));

        $this->assertStringContainsString("'preferred_language', 'tenant_id'", $ideationSource);
        $this->assertStringContainsString("'tenant_id' => \$tenantId", $ideationSource);
        $this->assertStringNotContainsString("'tenant_id' => TenantContext::currentId()", $ideationSource);
        $this->assertStringNotContainsString("'Project NEXUS'", $ideationSource);

        $this->assertStringContainsString("'preferred_language', 'tenant_id'", $balanceSource);
        $this->assertStringContainsString('$owner->tenant_id ?? TenantContext::currentId()', $balanceSource);

        $this->assertStringContainsString("'preferred_language', 'tenant_id'", $brokerSource);
        $this->assertStringContainsString('$broker->tenant_id ?? TenantContext::currentId()', $brokerSource);
        $this->assertStringContainsString("__('emails.common.fallback_manager')", $brokerSource);
    }

    public function test_instant_queue_stale_cleanup_only_marks_instant_rows_failed(): void
    {
        $source = file_get_contents(app_path('Services/CronJobRunner.php'));

        $this->assertStringNotContainsString(
            "WHERE status = 'processing' AND created_at < DATE_SUB",
            $source,
            'Instant queue cleanup must not mark digest processing rows as failed.'
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, "WHERE frequency = 'instant' AND status = 'processing' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")
        );
    }

    public function test_hot_match_cron_scopes_candidates_and_dedupe_by_tenant(): void
    {
        $source = file_get_contents(app_path('Services/CronJobRunner.php'));

        $this->assertStringContainsString('u.tenant_id = l.tenant_id', $source);
        $this->assertStringContainsString('mh.tenant_id = l.tenant_id', $source);
        $this->assertStringContainsString('AND l.tenant_id = ?', $source);
        $this->assertStringContainsString('TenantContext::runForTenant($tenantId', $source);
        $this->assertStringContainsString('if (!$dispatched)', $source);
    }

    public function test_event_notification_queue_resolves_tenant_from_recipient_when_context_is_missing(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'event-queue-' . uniqid('', true) . '@example.test',
        ]);

        DB::table('notification_settings')->insert([
            'user_id' => $user->id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'daily',
        ]);

        TenantContext::reset();

        try {
            $method = new \ReflectionMethod(EventNotificationService::class, 'sendEventEmail');
            $method->setAccessible(true);

            $sent = $method->invoke(
                new EventNotificationService(),
                (object) [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                ],
                'Event update',
                'Event changed',
                '/events/1',
                'event_update',
                '<p>Event changed</p>'
            );

            $row = DB::table('notification_queue')
                ->where('user_id', $user->id)
                ->where('activity_type', 'event_update')
                ->orderByDesc('id')
                ->first();

            $this->assertTrue($sent);
            $this->assertNotNull($row);
            $this->assertSame($this->testTenantId, (int) $row->tenant_id);
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_identity_verification_reminder_cron_uses_session_tenant_for_email_body(): void
    {
        $otherTenantId = 999;
        $otherTenantSlug = 'test-999';
        $user = User::factory()->forTenant($otherTenantId)->create([
            'status' => 'active',
            'email' => 'identity-reminder-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);

        DB::table('identity_verification_sessions')->insert([
            'tenant_id' => $otherTenantId,
            'user_id' => $user->id,
            'provider_slug' => 'mock',
            'verification_level' => 'document_only',
            'status' => 'created',
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        TenantContext::setById($this->testTenantId);

        try {
            $sent = RegistrationOrchestrationService::sendVerificationReminders();

            $row = DB::table('notification_queue')
                ->where('user_id', $user->id)
                ->where('activity_type', 'verification_reminder')
                ->orderByDesc('id')
                ->first();

            $this->assertSame(1, $sent);
            $this->assertNotNull($row);
            $this->assertSame($otherTenantId, (int) $row->tenant_id);
            $this->assertStringContainsString("/{$otherTenantSlug}/verify-identity", (string) $row->email_body);
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_dispatch_uses_recipient_tenant_for_frequency_when_context_is_leaked(): void
    {
        $otherTenantId = 999;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'configuration' => json_encode(['notifications' => ['default_frequency' => 'off']]),
        ]);
        DB::table('tenants')->where('id', $otherTenantId)->update([
            'configuration' => json_encode(['notifications' => ['default_frequency' => 'instant']]),
        ]);

        $user = User::factory()->forTenant($otherTenantId)->create([
            'status' => 'active',
            'email' => 'dispatch-tenant-' . uniqid('', true) . '@example.test',
        ]);

        TenantContext::setById($this->testTenantId);

        NotificationDispatcher::dispatch(
            $user->id,
            'global',
            0,
            'new_topic',
            'Tenant-specific default check',
            '/notifications',
            '<p>Tenant-specific default check</p>'
        );

        $row = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->where('activity_type', 'new_topic')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($otherTenantId, (int) $row->tenant_id);
        $this->assertSame($this->testTenantId, TenantContext::currentId());
    }
}
