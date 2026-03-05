<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class IdeationChallengesApiControllerTest extends ApiTestCase
{
    public function testListChallenges(): void
    {
        $response = $this->get('/api/v2/ideation-challenges', [], [],
            'Nexus\Controllers\Api\IdeationChallengesApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testShowChallenge(): void
    {
        $response = $this->get('/api/v2/ideation-challenges/1', [], [],
            'Nexus\Controllers\Api\IdeationChallengesApiController@show');

        $this->assertIsArray($response);
    }

    public function testCreateChallenge(): void
    {
        $response = $this->post('/api/v2/ideation-challenges', [
            'title'       => 'Test Challenge',
            'description' => 'A test ideation challenge',
            'status'      => 'draft',
        ], [], 'Nexus\Controllers\Api\IdeationChallengesApiController@store');

        $this->assertIsArray($response);
    }

    public function testListIdeas(): void
    {
        $response = $this->get('/api/v2/ideation-challenges/1/ideas', [], [],
            'Nexus\Controllers\Api\IdeationChallengesApiController@ideas');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testSubmitIdea(): void
    {
        $response = $this->post('/api/v2/ideation-challenges/1/ideas', [
            'title'       => 'My Idea',
            'description' => 'A great idea for the community',
        ], [], 'Nexus\Controllers\Api\IdeationChallengesApiController@submitIdea');

        $this->assertIsArray($response);
    }

    public function testToggleFavorite(): void
    {
        $response = $this->post('/api/v2/ideation-challenges/1/favorite', [], [],
            'Nexus\Controllers\Api\IdeationChallengesApiController@toggleFavorite');

        $this->assertIsArray($response);
    }
}
