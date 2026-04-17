<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Composite indexes for federation_audit_log activity feed
        // WHERE (source_tenant_id = ? OR target_tenant_id = ?) ORDER BY created_at DESC
        if (!$this->indexExists('federation_audit_log', 'idx_fed_audit_source_created')) {
            Schema::table('federation_audit_log', function (Blueprint $table) {
                $table->index(['source_tenant_id', 'created_at'], 'idx_fed_audit_source_created');
            });
        }
        if (!$this->indexExists('federation_audit_log', 'idx_fed_audit_target_created')) {
            Schema::table('federation_audit_log', function (Blueprint $table) {
                $table->index(['target_tenant_id', 'created_at'], 'idx_fed_audit_target_created');
            });
        }

        // Composite indexes for federation_partnerships — (tenant_id, status) and (partner_tenant_id, status)
        if (!$this->indexExists('federation_partnerships', 'idx_fed_partner_tenant_status')) {
            Schema::table('federation_partnerships', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'idx_fed_partner_tenant_status');
            });
        }
        if (!$this->indexExists('federation_partnerships', 'idx_fed_partner_partner_status')) {
            Schema::table('federation_partnerships', function (Blueprint $table) {
                $table->index(['partner_tenant_id', 'status'], 'idx_fed_partner_partner_status');
            });
        }

        // Composite index for federation_user_settings opt-in queries
        if (!$this->indexExists('federation_user_settings', 'idx_fed_user_optin_search')) {
            Schema::table('federation_user_settings', function (Blueprint $table) {
                $table->index(['federation_optin', 'appear_in_federated_search'], 'idx_fed_user_optin_search');
            });
        }

        // Composite index for federated reviews query (used by UserService::formatProfile)
        // reviews WHERE receiver_id = ? AND review_type = 'federated' AND show_cross_tenant = 1
        if (!$this->indexExists('reviews', 'idx_reviews_federated')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->index(['receiver_id', 'review_type', 'show_cross_tenant'], 'idx_reviews_federated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('federation_audit_log', function (Blueprint $table) {
            if ($this->indexExists('federation_audit_log', 'idx_fed_audit_source_created')) {
                $table->dropIndex('idx_fed_audit_source_created');
            }
            if ($this->indexExists('federation_audit_log', 'idx_fed_audit_target_created')) {
                $table->dropIndex('idx_fed_audit_target_created');
            }
        });
        Schema::table('federation_partnerships', function (Blueprint $table) {
            if ($this->indexExists('federation_partnerships', 'idx_fed_partner_tenant_status')) {
                $table->dropIndex('idx_fed_partner_tenant_status');
            }
            if ($this->indexExists('federation_partnerships', 'idx_fed_partner_partner_status')) {
                $table->dropIndex('idx_fed_partner_partner_status');
            }
        });
        Schema::table('federation_user_settings', function (Blueprint $table) {
            if ($this->indexExists('federation_user_settings', 'idx_fed_user_optin_search')) {
                $table->dropIndex('idx_fed_user_optin_search');
            }
        });
        Schema::table('reviews', function (Blueprint $table) {
            if ($this->indexExists('reviews', 'idx_reviews_federated')) {
                $table->dropIndex('idx_reviews_federated');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
