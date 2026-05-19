<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminEmailDeliverabilityControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_queues_endpoint_returns_source_diagnostics_and_stale_rows(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasTable('newsletter_queue') || !Schema::hasTable('newsletters')) {
            $this->markTestSkipped('Email deliverability queue tables are not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'queue-diagnostics-member@example.test',
        ]);
        Sanctum::actingAs($admin);

        DB::table('notification_queue')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'activity_type' => 'new_message',
            'content_snippet' => 'Queue diagnostics notification',
            'link' => '/messages',
            'status' => 'pending',
            'frequency' => 'instant',
            'created_at' => now()->subMinutes(20),
        ]);

        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'created_by' => $admin->id,
            'subject' => 'Queue Diagnostics Newsletter',
            'content' => '<p>Queue diagnostics</p>',
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('newsletter_queue')->insert([
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $newsletterId,
            'user_id' => $member->id,
            'email' => 'queue-diagnostics-member@example.test',
            'status' => 'failed',
            'attempts' => 2,
            'error_message' => 'simulated queue failure',
            'last_attempted_at' => now()->subMinutes(3),
            'created_at' => now()->subMinutes(4),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.notification_queue.available', true);
        $response->assertJsonPath('data.diagnostics.newsletter_queue.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.notification_queue.stale_pending'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.newsletter_queue.failed_recent'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.notification_queue.status_counts.pending'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.newsletter_queue.status_counts.failed'));
        $this->assertNotEmpty($response->json('data.rows'));
    }

    public function test_queues_endpoint_scopes_rows_to_admin_tenant(): void
    {
        if (!Schema::hasTable('notification_queue')) {
            $this->markTestSkipped('Notification queue table is not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $tenantMember = User::factory()->forTenant($this->testTenantId)->create();
        $otherMember = User::factory()->forTenant(999)->create();
        Sanctum::actingAs($admin);

        DB::table('notification_queue')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $tenantMember->id,
                'activity_type' => 'tenant_message',
                'content_snippet' => 'Tenant queue row',
                'link' => '/messages',
                'status' => 'pending',
                'frequency' => 'instant',
                'created_at' => now()->subMinutes(20),
            ],
            [
                'tenant_id' => 999,
                'user_id' => $otherMember->id,
                'activity_type' => 'other_message',
                'content_snippet' => 'Other tenant queue row',
                'link' => '/messages',
                'status' => 'pending',
                'frequency' => 'instant',
                'created_at' => now()->subMinutes(20),
            ],
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=notification_queue&limit=25');

        $response->assertOk();
        $tenantIds = collect($response->json('data.rows'))->pluck('tenant_id')->unique()->values()->all();

        $this->assertContains($this->testTenantId, $tenantIds);
        $this->assertNotContains(999, $tenantIds);
    }

    public function test_queues_endpoint_surfaces_marketplace_report_notifications(): void
    {
        if (!Schema::hasTable('marketplace_report_notifications')) {
            $this->markTestSkipped('Marketplace report notification table is not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'marketplace-report-queue@example.test',
        ]);
        Sanctum::actingAs($admin);

        DB::table('marketplace_report_notifications')->insert([
            'tenant_id' => $this->testTenantId,
            'marketplace_report_id' => 123456,
            'recipient_user_id' => $member->id,
            'event_type' => 'received',
            'channel' => 'email',
            'dedupe_key' => 'test-marketplace-report-queue-' . uniqid(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=marketplace_report_notifications&limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.marketplace_report_notifications.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.marketplace_report_notifications.stale_pending'));

        $sources = collect($response->json('data.rows'))->pluck('source')->unique()->values()->all();
        $this->assertSame(['marketplace_report_notifications'], $sources);
    }

    public function test_queues_endpoint_surfaces_event_reminders(): void
    {
        if (!Schema::hasTable('event_reminders') || !Schema::hasTable('events')) {
            $this->markTestSkipped('Event reminder tables are not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-queue@example.test',
        ]);
        Sanctum::actingAs($admin);

        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $admin->id,
            'title' => 'Email Queue Diagnostic Event',
            'description' => 'Event reminder queue diagnostics',
            'location' => 'Online',
            'start_time' => now()->addDay(),
            'start_date' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'is_online' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $member->id,
            'remind_before_minutes' => 60,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subHour(),
            'status' => 'pending',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=event_reminders&limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.event_reminders.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.event_reminders.stale_pending'));

        $sources = collect($response->json('data.rows'))->pluck('source')->unique()->values()->all();
        $this->assertSame(['event_reminders'], $sources);
    }
}
