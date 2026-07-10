<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class EngagementRecognitionServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\EngagementRecognitionService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\EngagementRecognitionService::class);
        foreach (['checkMonthlyEngagement', 'getEngagementHistory', 'getSeasonalRecognition', 'updateSeasonalRecognition'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    public function test_getEngagementHistory_propagates_query_errors(): void
    {
        DB::shouldReceive('table')->andThrow(new \RuntimeException('fail'));

        $this->expectException(\RuntimeException::class);
        \App\Services\EngagementRecognitionService::getEngagementHistory(1, 1, 3);
    }
}
