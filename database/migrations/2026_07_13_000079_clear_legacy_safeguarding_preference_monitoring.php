<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Remove message-monitoring flags that were incorrectly derived from member preferences. */
return new class extends Migration
{
    /** Must remain identical to SafeguardingTriggerService::MONITORING_REASON_ONBOARDING. */
    private const LEGACY_ONBOARDING_MARKER = 'Safeguarding: self-identified during onboarding';

    public function up(): void
    {
        if (! Schema::hasTable('user_messaging_restrictions')
            || ! Schema::hasColumn('user_messaging_restrictions', 'monitoring_reason')
            || ! Schema::hasColumn('user_messaging_restrictions', 'under_monitoring')
            || ! Schema::hasColumn('user_messaging_restrictions', 'requires_broker_approval')) {
            return;
        }

        DB::table('user_messaging_restrictions')
            ->where('monitoring_reason', self::LEGACY_ONBOARDING_MARKER)
            ->update([
                'under_monitoring' => 0,
                'requires_broker_approval' => 0,
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring these flags would re-enable
        // message-content access that the member never authorised.
    }
};
