<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add ON DELETE CASCADE to listing_reports.listing_id foreign key.
 * Ensures reports are automatically removed when their parent listing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listing_reports')) {
            return;
        }

        Schema::table('listing_reports', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('listing_reports')) {
            return;
        }

        Schema::table('listing_reports', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
            $table->foreign('listing_id')->references('id')->on('listings');
        });
    }
};
