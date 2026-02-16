<?php
/**
 * Copy legal documents from tenant 2 (hOUR Timebank) to tenant 4 (Timebank Global)
 *
 * This script:
 * 1. Creates legal_documents entries for tenant 4 (terms, privacy, cookies)
 * 2. Copies version content from tenant 2, adapting branding
 * 3. Sets the current_version_id correctly
 * 4. Also fixes is_current flag on tenant 2 versions (data inconsistency)
 *
 * Run inside Docker:
 *   docker exec nexus-php-app php /var/www/html/scripts/copy-legal-docs-to-tenant4.php
 */

$sourceTenantId = 2;
$targetTenantId = 4;

// Brand replacements: hOUR Timebank → Timebank Global
$replacements = [
    'hOUR Timebank Ireland' => 'Timebank Global',
    'hOUR Timebank CLG' => 'Timebank Global',
    'hour-timebank.ie' => 'timebank.global',
    'hour-timebank' => 'timebank-global',
    'jasper@hour-timebank.ie' => 'hello@timebank.global',
    'hOUR Timebank' => 'Timebank Global',
    '/hour-timebank/' => '/timebank-global/',
];

// Direct PDO connection using environment variables
$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'nexus';
$user = getenv('DB_USER') ?: 'nexus';
$pass = getenv('DB_PASS') ?: 'nexus_secret';

$pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "=== Copy Legal Documents: Tenant $sourceTenantId → Tenant $targetTenantId ===\n\n";

// Step 0: Check if tenant 4 already has legal docs
$stmt = $pdo->prepare("SELECT id, document_type FROM legal_documents WHERE tenant_id = ?");
$stmt->execute([$targetTenantId]);
$existing = $stmt->fetchAll();

if (!empty($existing)) {
    echo "WARNING: Tenant $targetTenantId already has legal documents:\n";
    foreach ($existing as $doc) {
        echo "  - ID {$doc['id']}: {$doc['document_type']}\n";
    }
    echo "\nAborting to avoid duplicates. Delete existing docs first if you want to re-run.\n";
    exit(1);
}

// Step 1: Get source tenant's legal documents
$stmt = $pdo->prepare(
    "SELECT * FROM legal_documents WHERE tenant_id = ? ORDER BY document_type"
);
$stmt->execute([$sourceTenantId]);
$sourceDocs = $stmt->fetchAll();

echo "Found " . count($sourceDocs) . " documents for tenant $sourceTenantId:\n";
foreach ($sourceDocs as $doc) {
    echo "  - [{$doc['id']}] {$doc['document_type']}: {$doc['title']} (version_id: {$doc['current_version_id']})\n";
}
echo "\n";

// Step 2: Get all versions for source documents
$docIds = array_column($sourceDocs, 'id');
$placeholders = implode(',', array_fill(0, count($docIds), '?'));
$stmt = $pdo->prepare(
    "SELECT * FROM legal_document_versions WHERE document_id IN ($placeholders) ORDER BY document_id, id"
);
$stmt->execute($docIds);
$sourceVersions = $stmt->fetchAll();

echo "Found " . count($sourceVersions) . " versions:\n";
foreach ($sourceVersions as $v) {
    echo "  - [{$v['id']}] doc_id={$v['document_id']}, v{$v['version_number']}, " .
         strlen($v['content']) . " chars, is_current={$v['is_current']}\n";
}
echo "\n";

// Apply brand replacements to content
function adaptContent(string $content, array $replacements): string
{
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    return $content;
}

// Step 3: Begin transaction
$pdo->beginTransaction();

