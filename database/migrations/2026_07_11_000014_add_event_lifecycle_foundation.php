<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Support\Events\EventLifecycleCompatibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EVENT_LIFECYCLE_INDEX = 'idx_events_tenant_lifecycle_start';
    private const HISTORY_UPDATE_TRIGGER = 'trg_event_status_history_no_update';
    private const HISTORY_DELETE_TRIGGER = 'trg_event_status_history_no_delete';

    /**
     * Expand and backfill the canonical dual-axis Event lifecycle.
     *
     * All new event columns remain nullable for rolling-deploy compatibility.
     * A NULL axis may be resolved from a known legacy state, but any unknown or
     * conflicting stored value aborts migration rather than being published by
     * assumption.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $this->assertLegacyStatusesKnown();
        $this->addEventColumns();
        $this->assertExistingAxesConsistent();
        $this->createHistoryTable();
        $this->backfillCanonicalAxes();

        if (! Schema::hasIndex('events', self::EVENT_LIFECYCLE_INDEX)) {
            Schema::table('events', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'publication_status', 'operational_status', 'start_time', 'id'],
                    self::EVENT_LIFECYCLE_INDEX,
                );
            });
        }

        $this->installHistoryImmutabilityTriggers();
    }

    public function down(): void
    {
        $this->dropHistoryImmutabilityTriggers();
        Schema::dropIfExists('event_status_history');

        if (! Schema::hasTable('events')) {
            return;
        }
        if (Schema::hasIndex('events', self::EVENT_LIFECYCLE_INDEX)) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropIndex(self::EVENT_LIFECYCLE_INDEX);
            });
        }

        $columns = array_values(array_filter([
            'publication_status',
            'operational_status',
            'lifecycle_version',
            'publication_status_changed_at',
            'publication_status_changed_by',
            'operational_status_changed_at',
            'operational_status_changed_by',
            'lifecycle_reason',
            'moderation_submitted_at',
            'moderation_submitted_by',
            'moderated_at',
            'moderated_by',
            'moderation_reason',
        ], static fn (string $column): bool => Schema::hasColumn('events', $column)));

        if ($columns !== []) {
            Schema::table('events', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function addEventColumns(): void
    {
        $columns = [
            'publication_status',
            'operational_status',
            'lifecycle_version',
            'publication_status_changed_at',
            'publication_status_changed_by',
            'operational_status_changed_at',
            'operational_status_changed_by',
            'lifecycle_reason',
            'moderation_submitted_at',
            'moderation_submitted_by',
            'moderated_at',
            'moderated_by',
            'moderation_reason',
        ];
        $missing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => ! Schema::hasColumn('events', $column),
        ));
        if ($missing === []) {
            return;
        }

        Schema::table('events', function (Blueprint $table) use ($missing): void {
            if (in_array('publication_status', $missing, true)) {
                $table->string('publication_status', 32)->nullable()->after('status')
                    ->comment('Canonical editorial/publication lifecycle state');
            }
            if (in_array('operational_status', $missing, true)) {
                $table->string('operational_status', 32)->nullable()->after('publication_status')
                    ->comment('Canonical scheduling/delivery lifecycle state');
            }
            if (in_array('lifecycle_version', $missing, true)) {
                $table->unsignedBigInteger('lifecycle_version')->nullable()->after('operational_status')
                    ->comment('Monotonic version for lifecycle history and outbox ordering');
            }
            if (in_array('publication_status_changed_at', $missing, true)) {
                $table->timestamp('publication_status_changed_at')->nullable()->after('lifecycle_version');
            }
            if (in_array('publication_status_changed_by', $missing, true)) {
                $table->integer('publication_status_changed_by')->nullable()->after('publication_status_changed_at');
            }
            if (in_array('operational_status_changed_at', $missing, true)) {
                $table->timestamp('operational_status_changed_at')->nullable()->after('publication_status_changed_by');
            }
            if (in_array('operational_status_changed_by', $missing, true)) {
                $table->integer('operational_status_changed_by')->nullable()->after('operational_status_changed_at');
            }
            if (in_array('lifecycle_reason', $missing, true)) {
                $table->text('lifecycle_reason')->nullable()->after('operational_status_changed_by');
            }
            if (in_array('moderation_submitted_at', $missing, true)) {
                $table->timestamp('moderation_submitted_at')->nullable()->after('lifecycle_reason');
            }
            if (in_array('moderation_submitted_by', $missing, true)) {
                $table->integer('moderation_submitted_by')->nullable()->after('moderation_submitted_at');
            }
            if (in_array('moderated_at', $missing, true)) {
                $table->timestamp('moderated_at')->nullable()->after('moderation_submitted_by');
            }
            if (in_array('moderated_by', $missing, true)) {
                $table->integer('moderated_by')->nullable()->after('moderated_at');
            }
            if (in_array('moderation_reason', $missing, true)) {
                $table->text('moderation_reason')->nullable()->after('moderated_by');
            }
        });
    }

    private function createHistoryTable(): void
    {
        if (Schema::hasTable('event_status_history')) {
            return;
        }

        Schema::create('event_status_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('lifecycle_version');
            $table->string('from_publication_status', 32);
            $table->string('to_publication_status', 32);
            $table->string('from_operational_status', 32);
            $table->string('to_operational_status', 32);
            $table->string('from_legacy_status', 32);
            $table->string('to_legacy_status', 32);
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'event_id', 'lifecycle_version'],
                'uq_event_status_history_version',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_status_history_event',
            );
            $table->index(
                ['tenant_id', 'actor_user_id', 'created_at'],
                'idx_event_status_history_actor',
            );
        });
    }

    private function assertLegacyStatusesKnown(): void
    {
        foreach (DB::table('events')->distinct()->pluck('status') as $status) {
            if ($status !== null && ! is_string($status)) {
                throw new UnexpectedValueException('event_lifecycle_unknown_legacy_status');
            }
            $legacyStatus = is_string($status) ? $status : null;
            EventPublicationState::fromLegacyStatus($legacyStatus);
            EventOperationalState::fromLegacyStatus($legacyStatus);
        }
    }

    private function assertExistingAxesConsistent(): void
    {
        DB::table('events')
            ->select(['id', 'status', 'publication_status', 'operational_status'])
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    EventLifecycleCompatibility::resolve(
                        $this->storedString($event->publication_status, 'event_lifecycle_unknown_publication_state'),
                        $this->storedString($event->operational_status, 'event_lifecycle_unknown_operational_state'),
                        $this->storedString($event->status, 'event_lifecycle_unknown_legacy_status'),
                    );
                }
            });
    }

    private function backfillCanonicalAxes(): void
    {
        DB::table('events')
            ->whereNull('publication_status')
            ->where('status', 'draft')
            ->update(['publication_status' => EventPublicationState::Draft->value]);
        DB::table('events')
            ->whereNull('publication_status')
            ->where(static function ($query): void {
                $query->whereNull('status')
                    ->orWhereIn('status', ['active', 'cancelled', 'completed']);
            })
            ->update(['publication_status' => EventPublicationState::Published->value]);

        DB::table('events')
            ->whereNull('operational_status')
            ->where(static function ($query): void {
                $query->whereNull('status')->orWhereIn('status', ['active', 'draft']);
            })
            ->update(['operational_status' => EventOperationalState::Scheduled->value]);
        DB::table('events')
            ->whereNull('operational_status')
            ->where('status', 'cancelled')
            ->update(['operational_status' => EventOperationalState::Cancelled->value]);
        DB::table('events')
            ->whereNull('operational_status')
            ->where('status', 'completed')
            ->update(['operational_status' => EventOperationalState::Completed->value]);
        DB::table('events')
            ->whereNull('lifecycle_version')
            ->update(['lifecycle_version' => 0]);
    }

    private function installHistoryImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('event_status_history')) {
            return;
        }

        if (! $this->triggerExists(self::HISTORY_UPDATE_TRIGGER)) {
            DB::unprepared(
                'CREATE TRIGGER `' . self::HISTORY_UPDATE_TRIGGER . '` '
                . 'BEFORE UPDATE ON `event_status_history` FOR EACH ROW '
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_status_history_immutable'"
            );
        }
        if (! $this->triggerExists(self::HISTORY_DELETE_TRIGGER)) {
            DB::unprepared(
                'CREATE TRIGGER `' . self::HISTORY_DELETE_TRIGGER . '` '
                . 'BEFORE DELETE ON `event_status_history` FOR EACH ROW '
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_status_history_immutable'"
            );
        }
    }

    private function dropHistoryImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::HISTORY_UPDATE_TRIGGER . '`');
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::HISTORY_DELETE_TRIGGER . '`');
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    private function storedString(mixed $value, string $errorCode): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException($errorCode);
        }

        return $value;
    }
};
