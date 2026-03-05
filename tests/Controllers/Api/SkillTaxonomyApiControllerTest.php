<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class SkillTaxonomyApiControllerTest extends ApiTestCase
{
    public function testListSkills(): void
    {
        $response = $this->get('/api/v2/skills', [], [],
            'Nexus\Controllers\Api\SkillTaxonomyApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testSearchSkills(): void
    {
        $response = $this->get('/api/v2/skills/search', ['q' => 'garden'], [],
            'Nexus\Controllers\Api\SkillTaxonomyApiController@search');

        $this->assertIsArray($response);
    }

    public function testGetCategories(): void
    {
        $response = $this->get('/api/v2/skills/categories', [], [],
            'Nexus\Controllers\Api\SkillTaxonomyApiController@categories');

        $this->assertIsArray($response);
    }
}
