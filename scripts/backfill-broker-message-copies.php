<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Backfill Broker Message Copies
 *
 * Retroactively applies broker visibility rules to existing messages.
 * This script is needed because a column name mismatch bug (user_a vs user1_id)
 * caused all broker message copy attempts to silently fail, meaning brokers
 * never received copies of any messages for review.
 *
 * IMPORTANT: This is a UK compliance requirement — brokers must be able
 * to review messages for safeguarding purposes.
 *
 * Usage:
 *   docker exec nexus-php-app php /var/www/html/scripts/backfill-broker-message-copies.php [--tenant=2] [--dry-run]
 *
 * Options:
 *   --tenant=N    Only backfill for a specific tenant (default: all tenants with broker_visibility enabled)
 *   --dry-run     Show what would be copied without actually copying
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\BrokerControlConfigService;

// Parse CLI arguments
$dryRun = in_array('--dry-run', $argv ?? []);
$tenantFilter = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--tenant=')) {
        $tenantFilter = (int) substr($arg, 9);
    }
}

echo "=== Broker Message Copies Backfill ===\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE") . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Get tenants to process
$tenantQuery = "SELECT id, name, slug FROM tenants WHERE id > 0";
$tenantParams = [];
if ($tenantFilter) {
    $tenantQuery .= " AND id = ?";
    $tenantParams[] = $tenantFilter;
}
$tenants = Database::query($tenantQuery, $tenantParams)->fetchAll();

$totalCopied = 0;
$totalSkipped = 0;
$totalFirstContacts = 0;

