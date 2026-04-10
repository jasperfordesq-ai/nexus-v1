<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop foreign keys on transactions.sender_id and transactions.receiver_id
 * so that external federation partners can store transactions where these
 * IDs reference users on remote servers (not in the local users table).
 */
return new class extends Migration
{
    public function up(): void
    {
        $fksToCheck = ['transactions_ibfk_2', 'transactions_ibfk_3'];

        foreach ($fksToCheck as $fk) {
            $exists = DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?
            ", [$fk]);

            if (!empty($exists)) {
                Schema::table('transactions', function ($table) use ($fk) {
                    $table->dropForeign($fk);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function ($table) {
            $table->foreign('sender_id', 'transactions_ibfk_2')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id', 'transactions_ibfk_3')
                ->references('id')->on('users')->onDelete('cascade');
        });
    }
};
