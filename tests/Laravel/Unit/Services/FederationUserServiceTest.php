<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationUserService;
use Illuminate\Support\Facades\DB;

class FederationUserServiceTest extends TestCase
{
    public function test_getUserSettings_returns_defaults_when_no_record(): void
    {
        DB::shouldReceive('table->where->first')->andReturn(null);

        $result = FederationUserService::getUserSettings(1);
        $this->assertEquals(1, $result['user_id']);
        $this->assertFalse($result['federation_optin']);
        $this->assertFalse($result['profile_visible_federated']);
        $this->assertEquals('local_only', $result['service_reach']);
    }

    public function test_getUserSettings_returns_stored_values(): void
    {
        $row = (object) [
            'user_id' => 1,
            'federation_optin' => 1,
            'profile_visible_federated' => 1,
            'messaging_enabled_federated' => 0,
            'transactions_enabled_federated' => 0,
            'appear_in_federated_search' => 1,
            'show_skills_federated' => 1,
            'show_location_federated' => 0,
            'service_reach' => 'remote_ok',
            'travel_radius_km' => 50,
        ];
        DB::shouldReceive('table->where->first')->andReturn($row);

        $result = FederationUserService::getUserSettings(1);
        $this->assertTrue($result['federation_optin']);
        $this->assertEquals('remote_ok', $result['service_reach']);
        $this->assertEquals(50, $result['travel_radius_km']);
    }

    public function test_getUserSettings_returns_defaults_on_exception(): void
    {
        DB::shouldReceive('table->where->first')->andThrow(new \Exception('error'));

        $result = FederationUserService::getUserSettings(1);
        $this->assertFalse($result['federation_optin']);
    }

    public function test_updateSettings_requires_integration_test(): void
    {
        $this->markTestIncomplete('updateSettings uses INSERT ON DUPLICATE KEY UPDATE with complex field mapping');
    }
}
