<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Gdpr;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\Enterprise\GdprService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the volunteering GDPR erasure gaps (module audit,
 * round 2): expense receipt files + path columns must be removed, the
 * custom-field-value delete must be entity_type-scoped (no collision damage),
 * and an organisation's public contact email that equals the erased user's
 * personal email must be scrubbed along with their org membership.
 */
class VolunteeringErasureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_erasure_cleans_volunteering_pii(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create(['email' => 'erase-me-' . uniqid() . '@example.test']);
        $originalEmail = (string) $user->email;
        TenantContext::setById($tenantId);

        // Receipt file on the 'local' disk + expense row pointing at it.
        $receiptPath = "volunteer-expenses/{$tenantId}/receipt-" . uniqid() . '.pdf';
        Storage::disk('local')->put($receiptPath, '%PDF-1.4 fake receipt');
        $this->assertTrue(Storage::disk('local')->exists($receiptPath));

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'name' => 'Erasure Org',
            'status' => 'active',
            'contact_email' => $originalEmail, // owner used their personal email
            'created_at' => now(),
        ]);
        DB::table('vol_expenses')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'organization_id' => $orgId,
            'expense_type' => 'travel',
            'amount' => 10.00,
            'currency' => 'EUR',
            'description' => 'Bus fare',
            'receipt_path' => $receiptPath,
            'receipt_filename' => 'receipt.pdf',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        DB::table('org_members')->insert([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'org_type' => 'volunteer',
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Custom-field values: one attached to the user's application (must be
        // deleted), and one on an OPPORTUNITY sharing the same entity_id (must
        // survive — the pre-fix delete matched on entity_id alone).
        $appId = (int) DB::table('vol_applications')->insertGetId([
            'tenant_id' => $tenantId,
            'opportunity_id' => 1,
            'user_id' => $user->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $fieldId = (int) DB::table('vol_custom_fields')->insertGetId([
            'tenant_id' => $tenantId,
            'field_key' => 'k',
            'field_label' => 'K',
            'field_type' => 'text',
            'applies_to' => 'application',
            'is_active' => 1,
            'created_at' => now(),
        ]);
        DB::table('vol_custom_field_values')->insert([
            'tenant_id' => $tenantId,
            'custom_field_id' => $fieldId,
            'entity_type' => 'application',
            'entity_id' => $appId,
            'field_value' => 'my answer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Collision: an opportunity custom-field value whose entity_id == $appId.
        DB::table('vol_custom_field_values')->insert([
            'tenant_id' => $tenantId,
            'custom_field_id' => $fieldId,
            'entity_type' => 'opportunity',
            'entity_id' => $appId,
            'field_value' => 'unrelated opportunity data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new GdprService($tenantId))->executeAccountDeletion($user->id);

        // Receipt file removed and path columns cleared.
        $this->assertFalse(Storage::disk('local')->exists($receiptPath));
        $expense = DB::table('vol_expenses')->where('user_id', $user->id)->where('tenant_id', $tenantId)->first();
        $this->assertNotNull($expense);
        $this->assertNull($expense->receipt_path);
        $this->assertNull($expense->receipt_filename);

        // Org contact email scrubbed; org membership deleted.
        $this->assertNull(DB::table('vol_organizations')->where('id', $orgId)->value('contact_email'));
        $this->assertSame(0, DB::table('org_members')->where('user_id', $user->id)->where('tenant_id', $tenantId)->count());

        // Application custom-field value deleted; the colliding opportunity one survives.
        $this->assertSame(0, DB::table('vol_custom_field_values')->where('entity_type', 'application')->where('entity_id', $appId)->where('tenant_id', $tenantId)->count());
        $this->assertSame(1, DB::table('vol_custom_field_values')->where('entity_type', 'opportunity')->where('entity_id', $appId)->where('tenant_id', $tenantId)->count());
    }

    public function test_erasure_scrubs_gift_aid_except_hmrc_claimed_declarations(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create();
        TenantContext::setById($tenantId);

        $mkDonation = fn (string $claimStatus): int => (int) DB::table('vol_donations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'amount' => 25.00,
            'currency' => 'GBP',
            'payment_method' => 'stripe',
            'donor_name' => 'Donor Name',
            'donor_email' => 'donor@example.test',
            'message' => 'In memory of...',
            'status' => 'completed',
            'gift_aid_claim_status' => $claimStatus,
            'gift_aid_declaration_name' => 'Declared Name',
            'gift_aid_address_line1' => '1 Home Street',
            'gift_aid_address_line2' => 'Flat 2',
            'gift_aid_town' => 'Hometown',
            'gift_aid_postcode' => 'HT1 2AB',
            'gift_aid_country' => 'GB',
            'gift_aid_consented_at' => now(),
            'created_at' => now(),
        ]);

        $unclaimedId = $mkDonation('ready');
        $claimedId = $mkDonation('claimed');
        $refundAfterClaimId = $mkDonation('refund_after_claim');

        (new GdprService($tenantId))->executeAccountDeletion($user->id);

        // Donor identity + free-text message are scrubbed on EVERY row,
        // claimed or not.
        foreach ([$unclaimedId, $claimedId, $refundAfterClaimId] as $id) {
            $row = DB::table('vol_donations')->find($id);
            $this->assertNotNull($row, 'donation rows are retained for org accounting');
            $this->assertNull($row->donor_name);
            $this->assertNull($row->donor_email);
            $this->assertNull($row->message);
        }

        // Unclaimed declaration ('ready') — the gift-aid PII goes too.
        $unclaimed = DB::table('vol_donations')->find($unclaimedId);
        $this->assertNull($unclaimed->gift_aid_declaration_name);
        $this->assertNull($unclaimed->gift_aid_address_line1);
        $this->assertNull($unclaimed->gift_aid_address_line2);
        $this->assertNull($unclaimed->gift_aid_town);
        $this->assertNull($unclaimed->gift_aid_postcode);

        // Claimed declarations are RETAINED — HMRC's ~6-year record-keeping
        // obligation for submitted Gift Aid claims (Art. 17(3)(b) carve-out).
        foreach ([$claimedId, $refundAfterClaimId] as $id) {
            $claimed = DB::table('vol_donations')->find($id);
            $this->assertSame('Declared Name', $claimed->gift_aid_declaration_name);
            $this->assertSame('1 Home Street', $claimed->gift_aid_address_line1);
            $this->assertSame('Flat 2', $claimed->gift_aid_address_line2);
            $this->assertSame('Hometown', $claimed->gift_aid_town);
            $this->assertSame('HT1 2AB', $claimed->gift_aid_postcode);
        }
    }

    public function test_erasure_scrubs_supporter_reservation_and_swap_free_text_but_keeps_rows(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create();
        $other = User::factory()->forTenant($tenantId)->create();
        TenantContext::setById($tenantId);

        // Community project + supporter pledges (one from the erased user,
        // one from another member).
        $projectId = (int) DB::table('vol_community_projects')->insertGetId([
            'tenant_id' => $tenantId,
            'proposed_by' => $other->id,
            'title' => 'Erasure Project',
            'description' => 'Community garden.',
            'status' => 'proposed',
            'supporter_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mySupportId = (int) DB::table('vol_community_project_supporters')->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'user_id' => $user->id,
            'message' => 'Count me in, I live next door!',
            'supported_at' => now(),
        ]);
        $otherSupportId = (int) DB::table('vol_community_project_supporters')->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'user_id' => $other->id,
            'message' => 'Happy to help at weekends',
            'supported_at' => now(),
        ]);

        // Shifts (FK targets for reservations and swaps).
        $opportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $tenantId,
            'title' => 'Erasure Opportunity',
            'description' => 'Shift work.',
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
        ]);
        $mkShift = fn (): int => (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'capacity' => 5,
            'created_at' => now(),
        ]);
        $shiftA = $mkShift();
        $shiftB = $mkShift();

        // Group reservations: one led by the erased user, one by someone else.
        $ledReservationId = (int) DB::table('vol_shift_group_reservations')->insertGetId([
            'tenant_id' => $tenantId,
            'shift_id' => $shiftA,
            'group_id' => 1,
            'reserved_slots' => 3,
            'reserved_by' => $user->id,
            'status' => 'active',
            'notes' => 'Our minibus arrives at 9, ring me on my mobile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherReservationId = (int) DB::table('vol_shift_group_reservations')->insertGetId([
            'tenant_id' => $tenantId,
            'shift_id' => $shiftB,
            'group_id' => 2,
            'reserved_slots' => 2,
            'reserved_by' => $other->id,
            'status' => 'active',
            'notes' => 'Other leader logistics',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Swap requests: outgoing (user authored the message) and incoming
        // (counterparty authored it).
        $outgoingSwapId = (int) DB::table('vol_shift_swap_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'from_user_id' => $user->id,
            'to_user_id' => $other->id,
            'from_shift_id' => $shiftA,
            'to_shift_id' => $shiftB,
            'status' => 'pending',
            'message' => 'Sorry, my daughter has a hospital appointment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $incomingSwapId = (int) DB::table('vol_shift_swap_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'from_user_id' => $other->id,
            'to_user_id' => $user->id,
            'from_shift_id' => $shiftB,
            'to_shift_id' => $shiftA,
            'status' => 'accepted',
            'message' => 'Counterparty reason for swapping',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new GdprService($tenantId))->executeAccountDeletion($user->id);

        // Supporter row kept (supporter counts stay honest), message scrubbed;
        // the other member's pledge is untouched.
        $mySupport = DB::table('vol_community_project_supporters')->find($mySupportId);
        $this->assertNotNull($mySupport);
        $this->assertNull($mySupport->message);
        $this->assertSame('Happy to help at weekends', DB::table('vol_community_project_supporters')->find($otherSupportId)->message);

        // Led reservation kept (group capacity record), notes scrubbed; the
        // other leader's notes are untouched.
        $ledReservation = DB::table('vol_shift_group_reservations')->find($ledReservationId);
        $this->assertNotNull($ledReservation);
        $this->assertNull($ledReservation->notes);
        $this->assertSame('Other leader logistics', DB::table('vol_shift_group_reservations')->find($otherReservationId)->notes);

        // Swap requests kept in BOTH directions (two-party records). The
        // erased user's own message is scrubbed; the counterparty's survives.
        $outgoing = DB::table('vol_shift_swap_requests')->find($outgoingSwapId);
        $this->assertNotNull($outgoing, 'outgoing swap row must be retained for the counterparty');
        $this->assertNull($outgoing->message);
        $incoming = DB::table('vol_shift_swap_requests')->find($incomingSwapId);
        $this->assertNotNull($incoming, 'incoming swap row must be retained for the counterparty');
        $this->assertSame('Counterparty reason for swapping', $incoming->message);
    }

    public function test_sar_export_includes_supporter_pledges_and_led_group_reservations(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create();
        TenantContext::setById($tenantId);

        $projectId = (int) DB::table('vol_community_projects')->insertGetId([
            'tenant_id' => $tenantId,
            'proposed_by' => $user->id,
            'title' => 'SAR Export Project',
            'description' => 'Litter pick.',
            'status' => 'proposed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vol_community_project_supporters')->insert([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'user_id' => $user->id,
            'message' => 'My supporter pledge message',
            'supported_at' => now(),
        ]);

        $opportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $tenantId,
            'title' => 'SAR Export Opportunity',
            'description' => 'Shift work.',
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
        ]);
        $shiftId = (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'capacity' => 4,
            'created_at' => now(),
        ]);
        DB::table('vol_shift_group_reservations')->insert([
            'tenant_id' => $tenantId,
            'shift_id' => $shiftId,
            'group_id' => 1,
            'reserved_slots' => 3,
            'reserved_by' => $user->id,
            'status' => 'active',
            'notes' => 'Leader logistics notes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new GdprService($tenantId);
        $method = new \ReflectionMethod($service, 'exportVolunteerData');
        $data = $method->invoke($service, (int) $user->id);

        // Supporter pledge disclosed, including the free-text message and
        // the project it relates to.
        $this->assertNotEmpty($data['community_project_support']);
        $pledge = $data['community_project_support'][0];
        $this->assertSame('My supporter pledge message', $pledge['message']);
        $this->assertSame('SAR Export Project', $pledge['project_title']);

        // Group reservations the user LEADS are disclosed with their notes
        // (memberships alone would miss these rows).
        $this->assertNotEmpty($data['shift_group_reservations_led']);
        $reservation = $data['shift_group_reservations_led'][0];
        $this->assertSame($shiftId, (int) $reservation['shift_id']);
        $this->assertSame('Leader logistics notes', $reservation['notes']);
    }

    public function test_sar_export_includes_payment_ledger_and_subject_side_incidents(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create();
        $reporter = User::factory()->forTenant($tenantId)->create();
        TenantContext::setById($tenantId);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $reporter->id,
            'name' => 'SAR Ledger Org',
            'status' => 'active',
            'created_at' => now(),
        ]);
        DB::table('vol_org_transactions')->insert([
            'tenant_id' => $tenantId,
            'vol_organization_id' => $orgId,
            'user_id' => $user->id,
            'vol_log_id' => null,
            'type' => 'volunteer_payment',
            'amount' => -2.00,
            'balance_after' => -2.00,
            'description' => 'Volunteer payment for approved hours',
            'created_at' => now(),
        ]);
        // Incident ABOUT the user, reported by someone else — narrative must be withheld.
        DB::table('vol_safeguarding_incidents')->insert([
            'tenant_id' => $tenantId,
            'reported_by' => $reporter->id,
            'subject_user_id' => $user->id,
            'incident_type' => 'concern',
            'severity' => 'low',
            'description' => 'Third-party narrative that must NOT be disclosed',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new GdprService($tenantId);
        $method = new \ReflectionMethod($service, 'exportVolunteerData');
        $data = $method->invoke($service, (int) $user->id);

        // Payment ledger disclosed.
        $this->assertNotEmpty($data['volunteer_payment_ledger']);
        $this->assertSame('volunteer_payment', $data['volunteer_payment_ledger'][0]['type']);

        // Subject-side incident disclosed as facts only — role marked, no narrative.
        $this->assertNotEmpty($data['safeguarding_incidents_about_you']);
        $incident = $data['safeguarding_incidents_about_you'][0];
        $this->assertSame('subject', $incident['your_role']);
        $this->assertArrayNotHasKey('description', $incident);
        $this->assertArrayNotHasKey('resolution_notes', $incident);
        $this->assertArrayNotHasKey('reported_by', $incident);
    }
}
