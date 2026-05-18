<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasTable('users') || !Schema::hasColumn('notifications', 'tenant_id')) {
            return;
        }

        DB::statement(
            "UPDATE notifications n
             JOIN users u ON u.id = n.user_id
             SET n.tenant_id = u.tenant_id
             WHERE n.tenant_id IS NULL OR n.tenant_id <> u.tenant_id"
        );

        DB::statement('ALTER TABLE notifications MODIFY tenant_id INT NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('notifications', 'tenant_id')) {
            return;
        }

        DB::statement('ALTER TABLE notifications MODIFY tenant_id INT NULL');
    }
};
