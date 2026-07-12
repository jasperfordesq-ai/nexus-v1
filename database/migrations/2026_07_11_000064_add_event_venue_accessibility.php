<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $needsIndex = ! Schema::hasIndex('events', 'idx_events_tenant_step_free_start');
        Schema::table('events', static function (Blueprint $table) use ($needsIndex): void {
            if (! Schema::hasColumn('events', 'accessibility_step_free')) {
                $table->boolean('accessibility_step_free')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('events', 'accessibility_toilet')) {
                $table->boolean('accessibility_toilet')->nullable()->after('accessibility_step_free');
            }
            if (! Schema::hasColumn('events', 'accessibility_hearing_loop')) {
                $table->boolean('accessibility_hearing_loop')->nullable()->after('accessibility_toilet');
            }
            if (! Schema::hasColumn('events', 'accessibility_quiet_space')) {
                $table->boolean('accessibility_quiet_space')->nullable()->after('accessibility_hearing_loop');
            }
            if (! Schema::hasColumn('events', 'accessibility_seating')) {
                $table->boolean('accessibility_seating')->nullable()->after('accessibility_quiet_space');
            }
            if (! Schema::hasColumn('events', 'accessibility_parking')) {
                $table->boolean('accessibility_parking')->nullable()->after('accessibility_seating');
            }
            if (! Schema::hasColumn('events', 'accessibility_parking_details')) {
                $table->string('accessibility_parking_details', 1000)->nullable()->after('accessibility_parking');
            }
            if (! Schema::hasColumn('events', 'accessibility_transit_details')) {
                $table->string('accessibility_transit_details', 1000)->nullable()->after('accessibility_parking_details');
            }
            if (! Schema::hasColumn('events', 'accessibility_assistance_contact')) {
                $table->string('accessibility_assistance_contact', 500)->nullable()->after('accessibility_transit_details');
            }
            if (! Schema::hasColumn('events', 'accessibility_notes')) {
                $table->text('accessibility_notes')->nullable()->after('accessibility_assistance_contact');
            }

            if ($needsIndex) {
                $table->index(
                    ['tenant_id', 'accessibility_step_free', 'start_time'],
                    'idx_events_tenant_step_free_start',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $hasIndex = Schema::hasIndex('events', 'idx_events_tenant_step_free_start');
        Schema::table('events', static function (Blueprint $table) use ($hasIndex): void {
            if ($hasIndex) {
                $table->dropIndex('idx_events_tenant_step_free_start');
            }

            $columns = [
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
            foreach ($columns as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
