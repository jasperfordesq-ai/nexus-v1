<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Models\User;
use Nexus\Models\Listing;
use Nexus\Models\Event;
use Nexus\Models\VolOpportunity;

class DigestService
{
    public static function sendWeeklyDigests()
    {
        // Loop all active tenants — each tenant gets their own digest content
        $tenants = Database::query(
            "SELECT id, name FROM tenants WHERE is_active = 1"
        )->fetchAll();

        $mailer = new Mailer();
        $totalCount = 0;

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];
            echo "Processing tenant {$tenantId} ({$tenant['name']})...\n";

            $start = date('Y-m-d H:i:s', strtotime('-7 days'));

            // Fetch tenant-scoped content
            $newOffers = Database::query(
                "SELECT l.*, u.name as user_name FROM listings l
                 JOIN users u ON l.user_id = u.id
                 WHERE l.tenant_id = ? AND l.type = 'offer' AND l.status = 'active'
                   AND l.created_at >= ?
                 ORDER BY l.created_at DESC LIMIT 5",
                [$tenantId, $start]
            )->fetchAll();

            $newRequests = Database::query(
                "SELECT l.*, u.name as user_name FROM listings l
                 JOIN users u ON l.user_id = u.id
                 WHERE l.tenant_id = ? AND l.type = 'request' AND l.status = 'active'
                   AND l.created_at >= ?
                 ORDER BY l.created_at DESC LIMIT 5",
                [$tenantId, $start]
            )->fetchAll();

            $upcomingEvents = Event::upcoming($tenantId, 5);

            if (empty($newOffers) && empty($newRequests) && empty($upcomingEvents)) {
                echo "  No new content for tenant {$tenantId}.\n";
                continue;
            }

            // Fetch users who want digests for this tenant
            $users = Database::query(
                "SELECT id, name, email FROM users WHERE tenant_id = ? AND receive_digests = 1",
                [$tenantId]
            )->fetchAll();

            echo "  Found " . count($users) . " subscribers.\n";

            foreach ($users as $user) {
                $html = self::renderTemplate($user, $newOffers, $newRequests, $upcomingEvents);

                try {
                    $mailer->send($user['email'], "Weekly Community Updates", $html);
                    $totalCount++;
                } catch (\Exception $e) {
                    echo "  Failed to send to {$user['email']}: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Sent $totalCount digests across " . count($tenants) . " tenants.\n";
    }

    private static function renderTemplate($user, $offers, $requests, $events)
    {
        // Simple PHP Template Buffer
        ob_start();
        $userName = $user['name'];
        require __DIR__ . '/../../views/emails/weekly_digest.php';
        return ob_get_clean();
    }
}
