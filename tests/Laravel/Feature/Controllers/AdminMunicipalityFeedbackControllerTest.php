<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminMunicipalityFeedbackControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && ! empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    private function requireFeedbackTable(): void
    {
        if (! Schema::hasTable('caring_municipality_feedback')) {
            $this->markTestSkipped('Municipality feedback table is not present in the test database.');
        }
    }

    public function test_admin_can_triage_resolve_and_close_member_feedback(): void
    {
        $this->requireFeedbackTable();
        $this->setCaringCommunityFeature(true);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        $assignee = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();

        Sanctum::actingAs($member);
        $submit = $this->apiPost('/v2/caring-community/feedback', [
            'category' => 'issue_report',
            'subject' => 'Unsafe crossing near the care centre',
            'body' => 'Residents need a safer crossing after evening activities.',
            'sentiment_tag' => 'concerned',
        ]);
        $submit->assertStatus(201);
        $feedbackId = (int) $submit->json('data.id');

        Sanctum::actingAs($admin);
        $triage = $this->apiPut("/v2/admin/caring-community/feedback/{$feedbackId}/triage", [
            'status' => 'triaging',
            'assigned_user_id' => $assignee->id,
            'assigned_role' => 'municipality_announcer',
            'triage_notes' => 'Route to municipal transport liaison.',
        ]);

        $triage->assertStatus(200);
        $triage->assertJsonPath('data.status', 'triaging');
        $triage->assertJsonPath('data.assigned_user_id', $assignee->id);
        $triage->assertJsonPath('data.assigned_role', 'municipality_announcer');

        $resolve = $this->apiPost("/v2/admin/caring-community/feedback/{$feedbackId}/resolve", [
            'resolution_notes' => 'Transport liaison opened a ticket.',
        ]);
        $resolve->assertStatus(200);
        $resolve->assertJsonPath('data.status', 'resolved');
        $resolve->assertJsonPath('data.resolution_notes', 'Transport liaison opened a ticket.');

        $close = $this->apiPost("/v2/admin/caring-community/feedback/{$feedbackId}/close");
        $close->assertStatus(200);
        $close->assertJsonPath('data.status', 'closed');
    }

    public function test_feedback_csv_export_redacts_anonymous_submitter_and_sanitizes_formula_cells(): void
    {
        $this->requireFeedbackTable();
        $this->setCaringCommunityFeature(true);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();

        Sanctum::actingAs($member);
        $submit = $this->apiPost('/v2/caring-community/feedback', [
            'category' => 'idea',
            'subject' => '=IMPORTXML("https://example.test")',
            'body' => '+SUM(1,1)',
            'sentiment_tag' => 'positive',
            'is_anonymous' => true,
            'is_public' => true,
        ]);
        $submit->assertStatus(201);

        Sanctum::actingAs($admin);
        $export = $this->apiGet('/v2/admin/caring-community/feedback/export.csv?category=idea');

        $export->assertStatus(200);
        $this->assertStringContainsString('attachment; filename="municipality-feedback-export.csv"', (string) $export->headers->get('Content-Disposition'));

        $csv = $export->getContent();
        $this->assertIsString($csv);
        $this->assertStringContainsString('(anonymous)', $csv);
        $this->assertStringNotContainsString(',' . $member->id . ',', $csv);
        $this->assertStringContainsString("'=IMPORTXML(\"\"https://example.test\"\")", $csv);
        $this->assertStringContainsString("'+SUM(1,1)", $csv);
    }
}
