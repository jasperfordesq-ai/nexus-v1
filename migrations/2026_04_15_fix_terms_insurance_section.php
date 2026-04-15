<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Fix Terms of Service — Insurance Section (v3.0)
 *
 * The v2.0 terms incorrectly stated "hOUR Timebank Ireland maintains appropriate
 * insurance for its organisational activities." This was false and contradicted the
 * platform-provider framing elsewhere in the document.
 *
 * This migration inserts a corrected v3.0 that replaces the false insurance claim
 * with the accurate platform-provider explanation: as a connection platform (not a
 * service provider), hOUR Timebank Ireland does not carry liability insurance for
 * member exchanges and is not required to do so.
 *
 * Run after: 2026_01_25_seed_legal_documents_content.php
 * Usage: php migrations/2026_04_15_fix_terms_insurance_section.php
 *
 * @date 2026-04-15
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

echo "=== Terms of Service v3.0 — Insurance Section Fix ===\n\n";

// Get admin user ID
$adminStmt = Database::query("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
$admin = $adminStmt->fetch();
$adminId = $admin ? $admin['id'] : 1;

// Get the Terms document for tenant 2
$stmt = Database::query(
    "SELECT id FROM legal_documents WHERE tenant_id = 2 AND document_type = 'terms'"
);
$termsDoc = $stmt->fetch();

if (!$termsDoc) {
    die("ERROR: Terms document not found for tenant 2. Run 2026_01_25_seed_legal_documents_content.php first.\n");
}

$termsDocId = $termsDoc['id'];

// Check if v3.0 already exists
$stmt = Database::query(
    "SELECT id FROM legal_document_versions WHERE document_id = ? AND version_number = '3.0'",
    [$termsDocId]
);
if ($stmt->fetch()) {
    echo "Terms v3.0 already exists — skipping.\n";
    exit(0);
}

// Fetch the current live version content as our base
$stmt = Database::query(
    "SELECT content FROM legal_document_versions WHERE document_id = ? AND is_current = 1 LIMIT 1",
    [$termsDocId]
);
$current = $stmt->fetch();

if (!$current) {
    die("ERROR: No current version found for Terms document.\n");
}

// Replace the incorrect insurance section with the corrected platform-provider version
$oldInsuranceSection = '<h2 id="insurance">13. Insurance</h2>
<p>hOUR Timebank Ireland maintains appropriate insurance for its organisational activities. However:</p>
<ul>
    <li>This insurance does <strong>not</strong> cover exchanges between members</li>
    <li>Members are responsible for their own insurance (home, liability, etc.)</li>
    <li>We recommend members check their existing policies cover volunteer activities</li>
</ul>';

$newInsuranceSection = '<h2 id="insurance">13. Insurance</h2>
<div class="legal-notice">
    <h4>Platform Provider — No Organisational Insurance</h4>
    <p>As a <strong>platform provider</strong> (not a service provider), hOUR Timebank Ireland does not carry liability insurance for member-to-member exchanges. This is because we do not perform, direct, supervise, or control any exchange between members. We connect people — we are not a party to the exchange itself.</p>
</div>
<p>Platform providers (such as online notice boards, community apps, and peer-to-peer platforms) are not liable for the independent actions of the users they connect, and do not carry insurance in respect of those exchanges. Our position is analogous to a community notice board: we provide the space for connections, but what happens between members is independent of us.</p>
<p><strong>Members are solely responsible for ensuring they have appropriate cover</strong> for any activities they undertake. You should check whether your home insurance, public liability policy, or existing volunteering coverage extends to community exchange activities. If you are offering services professionally, you should hold appropriate professional indemnity or public liability insurance.</p>
<p>Nothing in this section limits our liability for our own negligence in operating the platform itself.</p>';

$updatedContent = str_replace($oldInsuranceSection, $newInsuranceSection, $current['content']);

if ($updatedContent === $current['content']) {
    // The old pattern wasn't found exactly — the DB content may already be different.
    // Do a looser replacement just on the h2 section.
    echo "WARNING: Exact old insurance section not found in current content.\n";
    echo "The current terms may already have been edited via the admin panel.\n";
    echo "Please review Section 13 manually in the legal documents admin.\n";
    exit(1);
}

try {
    // Mark all existing versions as not current
    Database::query(
        "UPDATE legal_document_versions SET is_current = 0 WHERE document_id = ?",
        [$termsDocId]
    );

    // Insert v3.0
    Database::query(
        "INSERT INTO legal_document_versions
         (document_id, version_number, version_label, content, content_plain, summary_of_changes, effective_date, is_draft, is_current, published_at, created_by, published_by)
         VALUES (?, '3.0', 'April 2026 Insurance Correction', ?, ?, ?, '2026-04-15', 0, 1, NOW(), ?, ?)",
        [
            $termsDocId,
            $updatedContent,
            strip_tags($updatedContent),
            'Corrected Section 13 (Insurance): removed false claim that the organisation holds insurance. Replaced with accurate platform-provider explanation — as a connection platform (not a service provider), hOUR Timebank Ireland does not carry liability insurance for member exchanges and is not required to do so.',
            $adminId,
            $adminId,
        ]
    );
    $newVersionId = Database::lastInsertId();

    // Point document to new version
    Database::query(
        "UPDATE legal_documents SET current_version_id = ? WHERE id = ?",
        [$newVersionId, $termsDocId]
    );

    echo "✓ Terms v3.0 inserted (version ID: {$newVersionId})\n";
    echo "✓ Marked as current live version\n";
    echo "\nDone. Section 13 now correctly reflects platform-provider status.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
