<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class MemberAvailabilityApiControllerTest extends ApiTestCase
{
    public function testGetMyAvailability(): void
    {
        $response = $this->get('/api/v2/users/me/availability', [], [],
            'Nexus\Controllers\Api\MemberAvailabilityApiController@getMyAvailability');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetUserAvailability(): void
    {
        $response = $this->get('/api/v2/users/1/availability', [], [],
            'Nexus\Controllers\Api\MemberAvailabilityApiController@getUserAvailability');

        $this->assertIsArray($response);
    }

    public function testSetBulkAvailability(): void
    {
        $response = $this->put('/api/v2/users/me/availability', [
            'schedule' => [
                '1' => [['start_time' => '09:00', 'end_time' => '17:00']],
                '3' => [['start_time' => '10:00', 'end_time' => '15:00']],
            ],
        ], [], 'Nexus\Controllers\Api\MemberAvailabilityApiController@setBulkAvailability');

        $this->assertIsArray($response);
    }

    public function testAddSpecificDate(): void
    {
        $response = $this->post('/api/v2/users/me/availability/date', [
            'date'       => '2026-04-01',
            'start_time' => '10:00',
            'end_time'   => '15:00',
            'note'       => 'Available for special event',
        ], [], 'Nexus\Controllers\Api\MemberAvailabilityApiController@addSpecificDate');

        $this->assertIsArray($response);
    }

    public function testFindCompatibleTimes(): void
    {
        $response = $this->get('/api/v2/members/availability/compatible', ['user_id' => 1], [],
            'Nexus\Controllers\Api\MemberAvailabilityApiController@findCompatibleTimes');

        $this->assertIsArray($response);
    }

    public function testGetAvailableMembers(): void
    {
        $response = $this->get('/api/v2/members/availability/available', ['day' => 1, 'time' => '14:00'], [],
            'Nexus\Controllers\Api\MemberAvailabilityApiController@getAvailableMembers');

        $this->assertIsArray($response);
    }
}
