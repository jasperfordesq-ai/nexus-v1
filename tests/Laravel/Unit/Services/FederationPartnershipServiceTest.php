<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationPartnershipService;

class FederationPartnershipServiceTest extends TestCase
{
    public function test_level_constants_are_defined(): void
    {
        $this->assertEquals(1, FederationPartnershipService::LEVEL_DISCOVERY);
        $this->assertEquals(2, FederationPartnershipService::LEVEL_SOCIAL);
        $this->assertEquals(3, FederationPartnershipService::LEVEL_ECONOMIC);
        $this->assertEquals(4, FederationPartnershipService::LEVEL_INTEGRATED);
    }

    public function test_requestPartnership_requires_integration_test(): void
    {
        // Uses FederationFeatureService::isOperationAllowed() and complex DB queries
        $this->markTestIncomplete('Requires integration test with FederationFeatureService and DB');
    }
}
