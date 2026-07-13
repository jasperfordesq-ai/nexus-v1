<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Safeguarding;

use App\Models\User;
use App\Services\SafeguardingTriggerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class LegacyPreferenceMonitoringCleanupMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_migration_clears_only_legacy_preference_flags_and_preserves_other_fields(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $legacyUser = User::factory()->forTenant($this->testTenantId)->create();
        $adminMonitoredUser = User::factory()->forTenant($this->testTenantId)->create();

        DB::table('user_messaging_restrictions')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $legacyUser->id,
                'under_monitoring' => 1,
                'requires_broker_approval' => 1,
                'messaging_disabled' => 1,
                'monitoring_reason' => SafeguardingTriggerService::MONITORING_REASON_ONBOARDING,
                'restriction_reason' => 'Independent administrative messaging restriction',
                'monitoring_started_at' => now()->subDay(),
                'restricted_by' => $admin->id,
                'restricted_at' => now()->subDay(),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $adminMonitoredUser->id,
                'under_monitoring' => 1,
                'requires_broker_approval' => 1,
                'messaging_disabled' => 0,
                'monitoring_reason' => 'Manual safeguarding case decision',
                'restriction_reason' => 'Manual safeguarding case decision',
                'monitoring_started_at' => now()->subDay(),
                'restricted_by' => $admin->id,
                'restricted_at' => now()->subDay(),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ]);

        $migration = require base_path(
            'database/migrations/2026_07_13_000079_clear_legacy_safeguarding_preference_monitoring.php'
        );
        $migration->up();
        $migration->up();

        $legacyRow = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $legacyUser->id)
            ->first();
        $this->assertNotNull($legacyRow);
        $this->assertSame(0, (int) $legacyRow->under_monitoring);
        $this->assertSame(0, (int) $legacyRow->requires_broker_approval);
        $this->assertSame(1, (int) $legacyRow->messaging_disabled);
        $this->assertSame($admin->id, (int) $legacyRow->restricted_by);
        $this->assertSame(
            'Independent administrative messaging restriction',
            $legacyRow->restriction_reason,
        );

        $adminRow = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $adminMonitoredUser->id)
            ->first();
        $this->assertNotNull($adminRow);
        $this->assertSame(1, (int) $adminRow->under_monitoring);
        $this->assertSame(1, (int) $adminRow->requires_broker_approval);

        $migration->down();
        $this->assertDatabaseHas('user_messaging_restrictions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $legacyUser->id,
            'under_monitoring' => 0,
            'requires_broker_approval' => 0,
        ]);
    }
}
