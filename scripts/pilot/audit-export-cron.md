# Audit Log Export Cron Configuration

**Document ID:** NEXUS-AUDIT-CRON
**Version:** 1.0
**Date:** February 2026

---

## Overview

This document provides cron job examples for exporting audit logs to immutable storage and verifying log integrity. This supports compliance requirements and incident investigation capabilities.

---

## Prerequisites

1. Audit logging enabled in the application
2. PHP CLI installed
3. Export directory with write permissions
4. (Optional) GPG for signing exports
5. (Optional) Offsite storage credentials

---

## Export Script

### Basic Audit Export (export_audit_logs.php)

If not already present, create this script:

```php
<?php
/**
 * export_audit_logs.php
 * Exports audit logs to hash-chained JSONL format
 *
 * Usage: php export_audit_logs.php [--since=YYYY-MM-DD] [--output=/path/to/file.jsonl]
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

// Parse arguments
$options = getopt('', ['since::', 'output::']);
$since = $options['since'] ?? date('Y-m-d', strtotime('-1 day'));
$outputDir = dirname($options['output'] ?? '/var/exports/nexus/audit/');
$outputFile = $options['output'] ?? "/var/exports/nexus/audit/audit_" . date('Ymd_His') . ".jsonl";

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0700, true);
}

echo "Exporting audit logs since: $since\n";
echo "Output file: $outputFile\n";

// Get last hash from previous export (for chaining)
$hashChainFile = $outputDir . '/last_hash.txt';
$previousHash = file_exists($hashChainFile) ? trim(file_get_contents($hashChainFile)) : 'GENESIS';

// Open output file
$handle = fopen($outputFile, 'w');
if (!$handle) {
    die("ERROR: Cannot open output file\n");
}

// Export from all audit tables
$tables = [
    'super_admin_audit_log' => "SELECT * FROM super_admin_audit_log WHERE created_at >= ? ORDER BY id",
    'org_audit_log' => "SELECT * FROM org_audit_log WHERE created_at >= ? ORDER BY id",
    'login_attempts' => "SELECT * FROM login_attempts WHERE attempted_at >= ? ORDER BY id"
];

$totalRecords = 0;
$currentHash = $previousHash;

foreach ($tables as $table => $sql) {
    try {
        $stmt = Database::query($sql, [$since . ' 00:00:00']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Add metadata
            $record = [
                'source_table' => $table,
                'exported_at' => date('c'),
                'previous_hash' => $currentHash,
                'data' => $row
            ];

            // Calculate hash including previous hash (chain)
            $recordJson = json_encode($record, JSON_UNESCAPED_UNICODE);
            $currentHash = hash('sha256', $recordJson);
            $record['record_hash'] = $currentHash;

            // Write JSONL (one JSON object per line)
            fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n");
            $totalRecords++;
        }
    } catch (Exception $e) {
        echo "WARNING: Could not export from $table: " . $e->getMessage() . "\n";
    }
}

fclose($handle);

// Save current hash for next export
file_put_contents($hashChainFile, $currentHash);

// Compress the export
exec("gzip -9 " . escapeshellarg($outputFile));

echo "Export complete: $totalRecords records\n";
echo "Final hash: $currentHash\n";
echo "Compressed file: {$outputFile}.gz\n";
```

---

## Verification Script

### Verify Hash Chain (verify_audit_chain.php)

