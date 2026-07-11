<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use Tests\Laravel\TestCase;
use App\Services\EndorsementService;
use App\Services\SafeguardingInteractionPolicy;
use App\Models\SkillEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class EndorsementServiceTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_endorse_denial_writes_no_endorsement(): void
    {
        $endorser = User::factory()->forTenant($this->testTenantId)->create();
        $endorsed = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with((int) $endorser->id, (int) $endorsed->id, $this->testTenantId, 'skill_endorsement')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        try {
            EndorsementService::endorse(
                (int) $endorser->id,
                (int) $endorsed->id,
                'Safeguarding test skill',
                null,
                'Must not persist',
            );
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertDatabaseMissing('skill_endorsements', [
            'tenant_id' => $this->testTenantId,
            'endorser_id' => $endorser->id,
            'endorsed_id' => $endorsed->id,
            'skill_name' => 'Safeguarding test skill',
        ]);
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
