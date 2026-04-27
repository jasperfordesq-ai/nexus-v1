<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 2a. Add tenant_id to exchange_history (missing — breaks tenant scoping)
        if (!Schema::hasColumn('exchange_history', 'tenant_id')) {
            DB::statement('ALTER TABLE exchange_history ADD COLUMN tenant_id INT NOT NULL DEFAULT 0 AFTER id');
            DB::statement('UPDATE exchange_history eh JOIN exchange_requests er ON er.id = eh.exchange_id SET eh.tenant_id = er.tenant_id WHERE eh.tenant_id = 0');
            DB::statement('ALTER TABLE exchange_history ADD INDEX idx_tenant_exchange (tenant_id, exchange_id)');
        }

        // 2b. broker_review_archives.broker_copy_id — add missing FK to broker_message_copies
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_review_archives'
               AND COLUMN_NAME = 'broker_copy_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        if (empty($fks)) {
            DB::statement('ALTER TABLE broker_review_archives ADD CONSTRAINT bra_ibfk_broker_copy FOREIGN KEY (broker_copy_id) REFERENCES broker_message_copies(id) ON DELETE RESTRICT');
        }

        // 2c. broker_message_copies.original_message_id — add missing FK to messages
        // First purge any orphaned rows (referential drift in dev/staging data)
        DB::statement('DELETE bmc FROM broker_message_copies bmc LEFT JOIN messages m ON m.id = bmc.original_message_id WHERE m.id IS NULL');
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_message_copies'
               AND COLUMN_NAME = 'original_message_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        if (empty($fks)) {
            DB::statement('ALTER TABLE broker_message_copies ADD CONSTRAINT bmc_ibfk_orig_msg FOREIGN KEY (original_message_id) REFERENCES messages(id) ON DELETE CASCADE');
        }

        // 2d. broker_message_copies.archive_id — add missing FK to broker_review_archives
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_message_copies'
               AND COLUMN_NAME = 'archive_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        if (empty($fks)) {
            DB::statement('ALTER TABLE broker_message_copies ADD CONSTRAINT bmc_ibfk_archive FOREIGN KEY (archive_id) REFERENCES broker_review_archives(id) ON DELETE SET NULL');
        }

        // 2e. broker_review_archives.decided_by — change ON DELETE CASCADE → RESTRICT
        // Current FK is broker_review_archives_ibfk_2 (CASCADE); drop and recreate as RESTRICT
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_review_archives'
               AND COLUMN_NAME = 'decided_by'
               AND REFERENCED_TABLE_NAME = 'users'"
        );
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE broker_review_archives DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
        DB::statement('ALTER TABLE broker_review_archives ADD CONSTRAINT bra_ibfk_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE RESTRICT');

        // 2f. listing_risk_tags unique key — change from (listing_id) to (tenant_id, listing_id)
        // MySQL won't drop unique_listing_tag while listing_risk_tags_ibfk_2 uses it as its backing index.
        // Strategy: drop the FK, drop/replace the index, then re-add the FK.
        $oldUniq = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'unique_listing_tag'");
        if (!empty($oldUniq)) {
            // Drop the FK that backs this index so we can replace the index
            $lrtFk2 = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'listing_risk_tags'
                   AND CONSTRAINT_NAME = 'listing_risk_tags_ibfk_2'"
            );
            if (!empty($lrtFk2)) {
                DB::statement('ALTER TABLE listing_risk_tags DROP FOREIGN KEY listing_risk_tags_ibfk_2');
            }
            DB::statement('ALTER TABLE listing_risk_tags DROP INDEX unique_listing_tag');
            // Re-add the FK (idx_tenant_listing now covers listing_id as part of a composite)
            DB::statement('ALTER TABLE listing_risk_tags ADD CONSTRAINT listing_risk_tags_ibfk_2 FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE');
        }
        // Drop separate idx_tenant_listing (will be superseded by the new unique key)
        $indexes2 = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'idx_tenant_listing'");
        if (!empty($indexes2)) {
            DB::statement('ALTER TABLE listing_risk_tags DROP INDEX idx_tenant_listing');
        }
        $newUniq = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'unique_tenant_listing'");
        if (empty($newUniq)) {
            DB::statement('ALTER TABLE listing_risk_tags ADD UNIQUE KEY unique_tenant_listing (tenant_id, listing_id)');
        }

        // 2g. user_first_contacts.first_message_id — add index and FK (currently unindexed, no FK)
        // First purge orphaned rows (referential drift in dev/staging data)
        DB::statement('DELETE ufc FROM user_first_contacts ufc LEFT JOIN messages m ON m.id = ufc.first_message_id WHERE m.id IS NULL');
        $indexes = DB::select("SHOW INDEX FROM user_first_contacts WHERE Column_name = 'first_message_id'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE user_first_contacts ADD INDEX idx_first_message (first_message_id)');
            DB::statement('ALTER TABLE user_first_contacts ADD CONSTRAINT ufc_ibfk_msg FOREIGN KEY (first_message_id) REFERENCES messages(id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        // 2g — remove user_first_contacts FK and index
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user_first_contacts'
               AND CONSTRAINT_NAME = 'ufc_ibfk_msg'"
        );
        if (!empty($fks)) {
            DB::statement('ALTER TABLE user_first_contacts DROP FOREIGN KEY ufc_ibfk_msg');
        }
        $indexes = DB::select("SHOW INDEX FROM user_first_contacts WHERE Key_name = 'idx_first_message'");
        if (!empty($indexes)) {
            DB::statement('ALTER TABLE user_first_contacts DROP INDEX idx_first_message');
        }

        // 2f — restore original listing_risk_tags unique key on listing_id only
        $newUniq = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'unique_tenant_listing'");
        if (!empty($newUniq)) {
            // Drop FK that may be backed by the unique_tenant_listing index before dropping it
            $lrtFk2 = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'listing_risk_tags'
                   AND CONSTRAINT_NAME = 'listing_risk_tags_ibfk_2'"
            );
            if (!empty($lrtFk2)) {
                DB::statement('ALTER TABLE listing_risk_tags DROP FOREIGN KEY listing_risk_tags_ibfk_2');
            }
            DB::statement('ALTER TABLE listing_risk_tags DROP INDEX unique_tenant_listing');
            // Re-add the FK
            DB::statement('ALTER TABLE listing_risk_tags ADD CONSTRAINT listing_risk_tags_ibfk_2 FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE');
        }
        $old = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'unique_listing_tag'");
        if (empty($old)) {
            // Must drop FK first since unique_listing_tag will back it
            $lrtFk2 = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'listing_risk_tags'
                   AND CONSTRAINT_NAME = 'listing_risk_tags_ibfk_2'"
            );
            if (!empty($lrtFk2)) {
                DB::statement('ALTER TABLE listing_risk_tags DROP FOREIGN KEY listing_risk_tags_ibfk_2');
            }
            DB::statement('ALTER TABLE listing_risk_tags ADD UNIQUE KEY unique_listing_tag (listing_id)');
            DB::statement('ALTER TABLE listing_risk_tags ADD CONSTRAINT listing_risk_tags_ibfk_2 FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE');
        }
        $idx = DB::select("SHOW INDEX FROM listing_risk_tags WHERE Key_name = 'idx_tenant_listing'");
        if (empty($idx)) {
            DB::statement('ALTER TABLE listing_risk_tags ADD INDEX idx_tenant_listing (tenant_id, listing_id)');
        }

        // 2e — restore decided_by as CASCADE (original behaviour)
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_review_archives'
               AND COLUMN_NAME = 'decided_by'
               AND REFERENCED_TABLE_NAME = 'users'"
        );
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE broker_review_archives DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
        DB::statement('ALTER TABLE broker_review_archives ADD CONSTRAINT broker_review_archives_ibfk_2 FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE CASCADE');

        // 2d — drop bmc_ibfk_archive
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_message_copies'
               AND CONSTRAINT_NAME = 'bmc_ibfk_archive'"
        );
        if (!empty($fks)) {
            DB::statement('ALTER TABLE broker_message_copies DROP FOREIGN KEY bmc_ibfk_archive');
        }

        // 2c — drop bmc_ibfk_orig_msg
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_message_copies'
               AND CONSTRAINT_NAME = 'bmc_ibfk_orig_msg'"
        );
        if (!empty($fks)) {
            DB::statement('ALTER TABLE broker_message_copies DROP FOREIGN KEY bmc_ibfk_orig_msg');
        }

        // 2b — drop bra_ibfk_broker_copy
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'broker_review_archives'
               AND CONSTRAINT_NAME = 'bra_ibfk_broker_copy'"
        );
        if (!empty($fks)) {
            DB::statement('ALTER TABLE broker_review_archives DROP FOREIGN KEY bra_ibfk_broker_copy');
        }

        // 2a — drop tenant_id from exchange_history
        if (Schema::hasColumn('exchange_history', 'tenant_id')) {
            $indexes = DB::select("SHOW INDEX FROM exchange_history WHERE Key_name = 'idx_tenant_exchange'");
            if (!empty($indexes)) {
                DB::statement('ALTER TABLE exchange_history DROP INDEX idx_tenant_exchange');
            }
            Schema::table('exchange_history', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
