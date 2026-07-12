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
    private const DELIVERY_ACTION_CHECK = 'chk_event_fed_delivery_action';
    private const DELIVERY_STATUS_CHECK = 'chk_event_fed_delivery_status';
    private const DELIVERY_ATTEMPTS_CHECK = 'chk_event_fed_delivery_attempts';
    private const DELIVERY_CLAIM_CHECK = 'chk_event_fed_delivery_claim';
    private const DELIVERY_TERMINAL_CHECK = 'chk_event_fed_delivery_terminal';
    private const INBOUND_ACTION_CHECK = 'chk_federation_events_source_action';
    private const INBOUND_TOMBSTONE_CHECK = 'chk_federation_events_tombstone';
    private const INBOUND_DISCOVERY_INDEX = 'idx_federation_events_current';

    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('federation_external_partners')
            || ! Schema::hasTable('federation_events')) {
            return;
        }

        $this->addFederationVersion();
        $this->createDeliveryLedger();
        $this->expandInboundProjection();
        $this->installChecks();
    }

    public function down(): void
    {
        if ($this->containsDurableRecords()) {
            throw new LogicException('event_federation_rollback_refused_records_exist');
        }

        Schema::dropIfExists('event_federation_deliveries');

        if (! Schema::hasTable('federation_events')) {
            if (Schema::hasTable('events') && Schema::hasColumn('events', 'federation_version')) {
                Schema::table('events', function (Blueprint $table): void {
                    $table->dropColumn('federation_version');
                });
            }

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $this->dropCheckIfPresent('federation_events', self::INBOUND_ACTION_CHECK);
            $this->dropCheckIfPresent('federation_events', self::INBOUND_TOMBSTONE_CHECK);
        }
        if (Schema::hasIndex('federation_events', self::INBOUND_DISCOVERY_INDEX)) {
            Schema::table('federation_events', function (Blueprint $table): void {
                $table->dropIndex(self::INBOUND_DISCOVERY_INDEX);
            });
        }
        $columns = array_values(array_filter([
            'payload_schema_version',
            'source_aggregate_version',
            'source_calendar_version',
            'source_action',
            'source_payload_hash',
            'source_occurred_at',
            'is_tombstone',
            'tombstoned_at',
            'tombstone_reason',
            'last_received_at',
            'replay_count',
            'last_replayed_at',
            'stale_count',
            'last_stale_at',
            'last_stale_hash',
            'conflict_count',
            'last_conflict_at',
            'last_conflict_hash',
        ], static fn (string $column): bool => Schema::hasColumn('federation_events', $column)));

        if ($columns !== []) {
            Schema::table('federation_events', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'federation_version')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('federation_version');
            });
        }
    }

    private function addFederationVersion(): void
    {
        if (! Schema::hasColumn('events', 'federation_version')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->unsignedBigInteger('federation_version')->default(1)
                    ->comment('Monotonic revision for every federation-visible mutation');
            });
        }

        $sources = ['`federation_version`', '1'];
        if (Schema::hasColumn('events', 'lifecycle_version')) {
            $sources[] = 'COALESCE(`lifecycle_version`, 0)';
        }
        if (Schema::hasColumn('events', 'calendar_sequence')) {
            $sources[] = 'COALESCE(`calendar_sequence`, 0)';
        }
        $backfill = 'GREATEST(' . implode(', ', $sources) . ')';
        DB::table('events')
            ->whereRaw("`federation_version` < {$backfill}")
            ->update(['federation_version' => DB::raw($backfill)]);
    }

    private function createDeliveryLedger(): void
    {
        if (Schema::hasTable('event_federation_deliveries')) {
            return;
        }

        Schema::create('event_federation_deliveries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('external_partner_id');
            $table->unsignedSmallInteger('payload_schema_version');
            $table->unsignedBigInteger('event_aggregate_version');
            $table->unsignedBigInteger('event_calendar_version')->default(0);
            $table->string('action', 16);
            $table->char('idempotency_key', 64);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'external_partner_id', 'idempotency_key'],
                'uq_event_fed_delivery_idempotency',
            );
            $table->unique(
                [
                    'tenant_id',
                    'event_id',
                    'external_partner_id',
                    'payload_schema_version',
                    'event_aggregate_version',
                    'event_calendar_version',
                ],
                'uq_event_fed_delivery_version',
            );
            $table->index(
                ['status', 'available_at', 'next_attempt_at', 'id'],
                'idx_event_fed_delivery_claim',
            );
            $table->index(
                ['tenant_id', 'external_partner_id', 'status', 'id'],
                'idx_event_fed_delivery_partner',
            );
            $table->index(
                ['tenant_id', 'event_id', 'external_partner_id', 'event_aggregate_version', 'id'],
                'idx_event_fed_delivery_event',
            );

            // Deliberately no foreign keys: this is durable delivery evidence.
            // Event archival/deletion and partner removal must not erase it.
        });
    }

    private function expandInboundProjection(): void
    {
        $definitions = [
            'payload_schema_version',
            'source_aggregate_version',
            'source_calendar_version',
            'source_action',
            'source_payload_hash',
            'source_occurred_at',
            'is_tombstone',
            'tombstoned_at',
            'tombstone_reason',
            'last_received_at',
            'replay_count',
            'last_replayed_at',
            'stale_count',
            'last_stale_at',
            'last_stale_hash',
            'conflict_count',
            'last_conflict_at',
            'last_conflict_hash',
        ];
        $missing = array_values(array_filter(
            $definitions,
            static fn (string $column): bool => ! Schema::hasColumn('federation_events', $column),
        ));

        if ($missing !== []) {
            Schema::table('federation_events', function (Blueprint $table) use ($missing): void {
                if (in_array('payload_schema_version', $missing, true)) {
                    $table->unsignedSmallInteger('payload_schema_version')->default(0)
                        ->comment('Zero marks a legacy unversioned projection');
                }
                if (in_array('source_aggregate_version', $missing, true)) {
                    $table->unsignedBigInteger('source_aggregate_version')->default(0);
                }
                if (in_array('source_calendar_version', $missing, true)) {
                    $table->unsignedBigInteger('source_calendar_version')->default(0);
                }
                if (in_array('source_action', $missing, true)) {
                    $table->string('source_action', 16)->default('upsert');
                }
                if (in_array('source_payload_hash', $missing, true)) {
                    $table->char('source_payload_hash', 64)->nullable();
                }
                if (in_array('source_occurred_at', $missing, true)) {
                    $table->dateTime('source_occurred_at')->nullable();
                }
                if (in_array('is_tombstone', $missing, true)) {
                    $table->boolean('is_tombstone')->default(false);
                }
                if (in_array('tombstoned_at', $missing, true)) {
                    $table->timestamp('tombstoned_at')->nullable()
                        ->comment('Accepted tombstone time while the current source action is tombstone');
                }
                if (in_array('tombstone_reason', $missing, true)) {
                    $table->string('tombstone_reason', 64)->nullable();
                }
                if (in_array('last_received_at', $missing, true)) {
                    $table->timestamp('last_received_at')->nullable();
                }
                if (in_array('replay_count', $missing, true)) {
                    $table->unsignedInteger('replay_count')->default(0);
                }
                if (in_array('last_replayed_at', $missing, true)) {
                    $table->timestamp('last_replayed_at')->nullable();
                }
                if (in_array('stale_count', $missing, true)) {
                    $table->unsignedInteger('stale_count')->default(0);
                }
                if (in_array('last_stale_at', $missing, true)) {
                    $table->timestamp('last_stale_at')->nullable();
                }
                if (in_array('last_stale_hash', $missing, true)) {
                    $table->char('last_stale_hash', 64)->nullable();
                }
                if (in_array('conflict_count', $missing, true)) {
                    $table->unsignedInteger('conflict_count')->default(0);
                }
                if (in_array('last_conflict_at', $missing, true)) {
                    $table->timestamp('last_conflict_at')->nullable();
                }
                if (in_array('last_conflict_hash', $missing, true)) {
                    $table->char('last_conflict_hash', 64)->nullable();
                }
            });
        }

        if (! Schema::hasIndex('federation_events', self::INBOUND_DISCOVERY_INDEX)) {
            Schema::table('federation_events', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'is_tombstone', 'starts_at', 'id'],
                    self::INBOUND_DISCOVERY_INDEX,
                );
            });
        }
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->addCheckIfMissing(
            'event_federation_deliveries',
            self::DELIVERY_ACTION_CHECK,
            '(`action` IN (\'upsert\', \'tombstone\'))',
        );
        $this->addCheckIfMissing(
            'event_federation_deliveries',
            self::DELIVERY_STATUS_CHECK,
            '(`status` IN (\'pending\', \'retry\', \'processing\', \'delivered\', \'dead_letter\'))',
        );
        $this->addCheckIfMissing(
            'event_federation_deliveries',
            self::DELIVERY_ATTEMPTS_CHECK,
            '(`payload_schema_version` > 0 AND `attempts` <= 5)',
        );
        $this->addCheckIfMissing(
            'event_federation_deliveries',
            self::DELIVERY_CLAIM_CHECK,
            '((`status` = \'processing\' AND `claim_token` IS NOT NULL AND `claimed_at` IS NOT NULL)'
                . ' OR (`status` <> \'processing\' AND `claim_token` IS NULL AND `claimed_at` IS NULL))',
        );
        $this->addCheckIfMissing(
            'event_federation_deliveries',
            self::DELIVERY_TERMINAL_CHECK,
            '((`status` <> \'delivered\' OR `delivered_at` IS NOT NULL)'
                . ' AND (`status` <> \'dead_letter\' OR `dead_lettered_at` IS NOT NULL))',
        );
        $this->addCheckIfMissing(
            'federation_events',
            self::INBOUND_ACTION_CHECK,
            '(`source_action` IN (\'upsert\', \'tombstone\'))',
        );
        $this->addCheckIfMissing(
            'federation_events',
            self::INBOUND_TOMBSTONE_CHECK,
            '((`source_action` = \'tombstone\' AND `is_tombstone` = 1'
                . ' AND `tombstoned_at` IS NOT NULL AND `tombstone_reason` IS NOT NULL'
                . ' AND `tombstone_reason` IN (\'visibility_withdrawn\', \'unpublished\','
                . ' \'cancelled\', \'archived\', \'deleted\'))'
                . ' OR (`source_action` = \'upsert\' AND `is_tombstone` = 0'
                . ' AND `tombstoned_at` IS NULL AND `tombstone_reason` IS NULL))',
        );
    }

    private function addCheckIfMissing(string $table, string $name, string $expression): void
    {
        if (! $this->checkExists($table, $name)) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK {$expression}");
        }
    }

    private function dropCheckIfPresent(string $table, string $name): void
    {
        if ($this->checkExists($table, $name)) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$name}`");
        }
    }

    private function checkExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
    }

    private function containsDurableRecords(): bool
    {
        if (Schema::hasTable('event_federation_deliveries')
            && DB::table('event_federation_deliveries')->exists()) {
            return true;
        }
        if (! Schema::hasTable('federation_events')
            || ! Schema::hasColumn('federation_events', 'source_payload_hash')) {
            return false;
        }

        return DB::table('federation_events')
            ->where(static function ($query): void {
                $query->whereNotNull('source_payload_hash')
                    ->orWhere('is_tombstone', true)
                    ->orWhere('replay_count', '>', 0)
                    ->orWhere('stale_count', '>', 0)
                    ->orWhere('conflict_count', '>', 0);
            })
            ->exists();
    }
};
