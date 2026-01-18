<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use PDO;

class MatchingDiagnosticController
{
    /**
     * Test and diagnose matching service
     */
    public function index()
    {
        // Must be admin
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            die("Access denied - Admin only");
        }

        $tenantId = TenantContext::getId();

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Matching Service Diagnostic</title>
    <style>
        body { font-family: system-ui; background: #1e1e1e; color: #fff; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #333; }
        .success { color: #0f0; }
        .warning { color: #ff0; }
        .error { color: #f00; }
        h2 { color: #6366f1; margin-top: 30px; }
        pre { background: #2a2a2a; padding: 15px; border-radius: 8px; overflow-x: auto; }
        .card { background: #2a2a2a; padding: 20px; border-radius: 12px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔍 Matching Service Diagnostic</h1>
    <p>Testing for Tenant ID: <strong><?= $tenantId ?></strong></p>

    <?php
    // 1. Check if users have matching preferences set
    echo "<div class='card'>\n";
    echo "<h2>1. User Matching Preferences</h2>\n";
    $sql = "SELECT COUNT(*) as total FROM users WHERE tenant_id = ?";
    $totalUsers = Database::query($sql, [$tenantId])->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "SELECT COUNT(*) as count FROM match_preferences WHERE tenant_id = ?";
    $usersWithPrefs = Database::query($sql, [$tenantId])->fetch(PDO::FETCH_ASSOC)['count'];

    echo "<p>Total users: <strong>{$totalUsers}</strong></p>\n";
    echo "<p>Users with match preferences: <strong class='" . ($usersWithPrefs > 0 ? 'success' : 'warning') . "'>{$usersWithPrefs}</strong></p>\n";

    if ($usersWithPrefs > 0) {
        // Show sample preferences
        $sql = "SELECT mp.*, u.name, u.email
                FROM match_preferences mp
                JOIN users u ON mp.user_id = u.id
                WHERE mp.tenant_id = ?
                LIMIT 5";
        $samplePrefs = Database::query($sql, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Sample User Preferences (showing 5)</h3>\n";
        echo "<table>\n";
        echo "<tr><th>User</th><th>Notify Hot</th><th>Notify Mutual</th><th>Frequency</th><th>Updated</th></tr>\n";
        foreach ($samplePrefs as $pref) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($pref['name']) . "</td>";
            echo "<td>" . ($pref['notify_hot_matches'] ? '✓' : '✗') . "</td>";
            echo "<td>" . ($pref['notify_mutual_matches'] ? '✓' : '✗') . "</td>";
            echo "<td>" . htmlspecialchars($pref['notification_frequency']) . "</td>";
            echo "<td>" . htmlspecialchars($pref['updated_at']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    echo "</div>\n";

    // 2. Check active listings
    echo "<div class='card'>\n";
    echo "<h2>2. Active Listings</h2>\n";
    $sql = "SELECT type, status, COUNT(*) as count
            FROM listings
            WHERE tenant_id = ?
            GROUP BY type, status";
    $listingStats = Database::query($sql, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>\n";
    echo "<tr><th>Type</th><th>Status</th><th>Count</th></tr>\n";
    foreach ($listingStats as $stat) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($stat['type']) . "</td>";
        echo "<td>" . htmlspecialchars($stat['status']) . "</td>";
        echo "<td>" . htmlspecialchars($stat['count']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    echo "</div>\n";

    // 3. Test matching for a random active user
    echo "<div class='card'>\n";
    echo "<h2>3. Test Live Matching</h2>\n";

    $sql = "SELECT u.id, u.name, u.email
            FROM users u
            WHERE u.tenant_id = ?
            AND u.id IN (SELECT user_id FROM match_preferences WHERE tenant_id = ?)
            ORDER BY RAND()
            LIMIT 1";
    $testUser = Database::query($sql, [$tenantId, $tenantId])->fetch(PDO::FETCH_ASSOC);

    if ($testUser) {
        echo "<p>Testing matches for user: <strong>" . htmlspecialchars($testUser['name']) . "</strong> (ID: {$testUser['id']})</p>\n";

        try {
            $matches = \Nexus\Services\MatchingService::getSuggestionsForUser($testUser['id'], 10);

            echo "<p class='success'>✓ Matching service is working!</p>\n";
            echo "<p>Found <strong>" . count($matches) . "</strong> matches for this user.</p>\n";

            if (count($matches) > 0) {
                echo "<h3>Top 5 Matches</h3>\n";
                echo "<table>\n";
                echo "<tr><th>Listing</th><th>Type</th><th>Score</th><th>Posted By</th><th>Match Type</th></tr>\n";
                foreach (array_slice($matches, 0, 5) as $match) {
                    $matchType = $match['match_type'] ?? 'standard';
                    $typeColor = $matchType === 'mutual' ? '#0f0' : ($match['match_score'] >= 85 ? '#ff0' : '#fff');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($match['title']) . "</td>";
                    echo "<td>" . htmlspecialchars($match['listing_type']) . "</td>";
                    echo "<td><strong>" . intval($match['match_score']) . "%</strong></td>";
                    echo "<td>" . htmlspecialchars($match['user_name']) . "</td>";
                    echo "<td style='color: {$typeColor}'>" . htmlspecialchars($matchType) . "</td>";
                    echo "</tr>\n";
                }
                echo "</table>\n";
            } else {
                echo "<p class='warning'>No matches found for this user (they may not have any listings or preferences set)</p>\n";
            }

        } catch (\Exception $e) {
            echo "<p class='error'>✗ Error testing matching service: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
        }
    } else {
        echo "<p class='warning'>No users with match preferences found to test.</p>\n";
    }
    echo "</div>\n";

    // 4. Check recent match notifications
    echo "<div class='card'>\n";
    echo "<h2>4. Recent Match Notifications (Last 7 Days)</h2>\n";
    $sql = "SELECT activity_type, frequency, status, COUNT(*) as count
            FROM notification_queue
            WHERE activity_type IN ('hot_match', 'mutual_match', 'match_digest')
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY activity_type, frequency, status";
    $recentNotifs = Database::query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recentNotifs)) {
        echo "<p class='warning'>⚠ No match notifications in the past 7 days.</p>\n";
    } else {
        echo "<table>\n";
        echo "<tr><th>Type</th><th>Frequency</th><th>Status</th><th>Count</th></tr>\n";
        foreach ($recentNotifs as $notif) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($notif['activity_type']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['frequency']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['status']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['count']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    echo "</div>\n";

    // 5. Check cron job execution
    echo "<div class='card'>\n";
    echo "<h2>5. Cron Job Status</h2>\n";

    // Check if cron_log table exists
    try {
        $sql = "SELECT * FROM cron_log
                WHERE job_name LIKE '%match%'
                ORDER BY run_at DESC
                LIMIT 5";
        $cronLogs = Database::query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $cronLogs = null;
    }

    if ($cronLogs === null) {
        echo "<p class='warning'>⚠ Cron log table doesn't exist. Cron logging is not set up.</p>\n";
    } elseif (empty($cronLogs)) {
        echo "<p class='warning'>⚠ No match-related cron jobs have run yet.</p>\n";
        echo "<p>The daily match digest cron needs to be configured to run automatically.</p>\n";
    } else {
        echo "<table>\n";
        echo "<tr><th>Job Name</th><th>Status</th><th>Run At</th><th>Output Preview</th></tr>\n";
        foreach ($cronLogs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['job_name']) . "</td>";
            echo "<td class='" . ($log['status'] === 'success' ? 'success' : 'error') . "'>" . htmlspecialchars($log['status']) . "</td>";
            echo "<td>" . htmlspecialchars($log['run_at']) . "</td>";
            echo "<td><pre style='max-width: 400px; white-space: pre-wrap;'>" . htmlspecialchars(substr($log['output'], 0, 200)) . "...</pre></td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    echo "</div>\n";

    // 6. Summary and recommendations
    echo "<div class='card'>\n";
    echo "<h2>6. Summary & Recommendations</h2>\n";
    echo "<ul style='line-height: 1.8;'>\n";

    if ($usersWithPrefs == 0) {
        echo "<li class='warning'>⚠ No users have match preferences set. Users need to configure their matching preferences.</li>\n";
    } else {
        echo "<li class='success'>✓ {$usersWithPrefs} users have match preferences configured.</li>\n";
    }

    $activeListings = 0;
    foreach ($listingStats as $stat) {
        if ($stat['status'] === 'active') {
            $activeListings += $stat['count'];
        }
    }
    if ($activeListings == 0) {
        echo "<li class='warning'>⚠ No active listings found. There need to be active listings for matching to work.</li>\n";
    } else {
        echo "<li class='success'>✓ {$activeListings} active listings available for matching.</li>\n";
    }

    if (empty($cronLogs)) {
        echo "<li class='warning'>⚠ Match digest cron job hasn't run yet. Make sure cron is configured properly.</li>\n";
    } else {
        echo "<li class='success'>✓ Match cron jobs are running.</li>\n";
    }

    if (empty($recentNotifs)) {
        echo "<li class='warning'>⚠ No match notifications generated in past 7 days. This could mean no new matches or cron not running.</li>\n";
    } else {
        echo "<li class='success'>✓ Match notifications are being generated.</li>\n";
    }

    echo "</ul>\n";
    echo "</div>\n";
    ?>

    <hr style='margin: 30px 0; border-color: #444;'>
    <p>
        <a href='<?= TenantContext::getBasePath() ?>/admin' style='color: #6366f1; text-decoration: none;'>← Back to Admin</a> |
        <a href='<?= TenantContext::getBasePath() ?>/cron/match-digest-daily' style='color: #6366f1; text-decoration: none;' target='_blank'>Run Match Digest Now</a> |
        <a href='<?= TenantContext::getBasePath() ?>/cron/process-queue' style='color: #6366f1; text-decoration: none;' target='_blank'>Process Queue</a>
    </p>
</body>
</html>
        <?php
        echo ob_get_clean();
    }
}
