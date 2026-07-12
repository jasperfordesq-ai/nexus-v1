<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class GroupFormsAndFilesMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000040_harden_group_forms_and_files.php';

    public function test_down_is_locked_against_destructive_schema_or_data_changes(): void
    {
        Schema::shouldReceive('hasColumn')->never();
        Schema::shouldReceive('table')->never();
        DB::shouldReceive('table')->never();
        DB::shouldReceive('statement')->never();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('expand-only');
        $this->migration()->down();
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
