<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MemberVerificationBadgeApiControllerTest extends ApiTestCase
{
    public function testGetBadgeStatus(): void
    {
        $response = $this->get('/api/v2/users/me/verification-badge', [], [],
            'Nexus\Controllers\Api\MemberVerificationBadgeApiController@getStatus');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testRequestVerification(): void
    {
        $response = $this->post('/api/v2/users/me/verification-badge', [], [],
            'Nexus\Controllers\Api\MemberVerificationBadgeApiController@request');

        $this->assertIsArray($response);
    }
}
