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
        if (!Schema::hasTable('notification_queue') || !Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('notification_queue', 'tenant_id')) {
            DB::statement(
                "UPDATE notification_queue q
                 JOIN users u ON u.id = q.user_id
                    SET q.tenant_id = u.tenant_id
                  WHERE q.tenant_id IS NULL"
            );

            DB::statement(
                "UPDATE notification_queue q
                 JOIN users u ON u.id = q.user_id
                    SET q.status = 'failed'
                  WHERE q.tenant_id <> u.tenant_id
                    AND q.status IN ('pending', 'processing')"
            );

            DB::statement('ALTER TABLE notification_queue MODIFY tenant_id INT NOT NULL');
        }

        if (Schema::hasColumn('notification_queue', 'frequency')) {
            DB::statement("ALTER TABLE notification_queue MODIFY frequency ENUM('instant','daily','weekly','monthly') DEFAULT 'daily'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasColumn('notification_queue', 'tenant_id')) {
            return;
        }

        DB::statement('ALTER TABLE notification_queue MODIFY tenant_id INT NULL');
    }
};
