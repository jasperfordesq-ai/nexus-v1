<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the laravel_migration_registry table.
 *
 * This table tracks which legacy SQL migration files (from /migrations/)
 * have been applied to the database. It bridges the old raw-SQL migration
 * workflow with Laravel's built-in migration system.
 *
 * Usage: php artisan legacy:migrate
 *
 * @see \App\Console\Commands\ImportLegacyMigrations
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laravel_migration_registry')) {
            return;
        }

        Schema::create('laravel_migration_registry', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255)->unique()->comment('Legacy SQL migration filename');
            $table->timestamp('applied_at')->useCurrent()->comment('When this migration was applied');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laravel_migration_registry');
    }
};
