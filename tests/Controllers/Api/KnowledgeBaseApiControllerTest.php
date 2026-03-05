<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class KnowledgeBaseApiControllerTest extends ApiTestCase
{
    public function testListArticles(): void
    {
        $response = $this->get('/api/v2/knowledge-base', [], [],
            'Nexus\Controllers\Api\KnowledgeBaseApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testShowArticle(): void
    {
        $response = $this->get('/api/v2/knowledge-base/1', [], [],
            'Nexus\Controllers\Api\KnowledgeBaseApiController@show');

        $this->assertIsArray($response);
    }

    public function testSearchArticles(): void
    {
        $response = $this->get('/api/v2/knowledge-base/search', ['q' => 'timebanking'], [],
            'Nexus\Controllers\Api\KnowledgeBaseApiController@search');

        $this->assertIsArray($response);
    }
}
