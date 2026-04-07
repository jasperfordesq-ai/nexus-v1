<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feed_posts') && !Schema::hasColumn('feed_posts', 'views_count')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->unsignedInteger('views_count')->default(0);
            });
        }

        if (!Schema::hasTable('post_views')) {
            Schema::create('post_views', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('post_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->timestamp('viewed_at')->useCurrent();

                $table->unique(['tenant_id', 'post_id', 'user_id'], 'post_views_user_unique');
                $table->unique(['tenant_id', 'post_id', 'ip_hash'], 'post_views_ip_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');

        if (Schema::hasTable('feed_posts') && Schema::hasColumn('feed_posts', 'views_count')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
    }
};
