<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ContentModerationService;
use App\Models\ContentModerationQueue;
use Illuminate\Support\Facades\DB;
use Mockery;

class ContentModerationServiceTest extends TestCase
{
    public function test_constants_defined(): void
    {
        $this->assertContains('post', ContentModerationService::CONTENT_TYPES);
        $this->assertContains('listing', ContentModerationService::CONTENT_TYPES);
        $this->assertSame('pending', ContentModerationService::STATUS_PENDING);
        $this->assertSame('approved', ContentModerationService::STATUS_APPROVED);
        $this->assertSame('rejected', ContentModerationService::STATUS_REJECTED);
        $this->assertSame('flagged', ContentModerationService::STATUS_FLAGGED);
    }

    public function test_approve_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_approve_returns_false_when_not_pending(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_reject_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_review_returns_error_for_invalid_decision(): void
    {
        $result = ContentModerationService::review(1, 2, 10, 'invalid');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid decision', $result['message']);
    }

    public function test_review_returns_error_when_item_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_review_returns_error_when_already_reviewed(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_review_requires_rejection_reason(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getStats_returns_expected_keys(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateSettings_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);

        $result = ContentModerationService::updateSettings(2, ['enabled' => true, 'auto_filter' => true]);
        $this->assertTrue($result);
    }
}
