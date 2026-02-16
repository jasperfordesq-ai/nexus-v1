<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SmartSegmentSuggestionService
 *
 * Generates intelligent segment suggestions based on member data analysis.
 * Analyzes engagement patterns, activity levels, and email behavior to
 * recommend high-value segments for newsletter targeting.
 */
class SmartSegmentSuggestionService
{
    /** @var int Cache TTL in seconds (5 minutes) */
    private const CACHE_TTL = 300;

    /** @var array In-memory cache for suggestions */
    private static array $cache = [];

    /**
     * Get all smart segment suggestions with member counts
     */
    public static function getSuggestions(): array
    {
        $tenantId = TenantContext::getId();
        $cacheKey = "suggestions_{$tenantId}";

        // Check in-memory cache first
        if (isset(self::$cache[$cacheKey])) {
            $cached = self::$cache[$cacheKey];
            if ($cached['expires_at'] > time()) {
                return $cached['data'];
            }
            unset(self::$cache[$cacheKey]);
        }

        // Check file cache
        $cachedData = self::getFromFileCache($cacheKey);
        if ($cachedData !== null) {
            // Store in memory for subsequent calls
            self::$cache[$cacheKey] = [
                'data' => $cachedData,
                'expires_at' => time() + self::CACHE_TTL
            ];
            return $cachedData;
        }

        $suggestions = [];

        // Get each suggestion type with isolated error handling
        $suggestionMethods = [
            'highly_engaged' => 'getHighlyEngagedSuggestion',
            'at_risk' => 'getAtRiskSuggestion',
            'new_nurture' => 'getNewMembersSuggestion',
            'power_users' => 'getPowerUsersSuggestion',
            'reengagement' => 'getReengagementSuggestion',
            'active_contributors' => 'getActiveContributorsSuggestion',
            'comeback_members' => 'getComebackMembersSuggestion',
            'incomplete_profiles' => 'getIncompleteProfilesSuggestion',
            'lurkers' => 'getLurkersSuggestion',
            'anniversary_coming' => 'getAnniversaryComingSuggestion',
            'silent_givers' => 'getSilentGiversSuggestion',
            'listing_hoarders' => 'getListingHoardersSuggestion',
            'first_week' => 'getFirstWeekSuggestion',
            'one_hit_wonders' => 'getOneHitWondersSuggestion',
            'ghost_accounts' => 'getGhostAccountsSuggestion',
            'premium_profiles' => 'getPremiumProfilesSuggestion',
            'connectors' => 'getConnectorsSuggestion',
            'declining_activity' => 'getDecliningActivitySuggestion',
            'social_butterflies' => 'getSocialButterfliesSuggestion',
            'feedback_champions' => 'getFeedbackChampionsSuggestion',
            'verified_members' => 'getVerifiedMembersSuggestion',
            'well_reviewed' => 'getWellReviewedSuggestion',
            'unreviewed_veterans' => 'getUnreviewedVeteransSuggestion',
            'long_time_no_listers' => 'getLongTimeNoListersSuggestion',
            'multi_group_members' => 'getMultiGroupMembersSuggestion',
            'email_bounces' => 'getEmailBouncesSuggestion',
            'high_value_receivers' => 'getHighValueReceiversSuggestion',
            'admins' => 'getAdminsSuggestion',
            'organisations' => 'getOrganisationsSuggestion',
            'no_photo' => 'getNoPhotoSuggestion',
        ];

        foreach ($suggestionMethods as $id => $method) {
            try {
                $suggestion = self::$method();
                if ($suggestion !== null) {
                    $suggestion['id'] = $id;
                    $suggestions[] = $suggestion;
                }
            } catch (\Exception $e) {
                // Log error but continue with other suggestions
                error_log("SmartSegmentSuggestion error in {$method}: " . $e->getMessage());
                continue;
            }
        }

        // Sort by member count descending (most impactful first)
        usort($suggestions, function($a, $b) {
            return $b['member_count'] <=> $a['member_count'];
        });

        // Cache the results
        self::$cache[$cacheKey] = [
            'data' => $suggestions,
            'expires_at' => time() + self::CACHE_TTL
        ];
        self::saveToFileCache($cacheKey, $suggestions);

        return $suggestions;
    }

