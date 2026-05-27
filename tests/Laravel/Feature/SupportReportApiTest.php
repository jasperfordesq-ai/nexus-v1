<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\User;
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
}
