<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('vol_logs', 'updated_at')) {
            Schema::table('vol_logs', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vol_logs', 'updated_at')) {
            Schema::table('vol_logs', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
