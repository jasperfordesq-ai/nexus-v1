<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class ResourceCategoriesApiControllerTest extends ApiTestCase
{
    public function testListCategories(): void
    {
        $response = $this->get('/api/v2/resources/categories', [], [],
            'Nexus\Controllers\Api\ResourceCategoriesApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testCreateCategory(): void
    {
        $response = $this->post('/api/v2/resources/categories', [
            'name' => 'Test Category',
        ], [], 'Nexus\Controllers\Api\ResourceCategoriesApiController@store');

        $this->assertIsArray($response);
    }

    public function testDeleteCategory(): void
    {
        $response = $this->delete('/api/v2/resources/categories/999', [], [],
            'Nexus\Controllers\Api\ResourceCategoriesApiController@destroy');

        $this->assertIsArray($response);
    }
}
