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
 * stripe_webhook_events — add `status` column used by StripeWebhookController
 * for idempotency and the processing/processed/failed lifecycle. Without this
 * column, every webhook hits a column-not-found error on the first DB write.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stripe_webhook_events')) {
            return;
        }
        if (Schema::hasColumn('stripe_webhook_events', 'status')) {
            return;
        }

        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->enum('status', ['processing', 'processed', 'failed'])
                ->default('processing')
                ->after('event_type')
                ->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stripe_webhook_events') || !Schema::hasColumn('stripe_webhook_events', 'status')) {
            return;
        }

        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
