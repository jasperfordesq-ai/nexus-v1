<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class JobVacanciesApiControllerTest extends ApiTestCase
{
    public function testListJobs(): void
    {
        $response = $this->get('/api/v2/jobs', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@index');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testShowJob(): void
    {
        $response = $this->get('/api/v2/jobs/1', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@show');

        $this->assertIsArray($response);
    }

    public function testCreateJob(): void
    {
        $response = $this->post('/api/v2/jobs', [
            'title'       => 'Community Coordinator',
            'description' => 'Help coordinate community events',
            'type'        => 'volunteer',
            'status'      => 'open',
        ], [], 'Nexus\Controllers\Api\JobVacanciesApiController@store');

        $this->assertIsArray($response);
    }

    public function testApplyToJob(): void
    {
        $response = $this->post('/api/v2/jobs/1/apply', [
            'cover_letter' => 'I am interested in this opportunity.',
        ], [], 'Nexus\Controllers\Api\JobVacanciesApiController@apply');

        $this->assertIsArray($response);
    }

    public function testMyApplications(): void
    {
        $response = $this->get('/api/v2/jobs/my-applications', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@myApplications');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testSaveJob(): void
    {
        $response = $this->post('/api/v2/jobs/1/save', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@saveJob');

        $this->assertIsArray($response);
    }

    public function testSavedJobs(): void
    {
        $response = $this->get('/api/v2/jobs/saved', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@savedJobs');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testMatchPercentage(): void
    {
        $response = $this->get('/api/v2/jobs/1/match', [], [],
            'Nexus\Controllers\Api\JobVacanciesApiController@matchPercentage');

        $this->assertIsArray($response);
    }
}
