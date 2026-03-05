<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MetricsApiControllerTest extends ApiTestCase
{
    public function testGetMetrics(): void
    {
        $response = $this->get('/api/v2/metrics', [], [],
            'Nexus\Controllers\Api\MetricsApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetCommunityMetrics(): void
    {
        $response = $this->get('/api/v2/metrics/community', [], [],
            'Nexus\Controllers\Api\MetricsApiController@community');

        $this->assertIsArray($response);
    }
}
