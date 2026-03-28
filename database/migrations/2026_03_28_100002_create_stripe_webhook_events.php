<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the stripe_webhook_events table for idempotent webhook processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stripe_webhook_events')) {
            Schema::create('stripe_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 255);
                $table->string('event_type', 100);
                $table->string('status', 20)->default('processing'); // processing, processed, failed
                $table->timestamp('processed_at')->useCurrent();

                $table->unique('event_id', 'uk_event_id');
                $table->index('event_type', 'idx_event_type');
                $table->index('status', 'idx_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
