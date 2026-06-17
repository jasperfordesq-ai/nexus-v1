<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Models\VolExpense;
use App\Models\VolOrganization;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for VolunteerExpenseController.
 *
 * Covers:
 *  - GET /v2/volunteering/expenses — my expenses (auth, feature gate)
 *  - GET /v2/admin/volunteering/expenses — admin-only listing
 *  - Feature flag disabled -> 403
 *  - reviewExpense validation of status enum
 */
class VolunteerExpenseControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function enableVolunteeringFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['volunteering'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }

    public function test_my_expenses_requires_auth(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_my_expenses_returns_data_for_authenticated_user(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_my_expenses_returns_403_when_feature_disabled(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['volunteering' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/volunteering/expenses');

        $response->assertStatus(403);
    }

    public function test_admin_expenses_rejects_regular_member(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/volunteering/expenses');

        $response->assertStatus(403);
    }

    /**
     * 2026-06-17 audit: the admin expenses endpoint returned only the paged
     * list, so the React admin summary cards (Total Submitted / Pending /
     * Approved / Paid) were permanently zero. The payload must carry a stats
     * block aggregated over the full tenant-scoped set.
     */
    public function test_admin_expenses_includes_full_set_stats_summary(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create();
        $organization = VolOrganization::factory()->forTenant($this->testTenantId)->create();

        VolExpense::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $volunteer->id,
            'organization_id' => $organization->id,
            'opportunity_id' => null,
            'status' => 'pending',
            'amount' => 30,
            'submitted_at' => now(),
        ]);
        VolExpense::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $volunteer->id,
            'organization_id' => $organization->id,
            'opportunity_id' => null,
            'status' => 'paid',
            'amount' => 20,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/volunteering/expenses');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'items',
                'stats' => ['total_submitted', 'pending_review', 'approved_total', 'paid_total'],
            ],
        ]);
        $stats = $response->json('data.stats');
        $this->assertEqualsWithDelta(50.0, (float) $stats['total_submitted'], 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $stats['pending_review'], 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $stats['paid_total'], 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $stats['approved_total'], 0.001);
    }

    public function test_review_expense_validates_status(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/volunteering/expenses/1', [
            'status' => 'not_a_valid_status',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_expense_export_neutralizes_spreadsheet_formula_cells(): void
    {
        $this->enableVolunteeringFeature($this->testTenantId);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $volunteer = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => '=IMPORTXML("https://example.test")',
            'last_name' => 'Member',
            'email' => 'volunteer@example.test',
        ]);
        $organization = VolOrganization::factory()->forTenant($this->testTenantId)->create([
            'name' => '+SUM(1,1)',
        ]);

        VolExpense::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $volunteer->id,
            'organization_id' => $organization->id,
            'opportunity_id' => null,
            'status' => 'pending',
            'description' => '@cmd',
            'review_notes' => "\t=hidden",
            'payment_reference' => '-10+20',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/volunteering/expenses/export');

        $response->assertStatus(200);
        $csv = $response->getContent();
        $lines = array_values(array_filter(explode("\n", trim((string) $csv))));
        $rows = array_map('str_getcsv', $lines);
        $headers = array_shift($rows);
        $exported = null;
        foreach ($rows as $row) {
            $indexed = array_combine($headers, $row);
            if (($indexed['email'] ?? '') === 'volunteer@example.test') {
                $exported = $indexed;
                break;
            }
        }

        $this->assertNotNull($exported);
        $this->assertSame('\'=IMPORTXML("https://example.test")', $exported['first_name']);
        $this->assertSame('\'+SUM(1,1)', $exported['organization_name']);
        $this->assertSame('\'@cmd', $exported['description']);
        $this->assertSame("'\t=hidden", $exported['review_notes']);
        $this->assertSame('\'-10+20', $exported['payment_reference']);
    }
}
