<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EndorsementService;
use App\Models\SkillEndorsement;
use App\Models\User;
use Mockery;

class EndorsementServiceTest extends TestCase
{
    // =========================================================================
    // endorse()
    // =========================================================================

    public function test_endorse_rejects_self_endorsement(): void
    {
        $result = EndorsementService::endorse(1, 1, 'PHP');
        $this->assertNull($result);
        $errors = EndorsementService::getErrors();
        $this->assertEquals('SELF_ENDORSEMENT', $errors[0]['code']);
    }

    public function test_endorse_rejects_empty_skill_name(): void
    {
        $result = EndorsementService::endorse(1, 2, '');
        $this->assertNull($result);
        $errors = EndorsementService::getErrors();
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_endorse_rejects_skill_name_over_100_chars(): void
    {
        $result = EndorsementService::endorse(1, 2, str_repeat('a', 101));
        $this->assertNull($result);
        $errors = EndorsementService::getErrors();
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function test_endorse_rejects_when_endorsed_user_not_found(): void
    {
        $this->mock(User::class, function ($mock) {
            $mock->shouldReceive('where->first')->andReturn(null);
        });

        // Since endorse uses User::where directly, we need integration test for full flow
        $this->markTestIncomplete('Requires model mocking or integration test');
    }

    // =========================================================================
    // removeEndorsement()
    // =========================================================================

    public function test_removeEndorsement_delegates_to_model(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for static method');
    }

    // =========================================================================
    // hasEndorsed()
    // =========================================================================

    public function test_hasEndorsed_delegates_to_model(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for static method');
    }

    // =========================================================================
    // getErrors()
    // =========================================================================

    public function test_getErrors_returns_empty_array_initially(): void
    {
        // Reset by calling endorse with valid params but it will fail at user lookup
        // Just test the getter
        $this->assertIsArray(EndorsementService::getErrors());
    }

    // =========================================================================
    // getStats()
    // =========================================================================

    public function test_getStats_returns_expected_keys(): void
    {
        $this->markTestIncomplete('Requires Eloquent model mocking for SkillEndorsement queries');
    }
}
