<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class PilotInquiryControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function requirePilotInquiryTable(): void
    {
        if (! Schema::hasTable('pilot_inquiries')) {
            $this->markTestSkipped('Pilot inquiries table is not present in the test database.');
        }
    }

    public function test_public_pilot_inquiry_scores_and_auto_qualifies_strong_fit(): void
    {
        $this->requirePilotInquiryTable();

        $response = $this->apiPost('/v2/pilot-inquiry', [
            'municipality_name' => 'Gemeinde Testwil',
            'region' => 'Zurich',
            'country' => 'CH',
            'population' => 12000,
            'has_kiss_cooperative' => true,
            'interest_modules' => ['time_banking', 'caring_community', 'municipal_announcements'],
            'has_existing_digital_tool' => false,
            'timeline_months' => 6,
            'budget_indication' => '10k_25k',
            'contact_name' => 'Mara Gemeinde',
            'contact_email' => 'mara@example.test',
            'contact_role' => 'Municipal coordinator',
            'source' => 'website_cta',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.stage', 'qualified');
        $this->assertGreaterThanOrEqual(60, (float) $response->json('data.fit_score'));

        $this->assertDatabaseHas('pilot_inquiries', [
            'tenant_id' => $this->testTenantId,
            'municipality_name' => 'Gemeinde Testwil',
            'contact_email' => 'mara@example.test',
            'stage' => 'qualified',
        ]);
    }

    public function test_admin_can_advance_pilot_inquiry_stage(): void
    {
        $this->requirePilotInquiryTable();

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $id = DB::table('pilot_inquiries')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'municipality_name' => 'Gemeinde Followup',
            'country' => 'CH',
            'contact_name' => 'Ari Admin',
            'contact_email' => 'ari@example.test',
            'has_kiss_cooperative' => true,
            'has_existing_digital_tool' => false,
            'fit_score' => 75,
            'fit_breakdown' => json_encode(['kiss_cooperative' => 30]),
            'stage' => 'qualified',
            'source' => 'website_cta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost("/v2/admin/pilot-inquiries/{$id}/stage", [
            'stage' => 'proposal_sent',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.stage', 'proposal_sent');
        $this->assertDatabaseHas('pilot_inquiries', [
            'id' => $id,
            'tenant_id' => $this->testTenantId,
            'stage' => 'proposal_sent',
        ]);
    }
}
