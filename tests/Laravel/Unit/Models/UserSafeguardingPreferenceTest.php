<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\TenantSafeguardingOption;
use App\Models\UserSafeguardingPreference;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for UserSafeguardingPreference model logic.
 * Uses unique tenant id 99768 to avoid collisions.
 */
class UserSafeguardingPreferenceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99768;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Seed the unique test tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'USP Test Tenant',
                'slug'              => 'usp-test-99768',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ─── structural / metadata ─────────────────────────────────────────────────

    public function test_table_name(): void
    {
        $model = new UserSafeguardingPreference();
        $this->assertSame('user_safeguarding_preferences', $model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $model = new UserSafeguardingPreference();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new UserSafeguardingPreference();
        $expected = [
            'tenant_id', 'user_id', 'option_id', 'selected_value',
            'notes', 'consent_given_at', 'consent_ip', 'revoked_at',
        ];
        $this->assertSame($expected, $model->getFillable());
    }

    public function test_casts_include_integer_fields(): void
    {
        $casts = (new UserSafeguardingPreference())->getCasts();
        $this->assertSame('integer', $casts['user_id']);
        $this->assertSame('integer', $casts['option_id']);
    }

    public function test_casts_include_datetime_fields(): void
    {
        $casts = (new UserSafeguardingPreference())->getCasts();
        $this->assertSame('datetime', $casts['consent_given_at']);
        $this->assertSame('datetime', $casts['revoked_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $traits = class_uses_recursive(UserSafeguardingPreference::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    // ─── relationships ─────────────────────────────────────────────────────────

    public function test_user_relationship_is_belongs_to(): void
    {
        $model = new UserSafeguardingPreference();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_option_relationship_is_belongs_to(): void
    {
        $model = new UserSafeguardingPreference();
        $this->assertInstanceOf(BelongsTo::class, $model->option());
    }

    // ─── isActive() instance helper ────────────────────────────────────────────

    public function test_is_active_returns_true_when_revoked_at_is_null(): void
    {
        $model = new UserSafeguardingPreference(['revoked_at' => null]);
        $this->assertTrue($model->isActive());
    }

    public function test_is_active_returns_false_when_revoked_at_is_set(): void
    {
        $model = new UserSafeguardingPreference();
        $model->revoked_at = now();
        $this->assertFalse($model->isActive());
    }

    // ─── scopeActive query scope ───────────────────────────────────────────────

    public function test_scope_active_filters_out_revoked_rows(): void
    {
        // Seed a safeguarding option for this tenant
        $optionId = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'test_scope_active_99768',
            'option_type' => 'checkbox',
            'label'       => 'Test Option',
            'sort_order'  => 1,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        // Seed a user for this tenant (minimal row, avoid FK on users.tenant_id)
        $userId = DB::table('users')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'name'             => 'USP Scope Test User',
            'email'            => 'usp-scope-99768@test.invalid',
            'username'         => 'usp_scope_99768',
            'password'         => bcrypt('password'),
            'role'             => 'member',
            'status'           => 'active',
            'balance'          => 0,
            'created_at'       => now(),
        ]);

        // Active preference (revoked_at = null)
        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userId,
            'option_id'       => $optionId,
            'selected_value'  => '1',
            'consent_given_at'=> now(),
            'revoked_at'      => null,
            'created_at'      => now(),
        ]);

        // Revoked preference (same user — unique key is tenant+user+option so insert a new option)
        $optionId2 = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'test_scope_active_revoked_99768',
            'option_type' => 'checkbox',
            'label'       => 'Test Option Revoked',
            'sort_order'  => 2,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userId,
            'option_id'       => $optionId2,
            'selected_value'  => '1',
            'consent_given_at'=> now(),
            'revoked_at'      => now()->subDay(),
            'created_at'      => now(),
        ]);

        $active = UserSafeguardingPreference::active()->get();

        $this->assertCount(1, $active);
        $this->assertNull($active->first()->revoked_at);
    }
}
