<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add ON DELETE CASCADE to listing_reports.listing_id foreign key.
 * Ensures reports are automatically removed when their parent listing is deleted.
 */
return new class extends Migration
{
    private function getListingIdForeignKeyName(): ?string
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?
             LIMIT 1",
            ['listing_reports', 'listing_id', 'listings']
        );

        return $row?->name;
    }

    private function getDeleteRule(?string $constraintName): ?string
    {
        if ($constraintName === null) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT DELETE_RULE AS delete_rule
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
             LIMIT 1",
            ['listing_reports', $constraintName]
        );

        return $row?->delete_rule;
    }

    private function getColumnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        );

        return $row?->column_type;
    }

    private function getReferencedColumnDefinition(): ?string
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            ['listings', 'id']
        );

        if ($row === null) {
            return null;
        }

        return $row->column_type . ($row->is_nullable === 'YES' ? ' NULL' : ' NOT NULL');
    }

    public function up(): void
    {
        if (!Schema::hasTable('listing_reports') || !Schema::hasTable('listings')) {
            return;
        }

        $listingIdType = $this->getColumnType('listing_reports', 'listing_id');
        $listingsIdType = $this->getColumnType('listings', 'id');
        $referencedColumnDefinition = $this->getReferencedColumnDefinition();

        if (
            $listingIdType !== null
            && $listingsIdType !== null
            && strtolower($listingIdType) !== strtolower($listingsIdType)
            && $referencedColumnDefinition !== null
        ) {
            DB::statement("ALTER TABLE `listing_reports` MODIFY `listing_id` {$referencedColumnDefinition}");
        }

        $constraintName = $this->getListingIdForeignKeyName();
        $deleteRule = strtoupper((string) $this->getDeleteRule($constraintName));

        if ($constraintName !== null && $deleteRule === 'CASCADE') {
            return;
        }

        if ($constraintName !== null) {
            Schema::table('listing_reports', function (Blueprint $table) use ($constraintName) {
                $table->dropForeign($constraintName);
            });
        }

        Schema::table('listing_reports', function (Blueprint $table) {
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('listing_reports') || !Schema::hasTable('listings')) {
            return;
        }

        $constraintName = $this->getListingIdForeignKeyName();
        $deleteRule = strtoupper((string) $this->getDeleteRule($constraintName));

        if ($constraintName !== null && $deleteRule !== 'CASCADE') {
            return;
        }

        if ($constraintName !== null) {
            Schema::table('listing_reports', function (Blueprint $table) use ($constraintName) {
                $table->dropForeign($constraintName);
            });
        }

        Schema::table('listing_reports', function (Blueprint $table) {
            $table->foreign('listing_id')->references('id')->on('listings');
        });
    }
};
