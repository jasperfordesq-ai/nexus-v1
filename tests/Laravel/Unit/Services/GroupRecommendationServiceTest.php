<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupRecommendationService;
use Tests\Laravel\TestCase;

final class GroupRecommendationServiceTest extends TestCase
{
    private GroupRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupRecommendationService();
    }

    public function test_unknown_user_has_no_recommendations(): void
    {
        $this->assertSame([], $this->service->getRecommendations(PHP_INT_MAX));
    }

    public function test_invalid_tracking_action_fails_without_writing(): void
    {
        $this->assertFalse($this->service->track(PHP_INT_MAX, PHP_INT_MAX, 'invalid'));
    }

    public function test_unknown_similar_source_is_concealed(): void
    {
        $this->assertSame([], $this->service->similar(PHP_INT_MAX));
    }
}
