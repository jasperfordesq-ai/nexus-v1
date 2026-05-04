<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FEED_TARGET_TYPES = [
        'listing',
        'user',
        'message',
        'post',
        'comment',
        'story',
        'event',
        'poll',
        'goal',
        'review',
        'resource',
        'volunteer',
        'challenge',
        'job',
        'blog',
        'discussion',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('reports') || !Schema::hasColumn('reports', 'target_type')) {
            return;
        }

        $this->setReportTargetTypes(self::FEED_TARGET_TYPES);
    }

    public function down(): void
    {
        if (!Schema::hasTable('reports') || !Schema::hasColumn('reports', 'target_type')) {
            return;
        }

        $this->setReportTargetTypes(['listing', 'user', 'message', 'post', 'comment', 'story']);
    }

    private function setReportTargetTypes(array $targetTypes): void
    {
        $enum = implode(',', array_map(
            static fn (string $type): string => "'" . str_replace("'", "''", $type) . "'",
            $targetTypes
        ));

        DB::statement("ALTER TABLE `reports` MODIFY COLUMN `target_type` ENUM({$enum}) NOT NULL");
    }
};
