<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Durable, tenant-scoped retry ledger for podcast media deletion. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('podcast_media_cleanup_tasks')) {
            return;
        }

        Schema::create('podcast_media_cleanup_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('asset_key', 64);
            $table->string('kind', 32);
            $table->string('disk', 50)->nullable();
            $table->string('path', 1000);
            $table->unsignedBigInteger('source_episode_id')->nullable();
            $table->string('reason', 50);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'asset_key'], 'pod_cleanup_tenant_asset_unique');
            $table->index(
                ['status', 'available_at', 'updated_at', 'id'],
                'pod_cleanup_dispatch_idx'
            );
            $table->index(
                ['tenant_id', 'source_episode_id'],
                'pod_cleanup_episode_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_media_cleanup_tasks');
    }
};
