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
    private const RECURRENCE_ID_UNIQUE = 'uq_events_tenant_parent_recurrence_id';
    private const RECURRENCE_ID_TRIGGER = 'trg_events_recurrence_id_immutable';
    private const RECURRENCE_ID_INSERT_TRIGGER = 'trg_events_recurrence_id_validate_insert';
    private const RECURRENCE_ENGINE = 'sabre-vobject';
    private const RECURRENCE_ENGINE_VERSION = '2';
    private const EXCEPTION_INDEX = 'idx_events_recurrence_exception';

    /** @var list<string> */
    private const OVERRIDE_FIELDS = [
        'title',
        'description',
        'location',
        'latitude',
        'longitude',
        'start_time',
        'end_time',
        'timezone',
        'timezone_source',
        'all_day',
        'category_id',
        'group_id',
        'series_id',
        'max_attendees',
        'is_online',
        'allow_remote_attendance',
        'online_link',
        'video_url',
        'image_url',
        'cover_image',
        'federated_visibility',
        'accessibility_step_free',
        'accessibility_toilet',
        'accessibility_hearing_loop',
        'accessibility_quiet_space',
        'accessibility_seating',
        'accessibility_parking',
        'accessibility_parking_details',
        'accessibility_transit_details',
        'accessibility_assistance_contact',
        'accessibility_notes',
    ];

    /** @var list<string> */
    private const COLUMNS = [
        'recurrence_id',
        'is_recurrence_exception',
        'recurrence_override_fields',
        'recurrence_override_version',
        'recurrence_override_updated_at',
        'recurrence_override_updated_by',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            throw new LogicException('event_recurrence_override_prerequisite_missing:events');
        }
        $this->preflightBeforeDdl();

        $needsIndex = ! Schema::hasIndex('events', self::EXCEPTION_INDEX);
        Schema::table('events', static function (Blueprint $table) use ($needsIndex): void {
            if (! Schema::hasColumn('events', 'recurrence_id')) {
                $table->string('recurrence_id', 32)->nullable()
                    ->after('recurrence_engine_version')
                    ->comment('Immutable canonical UTC recurrence identity (Ymd\\THis\\Z); NULL for roots and rollout gaps');
            }
            if (! Schema::hasColumn('events', 'is_recurrence_exception')) {
                $table->boolean('is_recurrence_exception')->default(false)
                    ->after('recurrence_id');
            }
            if (! Schema::hasColumn('events', 'recurrence_override_fields')) {
                $table->json('recurrence_override_fields')->nullable()
                    ->after('is_recurrence_exception');
            }
            if (! Schema::hasColumn('events', 'recurrence_override_version')) {
                $table->unsignedBigInteger('recurrence_override_version')->default(0)
                    ->after('recurrence_override_fields');
            }
            if (! Schema::hasColumn('events', 'recurrence_override_updated_at')) {
                $table->timestamp('recurrence_override_updated_at')->nullable()
                    ->after('recurrence_override_version');
            }
            if (! Schema::hasColumn('events', 'recurrence_override_updated_by')) {
                $table->integer('recurrence_override_updated_by')->nullable()
                    ->after('recurrence_override_updated_at')
                    ->comment('Signed users.id evidence; deliberately no FK so actor evidence survives account deletion');
            }
            if ($needsIndex) {
                $table->index(
                    ['tenant_id', 'parent_event_id', 'is_recurrence_exception'],
                    self::EXCEPTION_INDEX,
                );
            }
        });

        $this->backfillProvableRecurrenceIds();
        if (! Schema::hasIndex('events', self::RECURRENCE_ID_UNIQUE)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'parent_event_id', 'recurrence_id'],
                    self::RECURRENCE_ID_UNIQUE,
                );
            });
        }

        if ($this->supportsMysqlTriggers()) {
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::RECURRENCE_ID_TRIGGER . '`');
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::RECURRENCE_ID_INSERT_TRIGGER . '`');
            DB::unprepared(
                'CREATE TRIGGER `' . self::RECURRENCE_ID_INSERT_TRIGGER . '` BEFORE INSERT ON `events` FOR EACH ROW '
                . "BEGIN IF NEW.`recurrence_id` IS NOT NULL AND NEW.`recurrence_id` NOT REGEXP '^[0-9]{8}T[0-9]{6}Z$' "
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_id_invalid'; "
                . "ELSEIF NEW.`recurrence_id` IS NOT NULL AND (NEW.`parent_event_id` IS NULL "
                . "OR NEW.`is_recurring_template` <> 0 OR NOT (NEW.`recurrence_engine` <=> '" . self::RECURRENCE_ENGINE . "') "
                . "OR NOT (NEW.`recurrence_engine_version` <=> '" . self::RECURRENCE_ENGINE_VERSION . "')) "
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_id_scope_invalid'; "
                . 'ELSEIF ' . $this->overrideEvidenceInvalidSql('NEW') . ' '
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_override_evidence_invalid'; END IF; END",
            );
            DB::unprepared(
                'CREATE TRIGGER `' . self::RECURRENCE_ID_TRIGGER . '` BEFORE UPDATE ON `events` FOR EACH ROW '
                . "BEGIN IF NEW.`recurrence_id` IS NOT NULL AND NEW.`recurrence_id` NOT REGEXP '^[0-9]{8}T[0-9]{6}Z$' "
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_id_invalid'; "
                . "ELSEIF OLD.`recurrence_id` IS NOT NULL AND NOT (OLD.`recurrence_id` <=> NEW.`recurrence_id`) "
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_id_immutable'; "
                . "ELSEIF NEW.`recurrence_id` IS NOT NULL AND (NEW.`parent_event_id` IS NULL "
                . "OR NEW.`is_recurring_template` <> 0 OR NOT (NEW.`recurrence_engine` <=> '" . self::RECURRENCE_ENGINE . "') "
                . "OR NOT (NEW.`recurrence_engine_version` <=> '" . self::RECURRENCE_ENGINE_VERSION . "')) "
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_id_scope_invalid'; "
                . 'ELSEIF ' . $this->overrideEvidenceInvalidSql('NEW') . ' '
                . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_recurrence_override_evidence_invalid'; END IF; END",
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }
        if (Schema::hasColumn('events', 'is_recurrence_exception')
            && DB::table('events')->where('is_recurrence_exception', 1)->exists()) {
            throw new LogicException('event_recurrence_override_rollback_refused_evidence_exists');
        }
        if (Schema::hasColumn('events', 'recurrence_id')
            && DB::table('events')->whereNotNull('recurrence_id')->exists()) {
            throw new LogicException('event_recurrence_id_rollback_refused_evidence_exists');
        }

        if ($this->supportsMysqlTriggers()) {
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::RECURRENCE_ID_TRIGGER . '`');
            DB::unprepared('DROP TRIGGER IF EXISTS `' . self::RECURRENCE_ID_INSERT_TRIGGER . '`');
        }
        $hasRecurrenceIdUnique = Schema::hasIndex('events', self::RECURRENCE_ID_UNIQUE);
        $hasIndex = Schema::hasIndex('events', self::EXCEPTION_INDEX);
        Schema::table('events', static function (Blueprint $table) use ($hasIndex, $hasRecurrenceIdUnique): void {
            if ($hasRecurrenceIdUnique) {
                $table->dropUnique(self::RECURRENCE_ID_UNIQUE);
            }
            if ($hasIndex) {
                $table->dropIndex(self::EXCEPTION_INDEX);
            }
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Recover only identities whose current UTC start still produces the
     * already-persisted deterministic occurrence key. A moved occurrence no
     * longer matches and remains NULL for explicit repair/review.
     */
    private function backfillProvableRecurrenceIds(): void
    {
        DB::table('events')
            ->whereNotNull('parent_event_id')
            ->whereNull('recurrence_id')
            ->where('recurrence_engine', self::RECURRENCE_ENGINE)
            ->where('recurrence_engine_version', self::RECURRENCE_ENGINE_VERSION)
            ->where('is_recurrence_exception', 0)
            ->whereNull('recurrence_override_fields')
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                $utc = new DateTimeZone('UTC');
                foreach ($rows as $row) {
                    $start = DateTimeImmutable::createFromFormat(
                        '!Y-m-d H:i:s',
                        (string) $row->start_time,
                        $utc,
                    );
                    if ($start === false || $start->format('Y-m-d H:i:s') !== (string) $row->start_time) {
                        continue;
                    }
                    $recurrenceId = $start->format('Ymd\\THis\\Z');
                    $expectedKey = sprintf(
                        'recurrence:%d:%d:%s',
                        (int) $row->tenant_id,
                        (int) $row->parent_event_id,
                        substr(hash(
                            'sha256',
                            self::RECURRENCE_ENGINE . '|' . self::RECURRENCE_ENGINE_VERSION . '|' . $recurrenceId,
                        ), 0, 32),
                    );
                    if (! hash_equals($expectedKey, (string) $row->occurrence_key)) {
                        continue;
                    }

                    DB::table('events')
                        ->where('id', (int) $row->id)
                        ->where('tenant_id', (int) $row->tenant_id)
                        ->whereNull('recurrence_id')
                        ->where('occurrence_key', $expectedKey)
                        ->where('start_time', (string) $row->start_time)
                        ->update(['recurrence_id' => $recurrenceId]);
                }
            }, 'id', 'id');
    }

    /**
     * MariaDB DDL auto-commits. Reject dirty or incompatible starting states
     * before the first schema mutation so a failed deploy cannot strand a
     * partially-applied recurrence identity contract.
     */
    private function preflightBeforeDdl(): void
    {
        foreach ([
            'tenant_id',
            'parent_event_id',
            'start_time',
            'occurrence_key',
            'recurrence_engine',
            'recurrence_engine_version',
        ] as $column) {
            if (! Schema::hasColumn('events', $column)) {
                throw new LogicException("event_recurrence_override_prerequisite_missing:events.{$column}");
            }
        }

        $duplicateOccurrenceKey = DB::table('events')
            ->select(['tenant_id', 'occurrence_key'])
            ->whereNotNull('occurrence_key')
            ->groupBy(['tenant_id', 'occurrence_key'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicateOccurrenceKey) {
            throw new LogicException('event_recurrence_id_preflight_duplicate_occurrence_key');
        }

        $columnPresence = array_map(
            static fn (string $column): bool => Schema::hasColumn('events', $column),
            self::COLUMNS,
        );
        $presentColumns = count(array_filter($columnPresence));
        $exceptionIndex = Schema::hasIndex('events', self::EXCEPTION_INDEX);
        $identityIndex = Schema::hasIndex('events', self::RECURRENCE_ID_UNIQUE);
        $updateTrigger = $this->triggerExists(self::RECURRENCE_ID_TRIGGER);
        $insertTrigger = $this->triggerExists(self::RECURRENCE_ID_INSERT_TRIGGER);
        if ($presentColumns > 0 && $presentColumns < count(self::COLUMNS)) {
            throw new LogicException('event_recurrence_override_preflight_partial_columns');
        }
        if ($presentColumns === 0) {
            if ($exceptionIndex || $identityIndex || $updateTrigger || $insertTrigger) {
                throw new LogicException('event_recurrence_override_preflight_orphan_artifact');
            }
            return;
        }
        $triggersComplete = ! $this->supportsMysqlTriggers() || ($updateTrigger && $insertTrigger);
        if (! $exceptionIndex || ! $identityIndex || ! $triggersComplete) {
            throw new LogicException('event_recurrence_override_preflight_partial_artifacts');
        }
        $this->validateExistingArtifactDefinitions();
        DB::table('events')
            ->whereNotNull('recurrence_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $recurrenceId = $row->recurrence_id;
                    if (! is_string($recurrenceId)
                        || preg_match('/^\d{8}T\d{6}Z$/D', $recurrenceId) !== 1
                        || $row->parent_event_id === null
                        || (bool) $row->is_recurring_template
                        || (string) $row->recurrence_engine !== self::RECURRENCE_ENGINE
                        || (string) $row->recurrence_engine_version !== self::RECURRENCE_ENGINE_VERSION) {
                        throw new LogicException('event_recurrence_id_preflight_invalid');
                    }
                }
            }, 'id', 'id');
        if (DB::table('events')
            ->select(['tenant_id', 'parent_event_id', 'recurrence_id'])
            ->whereNotNull('parent_event_id')
            ->whereNotNull('recurrence_id')
            ->groupBy(['tenant_id', 'parent_event_id', 'recurrence_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new LogicException('event_recurrence_id_preflight_duplicate');
        }
        if (DB::table('events')
            ->whereRaw($this->overrideEvidenceInvalidSql('events'))
            ->exists()) {
            throw new LogicException('event_recurrence_override_preflight_evidence_invalid');
        }
    }

    private function supportsMysqlTriggers(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function triggerExists(string $trigger): bool
    {
        if (! $this->supportsMysqlTriggers()) {
            return false;
        }

        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    private function validateExistingArtifactDefinitions(): void
    {
        if (! $this->supportsMysqlTriggers()) {
            return;
        }
        $identity = DB::selectOne("SHOW COLUMNS FROM `events` WHERE Field = 'recurrence_id'");
        $actor = DB::selectOne("SHOW COLUMNS FROM `events` WHERE Field = 'recurrence_override_updated_by'");
        if ($identity === null
            || strtolower((string) $identity->{'Type'}) !== 'varchar(32)'
            || (string) $identity->{'Null'} !== 'YES'
            || $actor === null
            || ! str_starts_with(strtolower((string) $actor->{'Type'}), 'int')
            || str_contains(strtolower((string) $actor->{'Type'}), 'unsigned')) {
            throw new LogicException('event_recurrence_override_preflight_artifact_definition_invalid');
        }
    }

    private function overrideEvidenceInvalidSql(string $row): string
    {
        $allowedFieldCount = implode(' + ', array_map(
            static fn (string $field): string =>
                "JSON_CONTAINS({$row}.`recurrence_override_fields`, JSON_QUOTE('{$field}'))",
            self::OVERRIDE_FIELDS,
        ));

        return "(({$row}.`is_recurrence_exception` = 1 AND ({$row}.`parent_event_id` IS NULL "
            . "OR {$row}.`is_recurring_template` <> 0 OR {$row}.`recurrence_id` IS NULL "
            . "OR NOT ({$row}.`recurrence_engine` <=> '" . self::RECURRENCE_ENGINE . "') "
            . "OR NOT ({$row}.`recurrence_engine_version` <=> '" . self::RECURRENCE_ENGINE_VERSION . "') "
            . "OR {$row}.`recurrence_override_fields` IS NULL "
            . "OR JSON_TYPE({$row}.`recurrence_override_fields`) <> 'ARRAY' "
            . "OR JSON_LENGTH({$row}.`recurrence_override_fields`) = 0 "
            . "OR JSON_LENGTH({$row}.`recurrence_override_fields`) <> ({$allowedFieldCount}) "
            . "OR {$row}.`recurrence_override_version` < 1 "
            . "OR {$row}.`recurrence_override_updated_at` IS NULL "
            . "OR {$row}.`recurrence_override_updated_by` IS NULL "
            . "OR NOT EXISTS (SELECT 1 FROM `users` AS `override_actor` "
            . "WHERE `override_actor`.`id` = {$row}.`recurrence_override_updated_by` "
            . "AND `override_actor`.`tenant_id` = {$row}.`tenant_id`))) "
            . "OR ({$row}.`is_recurrence_exception` = 0 AND ({$row}.`recurrence_override_fields` IS NOT NULL "
            . "OR {$row}.`recurrence_override_version` <> 0 "
            . "OR {$row}.`recurrence_override_updated_at` IS NOT NULL "
            . "OR {$row}.`recurrence_override_updated_by` IS NOT NULL)))";
    }
};
