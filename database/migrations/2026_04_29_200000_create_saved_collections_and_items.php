<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('saved_collections')) {
            Schema::create('saved_collections', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Match users.id and tenants.id which are int(11) signed on this DB
                $table->integer('user_id');
                $table->integer('tenant_id');
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->string('color', 16)->default('#6366f1');
                $table->string('icon', 64)->default('bookmark');
                $table->unsignedInteger('items_count')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'name'], 'saved_collections_user_name_unique');
                $table->index(['user_id', 'tenant_id'], 'saved_collections_user_tenant_idx');
                $table->index('tenant_id', 'saved_collections_tenant_idx');

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('saved_items')) {
            Schema::create('saved_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('collection_id');
                // Match users.id and tenants.id which are int(11) signed on this DB
                $table->integer('user_id');
                $table->integer('tenant_id');
                $table->string('item_type', 32);
                // item_id is polymorphic — use bigInt to cover all referenced tables
                $table->unsignedBigInteger('item_id');
                $table->string('note', 500)->nullable();
                $table->timestamp('saved_at')->useCurrent();

                $table->unique(['collection_id', 'item_type', 'item_id'], 'saved_items_unique_per_collection');
                $table->index(['user_id', 'item_type', 'item_id'], 'saved_items_user_item_idx');
                $table->index('tenant_id', 'saved_items_tenant_idx');

                $table->foreign('collection_id')->references('id')->on('saved_collections')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_items');
        Schema::dropIfExists('saved_collections');
    }
};
