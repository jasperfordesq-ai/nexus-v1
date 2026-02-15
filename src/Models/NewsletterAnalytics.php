<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class NewsletterAnalytics
{
    /**
     * Record an email open
     */
    public static function recordOpen($newsletterId, $trackingToken, $email, $userAgent = null, $ipAddress = null)
    {
        // Look up the newsletter's actual tenant_id (tracking requests may arrive
        // via a different domain than the tenant's, e.g. api.project-nexus.ie)
        $tenantId = self::getNewsletterTenantId($newsletterId) ?? TenantContext::getId();

        // Find the queue entry by tracking token
        $queueId = null;
        if ($trackingToken) {
            $queue = Database::query(
                "SELECT id FROM newsletter_queue WHERE newsletter_id = ? AND tracking_token = ? LIMIT 1",
                [$newsletterId, $trackingToken]
            )->fetch();
            $queueId = $queue['id'] ?? null;
        }

        // Record the open
        Database::query(
            "INSERT INTO newsletter_opens (tenant_id, newsletter_id, queue_id, email, user_agent, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $newsletterId, $queueId, $email, $userAgent, $ipAddress]
        );

        // Update newsletter stats
        self::updateOpenStats($newsletterId, $tenantId);

        return true;
    }

    /**
     * Record a link click
     */
    public static function recordClick($newsletterId, $trackingToken, $email, $url, $linkId, $userAgent = null, $ipAddress = null)
    {
        // Look up the newsletter's actual tenant_id (tracking requests may arrive
        // via a different domain than the tenant's, e.g. api.project-nexus.ie)
        $tenantId = self::getNewsletterTenantId($newsletterId) ?? TenantContext::getId();

        // Find the queue entry by tracking token
        $queueId = null;
        if ($trackingToken) {
            $queue = Database::query(
                "SELECT id FROM newsletter_queue WHERE newsletter_id = ? AND tracking_token = ? LIMIT 1",
                [$newsletterId, $trackingToken]
            )->fetch();
            $queueId = $queue['id'] ?? null;
        }

        // Record the click
        Database::query(
            "INSERT INTO newsletter_clicks (tenant_id, newsletter_id, queue_id, email, url, link_id, user_agent, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$tenantId, $newsletterId, $queueId, $email, $url, $linkId, $userAgent, $ipAddress]
        );

        // Update newsletter stats
        self::updateClickStats($newsletterId, $tenantId);

        return true;
    }

    /**
     * Update open stats on the newsletter
     */
    private static function updateOpenStats($newsletterId, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Get total opens
        $total = Database::query(
            "SELECT COUNT(*) as count FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?",
            [$tenantId, $newsletterId]
        )->fetch();

        // Get unique opens (by email)
        $unique = Database::query(
            "SELECT COUNT(DISTINCT email) as count FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?",
            [$tenantId, $newsletterId]
        )->fetch();

        Database::query(
            "UPDATE newsletters SET total_opens = ?, unique_opens = ? WHERE id = ? AND tenant_id = ?",
            [$total['count'], $unique['count'], $newsletterId, $tenantId]
        );
    }

    /**
     * Update click stats on the newsletter
     */
    private static function updateClickStats($newsletterId, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Get total clicks
        $total = Database::query(
            "SELECT COUNT(*) as count FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?",
            [$tenantId, $newsletterId]
        )->fetch();

        // Get unique clicks (by email)
        $unique = Database::query(
            "SELECT COUNT(DISTINCT email) as count FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?",
            [$tenantId, $newsletterId]
        )->fetch();

        Database::query(
            "UPDATE newsletters SET total_clicks = ?, unique_clicks = ? WHERE id = ? AND tenant_id = ?",
            [$total['count'], $unique['count'], $newsletterId, $tenantId]
        );
    }

    /**
     * Get detailed analytics for a newsletter
     */
    public static function getDetails($newsletterId)
    {
        $tenantId = TenantContext::getId();

        // Open rate over time (by hour)
        $opensOverTime = Database::query(
            "SELECT DATE_FORMAT(opened_at, '%Y-%m-%d %H:00') as hour, COUNT(*) as opens
             FROM newsletter_opens
             WHERE tenant_id = ? AND newsletter_id = ?
             GROUP BY hour
             ORDER BY hour ASC",
            [$tenantId, $newsletterId]
        )->fetchAll();

        // Top clicked links
        $topLinks = Database::query(
            "SELECT url, COUNT(*) as clicks, COUNT(DISTINCT email) as unique_clicks
             FROM newsletter_clicks
             WHERE tenant_id = ? AND newsletter_id = ?
             GROUP BY url
             ORDER BY clicks DESC
             LIMIT 10",
            [$tenantId, $newsletterId]
        )->fetchAll();

        // Recent activity
        $recentActivity = Database::query(
            "(SELECT 'open' as type, email, opened_at as timestamp, NULL as url
              FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?)
             UNION ALL
             (SELECT 'click' as type, email, clicked_at as timestamp, url
              FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?)
             ORDER BY timestamp DESC
             LIMIT 50",
            [$tenantId, $newsletterId, $tenantId, $newsletterId]
        )->fetchAll();

        // Device breakdown from user agents
        $deviceStats = self::getDeviceBreakdown($newsletterId);

        return [
            'opens_over_time' => $opensOverTime,
            'top_links' => $topLinks,
            'recent_activity' => $recentActivity,
            'device_stats' => $deviceStats
        ];
    }

    /**
     * Parse user agents to get device breakdown
     */
    private static function getDeviceBreakdown($newsletterId)
    {
        $tenantId = TenantContext::getId();

        $opens = Database::query(
            "SELECT user_agent FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ? AND user_agent IS NOT NULL",
            [$tenantId, $newsletterId]
        )->fetchAll();

        $devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'unknown' => 0];

        foreach ($opens as $open) {
            $ua = strtolower($open['user_agent']);
            if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
                $devices['mobile']++;
            } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
                $devices['tablet']++;
            } elseif (strpos($ua, 'windows') !== false || strpos($ua, 'macintosh') !== false || strpos($ua, 'linux') !== false) {
                $devices['desktop']++;
            } else {
                $devices['unknown']++;
            }
        }

        return $devices;
    }

    /**
     * Get email clients breakdown
     */
    public static function getEmailClients($newsletterId)
    {
        $tenantId = TenantContext::getId();

        $opens = Database::query(
            "SELECT user_agent FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ? AND user_agent IS NOT NULL",
            [$tenantId, $newsletterId]
        )->fetchAll();

        $clients = [];

        foreach ($opens as $open) {
            $ua = strtolower($open['user_agent']);
            $client = 'Other';

            if (strpos($ua, 'outlook') !== false) {
                $client = 'Outlook';
            } elseif (strpos($ua, 'gmail') !== false || strpos($ua, 'googleimageproxy') !== false) {
                $client = 'Gmail';
            } elseif (strpos($ua, 'apple mail') !== false || strpos($ua, 'applewebkit') !== false && strpos($ua, 'mobile') === false) {
                $client = 'Apple Mail';
            } elseif (strpos($ua, 'yahoo') !== false) {
                $client = 'Yahoo Mail';
            } elseif (strpos($ua, 'thunderbird') !== false) {
                $client = 'Thunderbird';
            }

            if (!isset($clients[$client])) {
                $clients[$client] = 0;
            }
            $clients[$client]++;
        }

        arsort($clients);
        return $clients;
    }

    /**
     * Get list of subscribers who opened
     */
    public static function getOpeners($newsletterId, $limit = 100, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT email, MIN(opened_at) as first_opened, COUNT(*) as open_count
             FROM newsletter_opens
             WHERE tenant_id = ? AND newsletter_id = ?
             GROUP BY email
             ORDER BY first_opened DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $newsletterId, $limit, $offset]
        )->fetchAll();
    }

    /**
     * Get list of subscribers who clicked
     */
    public static function getClickers($newsletterId, $limit = 100, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT email, MIN(clicked_at) as first_clicked, COUNT(*) as click_count, COUNT(DISTINCT url) as unique_links
             FROM newsletter_clicks
             WHERE tenant_id = ? AND newsletter_id = ?
             GROUP BY email
             ORDER BY first_clicked DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $newsletterId, $limit, $offset]
        )->fetchAll();
    }

    /**
     * Get list of recipients who did NOT open the newsletter
     */
    public static function getNonOpeners($newsletterId)
    {
        $tenantId = TenantContext::getId();

        // Get all recipients who were sent the newsletter but didn't open
        return Database::query(
            "SELECT q.id, q.user_id, q.email, q.sent_at, u.first_name, u.last_name
             FROM newsletter_queue q
             LEFT JOIN users u ON q.user_id = u.id
             WHERE q.newsletter_id = ?
             AND q.status = 'sent'
             AND q.email NOT IN (
                 SELECT DISTINCT email FROM newsletter_opens
                 WHERE newsletter_id = ? AND tenant_id = ?
             )
             ORDER BY q.sent_at DESC",
            [$newsletterId, $newsletterId, $tenantId]
        )->fetchAll();
    }

    /**
     * Count non-openers for a newsletter
     */
    public static function countNonOpeners($newsletterId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COUNT(*) as count
             FROM newsletter_queue q
             WHERE q.newsletter_id = ?
             AND q.status = 'sent'
             AND q.email NOT IN (
                 SELECT DISTINCT email FROM newsletter_opens
                 WHERE newsletter_id = ? AND tenant_id = ?
             )",
            [$newsletterId, $newsletterId, $tenantId]
        )->fetch();

        return $result['count'] ?? 0;
    }

    /**
     * Get recipients who opened but didn't click
     */
    public static function getOpenersNoClick($newsletterId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT DISTINCT o.email, u.first_name, u.last_name, MIN(o.opened_at) as first_opened
             FROM newsletter_opens o
             LEFT JOIN newsletter_queue q ON o.email = q.email AND q.newsletter_id = ?
             LEFT JOIN users u ON q.user_id = u.id
             WHERE o.newsletter_id = ? AND o.tenant_id = ?
             AND o.email NOT IN (
                 SELECT DISTINCT email FROM newsletter_clicks
                 WHERE newsletter_id = ? AND tenant_id = ?
             )
             GROUP BY o.email, u.first_name, u.last_name
             ORDER BY first_opened DESC",
            [$newsletterId, $newsletterId, $tenantId, $newsletterId, $tenantId]
        )->fetchAll();
    }

    /**
     * Get aggregate engagement patterns by hour of day
     * Analyzes when subscribers tend to open emails
     */
    public static function getEngagementByHour($limit = 1000)
    {
        $tenantId = TenantContext::getId();

        // Get opens grouped by hour
        $opens = Database::query(
            "SELECT HOUR(opened_at) as hour, COUNT(*) as opens
             FROM newsletter_opens
             WHERE tenant_id = ?
             GROUP BY HOUR(opened_at)
             ORDER BY hour ASC",
            [$tenantId]
        )->fetchAll();

        // Get clicks grouped by hour
        $clicks = Database::query(
            "SELECT HOUR(clicked_at) as hour, COUNT(*) as clicks
             FROM newsletter_clicks
             WHERE tenant_id = ?
             GROUP BY HOUR(clicked_at)
             ORDER BY hour ASC",
            [$tenantId]
        )->fetchAll();

        // Build hourly data array (0-23)
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = ['hour' => $h, 'opens' => 0, 'clicks' => 0];
        }

        foreach ($opens as $row) {
            $hourlyData[(int)$row['hour']]['opens'] = (int)$row['opens'];
        }

        foreach ($clicks as $row) {
            $hourlyData[(int)$row['hour']]['clicks'] = (int)$row['clicks'];
        }

        return array_values($hourlyData);
    }

    /**
     * Get aggregate engagement patterns by day of week
     * Analyzes which days have best engagement
     */
    public static function getEngagementByDay()
    {
        $tenantId = TenantContext::getId();

        // Get opens grouped by day of week (0=Sunday, 6=Saturday)
        $opens = Database::query(
            "SELECT DAYOFWEEK(opened_at) as day_num, COUNT(*) as opens
             FROM newsletter_opens
             WHERE tenant_id = ?
             GROUP BY DAYOFWEEK(opened_at)
             ORDER BY day_num ASC",
            [$tenantId]
        )->fetchAll();

        // Get clicks grouped by day
        $clicks = Database::query(
            "SELECT DAYOFWEEK(clicked_at) as day_num, COUNT(*) as clicks
             FROM newsletter_clicks
             WHERE tenant_id = ?
             GROUP BY DAYOFWEEK(clicked_at)
             ORDER BY day_num ASC",
            [$tenantId]
        )->fetchAll();

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Build daily data array
        $dailyData = [];
        for ($d = 1; $d <= 7; $d++) {
            $dailyData[$d] = [
                'day_num' => $d,
                'day_name' => $dayNames[$d - 1],
                'opens' => 0,
                'clicks' => 0
            ];
        }

        foreach ($opens as $row) {
            $dailyData[(int)$row['day_num']]['opens'] = (int)$row['opens'];
        }

        foreach ($clicks as $row) {
            $dailyData[(int)$row['day_num']]['clicks'] = (int)$row['clicks'];
        }

        return array_values($dailyData);
    }

    /**
     * Calculate optimal send time based on historical engagement
     * Returns recommended times ranked by engagement score
     */
    public static function getOptimalSendTimes($topCount = 5)
    {
        $tenantId = TenantContext::getId();

        // Get total engagement data
        $totalOpens = Database::query(
            "SELECT COUNT(*) as count FROM newsletter_opens WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['count'] ?? 0;

        // If not enough data, return default recommendations
        if ($totalOpens < 50) {
            return [
                'has_data' => false,
                'message' => 'Not enough engagement data yet. Send a few newsletters to get personalized recommendations.',
                'recommendations' => self::getDefaultRecommendations()
            ];
        }

        $hourlyData = self::getEngagementByHour();
        $dailyData = self::getEngagementByDay();

        // Calculate engagement scores (weighted: opens=1, clicks=2)
        $hourlyScores = [];
        $totalScore = 0;
        foreach ($hourlyData as $h) {
            $score = $h['opens'] + ($h['clicks'] * 2);
            $hourlyScores[$h['hour']] = $score;
            $totalScore += $score;
        }

        $dailyScores = [];
        $totalDayScore = 0;
        foreach ($dailyData as $d) {
            $score = $d['opens'] + ($d['clicks'] * 2);
            $dailyScores[$d['day_num']] = ['score' => $score, 'name' => $d['day_name']];
            $totalDayScore += $score;
        }

        // Find best hours
        arsort($hourlyScores);
        $bestHours = array_slice($hourlyScores, 0, $topCount, true);

        // Find best days
        uasort($dailyScores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        $bestDays = array_slice($dailyScores, 0, 3, true);

        // Build recommendations
        $recommendations = [];
        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($bestHours as $hour => $score) {
            $percentage = $totalScore > 0 ? round(($score / $totalScore) * 100, 1) : 0;
            $timeFormatted = date('g:i A', strtotime("$hour:00"));

            $recommendations[] = [
                'hour' => $hour,
                'time' => $timeFormatted,
                'score' => $score,
                'percentage' => $percentage,
                'label' => self::getTimeLabel($hour)
            ];
        }

        // Get best day-hour combinations
        $bestCombinations = [];
        foreach (array_keys($bestDays) as $dayNum) {
            foreach (array_slice(array_keys($bestHours), 0, 2) as $hour) {
                $bestCombinations[] = [
                    'day' => $dayNames[$dayNum],
                    'day_num' => $dayNum,
                    'hour' => $hour,
                    'time' => date('g:i A', strtotime("$hour:00"))
                ];
            }
        }

        return [
            'has_data' => true,
            'total_opens_analyzed' => $totalOpens,
            'recommendations' => $recommendations,
            'best_days' => array_values(array_map(function($d, $k) {
                return ['day_num' => $k, 'day_name' => $d['name'], 'score' => $d['score']];
            }, $bestDays, array_keys($bestDays))),
            'best_combinations' => array_slice($bestCombinations, 0, 3),
            'hourly_data' => $hourlyData,
            'daily_data' => $dailyData
        ];
    }

    /**
     * Get label for time of day
     */
    private static function getTimeLabel($hour)
    {
        if ($hour >= 5 && $hour < 9) return 'Early Morning';
        if ($hour >= 9 && $hour < 12) return 'Morning';
        if ($hour >= 12 && $hour < 14) return 'Midday';
        if ($hour >= 14 && $hour < 17) return 'Afternoon';
        if ($hour >= 17 && $hour < 20) return 'Evening';
        if ($hour >= 20 && $hour < 23) return 'Night';
        return 'Late Night';
    }

    /**
     * Default recommendations when no data is available
     */
    private static function getDefaultRecommendations()
    {
        return [
            ['hour' => 9, 'time' => '9:00 AM', 'label' => 'Morning', 'note' => 'Popular time for professional emails'],
            ['hour' => 10, 'time' => '10:00 AM', 'label' => 'Morning', 'note' => 'High engagement for B2B'],
            ['hour' => 14, 'time' => '2:00 PM', 'label' => 'Afternoon', 'note' => 'Post-lunch peak'],
            ['hour' => 17, 'time' => '5:00 PM', 'label' => 'Evening', 'note' => 'Good for community newsletters'],
            ['hour' => 20, 'time' => '8:00 PM', 'label' => 'Night', 'note' => 'Evening leisure reading']
        ];
    }

    /**
     * Update engagement patterns for a specific email address
     * Called after opens/clicks are recorded
     */
    public static function updateEngagementPattern($email, $openHour = null, $clickHour = null)
    {
        $tenantId = TenantContext::getId();

        // Check if pattern exists
        $existing = Database::query(
            "SELECT * FROM newsletter_engagement_patterns WHERE tenant_id = ? AND email = ?",
            [$tenantId, $email]
        )->fetch();

        if (!$existing) {
            // Create new pattern record
            $opensHourly = array_fill(0, 24, 0);
            $clicksHourly = array_fill(0, 24, 0);

            if ($openHour !== null) $opensHourly[$openHour] = 1;
            if ($clickHour !== null) $clicksHourly[$clickHour] = 1;

            Database::query(
                "INSERT INTO newsletter_engagement_patterns
                 (tenant_id, email, opens_by_hour, clicks_by_hour, total_opens, total_clicks)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $tenantId,
                    $email,
                    json_encode($opensHourly),
                    json_encode($clicksHourly),
                    $openHour !== null ? 1 : 0,
                    $clickHour !== null ? 1 : 0
                ]
            );
        } else {
            // Update existing
            $opensHourly = json_decode($existing['opens_by_hour'] ?? '[]', true) ?: array_fill(0, 24, 0);
            $clicksHourly = json_decode($existing['clicks_by_hour'] ?? '[]', true) ?: array_fill(0, 24, 0);

            if ($openHour !== null) {
                $opensHourly[$openHour] = ($opensHourly[$openHour] ?? 0) + 1;
            }
            if ($clickHour !== null) {
                $clicksHourly[$clickHour] = ($clicksHourly[$clickHour] ?? 0) + 1;
            }

            // Calculate best hour for this user
            $maxOpens = max($opensHourly);
            $bestHour = $maxOpens > 0 ? array_search($maxOpens, $opensHourly) : null;

            Database::query(
                "UPDATE newsletter_engagement_patterns
                 SET opens_by_hour = ?, clicks_by_hour = ?,
                     total_opens = total_opens + ?, total_clicks = total_clicks + ?,
                     best_hour = ?, last_updated = NOW()
                 WHERE tenant_id = ? AND email = ?",
                [
                    json_encode($opensHourly),
                    json_encode($clicksHourly),
                    $openHour !== null ? 1 : 0,
                    $clickHour !== null ? 1 : 0,
                    $bestHour,
                    $tenantId,
                    $email
                ]
            );
        }
    }

    /**
     * Get all activity for a newsletter with pagination
     */
    public static function getAllActivity($newsletterId, $limit = 50, $offset = 0, $type = null)
    {
        $tenantId = TenantContext::getId();

        if ($type === 'open') {
            $activity = Database::query(
                "SELECT 'open' as type, email, opened_at as timestamp, NULL as url
                 FROM newsletter_opens
                 WHERE tenant_id = ? AND newsletter_id = ?
                 ORDER BY timestamp DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $newsletterId, $limit, $offset]
            )->fetchAll();
        } elseif ($type === 'click') {
            $activity = Database::query(
                "SELECT 'click' as type, email, clicked_at as timestamp, url
                 FROM newsletter_clicks
                 WHERE tenant_id = ? AND newsletter_id = ?
                 ORDER BY timestamp DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $newsletterId, $limit, $offset]
            )->fetchAll();
        } else {
            $activity = Database::query(
                "(SELECT 'open' as type, email, opened_at as timestamp, NULL as url
                  FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?)
                 UNION ALL
                 (SELECT 'click' as type, email, clicked_at as timestamp, url
                  FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?)
                 ORDER BY timestamp DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $newsletterId, $tenantId, $newsletterId, $limit, $offset]
            )->fetchAll();
        }

        return $activity;
    }

    /**
     * Count total activity for a newsletter
     */
    public static function countAllActivity($newsletterId, $type = null)
    {
        $tenantId = TenantContext::getId();

        if ($type === 'open') {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?",
                [$tenantId, $newsletterId]
            )->fetch();
        } elseif ($type === 'click') {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?",
                [$tenantId, $newsletterId]
            )->fetch();
        } else {
            $opens = Database::query(
                "SELECT COUNT(*) as count FROM newsletter_opens WHERE tenant_id = ? AND newsletter_id = ?",
                [$tenantId, $newsletterId]
            )->fetch();
            $clicks = Database::query(
                "SELECT COUNT(*) as count FROM newsletter_clicks WHERE tenant_id = ? AND newsletter_id = ?",
                [$tenantId, $newsletterId]
            )->fetch();
            return ($opens['count'] ?? 0) + ($clicks['count'] ?? 0);
        }

        return $result['count'] ?? 0;
    }

    /**
     * Get send time heatmap data for visualization
     * Returns a matrix of day vs hour engagement
     */
    public static function getSendTimeHeatmap()
    {
        $tenantId = TenantContext::getId();

        // Get opens grouped by day of week and hour
        $data = Database::query(
            "SELECT DAYOFWEEK(opened_at) as day_num, HOUR(opened_at) as hour, COUNT(*) as count
             FROM newsletter_opens
             WHERE tenant_id = ?
             GROUP BY DAYOFWEEK(opened_at), HOUR(opened_at)
             ORDER BY day_num, hour",
            [$tenantId]
        )->fetchAll();

        // Initialize heatmap matrix (7 days x 24 hours)
        $heatmap = [];
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        for ($d = 1; $d <= 7; $d++) {
            $heatmap[$dayNames[$d - 1]] = array_fill(0, 24, 0);
        }

        // Fill in data
        $maxValue = 0;
        foreach ($data as $row) {
            $dayName = $dayNames[$row['day_num'] - 1];
            $heatmap[$dayName][$row['hour']] = (int)$row['count'];
            $maxValue = max($maxValue, (int)$row['count']);
        }

        return [
            'heatmap' => $heatmap,
            'max_value' => $maxValue,
            'days' => $dayNames
        ];
    }

    /**
     * Get the tenant_id for a newsletter by its ID.
     * Used by tracking endpoints where TenantContext may resolve to the wrong
     * tenant (e.g. tracking pixel served via api.project-nexus.ie instead of
     * the tenant's own domain).
     */
    private static function getNewsletterTenantId($newsletterId)
    {
        $result = Database::query(
            "SELECT tenant_id FROM newsletters WHERE id = ? LIMIT 1",
            [$newsletterId]
        )->fetch();

        return $result ? (int) $result['tenant_id'] : null;
    }
}
