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
    private const OCCURRENCE_KEY_INDEX = 'uq_events_tenant_occurrence';

    /**
     * Expand-only foundation for the canonical event time and occurrence model.
     *
     * The columns deliberately remain nullable for rolling-deploy compatibility.
     * Existing concrete rows receive an ID-derived compatibility key; recurring
     * templates keep a NULL key because they are not registrable occurrences.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $addTimezone = ! Schema::hasColumn('events', 'timezone');
        $addTimezoneSource = ! Schema::hasColumn('events', 'timezone_source');
        $addAllDay = ! Schema::hasColumn('events', 'all_day');
        $addOccurrenceKey = ! Schema::hasColumn('events', 'occurrence_key');
        $addRecurrenceEngine = ! Schema::hasColumn('events', 'recurrence_engine');
        $addRecurrenceEngineVersion = ! Schema::hasColumn('events', 'recurrence_engine_version');

        if ($addTimezone || $addTimezoneSource || $addAllDay || $addOccurrenceKey
            || $addRecurrenceEngine || $addRecurrenceEngineVersion) {
            Schema::table('events', function (Blueprint $table) use (
                $addTimezone,
                $addTimezoneSource,
                $addAllDay,
                $addOccurrenceKey,
                $addRecurrenceEngine,
                $addRecurrenceEngineVersion,
            ): void {
                if ($addTimezone) {
                    $table->string('timezone', 64)->nullable()->after('end_time')
                        ->comment('IANA timezone retained for display and recurrence semantics');
                }
                if ($addTimezoneSource) {
                    $table->string('timezone_source', 64)->nullable()->after('timezone')
                        ->comment('Timezone provenance/backfill status; non-tenant sources remain auditable');
                }
                if ($addAllDay) {
                    $table->boolean('all_day')->nullable()->after('timezone_source')
                        ->comment('Explicit all-day semantics; NULL is reserved for rolling-deploy compatibility');
                }
                if ($addOccurrenceKey) {
                    $table->string('occurrence_key', 191)->nullable()->after('occurrence_date')
                        ->comment('Stable identity for a concrete registrable occurrence; NULL for templates');
                }
                if ($addRecurrenceEngine) {
                    $table->string('recurrence_engine', 32)->nullable()->after('occurrence_key')
                        ->comment('Engine that materialised this recurrence tree or occurrence');
                }
                if ($addRecurrenceEngineVersion) {
                    $table->string('recurrence_engine_version', 32)->nullable()->after('recurrence_engine')
                        ->comment('Engine contract version used to materialise this row');
                }
            });
        }

        $this->backfillTimezones();

        DB::table('events')
            ->whereNull('all_day')
            ->update(['all_day' => false]);

        if (Schema::hasColumn('events', 'is_recurring_template')) {
            DB::table('events')
                ->where('is_recurring_template', 0)
                ->whereNull('occurrence_key')
                ->update(['occurrence_key' => DB::raw("CONCAT('legacy:event:', `id`)")]);
        } else {
            DB::table('events')
                ->whereNull('occurrence_key')
                ->update(['occurrence_key' => DB::raw("CONCAT('legacy:event:', `id`)")]);
        }

        if (Schema::hasColumn('events', 'parent_event_id') && Schema::hasColumn('events', 'is_recurring_template')) {
            $legacyRecurrenceRows = static fn () => DB::table('events')
                ->where(static function ($query): void {
                    $query->where('is_recurring_template', 1)
                        ->orWhereNotNull('parent_event_id');
                });

            $legacyRecurrenceRows()
                ->whereNull('recurrence_engine')
                ->update(['recurrence_engine' => 'legacy']);
            $legacyRecurrenceRows()
                ->whereNull('recurrence_engine_version')
                ->update(['recurrence_engine_version' => '1']);
        }

        if (! $this->indexExists(self::OCCURRENCE_KEY_INDEX)) {
            Schema::table('events', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'occurrence_key'], self::OCCURRENCE_KEY_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if ($this->indexExists(self::OCCURRENCE_KEY_INDEX)) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropUnique(self::OCCURRENCE_KEY_INDEX);
            });
        }

        $columns = array_values(array_filter([
            'timezone',
            'timezone_source',
            'all_day',
            'occurrence_key',
            'recurrence_engine',
            'recurrence_engine_version',
        ], static fn (string $column): bool => Schema::hasColumn('events', $column)));

        if ($columns !== []) {
            Schema::table('events', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function backfillTimezones(): void
    {
        $settings = collect();
        if (Schema::hasTable('tenant_settings')
            && Schema::hasColumn('tenant_settings', 'tenant_id')
            && Schema::hasColumn('tenant_settings', 'setting_key')
            && Schema::hasColumn('tenant_settings', 'setting_value')) {
            $settings = DB::table('tenant_settings')
                ->where('setting_key', 'general.timezone')
                ->get(['tenant_id', 'setting_value'])
                ->keyBy(static fn (object $row): int => (int) $row->tenant_id);
        }

        $appTimezone = trim((string) config('app.timezone', 'UTC'));
        $appTimezoneIsValid = $this->isIanaTimezone($appTimezone);
        $tenantIds = DB::table('events')->select('tenant_id')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantIdValue) {
            $tenantId = (int) $tenantIdValue;
            $settingExists = $settings->has($tenantId);
            $settingValue = $settingExists
                ? trim((string) $settings->get($tenantId)->setting_value)
                : '';

            if ($settingExists && $this->isIanaTimezone($settingValue)) {
                $timezone = $settingValue;
                $source = 'tenant_setting';
            } elseif ($appTimezoneIsValid) {
                $timezone = $appTimezone;
                $source = $settingExists
                    ? 'app_config_invalid_tenant_setting'
                    : 'app_config_missing_tenant_setting';
            } else {
                $timezone = 'UTC';
                $source = $settingExists
                    ? 'utc_fallback_invalid_tenant_setting'
                    : 'utc_fallback_missing_tenant_setting';
            }

            DB::table('events')
                ->where('tenant_id', $tenantId)
                ->whereNull('timezone')
                ->update([
                    'timezone' => $timezone,
                    'timezone_source' => $source,
                ]);

            // A partially completed rolling migration may have written the
            // timezone before its provenance. Preserve the value and make that
            // exceptional state visible to the integrity audit.
            DB::table('events')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('timezone')
                ->whereNull('timezone_source')
                ->update(['timezone_source' => 'preexisting_unverified']);
        }
    }

    private function isIanaTimezone(string $timezone): bool
    {
        if ($timezone === 'UTC') {
            return true;
        }

        static $identifiers = null;
        $identifiers ??= array_fill_keys(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true);

        return isset($identifiers[$timezone]);
    }

    private function indexExists(string $index): bool
    {
        return DB::select("SHOW INDEX FROM `events` WHERE Key_name = ?", [$index]) !== [];
    }
};
