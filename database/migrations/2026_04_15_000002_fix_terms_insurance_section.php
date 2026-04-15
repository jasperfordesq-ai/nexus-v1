<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix Terms of Service — Insurance Section (v3.0 for hour-timebank, tenant 2)
 *
 * The v2.0 terms incorrectly stated "hOUR Timebank Ireland maintains appropriate
 * insurance for its organisational activities." This was false and contradicted the
 * platform-provider framing elsewhere in the document.
 *
 * This migration inserts a corrected v3.0 replacing the false insurance claim with
 * the accurate platform-provider explanation.
 */
return new class extends Migration
{
    private const OLD_INSURANCE = '<h2 id="insurance">13. Insurance</h2>
<p>hOUR Timebank Ireland maintains appropriate insurance for its organisational activities. However:</p>
<ul>
    <li>This insurance does <strong>not</strong> cover exchanges between members</li>
    <li>Members are responsible for their own insurance (home, liability, etc.)</li>
    <li>We recommend members check their existing policies cover volunteer activities</li>
</ul>';

    private const NEW_INSURANCE = '<h2 id="insurance">13. Insurance</h2>
<div class="legal-notice">
    <h4>Platform Provider — No Organisational Insurance</h4>
    <p>As a <strong>platform provider</strong> (not a service provider), hOUR Timebank Ireland does not carry liability insurance for member-to-member exchanges. This is because we do not perform, direct, supervise, or control any exchange between members. We connect people — we are not a party to the exchange itself.</p>
</div>
<p>Platform providers (such as online notice boards, community apps, and peer-to-peer platforms) are not liable for the independent actions of the users they connect, and do not carry insurance in respect of those exchanges. Our position is analogous to a community notice board: we provide the space for connections, but what happens between members is independent of us.</p>
<p><strong>Members are solely responsible for ensuring they have appropriate cover</strong> for any activities they undertake. You should check whether your home insurance, public liability policy, or existing volunteering coverage extends to community exchange activities. If you are offering services professionally, you should hold appropriate professional indemnity or public liability insurance.</p>
<p>Nothing in this section limits our liability for our own negligence in operating the platform itself.</p>';

    public function up(): void
    {
        // Get the Terms document for tenant 2 (hOUR Timebank)
        $doc = DB::table('legal_documents')
            ->where('tenant_id', 2)
            ->where('document_type', 'terms')
            ->first();

        if (! $doc) {
            return; // Not seeded yet — nothing to fix
        }

        // Check v3.0 doesn't already exist
        $exists = DB::table('legal_document_versions')
            ->where('document_id', $doc->id)
            ->where('version_number', '3.0')
            ->exists();

        if ($exists) {
            return;
        }

        // Get the current live version as our base
        $current = DB::table('legal_document_versions')
            ->where('document_id', $doc->id)
            ->where('is_current', 1)
            ->first();

        if (! $current) {
            return;
        }

        $updatedContent = str_replace(self::OLD_INSURANCE, self::NEW_INSURANCE, $current->content);

        if ($updatedContent === $current->content) {
            // Old exact pattern not found — may already have been fixed or content differs.
            // Log but do not fail the migration.
            \Illuminate\Support\Facades\Log::warning(
                'fix_terms_insurance_section: old insurance pattern not found in current terms for tenant 2 — manual review required.'
            );
            return;
        }

        $adminId = DB::table('users')->where('is_admin', 1)->value('id') ?? 1;

        DB::table('legal_document_versions')
            ->where('document_id', $doc->id)
            ->update(['is_current' => 0]);

        $newVersionId = DB::table('legal_document_versions')->insertGetId([
            'document_id'        => $doc->id,
            'version_number'     => '3.0',
            'version_label'      => 'April 2026 Insurance Correction',
            'content'            => $updatedContent,
            'content_plain'      => strip_tags($updatedContent),
            'summary_of_changes' => 'Corrected Section 13 (Insurance): removed false claim that the organisation holds insurance. Replaced with accurate platform-provider explanation — as a connection platform (not a service provider), hOUR Timebank Ireland does not carry liability insurance for member exchanges and is not required to do so.',
            'effective_date'     => '2026-04-15',
            'is_draft'           => 0,
            'is_current'         => 1,
            'published_at'       => now(),
            'created_by'         => $adminId,
            'published_by'       => $adminId,
        ]);

        DB::table('legal_documents')
            ->where('id', $doc->id)
            ->update(['current_version_id' => $newVersionId]);
    }

    public function down(): void
    {
        // Revert: mark v2.0 as current and remove v3.0
        $doc = DB::table('legal_documents')
            ->where('tenant_id', 2)
            ->where('document_type', 'terms')
            ->first();

        if (! $doc) {
            return;
        }

        $v3 = DB::table('legal_document_versions')
            ->where('document_id', $doc->id)
            ->where('version_number', '3.0')
            ->first();

        if ($v3) {
            DB::table('legal_document_versions')->where('id', $v3->id)->delete();
        }

        $v2 = DB::table('legal_document_versions')
            ->where('document_id', $doc->id)
            ->where('version_number', '2.0')
            ->first();

        if ($v2) {
            DB::table('legal_document_versions')
                ->where('document_id', $doc->id)
                ->update(['is_current' => 0]);

            DB::table('legal_document_versions')
                ->where('id', $v2->id)
                ->update(['is_current' => 1]);

            DB::table('legal_documents')
                ->where('id', $doc->id)
                ->update(['current_version_id' => $v2->id]);
        }
    }
};
