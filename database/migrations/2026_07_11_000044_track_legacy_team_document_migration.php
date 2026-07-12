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
        if (Schema::hasTable('team_documents') && ! Schema::hasColumn('team_documents', 'group_file_id')) {
            Schema::table('team_documents', function (Blueprint $table): void {
                $table->unsignedInteger('group_file_id')->nullable()->after('id');
            });
        }
        if (Schema::hasTable('team_documents') && ! Schema::hasColumn('team_documents', 'storage_migrated_at')) {
            Schema::table('team_documents', function (Blueprint $table): void {
                $table->timestamp('storage_migrated_at')->nullable()->after('group_file_id');
            });
        }
        if (Schema::hasTable('team_documents') && ! Schema::hasIndex('team_documents', 'idx_team_documents_group_file')) {
            Schema::table('team_documents', function (Blueprint $table): void {
                $table->index('group_file_id', 'idx_team_documents_group_file');
            });
        }

        if (! Schema::hasTable('group_private_storage_migrations')) {
            Schema::create('group_private_storage_migrations', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('group_id');
                $table->string('source_table', 32);
                $table->unsignedBigInteger('source_id');
                $table->string('asset_role', 32);
                $table->string('source_disk', 32);
                $table->string('source_path', 500);
                $table->string('target_disk', 32)->default('local');
                $table->string('target_path', 500);
                $table->char('sha256', 64);
                $table->unsignedBigInteger('bytes');
                $table->timestamp('migrated_at');
                $table->timestamp('source_deleted_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['source_table', 'source_id', 'asset_role'],
                    'uq_group_storage_migration_asset',
                );
                $table->index(
                    ['tenant_id', 'source_deleted_at', 'id'],
                    'idx_group_storage_migration_cleanup',
                );
                $table->index(
                    ['source_disk', 'source_path'],
                    'idx_group_storage_migration_source',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_private_storage_migrations');

        if (! Schema::hasTable('team_documents')) {
            return;
        }

        if (Schema::hasIndex('team_documents', 'idx_team_documents_group_file')) {
            Schema::table('team_documents', function (Blueprint $table): void {
                $table->dropIndex('idx_team_documents_group_file');
            });
        }
        foreach (['storage_migrated_at', 'group_file_id'] as $column) {
            if (Schema::hasColumn('team_documents', $column)) {
                Schema::table('team_documents', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