foreach ($tenants as $tenant) {
    TenantContext::setById($tenant['id']);
    $tenantId = $tenant['id'];

    // Check if broker visibility is enabled for this tenant
    if (!BrokerControlConfigService::isBrokerVisibilityEnabled()) {
        echo "Tenant {$tenantId} ({$tenant['slug']}): Broker visibility DISABLED — skipping\n";
        continue;
    }

    $config = BrokerControlConfigService::getConfig('broker_visibility');
    $messagingConfig = BrokerControlConfigService::getConfig('messaging');

    echo "\n--- Tenant {$tenantId}: {$tenant['name']} ({$tenant['slug']}) ---\n";
    echo "  Config: copy_first_contact=" . ($config['copy_first_contact'] ? 'true' : 'false');
    echo ", copy_new_member=" . ($config['copy_new_member_messages'] ? 'true' : 'false');
    echo ", copy_high_risk=" . ($config['copy_high_risk_listing_messages'] ? 'true' : 'false');
    echo ", new_member_days=" . ($messagingConfig['new_member_monitoring_days'] ?? 30) . "\n";

    // Get all messages for this tenant, ordered by date
    $messages = Database::query(
        "SELECT m.id, m.sender_id, m.receiver_id, m.body, m.listing_id, m.created_at
         FROM messages m
         WHERE m.tenant_id = ?
         ORDER BY m.created_at ASC",
        [$tenantId]
    )->fetchAll();

    echo "  Total messages: " . count($messages) . "\n";

    if (empty($messages)) {
        echo "  No messages to process\n";
        continue;
    }

    // Track first contacts as we process (to mimic chronological discovery)
    $contactPairs = [];
    $tenantCopied = 0;
    $tenantSkipped = 0;
    $tenantFirstContacts = 0;

    // Get existing broker copies to avoid duplicates
    $existingCopies = [];
    $existing = Database::query(
        "SELECT original_message_id FROM broker_message_copies WHERE tenant_id = ?",
        [$tenantId]
    )->fetchAll();
    foreach ($existing as $e) {
        $existingCopies[$e['original_message_id']] = true;
    }

    // Get existing first contacts
    $existingFirstContacts = [];
    $existingFC = Database::query(
        "SELECT CONCAT(LEAST(user1_id, user2_id), '-', GREATEST(user1_id, user2_id)) as pair_key
         FROM user_first_contacts WHERE tenant_id = ?",
        [$tenantId]
    )->fetchAll();
    foreach ($existingFC as $fc) {
        $existingFirstContacts[$fc['pair_key']] = true;
        $contactPairs[$fc['pair_key']] = true;
    }

    // Get users under monitoring
    $monitoredUsers = [];
    $monitored = Database::query(
        "SELECT user_id FROM user_messaging_restrictions
         WHERE tenant_id = ? AND under_monitoring = 1",
        [$tenantId]
    )->fetchAll();
    foreach ($monitored as $mu) {
        $monitoredUsers[$mu['user_id']] = true;
    }

    // Get high-risk listings
    $highRiskListings = [];
    $riskTags = Database::query(
        "SELECT listing_id FROM listing_risk_tags
         WHERE tenant_id = ? AND risk_level IN ('high', 'critical')",
        [$tenantId]
    )->fetchAll();
    foreach ($riskTags as $rt) {
        $highRiskListings[$rt['listing_id']] = true;
    }

    // Get new member monitoring days
    $newMemberDays = (int) ($messagingConfig['new_member_monitoring_days'] ?? 30);

    // Get user join dates for new member check
    $userJoinDates = [];
    $users = Database::query(
        "SELECT id, created_at FROM users WHERE tenant_id = ?",
        [$tenantId]
    )->fetchAll();
    foreach ($users as $u) {
        $userJoinDates[$u['id']] = strtotime($u['created_at']);
    }

    foreach ($messages as $msg) {
        // Skip if already copied
        if (isset($existingCopies[$msg['id']])) {
            $tenantSkipped++;
            continue;
        }

        $senderId = (int) $msg['sender_id'];
        $receiverId = (int) $msg['receiver_id'];
        $listingId = $msg['listing_id'] ? (int) $msg['listing_id'] : null;
        $sentAt = strtotime($msg['created_at']);
        $copyReason = null;

        // Check copy rules (same order as BrokerMessageVisibilityService::shouldCopyMessage)

        // 1. Flagged user
        if (isset($monitoredUsers[$senderId])) {
            $copyReason = 'flagged_user';
        }

        // 2. First contact
        if (!$copyReason && $config['copy_first_contact']) {
            $ids = [min($senderId, $receiverId), max($senderId, $receiverId)];
            $pairKey = $ids[0] . '-' . $ids[1];

            if (!isset($contactPairs[$pairKey])) {
                $copyReason = 'first_contact';
                $contactPairs[$pairKey] = true;

                // Record first contact
                if (!isset($existingFirstContacts[$pairKey]) && !$dryRun) {
                    Database::query(
                        "INSERT IGNORE INTO user_first_contacts
                         (tenant_id, user1_id, user2_id, first_message_id, first_contact_at)
                         VALUES (?, ?, ?, ?, ?)",
                        [$tenantId, $ids[0], $ids[1], $msg['id'], $msg['created_at']]
                    );
                    $tenantFirstContacts++;
                } elseif ($dryRun && !isset($existingFirstContacts[$pairKey])) {
                    $tenantFirstContacts++;
                }
            }
        }

        // 3. New member (sender joined within N days of sending)
        if (!$copyReason && $config['copy_new_member_messages'] && $newMemberDays > 0) {
            $joinDate = $userJoinDates[$senderId] ?? 0;
            if ($joinDate > 0) {
                $daysAfterJoin = ($sentAt - $joinDate) / 86400;
                if ($daysAfterJoin <= $newMemberDays) {
                    $copyReason = 'new_member';
                }
            }
        }

        // 4. High risk listing
        if (!$copyReason && $config['copy_high_risk_listing_messages'] && $listingId) {
            if (isset($highRiskListings[$listingId])) {
                $copyReason = 'high_risk_listing';
            }
        }

        // Note: We do NOT apply random sampling for backfill — that's only for real-time

        if ($copyReason) {
            $ids = [(int) $senderId, (int) $receiverId];
            sort($ids);
            $conversationKey = md5(implode('-', $ids));

            if ($dryRun) {
                echo "  [DRY RUN] Would copy msg #{$msg['id']} (sender={$senderId}, receiver={$receiverId}, reason={$copyReason})\n";
            } else {
                Database::query(
                    "INSERT INTO broker_message_copies
                     (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $tenantId,
                        $msg['id'],
                        $conversationKey,
                        $senderId,
                        $receiverId,
                        $msg['body'],
                        $msg['created_at'],
                        $copyReason,
                    ]
                );
            }
            $tenantCopied++;
        } else {
            $tenantSkipped++;
        }
    }

    echo "  Copied: {$tenantCopied}, Skipped: {$tenantSkipped}, First contacts recorded: {$tenantFirstContacts}\n";
    $totalCopied += $tenantCopied;
    $totalSkipped += $tenantSkipped;
    $totalFirstContacts += $tenantFirstContacts;
}

echo "\n=== TOTALS ===\n";
echo "Messages copied to broker queue: {$totalCopied}\n";
echo "Messages skipped (no rule match or already copied): {$totalSkipped}\n";
echo "First contact records created: {$totalFirstContacts}\n";
echo ($dryRun ? "\n*** DRY RUN — no changes were made ***\n" : "\nBackfill complete.\n");
