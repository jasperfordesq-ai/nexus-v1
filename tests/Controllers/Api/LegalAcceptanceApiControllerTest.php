<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class LegalAcceptanceApiControllerTest extends ApiTestCase
{
    public function testGetPendingDocuments(): void
    {
        $response = $this->get('/api/v2/legal/pending', [], [],
            'Nexus\Controllers\Api\LegalAcceptanceApiController@getPending');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testAcceptDocument(): void
    {
        $response = $this->post('/api/v2/legal/accept', [
            'document_id' => 1,
            'version'     => '1.0',
        ], [], 'Nexus\Controllers\Api\LegalAcceptanceApiController@accept');

        $this->assertIsArray($response);
    }

    public function testGetAcceptanceHistory(): void
    {
        $response = $this->get('/api/v2/legal/history', [], [],
            'Nexus\Controllers\Api\LegalAcceptanceApiController@history');

        $this->assertIsArray($response);
    }
}
