<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RetentionPolicyService;
use Illuminate\Support\Facades\DB;

class RetentionPolicyServiceTest extends TestCase
{
    public function test_registry_data_types_are_well_formed(): void
    {
        $this->assertNotEmpty(RetentionPolicyService::DATA_TYPES);

        foreach (RetentionPolicyService::DATA_TYPES as $type => $config) {
            $this->assertIsString($type);
            $this->assertArrayHasKey('table', $config);
            $this->assertArrayHasKey('column', $config);
            // User-generated content must never appear in the v1 delete registry
            $this->assertNotContains($config['table'], ['users', 'messages', 'listings', 'posts', 'transactions']);
        }
    }

    public function test_upsert_rejects_unknown_data_type(): void
    {
        DB::shouldReceive('table')->never();

        $error = RetentionPolicyService::upsertPolicy(1, 'users', 365, true);

        $this->assertNotNull($error);
    }

    public function test_upsert_rejects_unknown_action(): void
    {
        DB::shouldReceive('table')->never();

        $error = RetentionPolicyService::upsertPolicy(1, 'activity_log', 365, true, 'incinerate');

        $this->assertNotNull($error);
    }

    public function test_upsert_rejects_out_of_range_days(): void
    {
        DB::shouldReceive('table')->never();

        // Below the 30-day floor — a 1-day policy is a foot-gun
        $this->assertNotNull(RetentionPolicyService::upsertPolicy(1, 'activity_log', 7, true));
        // Above the 10-year ceiling
        $this->assertNotNull(RetentionPolicyService::upsertPolicy(1, 'activity_log', 99999, true));
    }

    public function test_upsert_accepts_valid_policy(): void
    {
        $builder = \Mockery::mock();
        $builder->shouldReceive('updateOrInsert')->once()->andReturn(true);
        DB::shouldReceive('table')->with('tenant_retention_policies')->andReturn($builder);

        $error = RetentionPolicyService::upsertPolicy(1, 'activity_log', 365, true);

        $this->assertNull($error);
    }

    public function test_getPolicies_returns_full_registry_with_defaults(): void
    {
        $builder = \Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('table')->with('tenant_retention_policies')->andReturn($builder);

        $policies = RetentionPolicyService::getPolicies(1);

        $this->assertSame(array_keys(RetentionPolicyService::DATA_TYPES), array_keys($policies));
        foreach ($policies as $type => $policy) {
            $this->assertFalse($policy['is_enabled'], 'policies must default to disabled (retain indefinitely)');
            // Each type advertises its registry default window (365 unless the
            // registry sets a type-specific default_days, e.g. safeguarding).
            $expected = (int) (RetentionPolicyService::DATA_TYPES[$type]['default_days'] ?? 365);
            $this->assertSame($expected, $policy['retention_days']);
        }
    }
}
