<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class MarketplaceReportAppealWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
    }

    public function test_affected_seller_can_appeal_and_successful_appeal_restores_listing_state(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'en']);
        $reporter = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'en']);
        $listingId = $this->createListing((int) $seller->id);
        $reportId = $this->createReport($listingId, (int) $reporter->id, 'under_review');

        MarketplaceReportService::resolveReport($reportId, 1, [
            'action_taken' => 'listing_removed',
            'resolution_reason' => 'Initial evidence supported removal.',
        ]);

        $sellerView = MarketplaceReportService::getReportForUser($reportId, (int) $seller->id);
        $this->assertSame('seller', $sellerView['viewer_role']);
        $this->assertTrue($sellerView['can_appeal']);
        $this->assertNull($sellerView['description']);
        $this->assertNull($sellerView['evidence_urls']);
        $this->assertArrayNotHasKey('reporter', $sellerView);

        MarketplaceReportService::appealReport(
            $reportId,
            (int) $seller->id,
            'The supporting evidence identifies a different product and seller.',
        );
        MarketplaceReportService::resolveAppeal($reportId, 1, [
            'action_taken' => 'none',
            'resolution_reason' => 'The appeal evidence shows the original decision was mistaken.',
        ]);

        $this->assertDatabaseHas('marketplace_reports', [
            'id' => $reportId,
            'status' => 'appeal_resolved',
            'action_taken' => 'none',
            'appealed_by' => $seller->id,
        ]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'status' => 'active',
            'moderation_status' => 'approved',
        ]);
    }

    public function test_reporter_can_appeal_no_action_but_unrelated_member_cannot_read_report(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'en']);
        $reporter = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'en']);
        $outsider = User::factory()->forTenant($this->testTenantId)->create(['preferred_language' => 'en']);
        $reportId = $this->createReport($this->createListing((int) $seller->id), (int) $reporter->id, 'no_action');

        $reporterView = MarketplaceReportService::getReportForUser($reportId, (int) $reporter->id);
        $this->assertTrue($reporterView['can_appeal']);
        $this->assertSame('Potentially unsafe electrical item.', $reporterView['description']);

        MarketplaceReportService::appealReport(
            $reportId,
            (int) $reporter->id,
            'The submitted safety certificate remains relevant to this exact item.',
        );
        $this->assertDatabaseHas('marketplace_reports', [
            'id' => $reportId,
            'status' => 'appealed',
            'appealed_by' => $reporter->id,
        ]);

        $this->expectException(AuthorizationException::class);
        MarketplaceReportService::getReportForUser($reportId, (int) $outsider->id);
    }

    private function createListing(int $sellerId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $sellerId,
            'title' => 'Appealable listing',
            'description' => 'Listing description',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createReport(int $listingId, int $reporterId, string $status): int
    {
        return (int) DB::table('marketplace_reports')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'reporter_id' => $reporterId,
            'reason' => 'unsafe',
            'description' => 'Potentially unsafe electrical item.',
            'evidence_urls' => json_encode(['https://example.test/evidence']),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
