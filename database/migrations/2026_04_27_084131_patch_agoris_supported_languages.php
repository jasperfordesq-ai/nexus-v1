<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds Spanish (es) and Portuguese (pt) to the agoris and hour-timebank
 * tenants' supported_languages. Both were omitted from the original seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $slugs = ['agoris', 'hour-timebank'];

        foreach ($slugs as $slug) {
            $tenant = DB::table('tenants')->where('slug', $slug)->first();
            if (!$tenant) {
                continue;
            }

            $config = is_string($tenant->configuration)
                ? (json_decode($tenant->configuration, true) ?: [])
                : [];

            $current = $config['supported_languages'] ?? null;

            if (is_array($current) && in_array('es', $current, true) && in_array('pt', $current, true)) {
                continue;
            }

            $merged = is_array($current) ? $current : ['en'];
            foreach (['es', 'pt'] as $lang) {
                if (!in_array($lang, $merged, true)) {
                    $merged[] = $lang;
                }
            }
            $config['supported_languages'] = array_values($merged);

            DB::table('tenants')
                ->where('slug', $slug)
                ->update(['configuration' => json_encode($config)]);
        }
    }

    public function down(): void
    {
        // Language removal is not safe to auto-reverse.
    }
};
