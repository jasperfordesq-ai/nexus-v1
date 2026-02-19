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
        $db = Database::getInstance();

        // 1. Fetch Content from last 7 days
        $start = date('Y-m-d H:i:s', strtotime('-7 days'));

        $newOffers = Listing::getRecent('offer', 5, $start);
        $newRequests = Listing::getRecent('request', 5, $start);
        $upcomingEvents = Event::upcoming(1, 5); // Global tenant 1 for now, or loop tenants
        // Note: For MVP we assume Tenant 1 or handle loop. 
        // Let's simpler: Just fetch global for now or loop all active tenants.
        // For simplicity: Single Tenant (1) for this MVP.
        $tenantId = 1;

        if (empty($newOffers) && empty($newRequests) && empty($upcomingEvents)) {
            echo "No new content to send.\n";
            return;
        }

        // 2. Fetch Users who want digests
        $users = Database::query("SELECT id, name, email FROM users WHERE tenant_id = ? AND receive_digests = 1", [$tenantId])->fetchAll();

        echo "Found " . count($users) . " subscribers.\n";

        // 3. Send Emails
        $mailer = new Mailer();
        $count = 0;

        foreach ($users as $user) {
            $html = self::renderTemplate($user, $newOffers, $newRequests, $upcomingEvents);

            try {
                // In a real system, queue this. For MVP, direct send.
                $mailer->send($user['email'], "Weekly Community Updates", $html);
                $count++;
                echo "Sent to {$user['email']}\n";
            } catch (\Exception $e) {
                echo "Failed to send to {$user['email']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Sent $count digests.\n";
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
