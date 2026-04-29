<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG45 — Click-and-collect pickup slots offered by a marketplace seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_pickup_slots')) {
            return;
        }

        Schema::create('marketplace_pickup_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('seller_id'); // FK -> marketplace_seller_profiles.id
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['seller_id', 'slot_start'], 'mpps_seller_start_idx');
            $table->index(['tenant_id', 'slot_start'], 'mpps_tenant_start_idx');

            $table->foreign('seller_id')
                ->references('id')->on('marketplace_seller_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_pickup_slots');
    }
};