try {
    $docIdMapping = []; // old_id => new_id
    $versionIdMapping = []; // old_id => new_id
    $docVersionMap = []; // new_doc_id => new_version_id (for current_version_id)

    // Step 4: Insert documents for tenant 4
    foreach ($sourceDocs as $doc) {
        $stmt = $pdo->prepare(
            "INSERT INTO legal_documents
            (tenant_id, document_type, title, slug, current_version_id, requires_acceptance,
             acceptance_required_for, notify_on_update, is_active, created_by)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $targetTenantId,
            $doc['document_type'],
            $doc['title'],
            $doc['slug'],
            $doc['requires_acceptance'],
            $doc['acceptance_required_for'],
            $doc['notify_on_update'],
            $doc['is_active'],
            $doc['created_by'] ?? 1,
        ]);
        $newDocId = $pdo->lastInsertId();
        $docIdMapping[$doc['id']] = $newDocId;
        echo "Created document: [{$newDocId}] tenant=$targetTenantId, type={$doc['document_type']}\n";
    }

    // Step 5: Insert versions with adapted content
    foreach ($sourceVersions as $v) {
        $newDocId = $docIdMapping[$v['document_id']] ?? null;
        if (!$newDocId) {
            echo "  SKIP version {$v['id']} — no matching document\n";
            continue;
        }

        $adaptedContent = adaptContent($v['content'], $replacements);
        $adaptedPlain = $v['content_plain'] ? adaptContent($v['content_plain'], $replacements) : null;
        $adaptedSummary = $v['summary_of_changes'] ? adaptContent($v['summary_of_changes'], $replacements) : null;

        $stmt = $pdo->prepare(
            "INSERT INTO legal_document_versions
            (document_id, version_number, version_label, content, content_plain, summary_of_changes,
             effective_date, published_at, is_draft, is_current, notification_sent, created_by, published_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 1, 0, ?, ?)"
        );
        $stmt->execute([
            $newDocId,
            $v['version_number'],
            $v['version_label'],
            $adaptedContent,
            $adaptedPlain,
            $adaptedSummary,
            $v['effective_date'],
            $v['created_by'],
            $v['published_by'] ?? $v['created_by'],
        ]);
        $newVersionId = $pdo->lastInsertId();
        $versionIdMapping[$v['id']] = $newVersionId;
        $docVersionMap[$newDocId] = $newVersionId;
        echo "  Created version: [{$newVersionId}] doc=$newDocId, v{$v['version_number']}, " .
             strlen($adaptedContent) . " chars\n";
    }

    // Step 6: Update current_version_id on new documents
    foreach ($docVersionMap as $newDocId => $newVersionId) {
        $stmt = $pdo->prepare(
            "UPDATE legal_documents SET current_version_id = ? WHERE id = ?"
        );
        $stmt->execute([$newVersionId, $newDocId]);
        echo "Set current_version_id=$newVersionId on document $newDocId\n";
    }

    // Step 7: Fix is_current flags on tenant 2 versions (data inconsistency)
    echo "\n--- Fixing tenant 2 is_current flags ---\n";
    foreach ($sourceDocs as $doc) {
        if ($doc['current_version_id']) {
            $stmt = $pdo->prepare(
                "UPDATE legal_document_versions SET is_current = 1 WHERE id = ? AND is_current = 0"
            );
            $stmt->execute([$doc['current_version_id']]);
            $affected = $stmt->rowCount();
            if ($affected > 0) {
                echo "Fixed is_current=1 on version {$doc['current_version_id']} (doc {$doc['id']})\n";
            }
        }
    }

    $pdo->commit();
    echo "\n=== SUCCESS: All documents created for tenant $targetTenantId ===\n";

    // Verification
    echo "\n--- Verification ---\n";
    $stmt = $pdo->prepare(
        "SELECT ld.id, ld.tenant_id, ld.document_type, ld.title, ld.current_version_id,
                ldv.version_number, ldv.is_current, LENGTH(ldv.content) as content_length
         FROM legal_documents ld
         LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
         WHERE ld.tenant_id IN (?, ?)
         ORDER BY ld.tenant_id, ld.document_type"
    );
    $stmt->execute([$sourceTenantId, $targetTenantId]);
    $all = $stmt->fetchAll();

    foreach ($all as $row) {
        echo sprintf(
            "  [%d] tenant=%d  %-12s  ver_id=%-4s  v%-5s  is_current=%s  %s chars\n",
            $row['id'],
            $row['tenant_id'],
            $row['document_type'],
            $row['current_version_id'] ?? 'NULL',
            $row['version_number'] ?? 'N/A',
            $row['is_current'] ?? 'N/A',
            $row['content_length'] ?? '0'
        );
    }

} catch (\Throwable $e) {
    $pdo->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
