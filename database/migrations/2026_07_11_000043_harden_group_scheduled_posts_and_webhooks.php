<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// G14: durable scheduled publishing and group webhook delivery state.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_scheduled_posts')) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement(
                    "ALTER TABLE group_scheduled_posts MODIFY status "
                    . "ENUM('scheduled','processing','published','cancelled','failed') "
                    . "NOT NULL DEFAULT 'scheduled'"
                );
            }

            // The maintained schema snapshot may already contain some or all
            // of this additive shape. Keep the migration safe both for an
            // existing deployment and for a schema-snapshot bootstrap.
            $columns = [
                'claim_token' => static fn (Blueprint $table) => $table->uuid('claim_token')->nullable()->after('status'),
                'claimed_at' => static fn (Blueprint $table) => $table->timestamp('claimed_at')->nullable()->after('claim_token'),
                'lease_expires_at' => static fn (Blueprint $table) => $table->timestamp('lease_expires_at')->nullable()->after('claimed_at'),
                'attempt_count' => static fn (Blueprint $table) => $table->unsignedSmallInteger('attempt_count')->default(0)->after('lease_expires_at'),
                'next_attempt_at' => static fn (Blueprint $table) => $table->timestamp('next_attempt_at')->nullable()->after('attempt_count'),
                'last_error_code' => static fn (Blueprint $table) => $table->string('last_error_code', 64)->nullable()->after('next_attempt_at'),
                'last_error_message' => static fn (Blueprint $table) => $table->string('last_error_message', 500)->nullable()->after('last_error_code'),
                'published_resource_type' => static fn (Blueprint $table) => $table->string('published_resource_type', 32)->nullable()->after('last_error_message'),
                'published_resource_id' => static fn (Blueprint $table) => $table->unsignedBigInteger('published_resource_id')->nullable()->after('published_resource_type'),
                'recurrence_parent_id' => static fn (Blueprint $table) => $table->unsignedInteger('recurrence_parent_id')->nullable()->after('published_resource_id'),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn('group_scheduled_posts', $column)) {
                    Schema::table('group_scheduled_posts', $definition);
                }
            }

            if (!Schema::hasIndex('group_scheduled_posts', 'uq_group_scheduled_claim_token')) {
                Schema::table('group_scheduled_posts', function (Blueprint $table): void {
                    $table->unique('claim_token', 'uq_group_scheduled_claim_token');
                });
            }
            if (!Schema::hasIndex('group_scheduled_posts', 'uq_group_scheduled_recurrence_parent')) {
                Schema::table('group_scheduled_posts', function (Blueprint $table): void {
                    $table->unique('recurrence_parent_id', 'uq_group_scheduled_recurrence_parent');
                });
            }
            if (!Schema::hasIndex('group_scheduled_posts', 'idx_group_scheduled_due_claim')) {
                Schema::table('group_scheduled_posts', function (Blueprint $table): void {
                    $table->index(
                        ['status', 'next_attempt_at', 'scheduled_at'],
                        'idx_group_scheduled_due_claim',
                    );
                });
            }
            if (!Schema::hasIndex('group_scheduled_posts', 'idx_group_scheduled_lease')) {
                Schema::table('group_scheduled_posts', function (Blueprint $table): void {
                    $table->index(
                        ['status', 'lease_expires_at'],
                        'idx_group_scheduled_lease',
                    );
                });
            }
        }

        if (Schema::hasTable('group_webhooks') && !Schema::hasColumn('group_webhooks', 'disabled_at')) {
            Schema::table('group_webhooks', function (Blueprint $table): void {
                $table->timestamp('disabled_at')->nullable()->after('failure_count');
            });
        }

        if (!Schema::hasTable('group_webhook_deliveries')) {
            Schema::create('group_webhook_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('group_id');
                $table->unsignedInteger('webhook_id');
                $table->string('event', 64);
                $table->longText('payload');
                $table->string('status', 20)->default('queued');
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->timestamp('available_at');
                $table->timestamp('dispatched_at')->nullable();
                $table->uuid('claim_token')->nullable();
                $table->timestamp('lease_expires_at')->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('response_excerpt')->nullable();
                $table->string('last_error_code', 64)->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique('claim_token', 'uq_group_webhook_delivery_claim');
                $table->index(
                    ['status', 'available_at', 'dispatched_at'],
                    'idx_group_webhook_delivery_due',
                );
                $table->index(
                    ['status', 'lease_expires_at'],
                    'idx_group_webhook_delivery_lease',
                );
                $table->index(
                    ['tenant_id', 'group_id', 'created_at'],
                    'idx_group_webhook_delivery_tenant_group',
                );
                $table->index(
                    ['webhook_id', 'created_at'],
                    'idx_group_webhook_delivery_webhook',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_webhook_deliveries');

        if (Schema::hasTable('group_webhooks') && Schema::hasColumn('group_webhooks', 'disabled_at')) {
            Schema::table('group_webhooks', function (Blueprint $table): void {
                $table->dropColumn('disabled_at');
            });
        }

        if (Schema::hasTable('group_scheduled_posts')) {
            DB::table('group_scheduled_posts')
                ->where('status', 'processing')
                ->update(['status' => 'scheduled']);
            DB::table('group_scheduled_posts')
                ->where('status', 'failed')
                ->update(['status' => 'cancelled']);

            Schema::table('group_scheduled_posts', function (Blueprint $table): void {
                $table->dropUnique('uq_group_scheduled_claim_token');
                $table->dropUnique('uq_group_scheduled_recurrence_parent');
                $table->dropIndex('idx_group_scheduled_due_claim');
                $table->dropIndex('idx_group_scheduled_lease');
                $table->dropColumn([
                    'claim_token',
                    'claimed_at',
                    'lease_expires_at',
                    'attempt_count',
                    'next_attempt_at',
                    'last_error_code',
                    'last_error_message',
                    'published_resource_type',
                    'published_resource_id',
                    'recurrence_parent_id',
                ]);
            });

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement(
                    "ALTER TABLE group_scheduled_posts MODIFY status "
                    . "ENUM('scheduled','published','cancelled') NOT NULL DEFAULT 'scheduled'"
                );
            }
        }
    }
};
