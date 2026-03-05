<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MatchingApiControllerTest extends ApiTestCase
{
    public function testGetMatches(): void
    {
        $response = $this->get('/api/v2/matches', [], [],
            'Nexus\Controllers\Api\MatchingApiController@getMatches');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetMatchDetail(): void
    {
        $response = $this->get('/api/v2/matches/1', [], [],
            'Nexus\Controllers\Api\MatchingApiController@getMatch');

        $this->assertIsArray($response);
    }

    public function testDismissMatch(): void
    {
        $response = $this->post('/api/v2/matches/1/dismiss', [], [],
            'Nexus\Controllers\Api\MatchingApiController@dismiss');

        $this->assertIsArray($response);
    }
}
