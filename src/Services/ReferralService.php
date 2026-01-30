<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class ReferralService
{
    /**
     * Referral badge definitions
     */
    public const REFERRAL_BADGES = [
        'first_referral' => [
            'name' => 'Connector',
            'description' => 'Refer your first member',
            'icon' => '🔗',
            'threshold' => 1,
            'xp_reward' => 50,
        ],
        'referral_3' => [
            'name' => 'Community Builder',
            'description' => 'Refer 3 members who become active',
            'icon' => '🏗️',
            'threshold' => 3,
            'xp_reward' => 100,
        ],
        'referral_5' => [
            'name' => 'Ambassador',
            'description' => 'Refer 5 members who become active',
            'icon' => '🎖️',
            'threshold' => 5,
            'xp_reward' => 150,
        ],
        'referral_10' => [
            'name' => 'Champion Recruiter',
            'description' => 'Refer 10 members who become active',
            'icon' => '🏆',
            'threshold' => 10,
            'xp_reward' => 300,
        ],
        'referral_25' => [
            'name' => 'Network Legend',
            'description' => 'Refer 25 members who become active',
            'icon' => '👑',
            'threshold' => 25,
            'xp_reward' => 500,
        ],
    ];

    /**
     * XP rewards for referral milestones
     */
    public const REFERRAL_XP = [
        'signup' => 25,          // When referred user signs up
        'active' => 50,          // When referred user becomes active (makes first transaction/post)
        'engaged' => 100,        // When referred user earns their first badge
    ];

    /**
     * Generate a referral code for a user
     */
    public static function generateReferralCode($userId)
    {
        // Check if user already has a code
        $existing = Database::query(
            "SELECT referral_code FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        if (!empty($existing['referral_code'])) {
            return $existing['referral_code'];
        }

        // Generate unique code
        $code = self::createUniqueCode($userId);

        Database::query(
            "UPDATE users SET referral_code = ? WHERE id = ?",
            [$code, $userId]
        );

        return $code;
    }

    /**
     * Create a unique referral code
     */
    private static function createUniqueCode($userId)
    {
        $tenantId = TenantContext::getId();
        $prefix = strtoupper(substr(md5($tenantId), 0, 3));
        $userPart = strtoupper(base_convert($userId, 10, 36));
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        return $prefix . $userPart . $random;
    }

    /**
     * Get referral code for user
     */
    public static function getReferralCode($userId)
    {
        $result = Database::query(
            "SELECT referral_code FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        if (empty($result['referral_code'])) {
            return self::generateReferralCode($userId);
        }

        return $result['referral_code'];
    }

    /**
     * Get referral link for user
     */
    public static function getReferralLink($userId)
    {
        $code = self::getReferralCode($userId);
        $basePath = TenantContext::getBasePath();
        $baseUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $baseUrl . $basePath . '/register?ref=' . $code;
    }

    /**
     * Track a referral (called when new user signs up with referral code)
     */
    public static function trackReferral($referralCode, $newUserId)
    {
        if (empty($referralCode)) {
            return false;
        }

        $tenantId = TenantContext::getId();

        // Find referrer by code
        $referrer = Database::query(
            "SELECT id FROM users WHERE referral_code = ? AND tenant_id = ?",
            [$referralCode, $tenantId]
        )->fetch();

        if (!$referrer) {
            return false;
        }

        $referrerId = $referrer['id'];

        // Don't allow self-referral
        if ($referrerId == $newUserId) {
            return false;
        }

        // Use transaction for the critical database operations
        Database::beginTransaction();

        try {
            // Record the referral
            Database::query(
                "INSERT INTO referral_tracking (tenant_id, referrer_id, referred_id, status, created_at)
                 VALUES (?, ?, ?, 'pending', NOW())",
                [$tenantId, $referrerId, $newUserId]
            );

            // Update referred user's record
            Database::query(
                "UPDATE users SET referred_by = ? WHERE id = ?",
                [$referrerId, $newUserId]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("ReferralService::trackReferral error: " . $e->getMessage());
            return false;
        }

        // Non-critical operations outside transaction
        // Award signup XP to referrer
        GamificationService::awardXP(
            $referrerId,
            self::REFERRAL_XP['signup'],
            'referral_signup',
            "New user signed up with your referral link"
        );

        // Notify the referrer
        \Nexus\Models\Notification::create(
            $referrerId,
            "🎉 Someone just signed up using your referral link! You earned " . self::REFERRAL_XP['signup'] . " XP."
        );

        // Check for referral badges
        self::checkReferralBadges($referrerId);

        return true;
    }

    /**
     * Mark referral as active (when referred user does something meaningful)
     */
    public static function markReferralActive($userId)
    {
        $tenantId = TenantContext::getId();

        // Find the referral record
        $referral = Database::query(
            "SELECT * FROM referral_tracking WHERE referred_id = ? AND tenant_id = ? AND status = 'pending'",
            [$userId, $tenantId]
        )->fetch();

        if (!$referral) {
            return false;
        }

        // Update status (single operation, transaction not strictly needed but keeping for consistency)
        Database::query(
            "UPDATE referral_tracking SET status = 'active', activated_at = NOW() WHERE id = ?",
            [$referral['id']]
        );

        // Non-critical operations - Award XP to referrer
        GamificationService::awardXP(
            $referral['referrer_id'],
            self::REFERRAL_XP['active'],
            'referral_active',
            "Your referral became an active member"
        );

        // Notify the referrer
        \Nexus\Models\Notification::create(
            $referral['referrer_id'],
            "🌟 Your referral just became active! You earned " . self::REFERRAL_XP['active'] . " bonus XP."
        );

        // Check for referral badges
        self::checkReferralBadges($referral['referrer_id']);

        return true;
    }

    /**
     * Mark referral as engaged (when referred user earns their first badge)
     */
    public static function markReferralEngaged($userId)
    {
        $tenantId = TenantContext::getId();

        // Find the referral record
        $referral = Database::query(
            "SELECT * FROM referral_tracking WHERE referred_id = ? AND tenant_id = ? AND status = 'active'",
            [$userId, $tenantId]
        )->fetch();

        if (!$referral) {
            return false;
        }

        // Update status
        Database::query(
            "UPDATE referral_tracking SET status = 'engaged', engaged_at = NOW() WHERE id = ?",
            [$referral['id']]
        );

        // Award XP to referrer
        GamificationService::awardXP(
            $referral['referrer_id'],
            self::REFERRAL_XP['engaged'],
            'referral_engaged',
            "Your referral earned their first badge"
        );

        // Notify the referrer
        \Nexus\Models\Notification::create(
            $referral['referrer_id'],
            "🏅 Your referral just earned their first badge! You earned " . self::REFERRAL_XP['engaged'] . " bonus XP."
        );

        return true;
    }

    /**
     * Check and award referral badges
     */
    public static function checkReferralBadges($userId)
    {
        $stats = self::getReferralStats($userId);
        $activeCount = $stats['active_count'];

        foreach (self::REFERRAL_BADGES as $key => $badge) {
            if ($activeCount >= $badge['threshold']) {
                // Check if already earned
                $exists = Database::query(
                    "SELECT id FROM user_badges WHERE user_id = ? AND badge_key = ?",
                    [$userId, 'referral_' . $key]
                )->fetch();

                if (!$exists) {
                    GamificationService::awardBadge($userId, 'referral_' . $key);
                    GamificationService::awardXP(
                        $userId,
                        $badge['xp_reward'],
                        'referral_badge',
                        "Referral badge: {$badge['name']}"
                    );
                }
            }
        }
    }

    /**
     * Get referral stats for a user
     */
    public static function getReferralStats($userId)
    {
        $tenantId = TenantContext::getId();

        $stats = Database::query(
            "SELECT
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status IN ('active', 'engaged') THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'engaged' THEN 1 ELSE 0 END) as engaged_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
             FROM referral_tracking
             WHERE referrer_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        // Calculate total XP earned from referrals
        $xpEarned = 0;
        $xpEarned += ($stats['total_referrals'] ?? 0) * self::REFERRAL_XP['signup'];
        $xpEarned += ($stats['active_count'] ?? 0) * self::REFERRAL_XP['active'];
        $xpEarned += ($stats['engaged_count'] ?? 0) * self::REFERRAL_XP['engaged'];

        return [
            'total_referrals' => (int)($stats['total_referrals'] ?? 0),
            'active_count' => (int)($stats['active_count'] ?? 0),
            'engaged_count' => (int)($stats['engaged_count'] ?? 0),
            'pending_count' => (int)($stats['pending_count'] ?? 0),
            'total_xp_earned' => $xpEarned,
            'referral_code' => self::getReferralCode($userId),
            'referral_link' => self::getReferralLink($userId),
        ];
    }

    /**
     * Get list of referrals for a user
     */
    public static function getReferrals($userId, $limit = 50)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT rt.*, u.first_name, u.last_name, u.photo, u.xp, u.level
             FROM referral_tracking rt
             JOIN users u ON rt.referred_id = u.id
             WHERE rt.referrer_id = ? AND rt.tenant_id = ?
             ORDER BY rt.created_at DESC
             LIMIT ?",
            [$userId, $tenantId, $limit]
        )->fetchAll();
    }

    /**
     * Get referral leaderboard
     */
    public static function getReferralLeaderboard($limit = 20)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.photo, u.level,
                    COUNT(rt.id) as referral_count,
                    SUM(CASE WHEN rt.status IN ('active', 'engaged') THEN 1 ELSE 0 END) as active_referrals
             FROM users u
             JOIN referral_tracking rt ON u.id = rt.referrer_id
             WHERE rt.tenant_id = ?
             GROUP BY u.id
             HAVING referral_count > 0
             ORDER BY active_referrals DESC, referral_count DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll();
    }

    /**
     * Get next referral badge progress
     */
    public static function getNextBadgeProgress($userId)
    {
        $stats = self::getReferralStats($userId);
        $activeCount = $stats['active_count'];

        foreach (self::REFERRAL_BADGES as $key => $badge) {
            if ($activeCount < $badge['threshold']) {
                return [
                    'badge' => $badge,
                    'key' => $key,
                    'current' => $activeCount,
                    'target' => $badge['threshold'],
                    'remaining' => $badge['threshold'] - $activeCount,
                    'percent' => round(($activeCount / $badge['threshold']) * 100),
                ];
            }
        }

        return null; // All badges earned
    }
}
