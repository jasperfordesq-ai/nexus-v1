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
        if (Schema::hasTable('listing_images')) {
            $this->deleteListingImageOrphans();
            $this->normaliseIntColumn('listing_images', 'tenant_id', 'NOT NULL');
            $this->normaliseIntColumn('listing_images', 'listing_id', 'NOT NULL');
            $this->addIndexIfMissing('listing_images', 'listing_images_listing_id_sort_order_index', ['listing_id', 'sort_order']);
            $this->addForeignKeyIfMissing('listing_images', 'listing_images_tenant_id_foreign', 'tenant_id', 'tenants', 'id');
            $this->addForeignKeyIfMissing('listing_images', 'listing_images_listing_id_foreign', 'listing_id', 'listings', 'id', 'CASCADE');
        }

        if (Schema::hasTable('listing_reports')) {
            $this->deleteListingReportOrphans();
            $this->dedupeListingReports();
            $this->normaliseIntColumn('listing_reports', 'tenant_id', 'NOT NULL');
            $this->normaliseIntColumn('listing_reports', 'reporter_id', 'NOT NULL');
            $this->normaliseIntColumn('listing_reports', 'reviewed_by', 'DEFAULT NULL');
            $this->addIndexIfMissing('listing_reports', 'listing_reports_tenant_id_status_index', ['tenant_id', 'status']);
            $this->addUniqueIfMissing('listing_reports', 'listing_reports_listing_id_reporter_id_tenant_id_unique', ['listing_id', 'reporter_id', 'tenant_id']);
            $this->addForeignKeyIfMissing('listing_reports', 'listing_reports_tenant_id_foreign', 'tenant_id', 'tenants', 'id');
            $this->addForeignKeyIfMissing('listing_reports', 'listing_reports_reporter_id_foreign', 'reporter_id', 'users', 'id');
            $this->addForeignKeyIfMissing('listing_reports', 'listing_reports_reviewed_by_foreign', 'reviewed_by', 'users', 'id');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('listing_reports')) {
            $this->dropForeignKeyIfExists('listing_reports', 'listing_reports_reviewed_by_foreign');
            $this->dropForeignKeyIfExists('listing_reports', 'listing_reports_reporter_id_foreign');
            $this->dropForeignKeyIfExists('listing_reports', 'listing_reports_tenant_id_foreign');
            $this->dropIndexIfExists('listing_reports', 'listing_reports_listing_id_reporter_id_tenant_id_unique');
            $this->dropIndexIfExists('listing_reports', 'listing_reports_tenant_id_status_index');
        }

        if (Schema::hasTable('listing_images')) {
            $this->dropForeignKeyIfExists('listing_images', 'listing_images_listing_id_foreign');
            $this->dropForeignKeyIfExists('listing_images', 'listing_images_tenant_id_foreign');
            $this->dropIndexIfExists('listing_images', 'listing_images_listing_id_sort_order_index');
        }
    }

    private function deleteListingImageOrphans(): void
    {
        DB::statement('DELETE li FROM listing_images li LEFT JOIN listings l ON l.id = li.listing_id WHERE l.id IS NULL');
        DB::statement('DELETE li FROM listing_images li LEFT JOIN tenants t ON t.id = li.tenant_id WHERE t.id IS NULL');
    }

    private function deleteListingReportOrphans(): void
    {
        DB::statement('DELETE lr FROM listing_reports lr LEFT JOIN listings l ON l.id = lr.listing_id WHERE l.id IS NULL');
        DB::statement('DELETE lr FROM listing_reports lr LEFT JOIN tenants t ON t.id = lr.tenant_id WHERE t.id IS NULL');
        DB::statement('DELETE lr FROM listing_reports lr LEFT JOIN users u ON u.id = lr.reporter_id WHERE u.id IS NULL');
        DB::statement('UPDATE listing_reports lr LEFT JOIN users u ON u.id = lr.reviewed_by SET lr.reviewed_by = NULL WHERE lr.reviewed_by IS NOT NULL AND u.id IS NULL');
    }

    private function dedupeListingReports(): void
    {
        DB::statement(
            'DELETE lr1 FROM listing_reports lr1
             INNER JOIN listing_reports lr2
                ON lr1.listing_id = lr2.listing_id
               AND lr1.reporter_id = lr2.reporter_id
               AND lr1.tenant_id = lr2.tenant_id
               AND lr1.id > lr2.id'
        );
    }

    private function normaliseIntColumn(string $table, string $column, string $nullClause): void
    {
        $type = DB::table('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_TYPE');

        if ($type !== 'int(11)') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` int(11) {$nullClause}");
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnList = implode('`, `', $columns);
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$columnList}`)");
    }

    private function addUniqueIfMissing(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnList = implode('`, `', $columns);
        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index}` (`{$columnList}`)");
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        ?string $onDelete = null
    ): void {
        if ($this->foreignKeyExists($table, $constraint) || $this->foreignKeyExistsOnColumn($table, $column, $referencedTable, $referencedColumn)) {
            return;
        }

        $cascade = $onDelete ? " ON DELETE {$onDelete}" : '';
        DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`){$cascade}");
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    private function foreignKeyExistsOnColumn(string $table, string $column, string $referencedTable, string $referencedColumn): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('REFERENCED_TABLE_NAME', $referencedTable)
            ->where('REFERENCED_COLUMN_NAME', $referencedColumn)
            ->exists();
    }
};
