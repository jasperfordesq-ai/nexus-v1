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
    private const HISTORY_DELETE_TRIGGER = 'trg_ev_session_reg_hist_no_delete';
    private const REGISTRATION_INSERT_TRIGGER = 'trg_ev_session_reg_validate_insert';
    private const REGISTRATION_UPDATE_TRIGGER = 'trg_ev_session_reg_validate_update';
    private const SESSION_SCOPE_INDEX = 'uq_event_sessions_tenant_event_id';
    private const EVENT_REGISTRATION_SCOPE_INDEX = 'uq_event_registrations_checkin_scope';
    private const RESOURCE_SCOPE_INDEX = 'uq_ev_session_resource_scope_id';
    private const REGISTRATION_SCOPE_INDEX = 'uq_ev_session_reg_scope_id';

    /** @var list<string> */
    private const OWNED_TABLES = [
        'event_session_resources',
        'event_session_registrations',
        'event_session_registration_history',
    ];

    public function up(): void
    {
        foreach ([
            'tenants',
            'events',
            'users',
            'event_sessions',
            'event_session_history',
            'event_registrations',
        ] as $required) {
            if (! Schema::hasTable($required)) {
                throw new LogicException("event_agenda_enterprise_prerequisite_missing:{$required}");
            }
        }
        foreach ([
            'event_sessions' => self::SESSION_SCOPE_INDEX,
            'event_registrations' => self::EVENT_REGISTRATION_SCOPE_INDEX,
        ] as $table => $index) {
            if (! Schema::hasIndex($table, $index)) {
                throw new LogicException(
                    "event_agenda_enterprise_prerequisite_missing:{$table}.{$index}",
                );
            }
        }

        $this->addSessionCapacity();
        $this->createResources();
        $this->createRegistrations();
        $this->createRegistrationHistory();
        $this->installCheckConstraints();
        $this->installValidationAndImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->hasDependentSchema()) {
            throw new LogicException('event_agenda_enterprise_rollback_refused_dependents_exist');
        }
        if ($this->containsDurableEvidence()) {
            throw new LogicException('event_agenda_enterprise_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        Schema::dropIfExists('event_session_registration_history');
        Schema::dropIfExists('event_session_registrations');
        Schema::dropIfExists('event_session_resources');

        if (Schema::hasTable('event_sessions') && Schema::hasColumn('event_sessions', 'capacity')) {
            if ($this->constraintExists('event_sessions', 'chk_ev_session_capacity')) {
                DB::statement('ALTER TABLE `event_sessions` DROP CONSTRAINT `chk_ev_session_capacity`');
            }
            Schema::table('event_sessions', static function (Blueprint $table): void {
                $table->dropColumn('capacity');
            });
        }
    }

    private function addSessionCapacity(): void
    {
        if (Schema::hasColumn('event_sessions', 'capacity')) {
            return;
        }

        Schema::table('event_sessions', static function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable()->after('visibility')
                ->comment('Optional independent capacity for this session; null means unlimited');
        });
    }

    private function createResources(): void
    {
        if (Schema::hasTable('event_session_resources')) {
            return;
        }

        Schema::create('event_session_resources', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('session_id');
            $table->string('resource_type', 24);
            $table->string('visibility', 24)->default('public');
            $table->string('title', 191);
            $table->text('url_ciphertext');
            $table->unsignedInteger('position');
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'session_id', 'id'],
                self::RESOURCE_SCOPE_INDEX,
            );
            $table->index(
                ['tenant_id', 'event_id', 'session_id', 'position', 'id'],
                'idx_ev_session_resources_order',
            );

            $table->foreign('tenant_id', 'fk_ev_session_resource_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_session_resource_event')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'session_id'],
                'fk_ev_session_resource_session',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_sessions')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_ev_session_resource_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_ev_session_resource_updater')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRegistrations(): void
    {
        if (Schema::hasTable('event_session_registrations')) {
            return;
        }

        Schema::create('event_session_registrations', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('session_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('event_registration_id');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('status', 16)->default('registered');
            $table->timestamp('registered_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'session_id', 'user_id'],
                'uq_ev_session_reg_member',
            );
            $table->unique(
                [
                    'tenant_id',
                    'event_id',
                    'session_id',
                    'id',
                    'user_id',
                    'event_registration_id',
                ],
                self::REGISTRATION_SCOPE_INDEX,
            );
            $table->index(
                ['tenant_id', 'event_id', 'session_id', 'status', 'id'],
                'idx_ev_session_reg_capacity',
            );
            $table->index(
                ['tenant_id', 'user_id', 'status', 'event_id', 'session_id'],
                'idx_ev_session_reg_member_state',
            );

            $table->foreign('tenant_id', 'fk_ev_session_reg_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_session_reg_event')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'session_id'],
                'fk_ev_session_reg_session',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_sessions')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'event_registration_id', 'user_id'],
                'fk_ev_session_reg_event_reg',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(['user_id', 'tenant_id'], 'fk_ev_session_reg_user')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRegistrationHistory(): void
    {
        if (Schema::hasTable('event_session_registration_history')) {
            return;
        }

        Schema::create('event_session_registration_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('event_registration_id');
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('registration_version');
            $table->string('action', 16);
            $table->string('idempotency_key', 191);
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'registration_id', 'registration_version'],
                'uq_ev_session_reg_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'uq_ev_session_reg_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'session_id', 'created_at', 'id'],
                'idx_ev_session_reg_history_session',
            );

            $table->foreign('tenant_id', 'fk_ev_session_reg_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_session_reg_hist_event')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'session_id'],
                'fk_ev_session_reg_hist_session',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_sessions')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'event_id',
                    'session_id',
                    'registration_id',
                    'user_id',
                    'event_registration_id',
                ],
                'fk_ev_session_reg_hist_registration',
            )->references([
                'tenant_id',
                'event_id',
                'session_id',
                'id',
                'user_id',
                'event_registration_id',
            ])->on('event_session_registrations')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'event_registration_id', 'user_id'],
                'fk_ev_session_reg_hist_event_reg',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(['user_id', 'tenant_id'], 'fk_ev_session_reg_hist_user')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_session_reg_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installCheckConstraints(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_sessions' => [
                'chk_ev_session_capacity' => '(`capacity` IS NULL OR `capacity` >= 1)',
            ],
            'event_session_resources' => [
                'chk_ev_session_resource_type' => "`resource_type` IN ('link','document','slides','download','stream','recording')",
                'chk_ev_session_resource_visibility' => "`visibility` IN ('public','registered','staff')",
                'chk_ev_session_resource_position' => '`position` >= 1',
                'chk_ev_session_resource_title' => 'CHAR_LENGTH(TRIM(`title`)) > 0',
                'chk_ev_session_resource_url' => 'CHAR_LENGTH(`url_ciphertext`) > 0',
                'chk_ev_session_resource_media' => "(`resource_type` NOT IN ('stream','recording') OR `visibility` IN ('registered','staff'))",
            ],
            'event_session_registrations' => [
                'chk_ev_session_reg_version' => '`version` >= 1',
                'chk_ev_session_reg_status' => "`status` IN ('registered','withdrawn')",
                'chk_ev_session_reg_state' => "((`status` = 'registered' AND `registered_at` IS NOT NULL AND `withdrawn_at` IS NULL) OR (`status` = 'withdrawn' AND `registered_at` IS NOT NULL AND `withdrawn_at` IS NOT NULL))",
            ],
            'event_session_registration_history' => [
                'chk_ev_session_reg_hist_version' => '`registration_version` >= 1',
                'chk_ev_session_reg_hist_action' => "`action` IN ('registered','withdrawn')",
            ],
        ];

        foreach ($checks as $table => $tableChecks) {
            foreach ($tableChecks as $name => $expression) {
                if (! $this->constraintExists($table, $name)) {
                    DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
                }
            }
        }
    }

    private function installValidationAndImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createTrigger(
            self::HISTORY_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_session_registration_history` FOR EACH ROW '
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_history_immutable'",
        );
        $this->createTrigger(
            self::HISTORY_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_session_registration_history` FOR EACH ROW '
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_registration_history_immutable'",
        );
        $validation = "BEGIN IF NEW.`status` = 'registered' AND (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`event_registration_id` AND `user_id` = NEW.`user_id` AND `registration_state` = 'confirmed') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_confirmed_registration_required'; END IF; END";
        $this->createTrigger(
            self::REGISTRATION_INSERT_TRIGGER,
            'BEFORE INSERT ON `event_session_registrations` FOR EACH ROW ' . $validation,
        );
        $this->createTrigger(
            self::REGISTRATION_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_session_registrations` FOR EACH ROW ' . $validation,
        );
    }

    private function createTrigger(string $name, string $definition): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$definition}");
        }
    }

    private function dropTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            self::HISTORY_UPDATE_TRIGGER,
            self::HISTORY_DELETE_TRIGGER,
            self::REGISTRATION_INSERT_TRIGGER,
            self::REGISTRATION_UPDATE_TRIGGER,
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    private function containsDurableEvidence(): bool
    {
        foreach (self::OWNED_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('event_sessions')
            && Schema::hasColumn('event_sessions', 'capacity')
            && DB::table('event_sessions')->whereNotNull('capacity')->exists();
    }

    private function hasDependentSchema(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->whereNotIn('TABLE_NAME', self::OWNED_TABLES)
            ->where(static function ($query): void {
                $query->where(static function ($resource): void {
                    $resource->where('REFERENCED_TABLE_NAME', 'event_session_resources')
                        ->where('UNIQUE_CONSTRAINT_NAME', self::RESOURCE_SCOPE_INDEX);
                })->orWhere(static function ($registration): void {
                    $registration->where('REFERENCED_TABLE_NAME', 'event_session_registrations')
                        ->where('UNIQUE_CONSTRAINT_NAME', self::REGISTRATION_SCOPE_INDEX);
                });
            })
            ->exists();
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }
};
