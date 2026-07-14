<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('insurance_certificates')) {
            DB::table('insurance_certificates')->update([
                'certificate_file_path' => null,
                'policy_number' => null,
                'coverage_amount' => null,
                'notes' => null,
            ]);
        }

        // This directory was exclusively used by the retired upload feature.
        $legacyDirectory = base_path('httpdocs/uploads/insurance');
        if (is_dir($legacyDirectory) && ! File::deleteDirectory($legacyDirectory)) {
            throw new RuntimeException('Legacy insurance documents could not be erased.');
        }
    }

    public function down(): void
    {
        // Privacy deletion is intentionally non-reversible.
    }
};
