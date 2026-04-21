<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear cached NexusScore data so insights regenerate with fixed PHP translations.
        if (Schema::hasTable('nexus_score_cache')) {
            DB::table('nexus_score_cache')->truncate();
        }
    }

    public function down(): void {}
};
