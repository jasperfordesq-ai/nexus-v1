<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * VOL-BE-008: approving a sub-whole-hour log floors to 0 credits (users.balance
 * is whole hours), so it must NOT imply a successful credit — the volunteer is
 * told plainly that no credit was added.
 */
class SubHourApprovalMessageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sub_whole_hour_approval_credits_nothing_and_sends_an_honest_message(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        TenantContext::setById($this->testTenantId);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $admin->id,
            'name' => 'Sub-hour Org',
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $logId = (int) DB::table('vol_logs')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $volunteer->id,
            'organization_id' => $orgId,
            'date_logged' => now()->toDateString(),
            'hours' => 0.75,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById($this->testTenantId);
        $ok = VolunteerService::verifyHours($logId, (int) $admin->id, 'approve');

        $this->assertTrue($ok);
        $this->assertSame('no_whole_hours', VolunteerService::getLastPaymentOutcome());

        // Nothing minted, nothing debited.
        $this->assertSame(0, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));
        $this->assertFalse(
            DB::table('vol_org_transactions')->where('vol_organization_id', $orgId)->exists(),
            'no payment transaction should be recorded for a sub-whole-hour approval'
        );

        // The volunteer is told honestly that no credit was added — not the generic
        // "approved!" message that implies success.
        $message = DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $volunteer->id)
            ->where('type', 'vol_hours_approved')
            ->orderByDesc('id')
            ->value('message');

        $this->assertNotNull($message, 'the volunteer should receive an approval notification');
        $this->assertStringContainsString('no time credit was added', $message);
    }

    /**
     * 2026-07-09 audit P4: the VOL-BE-008 fix originally reached only the
     * bell/push channel — the EMAIL stayed celebratory-generic. The email body
     * must carry the same honest no-credit note.
     */
    public function test_sub_whole_hour_approval_email_carries_the_no_credit_note(): void
    {
        TenantContext::setById($this->testTenantId);

        $honest = \App\Services\NotificationDispatcher::buildVolHoursApprovedEmail(0.75, 'Sub-hour Org', creditAdded: false);
        $this->assertStringContainsString(
            __('emails_notifications.volunteering.hours_approved_no_credit_note'),
            $honest,
            'sub-hour approval email must say no credit was added'
        );
        $this->assertStringNotContainsString(
            __('emails_notifications.volunteering.hours_approved_thanks'),
            $honest,
            'sub-hour approval email must not use the celebratory generic copy'
        );

        // Whole-hour approvals keep the celebratory copy.
        $generic = \App\Services\NotificationDispatcher::buildVolHoursApprovedEmail(2.0, 'Sub-hour Org');
        $this->assertStringContainsString(
            __('emails_notifications.volunteering.hours_approved_thanks'),
            $generic
        );
    }
}
