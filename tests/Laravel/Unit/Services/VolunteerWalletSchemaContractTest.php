<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class VolunteerWalletSchemaContractTest extends TestCase
{
    public function test_volunteer_wallet_schema_supports_auto_credit_reconciliation(): void
    {
        $this->assertTrue(Schema::hasTable('vol_organizations'));
        $this->assertHasColumns('vol_organizations', [
            'tenant_id',
            'user_id',
            'balance',
            'auto_pay_enabled',
        ]);

        $this->assertTrue(Schema::hasTable('vol_logs'));
        $this->assertHasColumns('vol_logs', [
            'tenant_id',
            'user_id',
            'organization_id',
            'hours',
            'status',
        ]);

        $this->assertTrue(Schema::hasTable('vol_org_transactions'));
        $this->assertHasColumns('vol_org_transactions', [
            'tenant_id',
            'vol_organization_id',
            'user_id',
            'vol_log_id',
            'type',
            'amount',
            'balance_after',
        ]);
    }

    /**
     * @param list<string> $columns
     */
    private function assertHasColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn($table, $column),
                "{$table}.{$column} should exist for volunteer wallet reconciliation"
            );
        }
    }
}
