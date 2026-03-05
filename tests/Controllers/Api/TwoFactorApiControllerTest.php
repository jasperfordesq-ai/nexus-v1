<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class TwoFactorApiControllerTest extends ApiTestCase
{
    public function testGetStatus(): void
    {
        $response = $this->get('/api/v2/auth/2fa/status', [], [],
            'Nexus\Controllers\Api\TwoFactorApiController@getStatus');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testEnable(): void
    {
        $response = $this->post('/api/v2/auth/2fa/enable', [], [],
            'Nexus\Controllers\Api\TwoFactorApiController@enable');

        $this->assertIsArray($response);
    }

    public function testDisable(): void
    {
        $response = $this->post('/api/v2/auth/2fa/disable', [
            'password' => 'test_password',
        ], [], 'Nexus\Controllers\Api\TwoFactorApiController@disable');

        $this->assertIsArray($response);
    }
}
