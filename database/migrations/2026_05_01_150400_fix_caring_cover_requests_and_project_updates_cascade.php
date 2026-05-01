<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert cascadeOnDelete to nullOnDelete on audit-history-bearing FKs so that
 * deleting a parent row doesn't erase historical cover requests / project updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caring_cover_requests') && Schema::hasColumn('caring_cover_requests', 'caregiver_link_id')) {
            $this->dropForeignIfExists('caring_cover_requests', 'caring_cover_requests_caregiver_link_id_foreign');
            DB::statement('ALTER TABLE caring_cover_requests MODIFY COLUMN caregiver_link_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE caring_cover_requests ADD CONSTRAINT caring_cover_requests_caregiver_link_id_foreign FOREIGN KEY (caregiver_link_id) REFERENCES caring_caregiver_links(id) ON DELETE SET NULL');
        }

        if (Schema::hasTable('caring_project_updates') && Schema::hasColumn('caring_project_updates', 'project_id')) {
            $this->dropForeignIfExists('caring_project_updates', 'caring_project_updates_project_id_foreign');
            DB::statement('ALTER TABLE caring_project_updates MODIFY COLUMN project_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE caring_project_updates ADD CONSTRAINT caring_project_updates_project_id_foreign FOREIGN KEY (project_id) REFERENCES caring_project_announcements(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_cover_requests') && Schema::hasColumn('caring_cover_requests', 'caregiver_link_id')) {
            $this->dropForeignIfExists('caring_cover_requests', 'caring_cover_requests_caregiver_link_id_foreign');
            // Cannot safely revert NULLs to NOT NULL without data — keep column nullable but reinstate cascade.
            DB::statement('ALTER TABLE caring_cover_requests ADD CONSTRAINT caring_cover_requests_caregiver_link_id_foreign FOREIGN KEY (caregiver_link_id) REFERENCES caring_caregiver_links(id) ON DELETE CASCADE');
        }

        if (Schema::hasTable('caring_project_updates') && Schema::hasColumn('caring_project_updates', 'project_id')) {
            $this->dropForeignIfExists('caring_project_updates', 'caring_project_updates_project_id_foreign');
            DB::statement('ALTER TABLE caring_project_updates ADD CONSTRAINT caring_project_updates_project_id_foreign FOREIGN KEY (project_id) REFERENCES caring_project_announcements(id) ON DELETE CASCADE');
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        } catch (\Throwable $e) {
            // FK may not exist under that name — ignore and continue.
        }
    }
};
