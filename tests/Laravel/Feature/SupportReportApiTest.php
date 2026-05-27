<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class SupportReportApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_can_submit_support_report_with_sanitised_diagnostics(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiPost('/v2/support/reports', [
            'summary' => 'Checkout button does not respond',
            'description' => 'I tapped the checkout button three times and nothing happened.',
            'impact' => 'major',
            'page_url' => 'https://app.project-nexus.ie/hour-timebank/marketplace/42',
            'route' => '/hour-timebank/marketplace/42',
            'sentry_event_id' => '9f4f1e3b6d324be8afefb6ad8f8b31d2',
            'include_diagnostics' => true,
            'diagnostics' => [
                'console' => [
                    ['level' => 'error', 'message' => 'Unhandled checkout error for person@example.com'],
                ],
                'api' => [
                    ['method' => 'POST', 'url' => '/v2/orders', 'status' => 500, 'Authorization' => 'Bearer secret-token'],
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'report' => ['id', 'reference', 'status', 'impact', 'summary'],
            ],
        ]);

        $row = DB::table('support_reports')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Checkout button does not respond', $row->summary);
        $this->assertSame('major', $row->impact);
        $this->assertSame('open', $row->status);
        $this->assertStringStartsWith('NXR-', $row->reference);

        $diagnosticsJson = (string) $row->diagnostics;
        $this->assertStringContainsString('[filtered]', $diagnosticsJson);
        $this->assertStringNotContainsString('secret-token', $diagnosticsJson);
        $this->assertStringNotContainsString('person@example.com', $diagnosticsJson);
    }

    public function test_support_report_discards_diagnostics_without_explicit_consent(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiPost('/v2/support/reports', [
            'summary' => 'Calendar looks wrong',
            'description' => 'The calendar page is showing last month by default.',
            'impact' => 'minor',
            'include_diagnostics' => false,
            'diagnostics' => [
                'console' => [['message' => 'private diagnostics should not be stored']],
            ],
        ]);

        $response->assertCreated();

        $row = DB::table('support_reports')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->diagnostics);
    }

    public function test_support_report_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/support/reports', [
            'summary' => 'I cannot open messages',
            'description' => 'Messages never finish loading for me.',
            'impact' => 'blocked',
            'include_diagnostics' => false,
        ]);

        $response->assertUnauthorized();
    }

    public function test_report_submission_notifies_tenant_admins(): void
    {
        $admin = User::factory()->admin()->forTenant($this->testTenantId)->create([
            'email' => 'support-admin-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $mailer = new SupportReportSuccessfulEmailDispatchService();
        app()->instance(EmailDispatchService::class, $mailer);
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiPost('/v2/support/reports', [
            'summary' => 'Checkout button does not respond',
            'description' => 'I tapped the checkout button three times and nothing happened.',
            'impact' => 'blocked',
            'include_diagnostics' => false,
        ]);

        $response->assertCreated();
        $reference = $response->json('data.report.reference');
        $reportId = (int) $response->json('data.report.id');

        $this->assertCount(1, $mailer->calls);
        $this->assertSame($admin->email, $mailer->calls[0]['to']);
        $this->assertSame('support_report', $mailer->calls[0]['options']['category']);
        $this->assertSame($this->testTenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertStringContainsString((string) $reference, $mailer->calls[0]['subject']);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $admin->id,
            'type' => 'support_report',
            'link' => '/admin/support-reports?report=' . $reportId,
        ]);
    }

    public function test_admin_can_list_and_view_only_tenant_support_reports(): void
    {
        $admin = User::factory()->admin()->forTenant($this->testTenantId)->create();
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $otherTenantMember = User::factory()->forTenant($this->testTenantId + 100)->create();

        $ownReportId = DB::table('support_reports')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'reference' => 'NXR-260527-TENANT',
            'source' => 'in_app',
            'summary' => 'Messages fail to open',
            'description' => 'The messages page keeps showing a blank panel.',
            'impact' => 'major',
            'status' => 'open',
            'route' => '/messages',
            'diagnostics' => json_encode(['captured_at' => now()->toIso8601String(), 'payload' => ['console' => []]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('support_reports')->insert([
            'tenant_id' => $this->testTenantId + 100,
            'user_id' => $otherTenantMember->id,
            'reference' => 'NXR-260527-FOREIGN',
            'source' => 'in_app',
            'summary' => 'Foreign tenant report',
            'description' => 'This report belongs to another tenant.',
            'impact' => 'blocked',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $list = $this->apiGet('/v2/admin/support-reports?status=open');
        $list->assertOk();
        $list->assertJsonPath('meta.total', 1);
        $list->assertJsonPath('data.0.reference', 'NXR-260527-TENANT');

        $detail = $this->apiGet('/v2/admin/support-reports/' . $ownReportId);
        $detail->assertOk();
        $detail->assertJsonPath('data.reference', 'NXR-260527-TENANT');
        $detail->assertJsonPath('data.reporter.id', $member->id);
        $detail->assertJsonPath('data.diagnostics.payload.console', []);

        $stats = $this->apiGet('/v2/admin/support-reports/stats');
        $stats->assertOk();
        $stats->assertJsonPath('data.total', 1);
        $stats->assertJsonPath('data.open', 1);
        $stats->assertJsonPath('data.major', 1);
    }

    public function test_admin_can_update_support_report_triage_state(): void
    {
        $admin = User::factory()->admin()->forTenant($this->testTenantId)->create();
        $member = User::factory()->forTenant($this->testTenantId)->create();

        $reportId = DB::table('support_reports')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'reference' => 'NXR-260527-TRIAGE',
            'source' => 'in_app',
            'summary' => 'Calendar defaults to the wrong month',
            'description' => 'Opening the calendar shows last month first.',
            'impact' => 'minor',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiPut('/v2/admin/support-reports/' . $reportId, [
            'status' => 'triaged',
            'assigned_user_id' => $admin->id,
            'triage_notes' => 'Reproduced in Chrome on the current build.',
            'sentry_issue_url' => 'https://example.sentry.io/issues/123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'triaged');
        $response->assertJsonPath('data.assignee.id', $admin->id);
        $response->assertJsonPath('data.triage_notes', 'Reproduced in Chrome on the current build.');
        $this->assertNotNull($response->json('data.triaged_at'));

        $this->assertDatabaseHas('support_reports', [
            'id' => $reportId,
            'tenant_id' => $this->testTenantId,
            'status' => 'triaged',
            'assigned_user_id' => $admin->id,
            'sentry_issue_url' => 'https://example.sentry.io/issues/123',
        ]);
    }

    public function test_member_cannot_access_admin_support_reports(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member, ['*']);

        $this->apiGet('/v2/admin/support-reports')->assertForbidden();
    }
}

class SupportReportSuccessfulEmailDispatchService extends EmailDispatchService
{
    public array $calls = [];

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $this->calls[] = compact('to', 'subject', 'body', 'options');

        return true;
    }
}
