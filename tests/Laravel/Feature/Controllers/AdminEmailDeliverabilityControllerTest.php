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

    public function test_queues_endpoint_surfaces_federated_review_delivery_failures(): void
    {
        if (
            !Schema::hasTable('reviews')
            || !Schema::hasColumn('reviews', 'external_partner_id')
            || !Schema::hasColumn('reviews', 'external_id')
            || !Schema::hasColumn('reviews', 'email_claimed_at')
            || !Schema::hasColumn('reviews', 'email_sent_at')
            || !Schema::hasColumn('reviews', 'email_skipped_at')
            || !Schema::hasColumn('reviews', 'email_failed_at')
            || !Schema::hasColumn('reviews', 'email_last_error')
        ) {
            $this->markTestSkipped('Federated review delivery columns are not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reviewer = User::factory()->forTenant(999)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'federated-review-queue@example.test',
        ]);
        Sanctum::actingAs($admin);

        DB::table('reviews')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 876543,
            'external_id' => 'admin-queue-fed-review-' . uniqid(),
            'reviewer_id' => $reviewer->id,
            'reviewer_tenant_id' => 999,
            'receiver_id' => $receiver->id,
            'receiver_tenant_id' => $this->testTenantId,
            'rating' => 5,
            'comment' => 'Federated review delivery diagnostics',
            'review_type' => 'federated',
            'status' => 'approved',
            'show_cross_tenant' => 1,
            'notification_sent_at' => null,
            'email_sent_at' => null,
            'email_skipped_at' => null,
            'email_failed_at' => now()->subMinutes(2),
            'email_last_error' => 'simulated federated review mail failure',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(2),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=reviews&limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.reviews.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.reviews.failed_recent'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.reviews.status_counts.failed'));

        $rows = collect($response->json('data.rows'));
        $this->assertSame(['reviews'], $rows->pluck('source')->unique()->values()->all());
        $this->assertSame('failed', $rows->first()['status'] ?? null);
        $this->assertSame('federated-review-queue@example.test', $rows->first()['email'] ?? null);
    }

    public function test_queues_endpoint_surfaces_federation_source_delivery_failures(): void
    {
        foreach (['federation_messages', 'federation_transactions', 'federation_inbound_connections'] as $table) {
            if (
                !Schema::hasTable($table)
                || !Schema::hasColumn($table, 'notification_sent_at')
                || !Schema::hasColumn($table, 'email_sent_at')
                || !Schema::hasColumn($table, 'email_failed_at')
                || !Schema::hasColumn($table, 'email_last_error')
            ) {
                $this->markTestSkipped("{$table} delivery columns are not available.");
            }
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'federation-source-queue@example.test',
        ]);
        Sanctum::actingAs($admin);

        DB::table('federation_messages')->insert([
            'sender_tenant_id' => 0,
            'sender_user_id' => 123456,
            'receiver_tenant_id' => $this->testTenantId,
            'receiver_user_id' => $receiver->id,
            'subject' => 'Admin federation message diagnostic',
            'body' => 'Admin federation message diagnostic body',
            'direction' => 'inbound',
            'status' => 'pending',
            'external_partner_id' => 987654,
            'external_receiver_name' => 'Remote Sender',
            'external_message_id' => 'admin-fed-msg-' . uniqid(),
            'notification_sent_at' => null,
            'email_sent_at' => null,
            'email_failed_at' => now()->subMinutes(2),
            'email_last_error' => 'simulated federation message failure',
            'created_at' => now()->subMinutes(20),
        ]);

        DB::table('federation_transactions')->insert([
            'sender_tenant_id' => 0,
            'sender_user_id' => 123456,
            'receiver_tenant_id' => $this->testTenantId,
            'receiver_user_id' => $receiver->id,
            'amount' => 2.5,
            'description' => 'Admin federation transaction diagnostic',
            'status' => 'completed',
            'completed_at' => now()->subMinute(),
            'external_partner_id' => 987654,
            'external_receiver_name' => 'Remote Sender',
            'external_transaction_id' => 'admin-fed-tx-' . uniqid(),
            'notification_sent_at' => null,
            'email_sent_at' => null,
            'email_failed_at' => now()->subMinutes(2),
            'email_last_error' => 'simulated federation transaction failure',
            'created_at' => now()->subMinutes(20),
        ]);

        DB::table('federation_inbound_connections')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 987654,
            'local_user_id' => $receiver->id,
            'external_user_id' => 'admin-remote-user-' . uniqid(),
            'status' => 'pending',
            'message' => 'Admin federation connection diagnostic',
            'notification_sent_at' => null,
            'email_sent_at' => null,
            'email_failed_at' => now()->subMinutes(2),
            'email_last_error' => 'simulated federation connection failure',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(2),
        ]);

        foreach (['federation_messages', 'federation_transactions', 'federation_inbound_connections'] as $source) {
            $response = $this->apiGet("/v2/admin/email-deliverability/queues?source={$source}&limit=10");

            $response->assertOk();
            $response->assertJsonPath("data.diagnostics.{$source}.available", true);
            $this->assertGreaterThanOrEqual(1, $response->json("data.diagnostics.{$source}.failed_recent"));
            $this->assertGreaterThanOrEqual(1, $response->json("data.diagnostics.{$source}.status_counts.failed"));

            $rows = collect($response->json('data.rows'));
            $this->assertSame([$source], $rows->pluck('source')->unique()->values()->all());
            $this->assertSame('failed', $rows->first()['status'] ?? null);
            $this->assertSame('federation-source-queue@example.test', $rows->first()['email'] ?? null);
        }
    }

    public function test_queues_endpoint_surfaces_member_subscription_event_delivery_failures(): void
    {
        if (
            !Schema::hasTable('member_subscription_events')
            || !Schema::hasTable('member_subscriptions')
            || !Schema::hasTable('member_premium_tiers')
            || !Schema::hasColumn('member_subscription_events', 'notification_sent_at')
            || !Schema::hasColumn('member_subscription_events', 'notification_failed_at')
            || !Schema::hasColumn('member_subscription_events', 'notification_last_error')
        ) {
            $this->markTestSkipped('Member subscription event delivery columns are not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'member-subscription-event-queue@example.test',
        ]);
        Sanctum::actingAs($admin);

        $tierId = DB::table('member_premium_tiers')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'slug' => 'audit-tier-' . uniqid(),
            'name' => 'Audit Tier',
            'monthly_price_cents' => 500,
            'yearly_price_cents' => 5000,
            'features' => json_encode(['audit']),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscriptionId = DB::table('member_subscriptions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'tier_id' => $tierId,
            'stripe_subscription_id' => 'sub_audit_' . uniqid(),
            'stripe_customer_id' => 'cus_audit_' . uniqid(),
            'status' => 'active',
            'billing_interval' => 'monthly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('member_subscription_events')->insert([
            'subscription_id' => $subscriptionId,
            'tenant_id' => $this->testTenantId,
            'event_type' => 'invoice.payment_failed',
            'stripe_event_id' => 'evt_member_subscription_queue_' . uniqid(),
            'payload' => json_encode(['object' => 'invoice']),
            'notification_sent_at' => null,
            'notification_failed_at' => now()->subMinutes(2),
            'notification_last_error' => 'simulated member subscription notification failure',
            'created_at' => now()->subMinutes(20),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=member_subscription_events&limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.member_subscription_events.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.member_subscription_events.failed_recent'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.member_subscription_events.status_counts.failed'));

        $rows = collect($response->json('data.rows'));
        $this->assertSame(['member_subscription_events'], $rows->pluck('source')->unique()->values()->all());
        $this->assertSame('failed', $rows->first()['status'] ?? null);
        $this->assertSame('member-subscription-event-queue@example.test', $rows->first()['email'] ?? null);
    }

    public function test_queues_endpoint_surfaces_stripe_donation_receipt_delivery_failures(): void
    {
        if (
            !Schema::hasTable('vol_donations')
            || !Schema::hasColumn('vol_donations', 'receipt_email_sent_at')
            || !Schema::hasColumn('vol_donations', 'receipt_email_failed_at')
        ) {
            $this->markTestSkipped('Donation receipt email evidence columns are not available.');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        DB::table('vol_donations')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => null,
            'amount' => 50.00,
            'currency' => 'EUR',
            'payment_method' => 'stripe',
            'payment_reference' => 'Donation queue diagnostics',
            'donor_name' => 'Donation Queue Donor',
            'donor_email' => 'donation-receipt-queue@example.test',
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_donation_queue_' . uniqid(),
            'receipt_email_sent_at' => null,
            'receipt_email_failed_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(20),
        ]);

        $response = $this->apiGet('/v2/admin/email-deliverability/queues?source=vol_donations&limit=10');

        $response->assertOk();
        $response->assertJsonPath('data.diagnostics.vol_donations.available', true);
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.vol_donations.failed_recent'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.diagnostics.vol_donations.status_counts.failed'));

        $rows = collect($response->json('data.rows'));
        $this->assertSame(['vol_donations'], $rows->pluck('source')->unique()->values()->all());
        $this->assertSame('failed', $rows->first()['status'] ?? null);
        $this->assertSame('donation-receipt-queue@example.test', $rows->first()['email'] ?? null);
    }
}
