<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class PagesPublicApiControllerTest extends ApiTestCase
{
    public function testGetPage(): void
    {
        $response = $this->get('/api/v2/pages/about', [], [],
            'Nexus\Controllers\Api\PagesPublicApiController@show');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testListPages(): void
    {
        $response = $this->get('/api/v2/pages', [], [],
            'Nexus\Controllers\Api\PagesPublicApiController@index');

        $this->assertIsArray($response);
    }
}
