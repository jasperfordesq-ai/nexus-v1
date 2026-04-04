<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix two schema issues found during authenticated API testing:
 *
 * 1. group_policies table has wrong columns (stor instead of policy_key,
 *    category as timestamp instead of ENUM, missing policy_value/description).
 *    This causes 500 on GET /api/v2/groups/{id}/welcome.
 *
 * 2. group_members.tenant_id defaults to 1, and the Group model's members()
 *    relationship never set it, so 957+ records have tenant_id=1 even for
 *    groups in other tenants. This causes 403 on tenant-scoped membership checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ====================================================================
        // FIX 1: Rebuild group_policies table with correct schema
        // ====================================================================
        // The table is empty (verified), so safe to drop and recreate.
        Schema::dropIfExists('group_policies');

        DB::statement("
            CREATE TABLE `group_policies` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `tenant_id` INT(11) NOT NULL COMMENT 'Tenant this policy belongs to',
                `policy_key` VARCHAR(100) NOT NULL COMMENT 'Policy identifier',
                `policy_value` TEXT NOT NULL COMMENT 'Policy value (JSON-encoded)',
                `category` ENUM('creation', 'membership', 'content', 'moderation', 'notifications', 'features') NOT NULL COMMENT 'Policy category',
                `value_type` ENUM('boolean', 'number', 'string', 'json', 'list') NOT NULL COMMENT 'Type of value stored',
                `description` TEXT NULL COMMENT 'Description of what this policy does',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_tenant_policy` (`tenant_id`, `policy_key`),
                INDEX `idx_tenant_category` (`tenant_id`, `category`),
                INDEX `idx_tenant` (`tenant_id`),
                FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Flexible tenant-specific policies for groups module'
        ");

        // ====================================================================
        // FIX 2: Correct group_members.tenant_id to match their group's tenant
        // ====================================================================
        // Update all group_members rows where tenant_id doesn't match the
        // group's actual tenant_id (957 rows for tenant 2, 1 for tenant 3).
        DB::statement("
            UPDATE group_members gm
            INNER JOIN `groups` g ON gm.group_id = g.id
            SET gm.tenant_id = g.tenant_id
            WHERE gm.tenant_id != g.tenant_id
        ");
    }

    public function down(): void
    {
        // Not reversible — the original schema was broken.
        // The data fix (tenant_id correction) is also not reversible
        // as the original values were wrong.
    }
};