    /**
     * Get cached data from file storage
     */
    private static function getFromFileCache(string $key): ?array
    {
        $cacheFile = self::getCacheFilePath($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $content = file_get_contents($cacheFile);
            if ($content === false) {
                return null;
            }

            $cached = json_decode($content, true);
            if (!is_array($cached) || !isset($cached['expires_at']) || !isset($cached['data'])) {
                return null;
            }

            if ($cached['expires_at'] < time()) {
                @unlink($cacheFile);
                return null;
            }

            return $cached['data'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save data to file cache
     */
    private static function saveToFileCache(string $key, array $data): void
    {
        $cacheFile = self::getCacheFilePath($key);
        $cacheDir = dirname($cacheFile);

        try {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $content = json_encode([
                'expires_at' => time() + self::CACHE_TTL,
                'data' => $data
            ]);

            file_put_contents($cacheFile, $content, LOCK_EX);
        } catch (\Exception $e) {
            // Silently fail - caching is optional
            error_log("SmartSegmentSuggestion cache write failed: " . $e->getMessage());
        }
    }

    /**
     * Get the cache file path for a given key
     */
    private static function getCacheFilePath(string $key): string
    {
        $cacheDir = sys_get_temp_dir() . '/nexus_cache';
        return $cacheDir . '/' . md5($key) . '.json';
    }

    /**
     * Clear all suggestion caches (call after segment data changes)
     */
    public static function clearCache(): void
    {
        self::$cache = [];

        $cacheDir = sys_get_temp_dir() . '/nexus_cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Get a specific suggestion by ID
     */
    public static function getSuggestionById(string $id): ?array
    {
        $methodMap = [
            'highly_engaged' => 'getHighlyEngagedSuggestion',
            'at_risk' => 'getAtRiskSuggestion',
            'new_nurture' => 'getNewMembersSuggestion',
            'power_users' => 'getPowerUsersSuggestion',
            'reengagement' => 'getReengagementSuggestion',
            'active_contributors' => 'getActiveContributorsSuggestion',
            'comeback_members' => 'getComebackMembersSuggestion',
            'incomplete_profiles' => 'getIncompleteProfilesSuggestion',
            'lurkers' => 'getLurkersSuggestion',
            'anniversary_coming' => 'getAnniversaryComingSuggestion',
            'silent_givers' => 'getSilentGiversSuggestion',
            'listing_hoarders' => 'getListingHoardersSuggestion',
            'first_week' => 'getFirstWeekSuggestion',
            'one_hit_wonders' => 'getOneHitWondersSuggestion',
            'ghost_accounts' => 'getGhostAccountsSuggestion',
            'premium_profiles' => 'getPremiumProfilesSuggestion',
            'connectors' => 'getConnectorsSuggestion',
            'declining_activity' => 'getDecliningActivitySuggestion',
            'social_butterflies' => 'getSocialButterfliesSuggestion',
            'feedback_champions' => 'getFeedbackChampionsSuggestion',
            'verified_members' => 'getVerifiedMembersSuggestion',
            'well_reviewed' => 'getWellReviewedSuggestion',
            'unreviewed_veterans' => 'getUnreviewedVeteransSuggestion',
            'long_time_no_listers' => 'getLongTimeNoListersSuggestion',
            'multi_group_members' => 'getMultiGroupMembersSuggestion',
            'email_bounces' => 'getEmailBouncesSuggestion',
            'high_value_receivers' => 'getHighValueReceiversSuggestion',
            'admins' => 'getAdminsSuggestion',
            'organisations' => 'getOrganisationsSuggestion',
            'no_photo' => 'getNoPhotoSuggestion',
        ];

        if (!isset($methodMap[$id])) {
            return null;
        }

        try {
            $suggestion = self::{$methodMap[$id]}();
            if ($suggestion) {
                $suggestion['id'] = $id;
            }
            return $suggestion;
        } catch (\Exception $e) {
            error_log("SmartSegmentSuggestion error getting {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Highly Engaged Members
     * Members with high email open rates and click rates
     */
    private static function getHighlyEngagedSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Count members with high engagement
            $count = Database::query("
                SELECT COUNT(DISTINCT u.id) as cnt
                FROM users u
                JOIN newsletter_queue nq ON nq.user_id = u.id AND nq.status = 'sent'
                WHERE u.tenant_id = ? AND u.is_approved = 1
                GROUP BY u.id
                HAVING
                    COUNT(DISTINCT nq.newsletter_id) >= 3
                    AND (
                        SELECT COUNT(DISTINCT no.newsletter_id)
                        FROM newsletter_opens no
                        WHERE no.email = u.email
                    ) * 100.0 / COUNT(DISTINCT nq.newsletter_id) >= 50
            ", [$tenantId])->fetchAll(\PDO::FETCH_COLUMN);

            $memberCount = count($count);

            if ($memberCount === 0) {
                return null;
            }

            return [
                'name' => 'Highly Engaged Members',
                'description' => 'Members who regularly open and interact with your newsletters',
                'explanation' => "These {$memberCount} members have opened 50%+ of your newsletters. They are your most engaged audience and ideal for important announcements.",
                'icon' => 'fa-fire',
                'color' => '#10b981',
                'member_count' => $memberCount,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'email_open_rate', 'operator' => 'at_least', 'value' => 50],
                        ['field' => 'newsletters_received', 'operator' => 'at_least', 'value' => 3]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * At-Risk Members
     * Members who haven't logged in recently and have low engagement
     */
    private static function getAtRiskSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), COALESCE(u.last_login_at, u.created_at)) > 60
                AND (
                    SELECT COUNT(DISTINCT nq.newsletter_id)
                    FROM newsletter_queue nq
                    LEFT JOIN newsletter_opens no ON nq.email = no.email AND nq.newsletter_id = no.newsletter_id
                    WHERE nq.user_id = u.id AND nq.status = 'sent'
                    AND no.id IS NOT NULL
                ) * 100.0 / NULLIF((
                    SELECT COUNT(DISTINCT nq2.newsletter_id)
                    FROM newsletter_queue nq2
                    WHERE nq2.user_id = u.id AND nq2.status = 'sent'
                ), 0) < 20
                OR (
                    SELECT COUNT(DISTINCT nq3.newsletter_id)
                    FROM newsletter_queue nq3
                    WHERE nq3.user_id = u.id AND nq3.status = 'sent'
                ) = 0
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'At-Risk Members',
                'description' => 'Members showing signs of disengagement who may need re-activation',
                'explanation' => "These {$count} members haven't logged in for 60+ days and have low email engagement. Consider a win-back campaign.",
                'icon' => 'fa-exclamation-triangle',
                'color' => '#ef4444',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'login_recency', 'operator' => 'older_than_days', 'value' => 60],
                        ['field' => 'activity_score', 'operator' => 'equals', 'value' => 'low']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * New Members Needing Nurture
     * Recently joined members with low activity
     */
    private static function getNewMembersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), u.created_at) <= 30
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE (t.sender_id = u.id OR t.receiver_id = u.id)
                    AND t.status = 'completed'
                ) < 3
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'New Members Needing Nurture',
                'description' => 'Recently joined members who need encouragement to get started',
                'explanation' => "These {$count} members joined in the last 30 days but haven't completed many transactions. Help them discover the community!",
                'icon' => 'fa-seedling',
                'color' => '#8b5cf6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'newer_than_days', 'value' => 30],
                        ['field' => 'transaction_count', 'operator' => 'less_than', 'value' => 3]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Power Users
     * Members with high CommunityRank scores (top 10%)
     */
    private static function getPowerUsersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Calculate total approved users first
            $totalUsers = Database::query("
                SELECT COUNT(*) as cnt
                FROM users
                WHERE tenant_id = ? AND is_approved = 1
            ", [$tenantId])->fetch()['cnt'];

            if ($totalUsers < 10) {
                return null; // Not enough users for meaningful percentiles
            }

            $top10Count = max(1, (int) ceil($totalUsers * 0.10));

            return [
                'name' => 'Power Users',
                'description' => 'Your most active and valuable community members',
                'explanation' => "These ~{$top10Count} members are in the top 10% by activity, contributions, and reputation. They are your community champions!",
                'icon' => 'fa-crown',
                'color' => '#f59e0b',
                'member_count' => $top10Count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'community_rank', 'operator' => 'equals', 'value' => 'top_10']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Re-engagement Candidates
     * Members who received newsletters but haven't opened recent ones
     */
    private static function getReengagementSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Find users who have received 5+ newsletters but haven't opened any in the last 3
            $count = Database::query("
                SELECT COUNT(DISTINCT u.id) as cnt
                FROM users u
                WHERE u.tenant_id = ? AND u.is_approved = 1
                AND (
                    SELECT COUNT(DISTINCT nq.newsletter_id)
                    FROM newsletter_queue nq
                    WHERE nq.user_id = u.id AND nq.status = 'sent'
                ) >= 5
                AND NOT EXISTS (
                    SELECT 1
                    FROM newsletter_queue nq2
                    JOIN newsletter_opens no ON nq2.email = no.email AND nq2.newsletter_id = no.newsletter_id
                    WHERE nq2.user_id = u.id
                    AND nq2.status = 'sent'
                    AND nq2.sent_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                )
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Re-engagement Candidates',
                'description' => 'Members who stopped opening newsletters but were previously engaged',
                'explanation' => "These {$count} members have received 5+ newsletters but haven't opened any in the last 90 days. Try a fresh approach!",
                'icon' => 'fa-bell-slash',
                'color' => '#6366f1',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'newsletters_received', 'operator' => 'at_least', 'value' => 5],
                        ['field' => 'email_engagement_level', 'operator' => 'equals', 'value' => 'never_opened']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Active Contributors
     * Members with high transaction counts and active listings
     */
    private static function getActiveContributorsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE (t.sender_id = u.id OR t.receiver_id = u.id)
                    AND t.status = 'completed'
                ) >= 5
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id AND l.status = 'active'
                ) >= 2
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Active Contributors',
                'description' => 'Members actively trading and offering services',
                'explanation' => "These {$count} members have completed 5+ transactions and have multiple active listings. They drive community activity!",
                'icon' => 'fa-hands-helping',
                'color' => '#06b6d4',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transaction_count', 'operator' => 'at_least', 'value' => 5],
                        ['field' => 'listing_count', 'operator' => 'at_least', 'value' => 2]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Comeback Members
     * Members who were inactive for 60+ days but recently logged back in
     */
    private static function getComebackMembersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Members who had a gap of 60+ days between logins but logged in within the last 14 days
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND DATEDIFF(u.last_login_at, COALESCE(
                    (SELECT MAX(al.created_at)
                     FROM activity_log al
                     WHERE al.user_id = u.id
                     AND al.action = 'login'
                     AND al.created_at < DATE_SUB(u.last_login_at, INTERVAL 7 DAY)
                    ), u.created_at
                )) >= 60
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Comeback Members',
                'description' => 'Members who returned after being inactive for 60+ days',
                'explanation' => "These {$count} members were gone for 60+ days but recently came back. Welcome them and help re-engage them!",
                'icon' => 'fa-undo',
                'color' => '#10b981',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'login_recency', 'operator' => 'newer_than_days', 'value' => 14],
                        ['field' => 'activity_score', 'operator' => 'equals', 'value' => 'returning']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Incomplete Profiles
     * Members missing bio, avatar, or location information
     */
    private static function getIncompleteProfilesSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    (u.bio IS NULL OR u.bio = '')
                    OR (u.avatar_url IS NULL OR u.avatar_url = '')
                    OR (u.location IS NULL OR u.location = '')
                )
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Incomplete Profiles',
                'description' => 'Members who haven\'t completed their profile information',
                'explanation' => "These {$count} members are missing bio, avatar, or location. Encourage them to complete their profile for better community connections!",
                'icon' => 'fa-user-edit',
                'color' => '#f59e0b',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'any',
                    'conditions' => [
                        ['field' => 'bio', 'operator' => 'is_empty', 'value' => ''],
                        ['field' => 'avatar', 'operator' => 'is_empty', 'value' => ''],
                        ['field' => 'location', 'operator' => 'is_empty', 'value' => '']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Lurkers
     * Members who log in frequently but never transact or create listings
     */
    private static function getLurkersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), u.last_login_at) <= 30
                AND (
                    SELECT COUNT(*)
                    FROM activity_log al
                    WHERE al.user_id = u.id
                    AND al.action = 'login'
                    AND al.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ) >= 5
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.sender_id = u.id OR t.receiver_id = u.id
                ) = 0
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id
                ) = 0
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Lurkers',
                'description' => 'Active observers who browse but don\'t participate',
                'explanation' => "These {$count} members log in regularly but haven't created listings or transactions. Help them take the first step!",
                'icon' => 'fa-eye',
                'color' => '#8b5cf6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'login_recency', 'operator' => 'newer_than_days', 'value' => 30],
                        ['field' => 'transaction_count', 'operator' => 'equals', 'value' => 0],
                        ['field' => 'listing_count', 'operator' => 'equals', 'value' => 0]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Anniversary Coming
     * Members approaching their 1-year membership anniversary
     */
    private static function getAnniversaryComingSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Members whose 1-year anniversary is within the next 30 days
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(DATE_ADD(u.created_at, INTERVAL 1 YEAR), NOW()) BETWEEN 0 AND 30
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Anniversary Coming',
                'description' => 'Members approaching their 1-year membership anniversary',
                'explanation' => "These {$count} members will celebrate 1 year with your community soon. Send a thank you or special offer!",
                'icon' => 'fa-birthday-cake',
                'color' => '#ec4899',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'older_than_days', 'value' => 335],
                        ['field' => 'created_at', 'operator' => 'newer_than_days', 'value' => 365]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Silent Givers
     * Members who transact frequently but don't have any listings (consumers only)
     */
    private static function getSilentGiversSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.sender_id = u.id OR t.receiver_id = u.id
                ) >= 3
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id
                ) = 0
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Silent Givers',
                'description' => 'Active transactors who haven\'t created any listings yet',
                'explanation' => "These {$count} members have completed 3+ transactions but never listed anything. Encourage them to share their skills!",
                'icon' => 'fa-hand-holding-heart',
                'color' => '#14b8a6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transaction_count', 'operator' => 'at_least', 'value' => 3],
                        ['field' => 'listing_count', 'operator' => 'equals', 'value' => 0]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Listing Hoarders
     * Members with many listings but few transactions (need help converting)
     */
    private static function getListingHoardersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id AND l.status = 'active'
                ) >= 3
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.sender_id = u.id OR t.receiver_id = u.id
                ) <= 1
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Listing Hoarders',
                'description' => 'Members with multiple listings but few transactions',
                'explanation' => "These {$count} members have 3+ listings but rarely transact. Help them with pricing or visibility tips!",
                'icon' => 'fa-boxes',
                'color' => '#f97316',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'listing_count', 'operator' => 'at_least', 'value' => 3],
                        ['field' => 'transaction_count', 'operator' => 'at_most', 'value' => 1]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * First Week
     * Members in their first 7 days - critical onboarding window
     */
    private static function getFirstWeekSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), u.created_at) <= 7
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'First Week',
                'description' => 'Brand new members in their first 7 days',
                'explanation' => "These {$count} members just joined! The first week is critical for engagement - send a warm welcome!",
                'icon' => 'fa-star',
                'color' => '#eab308',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'newer_than_days', 'value' => 7]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * One-Hit Wonders
     * Members with exactly 1 transaction - need second push
     */
    private static function getOneHitWondersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.sender_id = u.id OR t.receiver_id = u.id
                ) = 1
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'One-Hit Wonders',
                'description' => 'Members who completed exactly one transaction',
                'explanation' => "These {$count} members tried the platform once. Encourage them to complete their second transaction!",
                'icon' => 'fa-dice-one',
                'color' => '#0ea5e9',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transaction_count', 'operator' => 'equals', 'value' => 1]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ghost Accounts
     * Members who never logged in after signup
     */
    private static function getGhostAccountsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (u.last_login_at IS NULL OR u.last_login_at = u.created_at)
                AND DATEDIFF(NOW(), u.created_at) > 7
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Ghost Accounts',
                'description' => 'Members who never returned after signup',
                'explanation' => "These {$count} members signed up but never came back. Try an aggressive re-engagement or clean your list.",
                'icon' => 'fa-ghost',
                'color' => '#64748b',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'login_recency', 'operator' => 'older_than_days', 'value' => 7],
                        ['field' => 'transaction_count', 'operator' => 'equals', 'value' => 0],
                        ['field' => 'listing_count', 'operator' => 'equals', 'value' => 0]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Premium Profiles
     * Members with complete profile, photo, bio, and active listings (ambassadors)
     */
    private static function getPremiumProfilesSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND u.bio IS NOT NULL AND u.bio != ''
                AND u.avatar_url IS NOT NULL AND u.avatar_url != ''
                AND u.location IS NOT NULL AND u.location != ''
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id AND l.status = 'active'
                ) >= 1
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Premium Profiles',
                'description' => 'Members with complete profiles and active listings',
                'explanation' => "These {$count} members have bio, photo, location, and listings. They are community ambassadors ready for spotlight features!",
                'icon' => 'fa-id-badge',
                'color' => '#8b5cf6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'bio', 'operator' => 'is_not_empty', 'value' => ''],
                        ['field' => 'avatar', 'operator' => 'is_not_empty', 'value' => ''],
                        ['field' => 'location', 'operator' => 'is_not_empty', 'value' => ''],
                        ['field' => 'listing_count', 'operator' => 'at_least', 'value' => 1]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Connectors
     * Members who transact with many different people (community hubs)
     */
    private static function getConnectorsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Members who have transacted with at least 5 unique people
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(DISTINCT CASE WHEN t.sender_id = u.id THEN t.receiver_id ELSE t.sender_id END)
                    FROM transactions t
                    WHERE (t.sender_id = u.id OR t.receiver_id = u.id)
                    AND t.status = 'completed'
                ) >= 5
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Connectors',
                'description' => 'Members who transact with many different people',
                'explanation' => "These {$count} members have traded with 5+ different people. They are community hubs and super-networkers!",
                'icon' => 'fa-project-diagram',
                'color' => '#06b6d4',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transaction_count', 'operator' => 'at_least', 'value' => 5],
                        ['field' => 'activity_score', 'operator' => 'equals', 'value' => 'high']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Declining Activity
     * Members who were active but are now slowing down (early intervention)
     */
    private static function getDecliningActivitySuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Members who had activity 60-90 days ago but not in last 30 days
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE (t.sender_id = u.id OR t.receiver_id = u.id)
                    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) >= 2
                AND (
                    SELECT COUNT(*)
                    FROM transactions t2
                    WHERE (t2.sender_id = u.id OR t2.receiver_id = u.id)
                    AND t2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) = 0
                AND u.last_login_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Declining Activity',
                'description' => 'Previously active members who are slowing down',
                'explanation' => "These {$count} members were active 2-3 months ago but quiet recently. Early intervention can prevent churn!",
                'icon' => 'fa-chart-line',
                'color' => '#f97316',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transaction_count', 'operator' => 'at_least', 'value' => 2],
                        ['field' => 'login_recency', 'operator' => 'older_than_days', 'value' => 14],
                        ['field' => 'activity_score', 'operator' => 'equals', 'value' => 'medium']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Social Butterflies
     * Members with many accepted connections (networkers)
     */
    private static function getSocialButterfliesSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM connections c
                    WHERE (c.requester_id = u.id OR c.receiver_id = u.id)
                    AND c.status = 'accepted'
                ) >= 10
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Social Butterflies',
                'description' => 'Members with 10+ connections in the community',
                'explanation' => "These {$count} members are super-networkers with many connections. Great for spreading the word!",
                'icon' => 'fa-people-arrows',
                'color' => '#ec4899',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'connection_count', 'operator' => 'at_least', 'value' => 10]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Feedback Champions
     * Members who leave many reviews (engaged raters)
     */
    private static function getFeedbackChampionsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM reviews r
                    WHERE r.reviewer_id = u.id
                ) >= 5
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Feedback Champions',
                'description' => 'Members who actively leave reviews for others',
                'explanation' => "These {$count} members have left 5+ reviews. They care about quality and community trust!",
                'icon' => 'fa-comment-dots',
                'color' => '#14b8a6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'reviews_given', 'operator' => 'at_least', 'value' => 5]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verified Members
     * Members with verified email addresses
     */
    private static function getVerifiedMembersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND u.is_verified = 1
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Verified Members',
                'description' => 'Members with verified email addresses',
                'explanation' => "These {$count} members have verified their email. Higher trust and deliverability!",
                'icon' => 'fa-shield-check',
                'color' => '#10b981',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'email_verified', 'operator' => 'equals', 'value' => 'yes']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Well-Reviewed
     * Members with high average ratings (trusted)
     */
    private static function getWellReviewedSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM reviews r
                    WHERE r.receiver_id = u.id
                ) >= 3
                AND (
                    SELECT AVG(r2.rating)
                    FROM reviews r2
                    WHERE r2.receiver_id = u.id
                ) >= 4.5
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Well-Reviewed',
                'description' => 'Members with high average ratings (4.5+ stars)',
                'explanation' => "These {$count} members have excellent reputations with 3+ reviews averaging 4.5+ stars!",
                'icon' => 'fa-star',
                'color' => '#f59e0b',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'average_rating', 'operator' => 'at_least', 'value' => 4.5],
                        ['field' => 'reviews_received', 'operator' => 'at_least', 'value' => 3]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Unreviewed Veterans
     * Long-time active members who haven't received reviews yet
     */
    private static function getUnreviewedVeteransSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), u.created_at) >= 90
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.sender_id = u.id OR t.receiver_id = u.id
                ) >= 5
                AND (
                    SELECT COUNT(*)
                    FROM reviews r
                    WHERE r.receiver_id = u.id
                ) = 0
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Unreviewed Veterans',
                'description' => 'Active long-term members with no reviews yet',
                'explanation' => "These {$count} members have 90+ days and 5+ transactions but no reviews. Encourage their partners to leave feedback!",
                'icon' => 'fa-user-clock',
                'color' => '#6366f1',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'older_than_days', 'value' => 90],
                        ['field' => 'transaction_count', 'operator' => 'at_least', 'value' => 5],
                        ['field' => 'reviews_received', 'operator' => 'equals', 'value' => 0]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Long-Time No-Listers
     * Members who've been around 180+ days, are active, but never created a listing
     */
    private static function getLongTimeNoListersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND DATEDIFF(NOW(), u.created_at) >= 180
                AND DATEDIFF(NOW(), COALESCE(u.last_login_at, u.created_at)) <= 30
                AND (
                    SELECT COUNT(*)
                    FROM listings l
                    WHERE l.user_id = u.id
                ) = 0
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Long-Time No-Listers',
                'description' => 'Active veterans who never created a listing',
                'explanation' => "These {$count} members have been around 6+ months and are active, but never listed anything. Encourage them to share!",
                'icon' => 'fa-clock',
                'color' => '#8b5cf6',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'created_at', 'operator' => 'older_than_days', 'value' => 180],
                        ['field' => 'login_recency', 'operator' => 'newer_than_days', 'value' => 30],
                        ['field' => 'listing_count', 'operator' => 'equals', 'value' => 0]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Multi-Group Members
     * Members who belong to 3+ groups (cross-pollinators)
     */
    private static function getMultiGroupMembersSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(DISTINCT gm.group_id)
                    FROM group_members gm
                    WHERE gm.user_id = u.id
                    AND gm.status = 'approved'
                ) >= 3
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Multi-Group Members',
                'description' => 'Members active in 3+ community groups',
                'explanation' => "These {$count} members are in 3+ groups. They're cross-pollinators who can spread ideas across communities!",
                'icon' => 'fa-layer-group',
                'color' => '#0ea5e9',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'group_count', 'operator' => 'at_least', 'value' => 3]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Email Bounces
     * Members whose emails have bounced (list cleanup)
     */
    private static function getEmailBouncesSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(DISTINCT u.id) as cnt
                FROM users u
                JOIN newsletter_queue nq ON nq.user_id = u.id
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND nq.status = 'bounced'
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Email Bounces',
                'description' => 'Members with bounced email addresses',
                'explanation' => "These {$count} members have bounced emails. Consider reaching out to update their address or clean your list.",
                'icon' => 'fa-envelope-circle-check',
                'color' => '#ef4444',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'email_status', 'operator' => 'equals', 'value' => 'bounced']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * High-Value Receivers
     * Members who received a lot in transactions (top recipients)
     */
    private static function getHighValueReceiversSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            // Members who received in 10+ transactions
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (
                    SELECT COUNT(*)
                    FROM transactions t
                    WHERE t.receiver_id = u.id
                    AND t.status = 'completed'
                ) >= 10
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'High-Value Receivers',
                'description' => 'Members who received in 10+ transactions',
                'explanation' => "These {$count} members are top receivers with 10+ completed transactions. They're providing value to the community!",
                'icon' => 'fa-hand-holding-dollar',
                'color' => '#10b981',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'transactions_received', 'operator' => 'at_least', 'value' => 10]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Admins
     * Users with admin role for internal communications
     */
    private static function getAdminsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND u.role = 'admin'
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Admins',
                'description' => 'All admin users',
                'explanation' => "Target your {$count} admin users for internal communications and announcements.",
                'icon' => 'fa-user-shield',
                'color' => '#6366f1',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'role', 'operator' => 'equals', 'value' => 'admin']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Organisations
     * Organisation profile types only
     */
    private static function getOrganisationsSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND u.profile_type = 'organisation'
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'Organisations',
                'description' => 'Organisation profiles only',
                'explanation' => "Target your {$count} organisation accounts for business-focused communications.",
                'icon' => 'fa-building',
                'color' => '#0ea5e9',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'profile_type', 'operator' => 'equals', 'value' => 'organisation']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * No Photo
     * Members without an avatar/profile photo
     */
    private static function getNoPhotoSuggestion(): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $count = Database::query("
                SELECT COUNT(*) as cnt
                FROM users u
                WHERE u.tenant_id = ?
                AND u.is_approved = 1
                AND (u.avatar_url IS NULL OR u.avatar_url = '')
            ", [$tenantId])->fetch()['cnt'];

            if ($count === 0) {
                return null;
            }

            return [
                'name' => 'No Photo',
                'description' => 'Members without a profile photo',
                'explanation' => "{$count} members haven't uploaded a profile photo yet. Encourage them to personalize their profile!",
                'icon' => 'fa-user-slash',
                'color' => '#94a3b8',
                'member_count' => (int) $count,
                'rules' => [
                    'match' => 'all',
                    'conditions' => [
                        ['field' => 'avatar_url', 'operator' => 'is_empty', 'value' => '']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