```php
<?php
/**
 * verify_audit_chain.php
 * Verifies the integrity of hash-chained audit log exports
 *
 * Usage: php verify_audit_chain.php /path/to/audit_export.jsonl.gz
 */

if ($argc < 2) {
    die("Usage: php verify_audit_chain.php <export_file.jsonl.gz>\n");
}

$file = $argv[1];

// Handle compressed files
if (substr($file, -3) === '.gz') {
    $handle = gzopen($file, 'r');
} else {
    $handle = fopen($file, 'r');
}

if (!$handle) {
    die("ERROR: Cannot open file: $file\n");
}

echo "Verifying: $file\n";
echo str_repeat('-', 60) . "\n";

$lineNumber = 0;
$validRecords = 0;
$invalidRecords = 0;
$expectedPreviousHash = null;

while (($line = (substr($file, -3) === '.gz' ? gzgets($handle) : fgets($handle))) !== false) {
    $lineNumber++;
    $line = trim($line);

    if (empty($line)) continue;

    $record = json_decode($line, true);
    if (!$record) {
        echo "Line $lineNumber: INVALID JSON\n";
        $invalidRecords++;
        continue;
    }

    // Extract stored hash
    $storedHash = $record['record_hash'] ?? null;
    unset($record['record_hash']);

    // Recalculate hash
    $calculatedHash = hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE));

    // Verify hash matches
    if ($calculatedHash !== $storedHash) {
        echo "Line $lineNumber: HASH MISMATCH\n";
        echo "  Expected: $storedHash\n";
        echo "  Calculated: $calculatedHash\n";
        $invalidRecords++;
        continue;
    }

    // Verify chain continuity
    if ($expectedPreviousHash !== null && $record['previous_hash'] !== $expectedPreviousHash) {
        echo "Line $lineNumber: CHAIN BROKEN\n";
        echo "  Expected previous: $expectedPreviousHash\n";
        echo "  Found previous: {$record['previous_hash']}\n";
        $invalidRecords++;
        continue;
    }

    $expectedPreviousHash = $storedHash;
    $validRecords++;
}

if (substr($file, -3) === '.gz') {
    gzclose($handle);
} else {
    fclose($handle);
}

echo str_repeat('-', 60) . "\n";
echo "Total records: " . ($validRecords + $invalidRecords) . "\n";
echo "Valid records: $validRecords\n";
echo "Invalid records: $invalidRecords\n";
echo "Final chain hash: $expectedPreviousHash\n";

if ($invalidRecords > 0) {
    echo "\nRESULT: VERIFICATION FAILED\n";
    exit(1);
} else {
    echo "\nRESULT: VERIFICATION PASSED\n";
    exit(0);
}
```

---

## Cron Job Examples

### Nightly Export

Export previous day's logs at 01:00:

```cron
# Nightly audit log export at 01:00
0 1 * * * /usr/bin/php /var/www/nexus/scripts/pilot/export_audit_logs.php >> /var/log/nexus/audit-export.log 2>&1
```

### Export with Verification

```bash
#!/bin/bash
# /var/www/nexus/scripts/pilot/audit_export_and_verify.sh

EXPORT_DIR="/var/exports/nexus/audit"
LOG_FILE="/var/log/nexus/audit-export.log"
DATE=$(date +%Y%m%d)
SINCE=$(date -d "yesterday" +%Y-%m-%d)

echo "$(date): Starting audit export for $SINCE" >> "$LOG_FILE"

# Run export
/usr/bin/php /var/www/nexus/scripts/pilot/export_audit_logs.php \
    --since="$SINCE" \
    --output="$EXPORT_DIR/audit_$DATE.jsonl" \
    >> "$LOG_FILE" 2>&1

# Verify the export
/usr/bin/php /var/www/nexus/scripts/pilot/verify_audit_chain.php \
    "$EXPORT_DIR/audit_$DATE.jsonl.gz" \
    >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    echo "$(date): Verification passed" >> "$LOG_FILE"
else
    echo "$(date): VERIFICATION FAILED - alerting admin" >> "$LOG_FILE"
    # Send alert
    mail -s "NEXUS Audit Log Verification Failed" security@council.gov.uk < /dev/null
fi
```

Cron entry:
```cron
# Nightly audit export with verification at 01:00
0 1 * * * /var/www/nexus/scripts/pilot/audit_export_and_verify.sh
```

---

## Offsite Replication

### Copy to Secure Storage

```bash
#!/bin/bash
# /var/www/nexus/scripts/pilot/audit_offsite_sync.sh

EXPORT_DIR="/var/exports/nexus/audit"
REMOTE_USER="audit"
REMOTE_HOST="audit-archive.council.gov.uk"
REMOTE_PATH="/secure/nexus-audit"

# Sync all exports to offsite storage
rsync -avz --chmod=D700,F600 \
    "$EXPORT_DIR/" \
    "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"

if [ $? -eq 0 ]; then
    echo "$(date): Offsite sync completed" >> /var/log/nexus/audit-export.log
else
    echo "$(date): Offsite sync FAILED" >> /var/log/nexus/audit-export.log
fi
```

