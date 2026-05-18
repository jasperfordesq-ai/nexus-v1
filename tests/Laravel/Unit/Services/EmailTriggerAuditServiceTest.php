<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\EmailTriggerAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class EmailTriggerAuditServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_event_matrix_covers_critical_enterprise_email_flows(): void
    {
        $matrix = app(EmailTriggerAuditService::class)->eventMatrix();
        $keys = array_map(
            fn (array $row): string => $row['module'] . ':' . $row['event'] . ':' . $row['category'],
            $matrix
        );

        $this->assertContains('auth:password_reset_requested:password_reset', $keys);
        $this->assertContains('registration:email_verification_required:email_verification', $keys);
        $this->assertContains('groups:group_email_invite:group_invite', $keys);
        $this->assertContains('safeguarding:incident_flag_vetting_guardian_training:safeguarding', $keys);
        $this->assertContains('newsletter:newsletter_queue_dispatch:newsletter', $keys);
    }

    public function test_run_returns_score_and_issue_structure(): void
    {
        $result = app(EmailTriggerAuditService::class)->run(2, 24);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('matrix', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('issues_by_severity', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(1000, $result['score']);
    }

    public function test_direct_email_send_surface_is_empty_outside_dispatchers(): void
    {
        $surface = app(EmailTriggerAuditService::class)->directEmailSendSurface();

        $this->assertSame([], $surface);
    }

    public function test_dispatcher_send_surface_requires_explicit_tenant_options(): void
    {
        $surface = app(EmailTriggerAuditService::class)->tenantlessDispatcherSendSurface();

        $this->assertSame([], $surface);
    }

    public function test_run_detects_sent_newsletter_queue_without_successful_email_log(): void
    {
        if (!Schema::hasTable('newsletters') || !Schema::hasTable('newsletter_queue') || !Schema::hasTable('email_log')) {
            $this->markTestSkipped('Newsletter email audit tables are not available.');
        }

        $userId = DB::table('users')->insertGetId([
            'tenant_id' => 2,
            'name' => 'Newsletter Audit Admin',
            'email' => 'newsletter-audit-admin@example.test',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => 2,
            'created_by' => $userId,
            'subject' => 'Newsletter Audit',
            'content' => '<p>Audit</p>',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('newsletter_queue')->insert([
            'tenant_id' => 2,
            'newsletter_id' => $newsletterId,
            'user_id' => $userId,
            'email' => 'newsletter-audit-recipient@example.test',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now()->subMinute(),
        ]);

        $result = app(EmailTriggerAuditService::class)->run(2, 24);
        $codes = array_column($result['issues'], 'code');

        $this->assertContains('newsletter_queue_marked_sent_without_email_log', $codes);
    }

    public function test_run_surfaces_suppressed_notification_queue_rows(): void
    {
        if (!Schema::hasTable('notification_queue')) {
            $this->markTestSkipped('Notification queue table is not available.');
        }

        $userId = DB::table('users')->insertGetId([
            'tenant_id' => 2,
            'name' => 'Digest Suppressed User',
            'email' => 'digest-suppressed@example.test',
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notification_queue')->insert([
            'tenant_id' => 2,
            'user_id' => $userId,
            'activity_type' => 'digest_suppressed',
            'content_snippet' => 'Suppressed digest audit row',
            'link' => '/notifications',
            'status' => 'suppressed',
            'frequency' => 'daily',
            'created_at' => now()->subMinute(),
        ]);

        $result = app(EmailTriggerAuditService::class)->run(2, 24);
        $codes = array_column($result['issues'], 'code');

        $this->assertContains('notification_queue_suppressed_recently', $codes);
        $this->assertNotContains('notification_queue_marked_sent_without_email_log', $codes);
    }
}
