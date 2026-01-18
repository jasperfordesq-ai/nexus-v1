#!/usr/bin/env php
<?php
/**
 * Audit Migration Safety
 * Scans all migration files for dangerous operations
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         MIGRATION SAFETY AUDIT TOOL                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$migrationsDir = __DIR__ . '/../migrations';
$dangerousPatterns = [
    'DROP TABLE' => '🔴 CRITICAL',
    'DROP DATABASE' => '🔴 CRITICAL',
    'TRUNCATE TABLE' => '🔴 CRITICAL',
    'TRUNCATE ' => '🔴 CRITICAL',
    'DELETE FROM' => '🟡 WARNING',
    'UPDATE ' => '🟢 SAFE (usually)',
];

$findings = [];

// Scan all SQL files
$files = glob($migrationsDir . '/*.sql');
sort($files);

echo "→ Scanning " . count($files) . " migration files...\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    foreach ($dangerousPatterns as $pattern => $severity) {
        // Case-insensitive search
        if (stripos($content, $pattern) !== false) {
            // Find which lines
            $matchingLines = [];
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, $pattern) !== false && !preg_match('/^\\s*--/', $line)) {
                    $matchingLines[] = ($lineNum + 1) . ': ' . trim($line);
                }
            }

            if (!empty($matchingLines)) {
                $findings[] = [
                    'file' => $filename,
                    'pattern' => $pattern,
                    'severity' => $severity,
                    'lines' => $matchingLines,
                ];
            }
        }
    }
}

// Report findings
if (empty($findings)) {
    echo "✅ No dangerous operations found in any migrations!\n\n";
} else {
    echo "⚠️  FOUND " . count($findings) . " POTENTIAL ISSUES:\n\n";

    foreach ($findings as $finding) {
        echo "{$finding['severity']} {$finding['pattern']} in {$finding['file']}\n";
        foreach ($finding['lines'] as $line) {
            echo "    Line {$line}\n";
        }
        echo "\n";
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    AUDIT COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Legend:\n";
echo "🔴 CRITICAL - Can cause data loss\n";
echo "🟡 WARNING - Modifies data, review carefully\n";
echo "🟢 SAFE - Usually safe but review context\n\n";

echo "Next steps:\n";
echo "1. Review each finding above\n";
echo "2. Check if operation is intentional\n";
echo "3. Verify backups exist before running\n";
echo "4. Test on staging before production\n\n";
