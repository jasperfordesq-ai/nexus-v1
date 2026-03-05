<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class EndorsementApiControllerTest extends ApiTestCase
{
    public function testEndorseMember(): void
    {
        $response = $this->post('/api/v2/members/1/endorse', [
            'skill_name' => 'Web Development',
            'comment' => 'Great work',
        ], [], 'Nexus\Controllers\Api\EndorsementApiController@endorse');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testRemoveEndorsement(): void
    {
        $response = $this->delete('/api/v2/members/1/endorse', [
            'skill_name' => 'Web Development',
        ], [], 'Nexus\Controllers\Api\EndorsementApiController@removeEndorsement');

        $this->assertIsArray($response);
    }

    public function testGetEndorsements(): void
    {
        $response = $this->get('/api/v2/members/1/endorsements', [], [],
            'Nexus\Controllers\Api\EndorsementApiController@getEndorsements');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetTopEndorsed(): void
    {
        $response = $this->get('/api/v2/members/top-endorsed', [], [],
            'Nexus\Controllers\Api\EndorsementApiController@getTopEndorsed');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }
}
