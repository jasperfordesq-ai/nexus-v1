<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\PilotInquiryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

/**
 * PilotInquiryServiceTest
 *
 * Strategy: PilotInquiryService only touches the `pilot_inquiries` table and
 * the `users` table (for the left-join on assigned_to).  No HTTP, no mail, no
 * queue, no Pusher.  Every test uses DatabaseTransactions so rows are rolled
 * back automatically.
 *
 * The service does NOT call any mailer, so MAIL_MAILER=array is a precaution
 * only — it has no effect on these tests.
 *
 * Coverage:
 *   - submitInquiry: persist correct fields, normalisation, fit-score, auto-qualify
 *   - submitInquiry: validation guards (missing required, invalid email)
 *   - computeFitScore: KISS, population, timeline, greenfield, modules, country
 *   - updateStage: valid transitions + timestamp columns, rejection reason, invalid stage
 *   - assignTo / updateInternalNotes: DB write verified
 *   - listInquiries: basic list + stage filter
 *   - getInquiry: tenant isolation (wrong tenant returns null)
 *   - getPipelineStats: totals + by_stage counts
 *   - isAvailable: returns true in test env (table exists)
 */
class PilotInquiryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    /** Minimum valid payload for submitInquiry */
    private const BASE_PAYLOAD = [
        'municipality_name' => 'Testwil',
        'contact_name'      => 'Hans Muster',
        'contact_email'     => 'hans@testwil.ch',
        'country'           => 'CH',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function submit(array $overrides = []): array
    {
        return PilotInquiryService::submitInquiry(
            self::TENANT_ID,
            array_merge(self::BASE_PAYLOAD, $overrides)
        );
    }

    // ─── isAvailable ─────────────────────────────────────────────────────────

    public function test_isAvailable_returns_true_when_table_exists(): void
    {
        $this->assertTrue(PilotInquiryService::isAvailable());
    }

    // ─── submitInquiry: basic persistence ────────────────────────────────────

    public function test_submitInquiry_persists_row_with_correct_required_fields(): void
    {
        $result = $this->submit();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertNotNull($row);
        $this->assertEquals(self::TENANT_ID, $row->tenant_id);
        $this->assertEquals('Testwil', $row->municipality_name);
        $this->assertEquals('Hans Muster', $row->contact_name);
        $this->assertEquals('hans@testwil.ch', $row->contact_email);
        $this->assertEquals('CH', $row->country);
    }

    public function test_submitInquiry_normalises_email_to_lowercase(): void
    {
        $result = $this->submit(['contact_email' => 'HANS@Testwil.CH']);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertEquals('hans@testwil.ch', $row->contact_email);
    }

    public function test_submitInquiry_normalises_country_to_uppercase_two_chars(): void
    {
        $result = $this->submit(['country' => 'de']);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertEquals('DE', $row->country);
    }

    public function test_submitInquiry_encodes_interest_modules_array_as_json(): void
    {
        $modules = ['wallet', 'events', 'volunteers'];
        $result  = $this->submit(['interest_modules' => $modules]);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertJson($row->interest_modules);
        $decoded = json_decode($row->interest_modules, true);
        $this->assertEquals($modules, $decoded);
    }

    public function test_submitInquiry_stores_optional_fields_when_provided(): void
    {
        $result = $this->submit([
            'region'        => 'Zürich',
            'population'    => 12000,
            'contact_phone' => '+41 44 000 0000',
            'contact_role'  => 'Mayor',
            'notes'         => 'Very interested',
            'source'        => 'referral',
        ]);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertEquals('Zürich', $row->region);
        $this->assertEquals(12000, (int) $row->population);
        $this->assertEquals('+41 44 000 0000', $row->contact_phone);
        $this->assertEquals('Mayor', $row->contact_role);
        $this->assertEquals('Very interested', $row->notes);
        $this->assertEquals('referral', $row->source);
    }

    public function test_submitInquiry_defaults_source_to_website_cta(): void
    {
        $result = $this->submit();

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertEquals('website_cta', $row->source);
    }

    // ─── submitInquiry: fit-score + auto-qualify ──────────────────────────────

    public function test_submitInquiry_auto_qualifies_when_fit_score_reaches_60(): void
    {
        // KISS(+30) + pop 5000–20000(+20) + timeline 0(+25) = 75 >= 60 → qualified
        $result = $this->submit([
            'has_kiss_cooperative' => true,
            'population'           => 10000,
            'timeline_months'      => 0,
        ]);

        $this->assertGreaterThanOrEqual(60.0, (float) $result['fit_score']);
        $this->assertEquals('qualified', $result['stage']);
    }

    public function test_submitInquiry_stays_new_when_fit_score_below_60(): void
    {
        // No KISS, tiny pop (<2000) = 5, no timeline = 0, no modules, CH = +5 → 10 + greenfield 15 = 30
        $result = $this->submit([
            'has_kiss_cooperative'      => false,
            'population'                => 500,
            'has_existing_digital_tool' => true, // removes +15 greenfield
        ]);

        $this->assertLessThan(60.0, (float) $result['fit_score']);
        $this->assertEquals('new', $result['stage']);
    }

    public function test_submitInquiry_fit_score_awards_greenfield_bonus_when_no_existing_tool(): void
    {
        // Greenfield (+15) vs having existing tool (0)
        $withTool    = $this->submit(['has_existing_digital_tool' => true,  'has_kiss_cooperative' => false, 'country' => 'DE']);
        $withoutTool = $this->submit(['has_existing_digital_tool' => false, 'has_kiss_cooperative' => false, 'country' => 'DE']);

        $this->assertGreaterThan((float) $withTool['fit_score'], (float) $withoutTool['fit_score']);
    }

    public function test_submitInquiry_fit_score_stored_in_db(): void
    {
        $result = $this->submit(['has_kiss_cooperative' => true, 'population' => 10000]);

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertGreaterThan(0, (float) $row->fit_score);
    }

    public function test_submitInquiry_fit_breakdown_stored_as_json(): void
    {
        $result = $this->submit();

        $row = DB::table('pilot_inquiries')->where('id', $result['id'])->first();
        $this->assertJson($row->fit_breakdown);
        $breakdown = json_decode($row->fit_breakdown, true);
        $this->assertArrayHasKey('kiss_cooperative', $breakdown);
        $this->assertArrayHasKey('population', $breakdown);
        $this->assertArrayHasKey('timeline', $breakdown);
        $this->assertArrayHasKey('greenfield', $breakdown);
        $this->assertArrayHasKey('interest_modules', $breakdown);
        $this->assertArrayHasKey('swiss_market', $breakdown);
    }

    // ─── submitInquiry: validation guards ────────────────────────────────────

    public function test_submitInquiry_throws_on_missing_municipality_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PilotInquiryService::submitInquiry(self::TENANT_ID, [
            'contact_name'  => 'Hans',
            'contact_email' => 'hans@example.com',
            'country'       => 'CH',
            // missing municipality_name
        ]);
    }

    public function test_submitInquiry_throws_on_missing_contact_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PilotInquiryService::submitInquiry(self::TENANT_ID, [
            'municipality_name' => 'Testwil',
            'contact_name'      => 'Hans',
            'country'           => 'CH',
            // missing contact_email
        ]);
    }

    public function test_submitInquiry_throws_on_invalid_email_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->submit(['contact_email' => 'not-an-email']);
    }

    public function test_submitInquiry_throws_on_missing_country(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PilotInquiryService::submitInquiry(self::TENANT_ID, [
            'municipality_name' => 'Testwil',
            'contact_name'      => 'Hans',
            'contact_email'     => 'hans@example.com',
            // missing country
        ]);
    }

    // ─── updateStage: transitions ─────────────────────────────────────────────

    public function test_updateStage_transitions_to_proposal_sent_and_records_timestamp(): void
    {
        $inquiry = $this->submit();
        $id      = $inquiry['id'];

        $updated = PilotInquiryService::updateStage($id, self::TENANT_ID, 'proposal_sent');

        $this->assertEquals('proposal_sent', $updated['stage']);
        $this->assertNotNull($updated['proposal_sent_at']);
    }

    public function test_updateStage_transitions_to_rejected_with_reason(): void
    {
        $inquiry = $this->submit();
        $id      = $inquiry['id'];

        $updated = PilotInquiryService::updateStage($id, self::TENANT_ID, 'rejected', 'Too small a population');

        $this->assertEquals('rejected', $updated['stage']);
        $this->assertEquals('Too small a population', $updated['rejection_reason']);
    }

    public function test_updateStage_throws_on_invalid_stage(): void
    {
        $inquiry = $this->submit();

        $this->expectException(InvalidArgumentException::class);

        PilotInquiryService::updateStage($inquiry['id'], self::TENANT_ID, 'nonsense_stage');
    }

    // ─── assignTo ─────────────────────────────────────────────────────────────

    public function test_assignTo_sets_assigned_to_column(): void
    {
        $inquiry = $this->submit();
        $userId  = DB::table('users')
            ->where('tenant_id', self::TENANT_ID)
            ->where('status', 'active')
            ->value('id');

        if ($userId === null) {
            $this->markTestSkipped('No active user in tenant 2 to use as assignee.');
        }

        PilotInquiryService::assignTo($inquiry['id'], self::TENANT_ID, (int) $userId);

        $row = DB::table('pilot_inquiries')->where('id', $inquiry['id'])->first();
        $this->assertEquals($userId, (int) $row->assigned_to);
    }

    // ─── updateInternalNotes ──────────────────────────────────────────────────

    public function test_updateInternalNotes_persists_notes(): void
    {
        $inquiry = $this->submit();

        PilotInquiryService::updateInternalNotes($inquiry['id'], self::TENANT_ID, 'Internal memo for sales team.');

        $row = DB::table('pilot_inquiries')->where('id', $inquiry['id'])->first();
        $this->assertEquals('Internal memo for sales team.', $row->internal_notes);
    }

    // ─── listInquiries ────────────────────────────────────────────────────────

    public function test_listInquiries_returns_submitted_inquiry(): void
    {
        $this->submit(['municipality_name' => 'ListTest']);

        $list = PilotInquiryService::listInquiries(self::TENANT_ID);

        $found = array_filter($list, fn ($r) => $r['municipality_name'] === 'ListTest');
        $this->assertNotEmpty($found);
    }

    public function test_listInquiries_filters_by_stage(): void
    {
        // Submit a high-score inquiry that becomes 'qualified'
        $this->submit([
            'municipality_name'    => 'FilterQualified',
            'has_kiss_cooperative' => true,
            'population'           => 10000,
            'timeline_months'      => 0,
        ]);

        $qualified = PilotInquiryService::listInquiries(self::TENANT_ID, 'qualified');

        foreach ($qualified as $row) {
            $this->assertEquals('qualified', $row['stage']);
        }

        // At least our seeded 'qualified' row should appear
        $names = array_column($qualified, 'municipality_name');
        $this->assertContains('FilterQualified', $names);
    }

    // ─── getInquiry: tenant isolation ────────────────────────────────────────

    public function test_getInquiry_returns_null_for_wrong_tenant(): void
    {
        $inquiry = $this->submit();

        $result = PilotInquiryService::getInquiry($inquiry['id'], 9999);

        $this->assertNull($result);
    }

    // ─── getPipelineStats ────────────────────────────────────────────────────

    public function test_getPipelineStats_reflects_submitted_inquiry(): void
    {
        $this->submit();

        $stats = PilotInquiryService::getPipelineStats(self::TENANT_ID);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_stage', $stats);
        $this->assertArrayHasKey('by_country', $stats);
        $this->assertArrayHasKey('avg_fit_score', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['total']);
    }
}
