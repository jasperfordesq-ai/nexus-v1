<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class EmailAdminApiControllerTest extends ApiTestCase
{
    public function testGetStats(): void
    {
        $response = $this->get('/api/v2/admin/email/stats', [], [],
            'Nexus\Controllers\Api\EmailAdminApiController@getStats');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetRecentSends(): void
    {
        $response = $this->get('/api/v2/admin/email/recent', [], [],
            'Nexus\Controllers\Api\EmailAdminApiController@getRecent');

        $this->assertIsArray($response);
    }
}
