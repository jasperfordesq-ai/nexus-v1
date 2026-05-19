<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\VolunteerReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class VolunteerEmailReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shift_reminder_uses_explicit_tenant_context_and_restores_previous_context(): void
    {
        $tenantId = 999;
        $volunteer = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Volunteer',
            'email' => 'vol-reminder-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        [, $opportunityId, $shiftId] = $this->createVolunteerShift($tenantId, (int) $volunteer->id);

        DB::table('vol_reminder_settings')->insert([
            'tenant_id' => $tenantId,
            'reminder_type' => 'pre_shift',
            'enabled' => 1,
            'hours_before' => 24,
            'email_enabled' => 1,
            'push_enabled' => 1,
            'sms_enabled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        TenantContext::setById(2);

        $sent = VolunteerReminderService::sendReminders($tenantId, $opportunityId);

        $this->assertSame(1, $sent);
        $this->assertSame(2, TenantContext::currentId());
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertStringContainsString('/test-999/volunteering/opportunities/' . $opportunityId, $mailer->calls[0]['body']);
        $this->assertStringNotContainsString('/hour-timebank/volunteering', $mailer->calls[0]['body']);
        $this->assertDatabaseHas('vol_reminders_sent', [
            'tenant_id' => $tenantId,
            'user_id' => $volunteer->id,
            'reference_id' => $shiftId,
            'channel' => 'email',
        ]);
        $this->assertSame(0, DB::table('vol_reminders_sent')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $volunteer->id)
            ->whereIn('channel', ['push', 'sms'])
            ->count());
    }

    public function test_pre_shift_cron_does_not_stamp_push_sent_without_email_delivery(): void
    {
        $tenantId = 999;
        $volunteer = User::factory()->forTenant($tenantId)->create([
            'email' => 'vol-push-only-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $this->createVolunteerShift($tenantId, (int) $volunteer->id);

        DB::table('vol_reminder_settings')->insert([
            'tenant_id' => $tenantId,
            'reminder_type' => 'pre_shift',
            'enabled' => 1,
            'hours_before' => 24,
            'email_enabled' => 0,
            'push_enabled' => 1,
            'sms_enabled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        VolunteerReminderService::sendPreShiftReminders();

        $this->assertCount(0, array_filter(
            $mailer->calls,
            fn (array $call): bool => $call['to'] === $volunteer->email
        ));
        $this->assertSame(0, DB::table('vol_reminders_sent')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $volunteer->id)
            ->count());
    }

    public function test_admin_hours_verification_no_longer_sends_duplicate_raw_email_after_wallet_payment(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/AdminVolunteerController.php'));
        $start = strpos($source, 'public function verifyHours');
        $end = strpos($source, '/** GET /api/v2/admin/volunteering */', $start);
        $method = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString('EmailDispatchService::sendRaw', $method);
        $this->assertStringContainsString("\$paymentOutcome === 'paid'", $method);
        $this->assertStringContainsString('Notification::createNotification', $method);
        $this->assertStringContainsString('NotificationDispatcher::dispatch', $method);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function createVolunteerShift(int $tenantId, int $volunteerId): array
    {
        $owner = User::factory()->forTenant($tenantId)->create([
            'email' => 'vol-org-owner-' . uniqid('', true) . '@example.test',
        ]);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'name' => 'Reminder Org',
            'status' => 'approved',
            'balance' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $opportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'title' => 'Reminder Opportunity',
            'description' => 'Help with reminders.',
            'location' => 'Community Hall',
            'is_active' => 1,
            'status' => 'open',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shiftId = (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(5),
            'capacity' => 5,
            'created_at' => now(),
        ]);

        DB::table('vol_applications')->insert([
            'tenant_id' => $tenantId,
            'opportunity_id' => $opportunityId,
            'shift_id' => $shiftId,
            'user_id' => $volunteerId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$orgId, $opportunityId, $shiftId];
    }

    private function fakeMailer(bool $result = true): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            public array $calls = [];

            public function __construct(private bool $result)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return $this->result;
            }
        };
    }
}
