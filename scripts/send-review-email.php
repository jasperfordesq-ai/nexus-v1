<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * One-time script to send a review email notification retroactively.
 * Usage: php scripts/send-review-email.php <review_id>
 */

require_once __DIR__ . '/../httpdocs/index.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\NotificationDispatcher;

$reviewId = (int)($argv[1] ?? 0);

if ($reviewId <= 0) {
    echo "Usage: php scripts/send-review-email.php <review_id>\n";
    exit(1);
}

// Fetch the review with user details
$stmt = Database::query(
    "SELECT r.*,
            reviewer.name as reviewer_name, reviewer.first_name as reviewer_first_name,
            receiver.name as receiver_name, receiver.email as receiver_email, receiver.tenant_id as receiver_tenant_id
     FROM reviews r
     JOIN users reviewer ON reviewer.id = r.reviewer_id
     JOIN users receiver ON receiver.id = r.receiver_id
     WHERE r.id = ?",
    [$reviewId]
);
$review = $stmt->fetch();

if (!$review) {
    echo "Review #{$reviewId} not found.\n";
    exit(1);
}

// Set tenant context for the receiver's tenant
$tenantId = $review['receiver_tenant_id'] ?? $review['receiver_tenant_id'];
TenantContext::setById((int)$tenantId);

echo "Review #{$reviewId}:\n";
echo "  Reviewer: {$review['reviewer_name']} (#{$review['reviewer_id']})\n";
echo "  Receiver: {$review['receiver_name']} ({$review['receiver_email']})\n";
echo "  Rating: {$review['rating']} stars\n";
echo "  Comment: " . substr($review['comment'] ?? '(none)', 0, 80) . "\n";
echo "  Tenant: {$tenantId}\n";
echo "\nSending email...\n";

NotificationDispatcher::sendReviewEmail(
    (int)$review['receiver_id'],
    $review['reviewer_name'],
    (int)$review['rating'],
    $review['comment'] ?? null,
    (bool)($review['is_anonymous'] ?? false)
);

echo "Done! Email sent to {$review['receiver_email']}\n";
