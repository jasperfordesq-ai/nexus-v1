<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HISTORY_UPDATE_TRIGGER = 'trg_ev_session_reg_hist_no_update';
    private const HISTORY_INSERT_TRIGGER = 'trg_ev_session_reg_hist_pin_insert';
    private const REGISTRATION_INSERT_TRIGGER = 'trg_ev_session_reg_validate_insert';
    private const REGISTRATION_UPDATE_TRIGGER = 'trg_ev_session_reg_validate_update';
    private const REGISTRATION_CHECK = 'chk_ev_session_reg_event_version';
    private const HISTORY_CHECK = 'chk_ev_session_reg_hist_event_version';

    public function up(): void
    {
        foreach ([
            'event_registrations',
            'event_session_registrations',
            'event_session_registration_history',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new LogicException('event_agenda_registration_version_dependencies_missing');
            }
        }

        $this->addNullableVersionColumns();

        if (DB::getDriverName() === 'mysql') {
            // Keep old blue/green workers compatible while the expand migration
            // runs: omitted versions are populated by the database, and the
            // canonical-confirmation guard remains continuously enforced.
            $this->installRollingCompatibilityTriggers();
            $this->installBackfillHistoryGuard();
            DB::statement('SET @nexus_event_agenda_version_backfill = 1');
        }

        try {
            $this->backfillPinnedVersions();
            $this->assertBackfillComplete();
            $this->installVersionChecks();
        } finally {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET @nexus_event_agenda_version_backfill = 0');
            }
            $this->installHistoryImmutabilityTrigger();
            $this->installRollingCompatibilityTriggers();
        }
    }

    public function down(): void
    {
        foreach (['event_session_registrations', 'event_session_registration_history'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('event_agenda_registration_version_rollback_refused_evidence_exists');
            }
        }

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::HISTORY_INSERT_TRIGGER . '`');
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::REGISTRATION_INSERT_TRIGGER . '`');
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::REGISTRATION_UPDATE_TRIGGER . '`');
            foreach ([
                'event_session_registrations' => self::REGISTRATION_CHECK,
                'event_session_registration_history' => self::HISTORY_CHECK,
            ] as $table => $constraint) {
                if ($this->constraintExists($table, $constraint)) {
                    DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$constraint}`");
                }
            }
        }

        if (Schema::hasTable('event_session_registration_history')
            && Schema::hasColumn('event_session_registration_history', 'event_registration_version')) {
            Schema::table('event_session_registration_history', static function (Blueprint $table): void {
                $table->dropColumn('event_registration_version');
            });
        }
        if (Schema::hasTable('event_session_registrations')
            && Schema::hasColumn('event_session_registrations', 'event_registration_version')) {
            Schema::table('event_session_registrations', static function (Blueprint $table): void {
                $table->dropColumn('event_registration_version');
            });
        }

        $this->installLegacyRegistrationTriggers();
    }

    private function addNullableVersionColumns(): void
    {
        if (! Schema::hasColumn('event_session_registrations', 'event_registration_version')) {
            Schema::table('event_session_registrations', static function (Blueprint $table): void {
                $table->unsignedBigInteger('event_registration_version')->nullable()
                    ->after('event_registration_id');
            });
        }
        if (! Schema::hasColumn('event_session_registration_history', 'event_registration_version')) {
            Schema::table('event_session_registration_history', static function (Blueprint $table): void {
                $table->unsignedBigInteger('event_registration_version')->nullable()
                    ->after('event_registration_id');
            });
        }
    }

    private function backfillPinnedVersions(): void
    {
        DB::statement(
            'UPDATE `event_session_registrations` AS `session_registration` '
            . 'INNER JOIN `event_registrations` AS `event_registration` '
            . 'ON `event_registration`.`tenant_id` = `session_registration`.`tenant_id` '
            . 'AND `event_registration`.`event_id` = `session_registration`.`event_id` '
            . 'AND `event_registration`.`id` = `session_registration`.`event_registration_id` '
            . 'AND `event_registration`.`user_id` = `session_registration`.`user_id` '
            . 'SET `session_registration`.`event_registration_version` = `event_registration`.`registration_version` '
            . 'WHERE `session_registration`.`event_registration_version` IS NULL',
        );
        DB::statement(
            'UPDATE `event_session_registration_history` AS `history` '
            . 'INNER JOIN `event_session_registrations` AS `session_registration` '
            . 'ON `session_registration`.`tenant_id` = `history`.`tenant_id` '
            . 'AND `session_registration`.`event_id` = `history`.`event_id` '
            . 'AND `session_registration`.`session_id` = `history`.`session_id` '
            . 'AND `session_registration`.`id` = `history`.`registration_id` '
            . 'AND `session_registration`.`user_id` = `history`.`user_id` '
            . 'AND `session_registration`.`event_registration_id` = `history`.`event_registration_id` '
            . 'SET `history`.`event_registration_version` = `session_registration`.`event_registration_version` '
            . 'WHERE `history`.`event_registration_version` IS NULL',
        );
    }

    private function assertBackfillComplete(): void
    {
        if (DB::table('event_session_registrations')->whereNull('event_registration_version')->exists()
            || DB::table('event_session_registration_history')->whereNull('event_registration_version')->exists()) {
            throw new LogicException('event_agenda_registration_version_backfill_incomplete');
        }
    }

    private function installVersionChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->constraintExists('event_session_registrations', self::REGISTRATION_CHECK)) {
            DB::statement(
                'ALTER TABLE `event_session_registrations` ADD CONSTRAINT `'
                . self::REGISTRATION_CHECK . '` CHECK (`event_registration_version` >= 1)',
            );
        }
        if (! $this->constraintExists('event_session_registration_history', self::HISTORY_CHECK)) {
            DB::statement(
                'ALTER TABLE `event_session_registration_history` ADD CONSTRAINT `'
                . self::HISTORY_CHECK . '` CHECK (`event_registration_version` >= 1)',
            );
        }
    }

    private function installHistoryImmutabilityTrigger(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(
            'CREATE OR REPLACE TRIGGER `' . self::HISTORY_UPDATE_TRIGGER . '` '
            . 'BEFORE UPDATE ON `event_session_registration_history` FOR EACH ROW '
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_history_immutable'",
        );
    }

    private function installBackfillHistoryGuard(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(
            'CREATE OR REPLACE TRIGGER `' . self::HISTORY_UPDATE_TRIGGER . '` '
            . 'BEFORE UPDATE ON `event_session_registration_history` FOR EACH ROW '
            . "BEGIN IF COALESCE(@nexus_event_agenda_version_backfill, 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_history_immutable'; END IF; END",
        );
    }

    private function installRollingCompatibilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $versionLookup = "(SELECT `registration_version` FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`event_registration_id` AND `user_id` = NEW.`user_id` LIMIT 1)";
        $canonicalValidation = "IF NEW.`status` = 'registered' AND (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`event_registration_id` AND `user_id` = NEW.`user_id` AND `registration_state` = 'confirmed' AND `registration_version` = NEW.`event_registration_version`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_confirmed_registration_required'; END IF;";
        $insertValidation = "BEGIN IF NEW.`event_registration_version` IS NULL THEN SET NEW.`event_registration_version` = {$versionLookup}; END IF; IF NEW.`event_registration_version` IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_version_required'; END IF; {$canonicalValidation} END";
        DB::unprepared(
            'CREATE OR REPLACE TRIGGER `' . self::REGISTRATION_INSERT_TRIGGER . '` '
            . 'BEFORE INSERT ON `event_session_registrations` FOR EACH ROW '
            . $insertValidation,
        );

        $updateValidation = "BEGIN IF OLD.`status` = 'withdrawn' AND NEW.`status` = 'registered' AND (NEW.`event_registration_version` <=> OLD.`event_registration_version`) THEN SET NEW.`event_registration_version` = {$versionLookup}; ELSEIF NEW.`event_registration_version` IS NULL THEN SET NEW.`event_registration_version` = {$versionLookup}; END IF; IF NEW.`event_registration_version` IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_version_required'; END IF; IF NEW.`status` <> 'registered' AND OLD.`event_registration_version` IS NOT NULL AND NOT (NEW.`event_registration_version` <=> OLD.`event_registration_version`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_version_immutable'; END IF; IF NEW.`status` <> 'registered' AND OLD.`event_registration_version` IS NULL AND NEW.`event_registration_version` <> {$versionLookup} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_version_invalid'; END IF; {$canonicalValidation} END";
        DB::unprepared(
            'CREATE OR REPLACE TRIGGER `' . self::REGISTRATION_UPDATE_TRIGGER . '` '
            . 'BEFORE UPDATE ON `event_session_registrations` FOR EACH ROW '
            . $updateValidation,
        );

        $historyVersionLookup = "(SELECT `event_registration_version` FROM `event_session_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `session_id` = NEW.`session_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id` AND `event_registration_id` = NEW.`event_registration_id` LIMIT 1)";
        $historyValidation = "BEGIN IF NEW.`event_registration_version` IS NULL THEN SET NEW.`event_registration_version` = {$historyVersionLookup}; END IF; IF NEW.`event_registration_version` IS NULL OR (SELECT COUNT(*) FROM `event_session_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `session_id` = NEW.`session_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id` AND `event_registration_id` = NEW.`event_registration_id` AND `event_registration_version` = NEW.`event_registration_version`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_history_version_required'; END IF; END";
        DB::unprepared(
            'CREATE OR REPLACE TRIGGER `' . self::HISTORY_INSERT_TRIGGER . '` '
            . 'BEFORE INSERT ON `event_session_registration_history` FOR EACH ROW '
            . $historyValidation,
        );
    }

    private function installLegacyRegistrationTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql'
            || ! Schema::hasTable('event_session_registrations')
            || ! Schema::hasTable('event_registrations')) {
            return;
        }

        $validation = "BEGIN IF NEW.`status` = 'registered' AND (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`event_registration_id` AND `user_id` = NEW.`user_id` AND `registration_state` = 'confirmed') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_confirmed_registration_required'; END IF; END";
        foreach ([
            self::REGISTRATION_INSERT_TRIGGER => 'INSERT',
            self::REGISTRATION_UPDATE_TRIGGER => 'UPDATE',
        ] as $trigger => $operation) {
            DB::unprepared(
                "CREATE OR REPLACE TRIGGER `{$trigger}` BEFORE {$operation} ON `event_session_registrations` "
                . 'FOR EACH ROW ' . $validation,
            );
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

};
