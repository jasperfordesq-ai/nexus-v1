<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\MarketplaceListing;
use App\Models\MarketplaceReport;
use App\Services\MarketplaceReportService;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketplaceReportServiceTest extends TestCase
{
    public function test_createReport_throws_when_listing_not_found(): void
    {
        $listingMock = Mockery::mock('alias:' . MarketplaceListing::class);
        $listingMock->shouldReceive('find')->with(999)->andReturnNull();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Marketplace listing not found.');

        MarketplaceReportService::createReport(1, 999, [
            'reason' => 'spam',
            'description' => 'test',
        ]);
    }

    public function test_createReport_throws_on_duplicate_active_report(): void
    {
        $listingMock = Mockery::mock('alias:' . MarketplaceListing::class);
        $listingMock->shouldReceive('find')->with(10)->andReturn(new \stdClass());

        $existingReport = new \stdClass();

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($existingReport);

        $reportMock = Mockery::mock('alias:' . MarketplaceReport::class);
        $reportMock->shouldReceive('where')->andReturn($builder);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You already have an active report for this listing.');

        MarketplaceReportService::createReport(1, 10, [
            'reason' => 'spam',
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
