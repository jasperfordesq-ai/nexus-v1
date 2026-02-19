<?php

namespace Nexus\Services;

use Nexus\Models\UserBadge;
use Nexus\Models\VolLog;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GamificationService
{
    /**
     * Cache for badge definitions to avoid repeated array creation
     */
    private static $badgeDefinitionsCache = null;

    /**
     * XP values for various actions
     */
    public const XP_VALUES = [
        'send_credits' => 10,      // per credit sent
        'receive_credits' => 5,    // per credit received
        'volunteer_hour' => 20,    // per verified hour
        'create_listing' => 15,
        'complete_transaction' => 25,
        'leave_review' => 10,
        'attend_event' => 15,
        'create_event' => 30,
        'join_group' => 10,
        'create_group' => 50,
        'create_post' => 5,
        'daily_login' => 5,
        'complete_profile' => 50,  // one-time
        'earn_badge' => 25,        // per badge
        'vote_poll' => 2,
        'send_message' => 2,
        'make_connection' => 10,
        'complete_goal' => 10,
    ];

    /**
     * Level thresholds (XP required for each level)
     */
    public const LEVEL_THRESHOLDS = [
        1 => 0,
        2 => 100,
        3 => 300,
        4 => 600,
        5 => 1000,
        6 => 1500,
        7 => 2200,
        8 => 3000,
        9 => 4000,
        10 => 5500,
    ];

    /**
     * Get all System Badge Definitions.
     * Central source of truth for Keys, Names, and Icons.
     * @return array
     */
    public static function getBadgeDefinitions()
    {
        if (self::$badgeDefinitionsCache !== null) {
            return self::$badgeDefinitionsCache;
        }

        self::$badgeDefinitionsCache = [
            // =====================================================
            // VOLUNTEERING BADGES (Hours)
            // =====================================================
            ['key' => 'vol_1h',    'name' => 'First Steps',       'icon' => '👣', 'type' => 'vol', 'threshold' => 1, 'msg' => 'logging your first volunteer hour'],
            ['key' => 'vol_10h',   'name' => 'Helping Hand',      'icon' => '🤲', 'type' => 'vol', 'threshold' => 10, 'msg' => 'volunteering 10 hours'],
            ['key' => 'vol_50h',   'name' => 'Change Maker',      'icon' => '🌍', 'type' => 'vol', 'threshold' => 50, 'msg' => 'volunteering 50 hours'],
            ['key' => 'vol_100h',  'name' => 'TimeBank Legend',   'icon' => '👑', 'type' => 'vol', 'threshold' => 100, 'msg' => 'volunteering 100 hours'],
            ['key' => 'vol_250h',  'name' => 'Volunteer Hero',    'icon' => '🦸', 'type' => 'vol', 'threshold' => 250, 'msg' => 'volunteering 250 hours'],
            ['key' => 'vol_500h',  'name' => 'Volunteer Champion','icon' => '🏅', 'type' => 'vol', 'threshold' => 500, 'msg' => 'volunteering 500 hours'],

            // =====================================================
            // LISTING BADGES (Offers)
            // =====================================================
            ['key' => 'offer_1',   'name' => 'First Offer',       'icon' => '🎁', 'type' => 'offer', 'threshold' => 1, 'msg' => 'posting your first offer'],
            ['key' => 'offer_5',   'name' => 'Generous Soul',     'icon' => '🤝', 'type' => 'offer', 'threshold' => 5, 'msg' => 'posting 5 offers'],
            ['key' => 'offer_10',  'name' => 'Gift Giver',        'icon' => '🎀', 'type' => 'offer', 'threshold' => 10, 'msg' => 'posting 10 offers'],
            ['key' => 'offer_25',  'name' => 'Offer Master',      'icon' => '🌟', 'type' => 'offer', 'threshold' => 25, 'msg' => 'posting 25 offers'],

            // =====================================================
            // LISTING BADGES (Requests)
            // =====================================================
            ['key' => 'request_1', 'name' => 'First Request',     'icon' => '🙋', 'type' => 'request', 'threshold' => 1, 'msg' => 'posting your first request'],
            ['key' => 'request_5', 'name' => 'Community Seeker',  'icon' => '🗣️', 'type' => 'request', 'threshold' => 5, 'msg' => 'making 5 requests'],
            ['key' => 'request_10','name' => 'Active Requester',  'icon' => '📢', 'type' => 'request', 'threshold' => 10, 'msg' => 'making 10 requests'],

            // =====================================================
            // TIMEBANKING - EARNING BADGES
            // =====================================================
            ['key' => 'earn_1',    'name' => 'First Earn',        'icon' => '🪙', 'type' => 'earn', 'threshold' => 1, 'msg' => 'earning your first time credit'],
            ['key' => 'earn_10',   'name' => 'Go Getter',         'icon' => '🚀', 'type' => 'earn', 'threshold' => 10, 'msg' => 'earning 10 time credits'],
            ['key' => 'earn_50',   'name' => 'Credit Builder',    'icon' => '⚡', 'type' => 'earn', 'threshold' => 50, 'msg' => 'earning 50 time credits'],
            ['key' => 'earn_100',  'name' => 'Centurion',         'icon' => '💯', 'type' => 'earn', 'threshold' => 100, 'msg' => 'earning 100 time credits'],
            ['key' => 'earn_250',  'name' => 'Credit Master',     'icon' => '💎', 'type' => 'earn', 'threshold' => 250, 'msg' => 'earning 250 time credits'],

            // =====================================================
            // TIMEBANKING - SPENDING BADGES
            // =====================================================
            ['key' => 'spend_1',   'name' => 'First Spend',       'icon' => '💸', 'type' => 'spend', 'threshold' => 1, 'msg' => 'spending your first time credit'],
            ['key' => 'spend_10',  'name' => 'Active Spender',    'icon' => '💳', 'type' => 'spend', 'threshold' => 10, 'msg' => 'spending 10 time credits'],
            ['key' => 'spend_50',  'name' => 'Generous Spender',  'icon' => '🎊', 'type' => 'spend', 'threshold' => 50, 'msg' => 'spending 50 time credits'],

            // =====================================================
            // TIMEBANKING - TRANSACTION BADGES
            // =====================================================
            ['key' => 'transaction_1',  'name' => 'First Exchange',   'icon' => '🔄', 'type' => 'transaction', 'threshold' => 1, 'msg' => 'completing your first transaction'],
            ['key' => 'transaction_10', 'name' => 'Active Trader',    'icon' => '📊', 'type' => 'transaction', 'threshold' => 10, 'msg' => 'completing 10 transactions'],
            ['key' => 'transaction_50', 'name' => 'Exchange Master',  'icon' => '💱', 'type' => 'transaction', 'threshold' => 50, 'msg' => 'completing 50 transactions'],

            // =====================================================
            // TIMEBANKING - DIVERSITY BADGES (unique people helped)
            // =====================================================
            ['key' => 'diversity_3',  'name' => 'Community Helper',   'icon' => '🌈', 'type' => 'diversity', 'threshold' => 3, 'msg' => 'helping 3 different people'],
            ['key' => 'diversity_10', 'name' => 'Diverse Giver',      'icon' => '🌐', 'type' => 'diversity', 'threshold' => 10, 'msg' => 'helping 10 different people'],
            ['key' => 'diversity_25', 'name' => 'Community Pillar',   'icon' => '🏛️', 'type' => 'diversity', 'threshold' => 25, 'msg' => 'helping 25 different people'],

            // =====================================================
            // SOCIAL - CONNECTION BADGES
            // =====================================================
            ['key' => 'connect_1',  'name' => 'First Friend',       'icon' => '👋', 'type' => 'connection', 'threshold' => 1, 'msg' => 'making your first connection'],
            ['key' => 'connect_10', 'name' => 'Social Butterfly',   'icon' => '🦋', 'type' => 'connection', 'threshold' => 10, 'msg' => 'making 10 connections'],
            ['key' => 'connect_25', 'name' => 'Network Builder',    'icon' => '🕸️', 'type' => 'connection', 'threshold' => 25, 'msg' => 'making 25 connections'],
            ['key' => 'connect_50', 'name' => 'Community Connector','icon' => '🔗', 'type' => 'connection', 'threshold' => 50, 'msg' => 'making 50 connections'],

            // =====================================================
            // SOCIAL - MESSAGE BADGES
            // =====================================================
            ['key' => 'msg_1',    'name' => 'Conversation Starter', 'icon' => '💬', 'type' => 'message', 'threshold' => 1, 'msg' => 'sending your first message'],
            ['key' => 'msg_50',   'name' => 'Active Communicator',  'icon' => '📱', 'type' => 'message', 'threshold' => 50, 'msg' => 'sending 50 messages'],
            ['key' => 'msg_200',  'name' => 'Communication Pro',    'icon' => '📨', 'type' => 'message', 'threshold' => 200, 'msg' => 'sending 200 messages'],

            // =====================================================
            // REVIEW BADGES
            // =====================================================
            ['key' => 'review_1',  'name' => 'First Feedback',     'icon' => '⭐', 'type' => 'review_given', 'threshold' => 1, 'msg' => 'leaving your first review'],
            ['key' => 'review_10', 'name' => 'Trusted Reviewer',   'icon' => '📝', 'type' => 'review_given', 'threshold' => 10, 'msg' => 'leaving 10 reviews'],
            ['key' => 'review_25', 'name' => 'Review Expert',      'icon' => '🎯', 'type' => 'review_given', 'threshold' => 25, 'msg' => 'leaving 25 reviews'],

            // =====================================================
            // REVIEW RECEIVED BADGES
            // =====================================================
            ['key' => '5star_1',   'name' => 'First 5-Star',       'icon' => '🌟', 'type' => '5star', 'threshold' => 1, 'msg' => 'receiving your first 5-star review'],
            ['key' => '5star_10',  'name' => 'Highly Rated',       'icon' => '🏆', 'type' => '5star', 'threshold' => 10, 'msg' => 'receiving 10 five-star reviews'],
            ['key' => '5star_25',  'name' => 'Excellence Award',   'icon' => '👏', 'type' => '5star', 'threshold' => 25, 'msg' => 'receiving 25 five-star reviews'],

            // =====================================================
            // EVENT BADGES
            // =====================================================
            ['key' => 'event_attend_1',  'name' => 'First Event',      'icon' => '🎟️', 'type' => 'event_attend', 'threshold' => 1, 'msg' => 'attending your first event'],
            ['key' => 'event_attend_10', 'name' => 'Event Regular',    'icon' => '📅', 'type' => 'event_attend', 'threshold' => 10, 'msg' => 'attending 10 events'],
            ['key' => 'event_attend_25', 'name' => 'Event Enthusiast', 'icon' => '🎉', 'type' => 'event_attend', 'threshold' => 25, 'msg' => 'attending 25 events'],
            ['key' => 'event_host_1',    'name' => 'Event Host',       'icon' => '🎤', 'type' => 'event_host', 'threshold' => 1, 'msg' => 'hosting your first event'],
            ['key' => 'event_host_5',    'name' => 'Event Organizer',  'icon' => '🎪', 'type' => 'event_host', 'threshold' => 5, 'msg' => 'hosting 5 events'],

            // =====================================================
            // GROUP BADGES
            // =====================================================
            ['key' => 'group_join_1',  'name' => 'Team Player',        'icon' => '👥', 'type' => 'group_join', 'threshold' => 1, 'msg' => 'joining your first group'],
            ['key' => 'group_join_5',  'name' => 'Community Member',   'icon' => '🏘️', 'type' => 'group_join', 'threshold' => 5, 'msg' => 'joining 5 groups'],
            ['key' => 'group_create',  'name' => 'Group Founder',      'icon' => '🚀', 'type' => 'group_create', 'threshold' => 1, 'msg' => 'creating your first group'],

            // =====================================================
            // CONTENT CREATION BADGES
            // =====================================================
            ['key' => 'post_1',   'name' => 'First Post',          'icon' => '✏️', 'type' => 'post', 'threshold' => 1, 'msg' => 'creating your first post'],
            ['key' => 'post_25',  'name' => 'Content Creator',     'icon' => '📰', 'type' => 'post', 'threshold' => 25, 'msg' => 'creating 25 posts'],
            ['key' => 'post_100', 'name' => 'Prolific Poster',     'icon' => '📚', 'type' => 'post', 'threshold' => 100, 'msg' => 'creating 100 posts'],

            // =====================================================
            // ENGAGEMENT BADGES (likes received)
            // =====================================================
            ['key' => 'likes_50',  'name' => 'Getting Noticed',    'icon' => '❤️', 'type' => 'likes_received', 'threshold' => 50, 'msg' => 'receiving 50 likes'],
            ['key' => 'likes_200', 'name' => 'Popular Voice',      'icon' => '💕', 'type' => 'likes_received', 'threshold' => 200, 'msg' => 'receiving 200 likes'],

            // =====================================================
            // PROFILE & LOYALTY BADGES
            // =====================================================
            ['key' => 'profile_complete', 'name' => 'Profile Pro',    'icon' => '👤', 'type' => 'profile', 'threshold' => 100, 'msg' => 'completing your profile'],
            ['key' => 'member_30d',  'name' => 'Monthly Member',      'icon' => '📆', 'type' => 'membership', 'threshold' => 30, 'msg' => 'being a member for 30 days'],
            ['key' => 'member_180d', 'name' => 'Semester Member',     'icon' => '📅', 'type' => 'membership', 'threshold' => 180, 'msg' => 'being a member for 6 months'],
            ['key' => 'member_365d', 'name' => 'Annual Member',       'icon' => '🎂', 'type' => 'membership', 'threshold' => 365, 'msg' => 'being a member for one year'],

            // =====================================================
            // STREAK BADGES
            // =====================================================
            ['key' => 'streak_7d',   'name' => 'Week Warrior',       'icon' => '🔥', 'type' => 'streak', 'threshold' => 7, 'msg' => 'maintaining a 7-day streak'],
            ['key' => 'streak_30d',  'name' => 'Monthly Dedication', 'icon' => '🔥', 'type' => 'streak', 'threshold' => 30, 'msg' => 'maintaining a 30-day streak'],
            ['key' => 'streak_100d', 'name' => 'Streak Master',      'icon' => '🔥', 'type' => 'streak', 'threshold' => 100, 'msg' => 'maintaining a 100-day streak'],
            ['key' => 'streak_365d', 'name' => 'Year-Long Legend',   'icon' => '🔥', 'type' => 'streak', 'threshold' => 365, 'msg' => 'maintaining a 365-day streak'],

            // =====================================================
            // LEVEL BADGES
            // =====================================================
            ['key' => 'level_5',  'name' => 'Rising Star',         'icon' => '🌟', 'type' => 'level', 'threshold' => 5, 'msg' => 'reaching level 5'],
            ['key' => 'level_10', 'name' => 'Community Champion',  'icon' => '🏆', 'type' => 'level', 'threshold' => 10, 'msg' => 'reaching level 10'],

            // =====================================================
            // SPECIAL / MANUAL BADGES
            // =====================================================
            ['key' => 'early_adopter', 'name' => 'Early Adopter',   'icon' => '🌱', 'type' => 'special', 'threshold' => 0, 'msg' => 'being an early adopter'],
            ['key' => 'verified',      'name' => 'Verified Member', 'icon' => '✅', 'type' => 'special', 'threshold' => 0, 'msg' => 'being a verified member'],
            ['key' => 'volunteer_org', 'name' => 'Organization Partner', 'icon' => '🏢', 'type' => 'vol_org', 'threshold' => 1, 'msg' => 'creating a volunteer organization'],
        ];

        return self::$badgeDefinitionsCache;
    }

    /**
     * Get badge definition by key
     */
    public static function getBadgeByKey($key)
    {
        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['key'] === $key) {
                return $def;
            }
        }
        return null;
    }

    /**
     * Get badges by type
     */
    public static function getBadgesByType($type)
    {
        return array_filter(self::getBadgeDefinitions(), function($def) use ($type) {
            return $def['type'] === $type;
        });
    }

    // =========================================================================
    // BADGE CHECKING METHODS
    // =========================================================================

    /**
     * Check and award badges based on volunteering hours.
     */
    public static function checkVolunteeringBadges($userId)
    {
        $totalHours = VolLog::getTotalVerifiedHours($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'vol' && $totalHours >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }

        // Award XP for volunteer hours
        self::awardXP($userId, self::XP_VALUES['volunteer_hour'] * $totalHours, 'volunteer_hour', "Volunteer hours: $totalHours");
    }

    /**
     * Check and award badges based on Earned Time Credits.
     */
    public static function checkTimebankingBadges($userId)
    {
        $creditsEarned = \Nexus\Models\Transaction::getTotalEarned($userId);
        $creditsSpent = self::getTotalSpent($userId);
        $totalTransactions = self::getTransactionCount($userId);
        $uniqueReceivers = self::getUniqueReceiversCount($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            $qualifies = false;

            switch ($def['type']) {
                case 'earn':
                    $qualifies = $creditsEarned >= $def['threshold'];
                    break;
                case 'spend':
                    $qualifies = $creditsSpent >= $def['threshold'];
                    break;
                case 'transaction':
                    $qualifies = $totalTransactions >= $def['threshold'];
                    break;
                case 'diversity':
                    $qualifies = $uniqueReceivers >= $def['threshold'];
                    break;
            }

            if ($qualifies) {
                self::awardBadge($userId, $def);
            }
        }
    }

    /**
     * Check and award badges based on Listings (Offers/Requests).
     */
    public static function checkListingBadges($userId)
    {
        $offerCount = \Nexus\Models\Listing::countByUser($userId, 'offer');
        $requestCount = \Nexus\Models\Listing::countByUser($userId, 'request');

        foreach (self::getBadgeDefinitions() as $def) {
            $count = 0;
            if ($def['type'] === 'offer') $count = $offerCount;
            if ($def['type'] === 'request') $count = $requestCount;

            if ($count > 0 && $count >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }

        // Award XP for creating listing
        self::awardXP($userId, self::XP_VALUES['create_listing'], 'create_listing', 'Created a listing');
    }

    /**
     * Check and award connection badges
     */
    public static function checkConnectionBadges($userId)
    {
        $connectionCount = self::getConnectionCount($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'connection' && $connectionCount >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }

        // Award XP
        self::awardXP($userId, self::XP_VALUES['make_connection'], 'make_connection', 'Made a new connection');
    }

    /**
     * Check and award message badges
     */
    public static function checkMessageBadges($userId)
    {
        $messageCount = self::getMessagesSentCount($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'message' && $messageCount >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }

        // Award XP
        self::awardXP($userId, self::XP_VALUES['send_message'], 'send_message', 'Sent a message');
    }

    /**
     * Check and award review badges (for giver and receiver)
     */
    public static function checkReviewBadges($reviewerId, $receiverId, $rating)
    {
        // Reviewer badges
        $reviewsGiven = self::getReviewsGivenCount($reviewerId);
        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'review_given' && $reviewsGiven >= $def['threshold']) {
                self::awardBadge($reviewerId, $def);
            }
        }

        // Receiver badges (5-star)
        if ($rating == 5) {
            $fiveStarCount = self::getFiveStarReceivedCount($receiverId);
            foreach (self::getBadgeDefinitions() as $def) {
                if ($def['type'] === '5star' && $fiveStarCount >= $def['threshold']) {
                    self::awardBadge($receiverId, $def);
                }
            }
        }

        // Award XP to reviewer
        self::awardXP($reviewerId, self::XP_VALUES['leave_review'], 'leave_review', 'Left a review');
    }

    /**
     * Check and award event badges
     */
    public static function checkEventBadges($userId, $action = 'attend')
    {
        if ($action === 'attend') {
            $eventsAttended = self::getEventsAttendedCount($userId);
            foreach (self::getBadgeDefinitions() as $def) {
                if ($def['type'] === 'event_attend' && $eventsAttended >= $def['threshold']) {
                    self::awardBadge($userId, $def);
                }
            }
            self::awardXP($userId, self::XP_VALUES['attend_event'], 'attend_event', 'Attending an event');
        } elseif ($action === 'host') {
            $eventsHosted = self::getEventsHostedCount($userId);
            foreach (self::getBadgeDefinitions() as $def) {
                if ($def['type'] === 'event_host' && $eventsHosted >= $def['threshold']) {
                    self::awardBadge($userId, $def);
                }
            }
            self::awardXP($userId, self::XP_VALUES['create_event'], 'create_event', 'Created an event');
        }
    }

    /**
     * Check and award group badges
     */
    public static function checkGroupBadges($userId, $action = 'join')
    {
        if ($action === 'join') {
            $groupsJoined = self::getGroupsJoinedCount($userId);
            foreach (self::getBadgeDefinitions() as $def) {
                if ($def['type'] === 'group_join' && $groupsJoined >= $def['threshold']) {
                    self::awardBadge($userId, $def);
                }
            }
            self::awardXP($userId, self::XP_VALUES['join_group'], 'join_group', 'Joined a group');
        } elseif ($action === 'create') {
            foreach (self::getBadgeDefinitions() as $def) {
                if ($def['type'] === 'group_create') {
                    self::awardBadge($userId, $def);
                }
            }
            self::awardXP($userId, self::XP_VALUES['create_group'], 'create_group', 'Created a group');
        }
    }

    /**
     * Check and award post/content badges
     */
    public static function checkPostBadges($userId)
    {
        $postCount = self::getPostCount($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'post' && $postCount >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }

        self::awardXP($userId, self::XP_VALUES['create_post'], 'create_post', 'Created a post');
    }

    /**
     * Check and award likes received badges
     */
    public static function checkLikesBadges($userId)
    {
        $likesReceived = self::getLikesReceivedCount($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'likes_received' && $likesReceived >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }
    }

    /**
     * Check profile completion badge
     */
    public static function checkProfileBadge($userId)
    {
        $completionPercent = self::getProfileCompletion($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'profile' && $completionPercent >= $def['threshold']) {
                self::awardBadge($userId, $def);
                // One-time XP for profile completion
                if (!UserBadge::hasBadge($userId, 'profile_complete')) {
                    self::awardXP($userId, self::XP_VALUES['complete_profile'], 'complete_profile', 'Completed profile');
                }
            }
        }
    }

    /**
     * Check membership duration badges
     */
    public static function checkMembershipBadges($userId)
    {
        $daysSinceJoined = self::getDaysSinceJoined($userId);

        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'membership' && $daysSinceJoined >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }
    }

    /**
     * Check streak badges
     */
    public static function checkStreakBadges($userId, $currentStreak)
    {
        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'streak' && $currentStreak >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }
    }

    /**
     * Check level badges
     */
    public static function checkLevelBadges($userId, $level)
    {
        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'level' && $level >= $def['threshold']) {
                self::awardBadge($userId, $def);
            }
        }
    }

    /**
     * Check volunteer organization badge
     */
    public static function checkVolOrgBadge($userId)
    {
        foreach (self::getBadgeDefinitions() as $def) {
            if ($def['type'] === 'vol_org') {
                self::awardBadge($userId, $def);
            }
        }
    }

    // =========================================================================
    // XP & LEVELING SYSTEM
    // =========================================================================

    /**
     * Award XP to a user and check for level up
     * Uses transaction to ensure data integrity
     */
    public static function awardXP($userId, $amount, $action, $description = '')
    {
        if ($amount <= 0) return;

        $tenantId = TenantContext::getId();
        $levelInfo = null;

        Database::beginTransaction();

        try {
            // Check if user already has this specific XP (prevent duplicates for one-time actions)
            $oneTimeActions = ['complete_profile'];
            if (in_array($action, $oneTimeActions)) {
                $existing = Database::query(
                    "SELECT id FROM user_xp_log WHERE tenant_id = ? AND user_id = ? AND action = ?",
                    [$tenantId, $userId, $action]
                )->fetch();
                if ($existing) {
                    Database::rollback();
                    return;
                }
            }

            // Log XP
            Database::query(
                "INSERT INTO user_xp_log (tenant_id, user_id, xp_amount, action, description) VALUES (?, ?, ?, ?, ?)",
                [$tenantId, $userId, $amount, $action, $description]
            );

            // Update user XP
            Database::query("UPDATE users SET xp = xp + ? WHERE id = ? AND tenant_id = ?", [$amount, $userId, $tenantId]);

            // Get updated user info for real-time broadcast
            $user = Database::query("SELECT xp, level FROM users WHERE id = ?", [$userId])->fetch();
            $levelInfo = [
                'total_xp' => (int)($user['xp'] ?? 0),
                'level' => (int)($user['level'] ?? 1),
                'progress' => self::getLevelProgress((int)($user['xp'] ?? 0), (int)($user['level'] ?? 1)),
            ];

            Database::commit();

            // Non-critical operations OUTSIDE transaction
            // Broadcast XP gain via Pusher
            GamificationRealtimeService::broadcastXPGained($userId, $amount, $description ?: $action, $levelInfo);

            // Check for level up
            self::checkLevelUp($userId);

        } catch (\Throwable $e) {
            Database::rollback();
            error_log("XP Award Error: " . $e->getMessage());
        }
    }

    /**
     * Check if user leveled up and award level badge
     */
    public static function checkLevelUp($userId)
    {
        try {
            $user = Database::query("SELECT xp, level FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user) return;

            $currentXP = (int)$user['xp'];
            $currentLevel = (int)$user['level'];
            $newLevel = self::calculateLevel($currentXP);

            if ($newLevel > $currentLevel) {
                // Update level
                $tenantId = TenantContext::getId();
                Database::query("UPDATE users SET level = ? WHERE id = ? AND tenant_id = ?", [$newLevel, $userId, $tenantId]);

                // Notify user
                $basePath = TenantContext::getBasePath();
                \Nexus\Models\Notification::create(
                    $userId,
                    "Congratulations! You've reached Level $newLevel! 🎉",
                    "{$basePath}/profile/me",
                    'achievement'
                );

                // Broadcast level up via Pusher for real-time celebration
                $rewards = [];
                // Milestone levels get bonus rewards
                $milestones = [5 => 50, 10 => 100, 15 => 150, 20 => 200, 25 => 300, 30 => 400, 50 => 500, 100 => 1000];
                if (isset($milestones[$newLevel])) {
                    $rewards['bonus_xp'] = $milestones[$newLevel];
                    self::awardXP($userId, $milestones[$newLevel], 'level_milestone', "Level $newLevel milestone bonus");
                }
                GamificationRealtimeService::broadcastLevelUp($userId, $newLevel, $rewards);

                // Check level badges
                self::checkLevelBadges($userId, $newLevel);
            }

        } catch (\Throwable $e) {
            error_log("Level Up Check Error: " . $e->getMessage());
        }
    }

    /**
     * Calculate level from XP
     */
    public static function calculateLevel($xp)
    {
        $level = 1;
        foreach (self::LEVEL_THRESHOLDS as $lvl => $threshold) {
            if ($xp >= $threshold) {
                $level = $lvl;
            }
        }
        return $level;
    }

    /**
     * Get XP needed for next level
     */
    public static function getXPForNextLevel($currentLevel)
    {
        $nextLevel = $currentLevel + 1;
        return self::LEVEL_THRESHOLDS[$nextLevel] ?? null;
    }

    /**
     * Calculate XP threshold for a specific level
     */
    public static function calculateXPForLevel($level)
    {
        return self::LEVEL_THRESHOLDS[$level] ?? self::LEVEL_THRESHOLDS[max(array_keys(self::LEVEL_THRESHOLDS))];
    }

    /**
     * Get level progress percentage
     */
    public static function getLevelProgress($xp, $level)
    {
        $currentThreshold = self::LEVEL_THRESHOLDS[$level] ?? 0;
        $nextThreshold = self::LEVEL_THRESHOLDS[$level + 1] ?? null;

        if ($nextThreshold === null) {
            return 100; // Max level
        }

        $xpInLevel = $xp - $currentThreshold;
        $xpNeeded = $nextThreshold - $currentThreshold;

        return min(100, round(($xpInLevel / $xpNeeded) * 100));
    }

    // =========================================================================
    // HELPER METHODS - STAT RETRIEVAL
    // =========================================================================

    private static function getTotalSpent($userId)
    {
        $result = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE sender_id = ? AND deleted_for_sender = 0",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getTransactionCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM transactions WHERE (sender_id = ? OR receiver_id = ?)",
            [$userId, $userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getUniqueReceiversCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(DISTINCT receiver_id) as total FROM transactions WHERE sender_id = ?",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getConnectionCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'",
            [$userId, $userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getMessagesSentCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM messages WHERE sender_id = ?",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getReviewsGivenCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM reviews WHERE reviewer_id = ?",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getFiveStarReceivedCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM reviews WHERE receiver_id = ? AND rating = 5",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getEventsAttendedCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM event_rsvps WHERE user_id = ? AND status = 'going'",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getEventsHostedCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM events WHERE user_id = ?",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getGroupsJoinedCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM group_members WHERE user_id = ? AND status = 'active'",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getPostCount($userId)
    {
        $result = Database::query(
            "SELECT COUNT(*) as total FROM feed_posts WHERE user_id = ?",
            [$userId]
        )->fetch();
        return (int)($result['total'] ?? 0);
    }

    private static function getLikesReceivedCount($userId)
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as total FROM post_likes pl JOIN feed_posts fp ON pl.post_id = fp.id WHERE fp.user_id = ?",
                [$userId]
            )->fetch();
            return (int)($result['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function getProfileCompletion($userId)
    {
        $user = \Nexus\Models\User::findById($userId);
        if (!$user) return 0;

        $fields = ['first_name', 'last_name', 'email', 'bio', 'avatar_url', 'location', 'phone'];
        $filled = 0;

        foreach ($fields as $field) {
            if (!empty($user[$field])) {
                $filled++;
            }
        }

        return round(($filled / count($fields)) * 100);
    }

    private static function getDaysSinceJoined($userId)
    {
        $user = Database::query("SELECT created_at FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) return 0;

        $joined = new \DateTime($user['created_at']);
        $now = new \DateTime();
        return $joined->diff($now)->days;
    }

    // =========================================================================
    // BADGE AWARDING
    // =========================================================================

    /**
     * Award a badge to a user with notification
     */
    public static function awardBadge($userId, $def)
    {
        if (!UserBadge::hasBadge($userId, $def['key'])) {
            UserBadge::award($userId, $def['key'], $def['name'], $def['icon']);

            $msg = $def['msg'] ?? 'reaching a new milestone';

            // In-App Notification
            $basePath = TenantContext::getBasePath();
            \Nexus\Models\Notification::create(
                $userId,
                "You earned the '{$def['name']}' badge! {$def['icon']}",
                "{$basePath}/profile/me",
                'achievement'
            );

            // Award XP for earning badge
            self::awardXP($userId, self::XP_VALUES['earn_badge'], 'earn_badge', "Badge: {$def['name']}");

            // Email Notification (only if user has milestones enabled)
            if (\Nexus\Models\User::isGamificationEmailEnabled($userId, 'milestones')) {
                try {
                    $user = \Nexus\Models\User::findById($userId);
                    if ($user && !empty($user['email'])) {
                    $mailer = new \Nexus\Core\Mailer();
                    $firstName = htmlspecialchars($user['first_name'] ?? 'Member');
                    $badgeName = htmlspecialchars($def['name']);
                    $badgeIcon = $def['icon'];
                    $achievementDesc = ucfirst($msg);
                    $siteUrl = \Nexus\Core\TenantContext::getFrontendUrl();
                    $achievementsUrl = $siteUrl . $basePath . "/achievements/badges";

                    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 40px; border-radius: 16px 16px 0 0; text-align: center;">
            <div style="font-size: 80px; margin-bottom: 20px;">{$badgeIcon}</div>
            <h1 style="margin: 0 0 10px 0; font-size: 28px;">New Badge Earned!</h1>
            <p style="margin: 0; font-size: 18px;">Congratulations, {$firstName}!</p>
        </div>
        <div style="background: white; padding: 40px; border-radius: 0 0 16px 16px;">
            <h2 style="color: #1e1e2e; margin: 0 0 20px 0; text-align: center; font-size: 24px;">{$badgeName}</h2>

            <div style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(139, 92, 246, 0.05)); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                    Achievement Unlocked For
                </div>
                <div style="font-size: 18px; color: #1f2937; font-weight: 500;">
                    {$achievementDesc}
                </div>
            </div>

            <p style="color: #6b7280; text-align: center; margin: 0 0 24px 0;">
                This badge has been added to your profile. Keep up the great work!
            </p>

            <div style="text-align: center;">
                <a href="{$achievementsUrl}" style="display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    View All Your Badges
                </a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
                        $mailer->send($user['email'], "You earned a new badge! {$badgeIcon} {$badgeName}", $body);
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to send badge email: " . $e->getMessage());
                }
            }

            return true;
        }
        return false;
    }

    /**
     * Manually award a badge by key (for admin use)
     */
    public static function awardBadgeByKey($userId, $badgeKey)
    {
        $def = self::getBadgeByKey($badgeKey);
        if ($def) {
            return self::awardBadge($userId, $def);
        }
        return false;
    }

    /**
     * Run all badge checks for a user (useful for catch-up)
     */
    public static function runAllBadgeChecks($userId)
    {
        self::checkVolunteeringBadges($userId);
        self::checkTimebankingBadges($userId);
        self::checkListingBadges($userId);
        self::checkConnectionBadges($userId);
        self::checkMessageBadges($userId);
        self::checkEventBadges($userId, 'attend');
        self::checkEventBadges($userId, 'host');
        self::checkGroupBadges($userId, 'join');
        self::checkPostBadges($userId);
        self::checkLikesBadges($userId);
        self::checkProfileBadge($userId);
        self::checkMembershipBadges($userId);
    }

    // =========================================================================
    // BADGE PROGRESS TRACKING
    // =========================================================================

    /**
     * Get badge progress for a user - shows current progress towards next badges
     */
    public static function getBadgeProgress($userId)
    {
        $progress = [];
        $userBadges = \Nexus\Models\UserBadge::getForUser($userId);
        $earnedKeys = array_column($userBadges, 'badge_key');

        // Get current stats
        $stats = self::getUserStatsForProgress($userId);

        // Group badges by type and find next unlockable
        $badgesByType = [];
        foreach (self::getBadgeDefinitions() as $def) {
            if (!isset($badgesByType[$def['type']])) {
                $badgesByType[$def['type']] = [];
            }
            $badgesByType[$def['type']][] = $def;
        }

        foreach ($badgesByType as $type => $badges) {
            // Sort by threshold
            usort($badges, fn($a, $b) => $a['threshold'] - $b['threshold']);

            // Find next unlockable badge in this category
            foreach ($badges as $badge) {
                if (in_array($badge['key'], $earnedKeys)) {
                    continue; // Already have it
                }

                // Get current value for this type
                $current = $stats[$type] ?? 0;
                $threshold = $badge['threshold'];

                if ($threshold > 0 && $current < $threshold) {
                    $percent = min(99, round(($current / $threshold) * 100));
                    $progress[] = [
                        'badge' => $badge,
                        'current' => $current,
                        'target' => $threshold,
                        'percent' => $percent,
                        'remaining' => $threshold - $current
                    ];
                    break; // Only show next badge per category
                }
            }
        }

        // Sort by closest to completion
        usort($progress, fn($a, $b) => $b['percent'] - $a['percent']);

        return array_slice($progress, 0, 6); // Top 6 closest badges
    }

    /**
     * Get comprehensive user stats for badge progress
     */
    public static function getUserStatsForProgress($userId)
    {
        $stats = [
            'vol' => 0,
            'offer' => 0,
            'request' => 0,
            'earn' => 0,
            'spend' => 0,
            'transaction' => 0,
            'diversity' => 0,
            'connection' => 0,
            'message' => 0,
            'review_given' => 0,
            '5star' => 0,
            'event_attend' => 0,
            'event_host' => 0,
            'group_join' => 0,
            'group_create' => 0,
            'post' => 0,
            'likes_received' => 0,
            'profile' => 0,
            'membership' => 0,
            'streak' => 0,
            'level' => 1,
        ];

        // Wrap each stat in try-catch so one failure doesn't break all stats
        try { $stats['vol'] = (int)\Nexus\Models\VolLog::getTotalVerifiedHours($userId); } catch (\Throwable $e) {}
        try { $stats['offer'] = (int)(\Nexus\Models\Listing::countByUser($userId, 'offer') ?? 0); } catch (\Throwable $e) {}
        try { $stats['request'] = (int)(\Nexus\Models\Listing::countByUser($userId, 'request') ?? 0); } catch (\Throwable $e) {}
        try { $stats['earn'] = (int)(\Nexus\Models\Transaction::getTotalEarned($userId) ?? 0); } catch (\Throwable $e) {}
        try { $stats['spend'] = (int)self::getTotalSpent($userId); } catch (\Throwable $e) {}
        try { $stats['transaction'] = (int)self::getTransactionCount($userId); } catch (\Throwable $e) {}
        try { $stats['diversity'] = (int)self::getUniqueReceiversCount($userId); } catch (\Throwable $e) {}
        try { $stats['connection'] = (int)self::getConnectionCount($userId); } catch (\Throwable $e) {}
        try { $stats['message'] = (int)self::getMessagesSentCount($userId); } catch (\Throwable $e) {}
        try { $stats['review_given'] = (int)self::getReviewsGivenCount($userId); } catch (\Throwable $e) {}
        try { $stats['5star'] = (int)self::getFiveStarReceivedCount($userId); } catch (\Throwable $e) {}
        try { $stats['event_attend'] = (int)self::getEventsAttendedCount($userId); } catch (\Throwable $e) {}
        try { $stats['event_host'] = (int)self::getEventsHostedCount($userId); } catch (\Throwable $e) {}
        try { $stats['group_join'] = (int)self::getGroupsJoinedCount($userId); } catch (\Throwable $e) {}
        try { $stats['post'] = (int)self::getPostCount($userId); } catch (\Throwable $e) {}
        try { $stats['likes_received'] = (int)self::getLikesReceivedCount($userId); } catch (\Throwable $e) {}
        try { $stats['profile'] = (int)self::getProfileCompletion($userId); } catch (\Throwable $e) {}
        try { $stats['membership'] = (int)self::getDaysSinceJoined($userId); } catch (\Throwable $e) {}
        try { $stats['streak'] = (int)self::getCurrentLoginStreak($userId); } catch (\Throwable $e) {}
        try { $stats['level'] = (int)self::getUserLevel($userId); } catch (\Throwable $e) {}

        return $stats;
    }

    /**
     * Get current login streak
     */
    private static function getCurrentLoginStreak($userId)
    {
        try {
            $result = Database::query(
                "SELECT current_streak FROM user_streaks WHERE user_id = ? AND streak_type = 'login'",
                [$userId]
            )->fetch();
            return (int)($result['current_streak'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get user level
     */
    private static function getUserLevel($userId)
    {
        $user = Database::query("SELECT level FROM users WHERE id = ?", [$userId])->fetch();
        return (int)($user['level'] ?? 1);
    }

    /**
     * Get full gamification dashboard data for a user
     */
    public static function getDashboardData($userId)
    {
        $user = \Nexus\Models\User::findById($userId);
        if (!$user) return null;

        // Get XP and level directly from database to ensure we have them
        // (User::findById may not include these columns on older installations)
        $gamificationData = Database::query(
            "SELECT COALESCE(xp, 0) as xp, COALESCE(level, 1) as level FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        $xp = (int)($gamificationData['xp'] ?? 0);
        $level = (int)($gamificationData['level'] ?? 1);
        $badges = \Nexus\Models\UserBadge::getForUser($userId);

        // Get streaks
        $streaks = [];
        try {
            $streaks = StreakService::getAllStreaks($userId);
        } catch (\Throwable $e) {
            error_log("Streak fetch error: " . $e->getMessage());
        }

        // Get leaderboard positions
        $rankings = [];
        foreach (['xp', 'badges', 'vol_hours', 'credits_earned'] as $type) {
            $rank = LeaderboardService::getUserRank($userId, $type, 'all_time');
            if ($rank) {
                $rankings[$type] = $rank['rank'];
            }
        }

        // Get badge progress
        $badgeProgress = self::getBadgeProgress($userId);

        // Get recent XP activity
        $recentXP = [];
        try {
            $tenantId = TenantContext::getId();
            $recentXP = Database::query(
                "SELECT action, xp_amount, description, created_at
                 FROM user_xp_log
                 WHERE tenant_id = ? AND user_id = ?
                 ORDER BY created_at DESC
                 LIMIT 10",
                [$tenantId, $userId]
            )->fetchAll();
        } catch (\Throwable $e) {
            // Table might not exist
        }

        // Calculate stats
        $stats = [];
        try {
            $stats = self::getUserStatsForProgress($userId);
        } catch (\Throwable $e) {
            error_log("Stats fetch error: " . $e->getMessage());
        }

        return [
            'user' => [
                'name' => $user['name'] ?? $user['first_name'] . ' ' . $user['last_name'],
                'avatar_url' => $user['avatar_url'] ?? null,
            ],
            'xp' => [
                'total' => $xp,
                'level' => $level,
                'progress' => self::getLevelProgress($xp, $level),
                'xp_for_next' => self::getXPForNextLevel($level),
                'xp_in_level' => $xp - (self::LEVEL_THRESHOLDS[$level] ?? 0),
            ],
            'badges' => [
                'earned' => $badges,
                'total_earned' => count($badges),
                'total_available' => count(self::getBadgeDefinitions()),
                'progress' => $badgeProgress,
            ],
            'streaks' => $streaks,
            'rankings' => $rankings,
            'stats' => $stats,
            'recent_xp' => $recentXP,
        ];
    }
}
