<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class VettingRenewalRemindersCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_notifies_active_staff_and_stamps_the_seven_day_reminder(): void
    {
        $tenantId = 999;
        $broker = User::factory()->forTenant($tenantId)->create([
            'role' => 'broker',
            'status' => 'active',
            'email' => '',
        ]);
        $member = User::factory()->forTenant($tenantId)->create(['status' => 'active']);

        $attestationId = (int) DB::table('member_vetting_attestations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $member->id,
            'scheme_code' => 'uk_national_safeguarding',
            'attestation_code' => 'uk_safeguarding_clearance',
            'certification_codes' => json_encode(['dbs_enhanced'], JSON_THROW_ON_ERROR),
            'purpose_code' => 'safeguarded_member_contact',
            'scope_type' => 'tenant',
            'scope_identifier' => (string) $tenantId,
            'scope_summary_encrypted' => null,
            'private_notes_encrypted' => null,
            'review_due_at' => now()->addDays(7)->toDateString(),
            'authority_expires_at' => null,
            'decision' => 'confirmed',
            'confirmed_by' => $broker->id,
            'confirmed_at' => now(),
            'revoked_by' => null,
            'revoked_at' => null,
            'revocation_reason_code' => null,
            'policy_version' => 'safeguarded-contact-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('safeguarding:vetting-renewals')
            ->expectsOutputToContain('Done: 1 vetting renewal notification.')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('member_vetting_attestations')
            ->where('id', $attestationId)
            ->value('renewal_reminder_7_sent_at'));
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $broker->id,
            'type' => 'vetting_renewal',
            'is_read' => 0,
        ]);
    }
}
