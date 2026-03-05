<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MatchPreferencesApiControllerTest extends ApiTestCase
{
    public function testGetPreferences(): void
    {
        $response = $this->get('/api/v2/matches/preferences', [], [],
            'Nexus\Controllers\Api\MatchPreferencesApiController@getPreferences');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUpdatePreferences(): void
    {
        $response = $this->put('/api/v2/matches/preferences', [
            'max_distance_km' => 50,
            'categories'      => ['Gardening', 'IT Support'],
        ], [], 'Nexus\Controllers\Api\MatchPreferencesApiController@updatePreferences');

        $this->assertIsArray($response);
    }
}
