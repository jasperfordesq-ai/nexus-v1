<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;

class DigestController
{
    /**
     * Get cron key from environment variable
     * Security: Moved from hardcoded value to environment variable
     */
    private function getCronKey(): string
    {
        // Check environment variable first, then fallback to checking .env file
        $key = getenv('NEXUS_CRON_KEY');
        if ($key === false || $key === '') {
            // Try to load from $_ENV if available
            $key = $_ENV['NEXUS_CRON_KEY'] ?? '';
        }
        if ($key === '') {
            error_log("WARNING: NEXUS_CRON_KEY environment variable not set. Cron endpoints are disabled.");
        }
        return $key;
    }

    public function weekly()
    {
        // 1. Security Check - Use environment variable for cron key
        $cronKey = $this->getCronKey();
        if ($cronKey === '') {
            http_response_code(503);
            die("Service Unavailable: Cron key not configured");
        }

        $key = $_GET['key'] ?? '';
        // Security: Use hash_equals for timing-safe comparison
        if (!hash_equals($cronKey, $key)) {
            http_response_code(403);
            die("Access Denied: Invalid Key");
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            die("Error: No Tenant Context");
        }

        // 2. Fetch Recent Data (Last 7 Days)
        $db = Database::getConnection();

        // New Listings
        $stmt = $db->prepare("SELECT l.title, l.type, l.user_id, u.name as author_name 
                              FROM listings l 
                              JOIN users u ON l.user_id = u.id
                              WHERE l.tenant_id = ? AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$tenantId]);
        $newListings = $stmt->fetchAll();

        // New Members
        $stmt = $db->prepare("SELECT name FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$tenantId]);
        $newMembers = $stmt->fetchAll();

        // 3. Render Logic
        if (empty($newListings) && empty($newMembers)) {
            die("No new activity this week. Digest skipped.");
        }

        // 4. Fetch Recipients
        $stmt = $db->prepare("SELECT email, name FROM users WHERE tenant_id = ? AND is_approved = 1");
        $stmt->execute([$tenantId]);
        $users = $stmt->fetchAll();

        $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
        $mailer = new Mailer();
        $count = 0;

        // DEBUG MODE - Only available in non-production environments
        // Security: Removed public debug mode to prevent information disclosure
        // To test digest emails, use the admin panel or check logs
        if (isset($_GET['debug'])) {
            // Only allow debug in development environment
            $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
            if ($appEnv !== 'development' && $appEnv !== 'local') {
                http_response_code(403);
                die("Debug mode disabled in production");
            }
            // Render for a dummy user (don't expose real user data)
            $userName = 'Test User';
            \Nexus\Core\View::render('emails/weekly_digest', [
                'userName' => $userName,
                'newListings' => $newListings,
                'newMembers' => $newMembers,
                'tenantName' => $tenantName
            ]);
            exit;
        }

        // SEND LOOP
        foreach ($users as $user) {
            if (empty($user['email'])) continue;

            $userName = $user['name'];

            // Render Email Body per user (so we can personalize name)
            // Capture output for email HTML
            ob_start();
            extract([
                'userName' => $userName,
                'newListings' => $newListings,
                'newMembers' => $newMembers,
                'tenantName' => $tenantName
            ]);
            \Nexus\Core\View::render('emails/weekly_digest', [
                'userName' => $userName,
                'newListings' => $newListings,
                'newMembers' => $newMembers,
                'tenantName' => $tenantName
            ]);
            $html = ob_get_clean();

            if ($mailer->send($user['email'], "Weekly Update - $tenantName", $html)) {
                $count++;
            }
        }

        echo "Success: Digest sent to $count users.";
    }
}
