<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\VolunteerService;
use Tests\Laravel\TestCase;

class VolunteerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    public function test_getOpportunities_returns_expected_structure(): void
    {
        $result = VolunteerService::getOpportunities();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->assertNull(VolunteerService::getById(2147483647));
    }
}
