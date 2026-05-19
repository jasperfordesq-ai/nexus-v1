<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceReport;
use App\Services\MarketplaceReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketplaceReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_createReport_throws_when_listing_not_found(): void
    {
        TenantContext::setById($this->testTenantId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Marketplace listing not found.');

        MarketplaceReportService::createReport(1, 999, [
            'reason' => 'spam',
            'description' => 'test',
        ]);
    }

    public function test_createReport_throws_on_duplicate_active_report(): void
    {
        TenantContext::setById($this->testTenantId);

        $sellerId = DB::table('users')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Marketplace Seller',
            'email' => 'marketplace-seller@example.test',
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reporterId = DB::table('users')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Marketplace Reporter',
            'email' => 'marketplace-reporter@example.test',
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listingId = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $sellerId,
            'title' => 'Audit Listing',
            'description' => 'Audit listing description',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_reports')->insert([
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'reporter_id' => $reporterId,
            'reason' => 'other',
            'description' => 'Existing active report',
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You already have an active report for this listing.');

        MarketplaceReportService::createReport($reporterId, $listingId, [
            'reason' => 'other',
            'description' => 'test',
        ]);
    }

    public function test_acknowledgeReport_throws_when_status_invalid(): void
    {
        $report = new \stdClass();
        $report->status = 'resolved';  // not acknowledgeable

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('findOrFail')->with(123)->andReturn($report);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Report cannot be acknowledged in its current state.');

        MarketplaceReportService::acknowledgeReport(123, 5);
    }

    public function test_resolveReport_throws_when_status_not_pending(): void
    {
        $report = new \stdClass();
        $report->status = 'appeal_resolved';

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('findOrFail')->with(123)->andReturn($report);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Report cannot be resolved in its current state.');

        MarketplaceReportService::resolveReport(123, 5, [
            'action_taken' => 'none',
            'resolution_reason' => 'ok',
        ]);
    }

    public function test_appealReport_throws_when_not_original_reporter(): void
    {
        $report = new \stdClass();
        $report->reporter_id = 42;
        $report->status = 'action_taken';

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('findOrFail')->with(7)->andReturn($report);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the original reporter can appeal.');

        MarketplaceReportService::appealReport(7, 99, 'I disagree');
    }

    public function test_appealReport_throws_when_not_resolved_yet(): void
    {
        $report = new \stdClass();
        $report->reporter_id = 42;
        $report->status = 'under_review';

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('findOrFail')->with(7)->andReturn($report);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Report cannot be appealed in its current state.');

        MarketplaceReportService::appealReport(7, 42, 'appeal');
    }

    public function test_resolveAppeal_throws_when_not_appealed(): void
    {
        $report = new \stdClass();
        $report->status = 'action_taken';

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('findOrFail')->with(7)->andReturn($report);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Report is not in appealed state.');

        MarketplaceReportService::resolveAppeal(7, 5, [
            'action_taken' => 'none',
            'resolution_reason' => 'appeal rejected',
        ]);
    }
}
