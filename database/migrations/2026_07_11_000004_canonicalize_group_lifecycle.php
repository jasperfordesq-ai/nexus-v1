<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use App\Enums\GroupStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHECK_NAME = 'chk_groups_status_canonical';

    public function up(): void
    {
        if (! Schema::hasTable('groups') || ! Schema::hasColumn('groups', 'status')) {
            return;
        }

        $unknown = [];
        $combinations = DB::table('groups')
            ->select(['status', 'is_active', DB::raw('COUNT(*) AS total')])
            ->groupBy('status', 'is_active')
            ->get();

        foreach ($combinations as $combination) {
            try {
                GroupStatus::normalize(
                    is_string($combination->status) ? $combination->status : null,
                    (bool) $combination->is_active,
                );
            } catch (InvalidArgumentException) {
                $unknown[] = [
                    'status' => $combination->status,
                    'is_active' => (int) $combination->is_active,
                    'total' => (int) $combination->total,
                ];
            }
        }

        if ($unknown !== []) {
            throw new RuntimeException(
                'Unknown group lifecycle values require manual resolution: '
                . json_encode($unknown, JSON_THROW_ON_ERROR),
            );
        }

        DB::transaction(function (): void {
            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('pending_review', 'pending_approval', 'pending', 'draft')")
                ->update(['status' => GroupStatus::PendingReview->value]);

            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'dormant'")
                ->update(['status' => GroupStatus::Dormant->value]);

            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('archived', 'inactive', 'deleted')")
                ->update(['status' => GroupStatus::Archived->value]);

            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'rejected'")
                ->update(['status' => GroupStatus::Rejected->value]);

            // The April expand migration defaulted old inactive rows to active;
            // preserve the legacy boolean evidence while repairing that drift.
            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('', 'active')")
                ->where('is_active', false)
                ->update(['status' => GroupStatus::Archived->value]);

            DB::table('groups')
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('', 'active')")
                ->where('is_active', true)
                ->update(['status' => GroupStatus::Active->value]);

            DB::table('groups')
                ->where('status', GroupStatus::Active->value)
                ->update(['is_active' => true]);

            DB::table('groups')
                ->where('status', '!=', GroupStatus::Active->value)
                ->update(['is_active' => false]);
        });

        $inconsistent = DB::table('groups')
            ->where(function ($query): void {
                $query->where(function ($active): void {
                    $active->where('status', GroupStatus::Active->value)
                        ->where('is_active', false);
                })->orWhere(function ($inactive): void {
                    $inactive->where('status', '!=', GroupStatus::Active->value)
                        ->where('is_active', true);
                });
            })
            ->count();

        if ($inconsistent > 0) {
            throw new RuntimeException("Group lifecycle reconciliation left {$inconsistent} inconsistent rows.");
        }

        $this->addAllowedStatusCheck();
    }

    public function down(): void
    {
        if (! Schema::hasTable('groups')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true) || ! $this->checkExists()) {
            return;
        }

        DB::statement('ALTER TABLE `groups` DROP CONSTRAINT `' . self::CHECK_NAME . '`');
    }

    private function addAllowedStatusCheck(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true) || $this->checkExists()) {
            return;
        }

        $quoted = implode(', ', array_map(
            static fn (string $status): string => DB::getPdo()->quote($status),
            GroupStatus::values(),
        ));

        DB::statement(
            'ALTER TABLE `groups` ADD CONSTRAINT `' . self::CHECK_NAME . '` CHECK (`status` IN (' . $quoted . '))',
        );
    }

    private function checkExists(): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'groups')
            ->where('CONSTRAINT_NAME', self::CHECK_NAME)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
    }
};
