<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class UserInsuranceApiControllerTest extends ApiTestCase
{
    public function testGetMyCertificates(): void
    {
        $response = $this->get('/api/v2/users/me/insurance', [], [],
            'Nexus\Controllers\Api\UserInsuranceApiController@getMyCertificates');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUploadCertificate(): void
    {
        $response = $this->post('/api/v2/users/me/insurance', [
            'type'        => 'public_liability',
            'provider'    => 'Allianz Insurance',
            'policy_number' => 'PLI-2026-001',
            'expiry_date' => '2027-03-01',
        ], [], 'Nexus\Controllers\Api\UserInsuranceApiController@upload');

        $this->assertIsArray($response);
    }

    public function testDeleteCertificate(): void
    {
        $response = $this->delete('/api/v2/users/me/insurance/999', [], [],
            'Nexus\Controllers\Api\UserInsuranceApiController@delete');

        $this->assertIsArray($response);
    }
}
