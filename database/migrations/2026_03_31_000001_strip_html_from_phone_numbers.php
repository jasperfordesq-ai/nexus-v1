<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strip HTML tags from phone numbers that were stored with anchor tag wrapping.
 * Bug report: phone field displayed raw HTML like <a href="tel:+353..." rel="nofollow">
 */
return new class extends Migration
{
    public function up(): void
    {
        // Extract the actual phone number from HTML-wrapped values
        // Pattern: <a href="tel:+353873567387" rel="nofollow">...</a> → +353873567387
        $affected = DB::table('users')
            ->where('phone', 'LIKE', '%<a %')
            ->orWhere('phone', 'LIKE', '%&lt;a %')
            ->get(['id', 'phone']);

        foreach ($affected as $row) {
            $clean = strip_tags(html_entity_decode($row->phone, ENT_QUOTES, 'UTF-8'));
            // Remove leftover escaped quotes and whitespace artifacts
            $clean = trim(preg_replace('/["\\\]+/', '', $clean));

            DB::table('users')
                ->where('id', $row->id)
                ->update(['phone' => $clean]);
        }
    }

    public function down(): void
    {
        // Data migration — cannot be reversed
    }
};
