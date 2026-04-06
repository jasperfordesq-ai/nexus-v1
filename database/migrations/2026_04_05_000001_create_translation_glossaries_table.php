<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the translation_glossaries table for tenant-specific translation
 * term mappings. Allows each tenant to define custom glossary entries that
 * map source terms to translated terms in target languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('translation_glossaries')) {
            return;
        }

        Schema::create('translation_glossaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->string('source_term', 255);
            $table->string('target_term', 255);
            $table->string('target_language', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            // Prevent duplicate glossary entries for the same term and language within a tenant
            $table->unique(['tenant_id', 'source_term', 'target_language'], 'glossaries_tenant_term_lang_unique');
            // Optimise lookups for active glossary entries by tenant and language
            $table->index(['tenant_id', 'target_language', 'is_active'], 'glossaries_tenant_lang_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_glossaries');
    }
};
