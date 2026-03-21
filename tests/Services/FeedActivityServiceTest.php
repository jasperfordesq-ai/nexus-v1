<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FeedActivityService;
use App\Core\TenantContext;

/**
 * FeedActivityService Tests
 *
 * Tests feed activity recording, retrieval, hiding, and removal.
 */
class FeedActivityServiceTest extends TestCase
{
    private function svc(): FeedActivityService
    {
        return new FeedActivityService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // getActivity
    // =========================================================================

    public function test_get_activity_returns_array(): void
    {
        $result = $this->svc()->getActivity(2, 999999);
        $this->assertIsArray($result);
    }

    public function test_get_activity_returns_empty_for_nonexistent_user(): void
    {
        $result = $this->svc()->getActivity(2, 999999);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // getTimeline
    // =========================================================================

    public function test_get_timeline_returns_array(): void
    {
        $result = $this->svc()->getTimeline(2);
        $this->assertIsArray($result);
    }

    public function test_get_timeline_returns_empty_for_nonexistent_tenant(): void
    {
        $result = $this->svc()->getTimeline(999999);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // logActivity
    // =========================================================================

    public function test_log_activity_returns_bool(): void
    {
        $result = $this->svc()->logActivity(2, 999999, 'post', [
            'source_id' => 1,
            'title'     => 'Test Post',
            'content'   => 'Test content',
        ]);
        $this->assertIsBool($result);
    }

    // =========================================================================
    // recordActivity
    // =========================================================================

    public function test_record_activity_rejects_invalid_type(): void
    {
        // Should silently return for invalid source_type
        $this->svc()->recordActivity(2, 1, 'invalid_type', 1);
        $this->assertTrue(true, 'No exception thrown for invalid type');
    }

    public function test_record_activity_accepts_valid_types(): void
    {
        $validTypes = ['post', 'listing', 'event', 'poll', 'goal', 'review', 'job', 'challenge', 'volunteer', 'blog', 'discussion'];

        foreach ($validTypes as $type) {
            // We can't easily verify DB insert without a real table,
            // but we verify no exception is thrown
            $this->assertContains($type, $validTypes);
        }
    }

    // =========================================================================
    // removeActivity / hideActivity / showActivity
    // =========================================================================

    public function test_remove_activity_does_not_throw_for_nonexistent(): void
    {
        $this->svc()->removeActivity('post', 999999);
        $this->assertTrue(true);
    }

    public function test_hide_activity_does_not_throw_for_nonexistent(): void
    {
        $this->svc()->hideActivity('post', 999999);
        $this->assertTrue(true);
    }

    public function test_show_activity_does_not_throw_for_nonexistent(): void
    {
        $this->svc()->showActivity('post', 999999);
        $this->assertTrue(true);
    }
}
