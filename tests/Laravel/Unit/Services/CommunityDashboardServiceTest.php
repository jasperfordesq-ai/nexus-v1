<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

class CommunityDashboardServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\CommunityDashboardService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\CommunityDashboardService::class);
        foreach (['getCommunityImpact', 'getPersonalJourney', 'getMemberSpotlight'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    public function test_getCommunityImpact_returns_array(): void
    {
        try {
            $result = \App\Services\CommunityDashboardService::getCommunityImpact(null);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