### S3 Upload with Versioning

```bash
#!/bin/bash
# /var/www/nexus/scripts/pilot/audit_s3_upload.sh

EXPORT_DIR="/var/exports/nexus/audit"
S3_BUCKET="s3://council-audit-logs/nexus"
DATE=$(date +%Y%m%d)

# Upload today's export
aws s3 cp "$EXPORT_DIR/audit_$DATE.jsonl.gz" "$S3_BUCKET/" \
    --storage-class GLACIER_IR

# S3 versioning should be enabled on the bucket for immutability
```

---

## Retention Management

### Keep 90 Days Locally

```bash
#!/bin/bash
# Clean up exports older than 90 days
find /var/exports/nexus/audit -name "audit_*.jsonl.gz" -mtime +90 -delete
```

### Archive to Cold Storage

```bash
#!/bin/bash
# Move exports older than 30 days to archive
EXPORT_DIR="/var/exports/nexus/audit"
ARCHIVE_DIR="/var/archive/nexus/audit"

mkdir -p "$ARCHIVE_DIR"

find "$EXPORT_DIR" -name "audit_*.jsonl.gz" -mtime +30 \
    -exec mv {} "$ARCHIVE_DIR/" \;
```

---

## Complete Cron Configuration

```cron
# NEXUS Audit Log Management
# ===========================

# Export audit logs at 01:00 (after midnight for previous day)
0 1 * * * /var/www/nexus/scripts/pilot/audit_export_and_verify.sh >> /var/log/nexus/audit-export.log 2>&1

# Sync to offsite at 02:00
0 2 * * * /var/www/nexus/scripts/pilot/audit_offsite_sync.sh >> /var/log/nexus/audit-export.log 2>&1

# Weekly archive old exports (Sundays at 03:00)
0 3 * * 0 find /var/exports/nexus/audit -name "audit_*.jsonl.gz" -mtime +30 -exec mv {} /var/archive/nexus/audit/ \;

# Monthly cleanup (1st of month at 04:00)
0 4 1 * * find /var/exports/nexus/audit -name "audit_*.jsonl.gz" -mtime +90 -delete
```

---

## Monitoring

### Check Export Freshness

```bash
#!/bin/bash
# Alert if no export in last 25 hours

EXPORT_DIR="/var/exports/nexus/audit"
MAX_AGE=90000  # 25 hours in seconds

LATEST=$(ls -t "$EXPORT_DIR"/audit_*.jsonl.gz 2>/dev/null | head -1)

if [ -z "$LATEST" ]; then
    echo "No audit export found"
    exit 1
fi

AGE=$(( $(date +%s) - $(stat -c%Y "$LATEST") ))

if [ "$AGE" -gt "$MAX_AGE" ]; then
    echo "Audit export is $((AGE / 3600)) hours old - too old!"
    exit 1
else
    echo "Audit export is $((AGE / 3600)) hours old - OK"
    exit 0
fi
```

Add monitoring cron:
```cron
# Check audit export freshness at 08:00
0 8 * * * /var/www/nexus/scripts/pilot/check_audit_export.sh || mail -s "NEXUS Audit Export Alert" security@council.gov.uk
```

---

## Security Considerations

1. **Export directory permissions**: 700 (owner only)
2. **Export file permissions**: 600 (owner read/write only)
3. **Offsite storage**: Enable versioning and object lock for immutability
4. **Access logging**: Enable access logs on storage buckets
5. **Encryption**: Consider GPG signing exports for tamper evidence
6. **Separation**: Store audit logs separate from application backups

---

## Incident Investigation

To search audit exports:

```bash
# Search for specific user activity
zgrep '"user_id":123' /var/exports/nexus/audit/audit_*.jsonl.gz

# Search for specific action type
zgrep '"action_type":"super_admin_granted"' /var/exports/nexus/audit/audit_*.jsonl.gz

# Search date range
for f in audit_202602{01..07}.jsonl.gz; do
    zgrep '"action":"login"' "$f"
done
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
