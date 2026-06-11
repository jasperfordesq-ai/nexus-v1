<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * org_members held memberships for BOTH community organizations
 * (organizations.id) and volunteer organizations (vol_organizations.id)
 * with no discriminator — the two AUTO_INCREMENT ID spaces collide, so
 * the owner of vol org #N satisfied membership checks for community
 * org #N (wallet access escalation), and the unique key
 * (organization_id, user_id) blocked a user from joining vol org #N if
 * they were already a member of community org #N.
 *
 * Adds org_type ('community'|'volunteer'), backfills, and replaces the
 * unique key with (org_type, organization_id, user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('org_members')) {
            return;
        }

        if (!Schema::hasColumn('org_members', 'org_type')) {
            Schema::table('org_members', function (Blueprint $table) {
                $table->string('org_type', 20)->default('community')->after('organization_id')->index();
            });
        }

        // Backfill. Every in-repo writer of org_members is on the
        // volunteer/Verein side (validated against vol_organizations), so:
        // 1) IDs that exist only in vol_organizations → volunteer.
        if (Schema::hasTable('vol_organizations')) {
            if (Schema::hasTable('organizations')) {
                DB::statement("
                    UPDATE org_members om
                    JOIN vol_organizations vo ON vo.id = om.organization_id
                    LEFT JOIN organizations co ON co.id = om.organization_id
                    SET om.org_type = 'volunteer'
                    WHERE co.id IS NULL
                ");
                // 2) IDs in both tables: volunteer when the row matches the
                // vol org's owner (the shape the vol-org create flow writes).
                DB::statement("
                    UPDATE org_members om
                    JOIN vol_organizations vo ON vo.id = om.organization_id AND vo.user_id = om.user_id
                    JOIN organizations co ON co.id = om.organization_id
                    SET om.org_type = 'volunteer'
                ");
            } else {
                DB::statement("
                    UPDATE org_members om
                    JOIN vol_organizations vo ON vo.id = om.organization_id
                    SET om.org_type = 'volunteer'
                ");
            }
        }

        // Replace the colliding unique key so the two ID spaces no longer
        // block each other's memberships.
        Schema::table('org_members', function (Blueprint $table) {
            if (Schema::hasIndex('org_members', 'unique_org_member')) {
                $table->dropUnique('unique_org_member');
            }
            if (!Schema::hasIndex('org_members', 'unique_org_member_typed')) {
                $table->unique(['org_type', 'organization_id', 'user_id'], 'unique_org_member_typed');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('org_members')) {
            return;
        }

        Schema::table('org_members', function (Blueprint $table) {
            if (Schema::hasIndex('org_members', 'unique_org_member_typed')) {
                $table->dropUnique('unique_org_member_typed');
            }
            if (!Schema::hasIndex('org_members', 'unique_org_member')) {
                $table->unique(['organization_id', 'user_id'], 'unique_org_member');
            }
        });

        if (Schema::hasColumn('org_members', 'org_type')) {
            Schema::table('org_members', function (Blueprint $table) {
                $table->dropColumn('org_type');
            });
        }
    }
};
