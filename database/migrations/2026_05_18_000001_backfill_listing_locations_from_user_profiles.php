<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set location/latitude/longitude on listings that are missing coordinates,
        // using the owning user's current profile values.  Only updates rows where
        // both the listing is missing coordinates AND the user has them.
        DB::statement("
            UPDATE listings l
            INNER JOIN users u
                ON u.id = l.user_id
               AND u.tenant_id = l.tenant_id
            SET
                l.location  = u.location,
                l.latitude  = u.latitude,
                l.longitude = u.longitude,
                l.updated_at = NOW()
            WHERE
                l.status != 'deleted'
                AND (l.latitude IS NULL OR l.longitude IS NULL)
                AND u.latitude  IS NOT NULL
                AND u.longitude IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Not reversible — coordinates that were NULL are now set. No rollback needed.
    }
};
