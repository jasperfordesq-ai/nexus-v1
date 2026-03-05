<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MemberActivityApiControllerTest extends ApiTestCase
{
    public function testGetMyActivity(): void
    {
        $response = $this->get('/api/v2/users/me/activity', [], [],
            'Nexus\Controllers\Api\MemberActivityApiController@getMyActivity');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetUserActivity(): void
    {
        $response = $this->get('/api/v2/users/1/activity', [], [],
            'Nexus\Controllers\Api\MemberActivityApiController@getUserActivity');

        $this->assertIsArray($response);
    }
}
