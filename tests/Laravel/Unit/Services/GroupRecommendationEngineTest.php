<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupRecommendationEngine;
use Tests\Laravel\TestCase;

final class GroupRecommendationEngineTest extends TestCase
{
    private GroupRecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new GroupRecommendationEngine();
    }

    public function test_unknown_tenant_user_does_not_receive_cold_start_results(): void
    {
        $this->assertSame([], $this->engine->getRecommendations(PHP_INT_MAX));
    }

    public function test_invalid_interaction_is_rejected(): void
    {
        $this->assertFalse($this->engine->trackInteraction(PHP_INT_MAX, PHP_INT_MAX, 'invalid'));
    }

    public function test_unknown_group_reference_is_rejected(): void
    {
        $this->assertFalse($this->engine->canReferenceGroup(PHP_INT_MAX, PHP_INT_MAX));
    }
}
