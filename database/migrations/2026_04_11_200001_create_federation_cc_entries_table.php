<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create federation_cc_entries table for Credit Commons double-entry ledger views.
 *
 * CC transactions have multiple Entry objects per transaction (payer/payee/quant).
 * This table stores entries generated from Nexus single-entry transactions,
 * enabling CC-compatible /entries endpoints.
 *
 * Also creates federation_cc_node_config for storing this node's CC identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('federation_cc_entries')) {
            Schema::create('federation_cc_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->uuid('transaction_uuid')->index();
                $table->unsignedBigInteger('federation_transaction_id')->nullable()->index()
                    ->comment('FK to federation_transactions.id (nullable for relay entries)');
                $table->string('payer', 100)->comment('CC account path (e.g., node-slug/username)');
                $table->string('payee', 100)->comment('CC account path');
                $table->decimal('quant', 12, 4)->comment('Amount in CC units');
                $table->text('description')->nullable();
                $table->char('state', 1)->default('P')->comment('CC state: P/V/C/E/X');
                $table->string('workflow', 50)->nullable()->comment('CC workflow code');
                $table->string('author', 100)->nullable()->comment('CC account path of entry creator');
                $table->json('metadata')->nullable()->comment('CC metadata (arbitrary key-value)');
                $table->timestamp('written_at')->nullable()->comment('When entry was permanently written');
                $table->timestamps();

                $table->index(['tenant_id', 'state']);
                $table->index(['payer', 'tenant_id']);
                $table->index(['payee', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('federation_cc_node_config')) {
            Schema::create('federation_cc_node_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->string('node_slug', 50)->comment('CC node identifier (3-15 chars, lowercase)');
                $table->string('display_name', 100)->nullable();
                $table->string('currency_format', 100)->default('<quantity> hours')
                    ->comment('CC currency display format');
                $table->decimal('exchange_rate', 10, 6)->default(1.0)
                    ->comment('Exchange rate relative to parent node (1.0 = same unit)');
                $table->unsignedInteger('validated_window')->default(300)
                    ->comment('Seconds a validated transaction remains valid before timeout');
                $table->string('parent_node_url', 500)->nullable()
                    ->comment('URL of parent CC node (null = root/standalone)');
                $table->string('parent_node_slug', 50)->nullable();
                $table->text('last_hash')->nullable()->comment('Last hashchain hash for this node');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_cc_entries');
        Schema::dropIfExists('federation_cc_node_config');
    }
};
