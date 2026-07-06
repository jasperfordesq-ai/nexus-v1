<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Fresh installs get a master tenant (tenant_id=1) and a first-run
     * platform administrator. Demo/E2E data is intentionally separate.
     */
    public function run(): void
    {
        $this->call(TenantSeeder::class);
    }
}
