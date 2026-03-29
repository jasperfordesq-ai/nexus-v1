<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the badges table with all system badge definitions.
 *
 * Migrates the 48 hardcoded badges from GamificationService::getStaticBadgeDefinitions()
 * into the database, and adds 15 new quality-based badges for the gamification redesign.
 *
 * Uses INSERT IGNORE so this migration is idempotent — safe to re-run.
 * Badges are seeded per-tenant (one row per tenant per badge).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id')->toArray();

        if (empty($tenantIds)) {
            return;
        }

        $badges = $this->getAllBadgeDefinitions();

        foreach ($tenantIds as $tenantId) {
            foreach (array_chunk($badges, 20) as $chunk) {
                $rows = [];
                foreach ($chunk as $badge) {
                    $rows[] = [
                        'tenant_id'         => $tenantId,
                        'badge_key'         => $badge['key'],
                        'name'              => $badge['name'],
                        'description'       => $badge['msg'],
                        'icon'              => $badge['icon'],
                        'category'          => $badge['type'],
                        'badge_tier'        => $badge['badge_tier'],
                        'badge_class'       => $badge['badge_class'],
                        'threshold'         => $badge['threshold'],
                        'threshold_type'    => $badge['threshold_type'],
                        'evaluation_method' => $badge['evaluation_method'],
                        'is_enabled'        => 1,
                        'is_active'         => 1,
                        'config_json'       => $badge['config_json'] ?? null,
                        'rarity'            => $badge['rarity'] ?? 'common',
                        'xp_value'          => 25,
                        'sort_order'        => $badge['sort_order'] ?? 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

                // INSERT IGNORE to skip duplicates (idempotent)
                foreach ($rows as $row) {
                    DB::table('badges')->insertOrIgnore($row);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove only the new quality badges added by this migration.
        // The original 48 quantity badges are left in place since they may have
        // been referenced by user_badges records before this migration existed.
        $qualityKeys = [
            'reliability_bronze', 'reliability_silver', 'reliability_gold',
            'bridge_builder_1', 'bridge_builder_2', 'bridge_builder_3',
            'mentor_1', 'mentor_2', 'mentor_3',
            'reciprocity_balanced', 'reciprocity_harmonious', 'reciprocity_exemplar',
            'community_champion_1', 'community_champion_2', 'community_champion_3',
        ];

        DB::table('badges')->whereIn('badge_key', $qualityKeys)->delete();
    }

    /**
     * All badge definitions: 48 existing quantity badges + 15 new quality badges.
     */
    private function getAllBadgeDefinitions(): array
    {
        $sortOrder = 0;

        // =====================================================================
        // EXISTING QUANTITY BADGES (48) — migrated from GamificationService
        // =====================================================================

        $existing = [
            // VOLUNTEERING (6) — core: vol_1h, vol_50h, vol_250h; template: vol_10h, vol_100h, vol_500h
            ['key' => 'vol_1h',    'name' => 'First Steps',        'icon' => "\xF0\x9F\x91\xA3", 'type' => 'vol', 'threshold' => 1,   'msg' => 'Log your first volunteer hour',           'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'vol_10h',   'name' => 'Helping Hand',       'icon' => "\xF0\x9F\xA4\xB2", 'type' => 'vol', 'threshold' => 10,  'msg' => 'Volunteer 10 hours',                      'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'vol_50h',   'name' => 'Change Maker',       'icon' => "\xF0\x9F\x8C\x8D", 'type' => 'vol', 'threshold' => 50,  'msg' => 'Volunteer 50 hours',                      'badge_tier' => 'core',     'rarity' => 'uncommon'],
            ['key' => 'vol_100h',  'name' => 'TimeBank Legend',    'icon' => "\xF0\x9F\x91\x91", 'type' => 'vol', 'threshold' => 100, 'msg' => 'Volunteer 100 hours',                     'badge_tier' => 'template', 'rarity' => 'rare'],
            ['key' => 'vol_250h',  'name' => 'Volunteer Hero',     'icon' => "\xF0\x9F\xA6\xB8", 'type' => 'vol', 'threshold' => 250, 'msg' => 'Volunteer 250 hours',                     'badge_tier' => 'core',     'rarity' => 'epic'],
            ['key' => 'vol_500h',  'name' => 'Volunteer Champion', 'icon' => "\xF0\x9F\x8F\x85", 'type' => 'vol', 'threshold' => 500, 'msg' => 'Volunteer 500 hours',                     'badge_tier' => 'template', 'rarity' => 'legendary'],

            // OFFERS (4) — core: offer_1; template: rest
            ['key' => 'offer_1',   'name' => 'First Offer',        'icon' => "\xF0\x9F\x8E\x81", 'type' => 'offer', 'threshold' => 1,  'msg' => 'Post your first offer',                  'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'offer_5',   'name' => 'Generous Soul',      'icon' => "\xF0\x9F\xA4\x9D", 'type' => 'offer', 'threshold' => 5,  'msg' => 'Post 5 offers',                          'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'offer_10',  'name' => 'Gift Giver',         'icon' => "\xF0\x9F\x8E\x80", 'type' => 'offer', 'threshold' => 10, 'msg' => 'Post 10 offers',                         'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'offer_25',  'name' => 'Offer Master',       'icon' => "\xF0\x9F\x8C\x9F", 'type' => 'offer', 'threshold' => 25, 'msg' => 'Post 25 offers',                         'badge_tier' => 'template', 'rarity' => 'rare'],

            // REQUESTS (3) — core: request_1; template: rest
            ['key' => 'request_1',  'name' => 'First Request',     'icon' => "\xF0\x9F\x99\x8B", 'type' => 'request', 'threshold' => 1,  'msg' => 'Post your first request',               'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'request_5',  'name' => 'Community Seeker',  'icon' => "\xF0\x9F\x97\xA3\xEF\xB8\x8F", 'type' => 'request', 'threshold' => 5,  'msg' => 'Make 5 requests',          'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'request_10', 'name' => 'Active Requester',  'icon' => "\xF0\x9F\x93\xA2", 'type' => 'request', 'threshold' => 10, 'msg' => 'Make 10 requests',                     'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // EARNING (5) — core: earn_1; template: rest
            ['key' => 'earn_1',    'name' => 'First Earn',         'icon' => "\xF0\x9F\xAA\x99", 'type' => 'earn', 'threshold' => 1,   'msg' => 'Earn your first time credit',             'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'earn_10',   'name' => 'Go Getter',          'icon' => "\xF0\x9F\x9A\x80", 'type' => 'earn', 'threshold' => 10,  'msg' => 'Earn 10 time credits',                    'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'earn_50',   'name' => 'Credit Builder',     'icon' => "\xE2\x9A\xA1",     'type' => 'earn', 'threshold' => 50,  'msg' => 'Earn 50 time credits',                    'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'earn_100',  'name' => 'Centurion',          'icon' => "\xF0\x9F\x92\xAF", 'type' => 'earn', 'threshold' => 100, 'msg' => 'Earn 100 time credits',                   'badge_tier' => 'template', 'rarity' => 'rare'],
            ['key' => 'earn_250',  'name' => 'Credit Master',      'icon' => "\xF0\x9F\x92\x8E", 'type' => 'earn', 'threshold' => 250, 'msg' => 'Earn 250 time credits',                   'badge_tier' => 'template', 'rarity' => 'epic'],

            // SPENDING (3) — core: spend_1; template: rest
            ['key' => 'spend_1',   'name' => 'First Spend',        'icon' => "\xF0\x9F\x92\xB8", 'type' => 'spend', 'threshold' => 1,  'msg' => 'Spend your first time credit',            'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'spend_10',  'name' => 'Active Spender',     'icon' => "\xF0\x9F\x92\xB3", 'type' => 'spend', 'threshold' => 10, 'msg' => 'Spend 10 time credits',                   'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'spend_50',  'name' => 'Generous Spender',   'icon' => "\xF0\x9F\x8E\x8A", 'type' => 'spend', 'threshold' => 50, 'msg' => 'Spend 50 time credits',                   'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // TRANSACTIONS (3) — core: transaction_1; template: rest
            ['key' => 'transaction_1',  'name' => 'First Exchange',  'icon' => "\xF0\x9F\x94\x84", 'type' => 'transaction', 'threshold' => 1,  'msg' => 'Complete your first transaction',   'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'transaction_10', 'name' => 'Active Trader',   'icon' => "\xF0\x9F\x93\x8A", 'type' => 'transaction', 'threshold' => 10, 'msg' => 'Complete 10 transactions',         'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'transaction_50', 'name' => 'Exchange Master', 'icon' => "\xF0\x9F\x92\xB1", 'type' => 'transaction', 'threshold' => 50, 'msg' => 'Complete 50 transactions',         'badge_tier' => 'template', 'rarity' => 'rare'],

            // DIVERSITY (3) — template
            ['key' => 'diversity_3',  'name' => 'Community Helper',  'icon' => "\xF0\x9F\x8C\x88", 'type' => 'diversity', 'threshold' => 3,  'msg' => 'Help 3 different people',             'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'diversity_10', 'name' => 'Diverse Giver',     'icon' => "\xF0\x9F\x8C\x90", 'type' => 'diversity', 'threshold' => 10, 'msg' => 'Help 10 different people',            'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'diversity_25', 'name' => 'Community Pillar',  'icon' => "\xF0\x9F\x8F\x9B\xEF\xB8\x8F", 'type' => 'diversity', 'threshold' => 25, 'msg' => 'Help 25 different people', 'badge_tier' => 'template', 'rarity' => 'rare'],

            // CONNECTIONS (4) — core: connect_1; template: rest
            ['key' => 'connect_1',  'name' => 'First Friend',       'icon' => "\xF0\x9F\x91\x8B", 'type' => 'connection', 'threshold' => 1,  'msg' => 'Make your first connection',           'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'connect_10', 'name' => 'Social Butterfly',   'icon' => "\xF0\x9F\xA6\x8B", 'type' => 'connection', 'threshold' => 10, 'msg' => 'Make 10 connections',                 'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'connect_25', 'name' => 'Network Builder',    'icon' => "\xF0\x9F\x95\xB8\xEF\xB8\x8F", 'type' => 'connection', 'threshold' => 25, 'msg' => 'Make 25 connections',   'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'connect_50', 'name' => 'Community Connector','icon' => "\xF0\x9F\x94\x97", 'type' => 'connection', 'threshold' => 50, 'msg' => 'Make 50 connections',                 'badge_tier' => 'template', 'rarity' => 'rare'],

            // MESSAGES (3) — template (removed from core — messaging volume isn't a community value)
            ['key' => 'msg_1',    'name' => 'Conversation Starter', 'icon' => "\xF0\x9F\x92\xAC", 'type' => 'message', 'threshold' => 1,   'msg' => 'Send your first message',              'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'msg_50',   'name' => 'Active Communicator',  'icon' => "\xF0\x9F\x93\xB1", 'type' => 'message', 'threshold' => 50,  'msg' => 'Send 50 messages',                     'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'msg_200',  'name' => 'Communication Pro',    'icon' => "\xF0\x9F\x93\xA8", 'type' => 'message', 'threshold' => 200, 'msg' => 'Send 200 messages',                    'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // REVIEWS (3) — core: review_1; template: rest
            ['key' => 'review_1',  'name' => 'First Feedback',      'icon' => "\xE2\xAD\x90",     'type' => 'review_given', 'threshold' => 1,  'msg' => 'Leave your first review',            'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'review_10', 'name' => 'Trusted Reviewer',    'icon' => "\xF0\x9F\x93\x9D", 'type' => 'review_given', 'threshold' => 10, 'msg' => 'Leave 10 reviews',                  'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'review_25', 'name' => 'Review Expert',       'icon' => "\xF0\x9F\x8E\xAF", 'type' => 'review_given', 'threshold' => 25, 'msg' => 'Leave 25 reviews',                  'badge_tier' => 'template', 'rarity' => 'rare'],

            // 5-STAR (3) — template
            ['key' => '5star_1',   'name' => 'First 5-Star',        'icon' => "\xF0\x9F\x8C\x9F", 'type' => '5star', 'threshold' => 1,  'msg' => 'Receive your first 5-star review',        'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => '5star_10',  'name' => 'Highly Rated',        'icon' => "\xF0\x9F\x8F\x86", 'type' => '5star', 'threshold' => 10, 'msg' => 'Receive 10 five-star reviews',             'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => '5star_25',  'name' => 'Excellence Award',    'icon' => "\xF0\x9F\x91\x8F", 'type' => '5star', 'threshold' => 25, 'msg' => 'Receive 25 five-star reviews',             'badge_tier' => 'template', 'rarity' => 'rare'],

            // EVENTS (5) — template
            ['key' => 'event_attend_1',  'name' => 'First Event',      'icon' => "\xF0\x9F\x8E\x9F\xEF\xB8\x8F", 'type' => 'event_attend', 'threshold' => 1,  'msg' => 'Attend your first event',   'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'event_attend_10', 'name' => 'Event Regular',    'icon' => "\xF0\x9F\x93\x85", 'type' => 'event_attend', 'threshold' => 10, 'msg' => 'Attend 10 events',                       'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'event_attend_25', 'name' => 'Event Enthusiast', 'icon' => "\xF0\x9F\x8E\x89", 'type' => 'event_attend', 'threshold' => 25, 'msg' => 'Attend 25 events',                       'badge_tier' => 'template', 'rarity' => 'rare'],
            ['key' => 'event_host_1',    'name' => 'Event Host',       'icon' => "\xF0\x9F\x8E\xA4", 'type' => 'event_host', 'threshold' => 1, 'msg' => 'Host your first event',                     'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'event_host_5',    'name' => 'Event Organizer',  'icon' => "\xF0\x9F\x8E\xAA", 'type' => 'event_host', 'threshold' => 5, 'msg' => 'Host 5 events',                             'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // GROUPS (3) — template
            ['key' => 'group_join_1',  'name' => 'Team Player',       'icon' => "\xF0\x9F\x91\xA5", 'type' => 'group_join', 'threshold' => 1, 'msg' => 'Join your first group',                      'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'group_join_5',  'name' => 'Community Member',  'icon' => "\xF0\x9F\x8F\x98\xEF\xB8\x8F", 'type' => 'group_join', 'threshold' => 5, 'msg' => 'Join 5 groups',                  'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'group_create',  'name' => 'Group Founder',     'icon' => "\xF0\x9F\x9A\x80", 'type' => 'group_create', 'threshold' => 1, 'msg' => 'Create your first group',                  'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // POSTS (3) — template
            ['key' => 'post_1',   'name' => 'First Post',            'icon' => "\xE2\x9C\x8F\xEF\xB8\x8F", 'type' => 'post', 'threshold' => 1,   'msg' => 'Create your first post',              'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'post_25',  'name' => 'Content Creator',       'icon' => "\xF0\x9F\x93\xB0", 'type' => 'post', 'threshold' => 25,  'msg' => 'Create 25 posts',                              'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'post_100', 'name' => 'Prolific Poster',       'icon' => "\xF0\x9F\x93\x9A", 'type' => 'post', 'threshold' => 100, 'msg' => 'Create 100 posts',                              'badge_tier' => 'template', 'rarity' => 'rare'],

            // LIKES RECEIVED (2) — template
            ['key' => 'likes_50',  'name' => 'Getting Noticed',      'icon' => "\xE2\x9D\xA4\xEF\xB8\x8F", 'type' => 'likes_received', 'threshold' => 50,  'msg' => 'Receive 50 likes',            'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'likes_200', 'name' => 'Popular Voice',        'icon' => "\xF0\x9F\x92\x95", 'type' => 'likes_received', 'threshold' => 200, 'msg' => 'Receive 200 likes',                    'badge_tier' => 'template', 'rarity' => 'uncommon'],

            // PROFILE & LOYALTY (4) — core: profile_complete, member_365d; template: rest
            ['key' => 'profile_complete', 'name' => 'Profile Pro',   'icon' => "\xF0\x9F\x91\xA4", 'type' => 'profile', 'threshold' => 100, 'msg' => 'Complete your profile',                       'badge_tier' => 'core',     'rarity' => 'common'],
            ['key' => 'member_30d',  'name' => 'Monthly Member',     'icon' => "\xF0\x9F\x93\x86", 'type' => 'membership', 'threshold' => 30,  'msg' => 'Be a member for 30 days',                  'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'member_180d', 'name' => 'Semester Member',    'icon' => "\xF0\x9F\x93\x85", 'type' => 'membership', 'threshold' => 180, 'msg' => 'Be a member for 6 months',                 'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'member_365d', 'name' => 'Annual Member',      'icon' => "\xF0\x9F\x8E\x82", 'type' => 'membership', 'threshold' => 365, 'msg' => 'Be a member for one year',                 'badge_tier' => 'core',     'rarity' => 'rare'],

            // STREAKS (4) — template (streaks are being de-emphasized in the redesign)
            ['key' => 'streak_7d',   'name' => 'Week Warrior',       'icon' => "\xF0\x9F\x94\xA5", 'type' => 'streak', 'threshold' => 7,   'msg' => 'Maintain a 7-day streak',                     'badge_tier' => 'template', 'rarity' => 'common'],
            ['key' => 'streak_30d',  'name' => 'Monthly Dedication', 'icon' => "\xF0\x9F\x94\xA5", 'type' => 'streak', 'threshold' => 30,  'msg' => 'Maintain a 30-day streak',                    'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'streak_100d', 'name' => 'Streak Master',      'icon' => "\xF0\x9F\x94\xA5", 'type' => 'streak', 'threshold' => 100, 'msg' => 'Maintain a 100-day streak',                   'badge_tier' => 'template', 'rarity' => 'rare'],
            ['key' => 'streak_365d', 'name' => 'Year-Long Legend',   'icon' => "\xF0\x9F\x94\xA5", 'type' => 'streak', 'threshold' => 365, 'msg' => 'Maintain a 365-day streak',                   'badge_tier' => 'template', 'rarity' => 'epic'],

            // LEVEL (2) — template
            ['key' => 'level_5',  'name' => 'Rising Star',           'icon' => "\xF0\x9F\x8C\x9F", 'type' => 'level', 'threshold' => 5,  'msg' => 'Reach level 5',                                 'badge_tier' => 'template', 'rarity' => 'uncommon'],
            ['key' => 'level_10', 'name' => 'Community Champion',    'icon' => "\xF0\x9F\x8F\x86", 'type' => 'level', 'threshold' => 10, 'msg' => 'Reach level 10',                                'badge_tier' => 'template', 'rarity' => 'rare'],

            // SPECIAL (3) — core: early_adopter, verified; template: volunteer_org
            ['key' => 'early_adopter', 'name' => 'Early Adopter',    'icon' => "\xF0\x9F\x8C\xB1", 'type' => 'special', 'threshold' => 0, 'msg' => 'Be an early adopter of the platform',          'badge_tier' => 'core',     'rarity' => 'rare'],
            ['key' => 'verified',      'name' => 'Verified Member',  'icon' => "\xE2\x9C\x85",     'type' => 'special', 'threshold' => 0, 'msg' => 'Complete identity verification',                'badge_tier' => 'core',     'rarity' => 'uncommon'],
            ['key' => 'volunteer_org', 'name' => 'Organization Partner','icon' => "\xF0\x9F\x8F\xA2", 'type' => 'vol_org', 'threshold' => 1, 'msg' => 'Create a volunteer organization',            'badge_tier' => 'template', 'rarity' => 'uncommon'],
        ];

        // Set evaluation methods and threshold types for existing badges
        $evalMap = [
            'vol' => ['method' => 'checkVolunteeringBadges', 'threshold_type' => 'count'],
            'offer' => ['method' => 'checkListingBadges', 'threshold_type' => 'count'],
            'request' => ['method' => 'checkListingBadges', 'threshold_type' => 'count'],
            'earn' => ['method' => 'checkTimebankingBadges', 'threshold_type' => 'count'],
            'spend' => ['method' => 'checkTimebankingBadges', 'threshold_type' => 'count'],
            'transaction' => ['method' => 'checkTimebankingBadges', 'threshold_type' => 'count'],
            'diversity' => ['method' => 'checkTimebankingBadges', 'threshold_type' => 'count'],
            'connection' => ['method' => 'checkConnectionBadges', 'threshold_type' => 'count'],
            'message' => ['method' => 'checkMessageBadges', 'threshold_type' => 'count'],
            'review_given' => ['method' => 'checkReviewBadges', 'threshold_type' => 'count'],
            '5star' => ['method' => 'checkReviewBadges', 'threshold_type' => 'count'],
            'event_attend' => ['method' => 'checkEventBadges', 'threshold_type' => 'count'],
            'event_host' => ['method' => 'checkEventBadges', 'threshold_type' => 'count'],
            'group_join' => ['method' => 'checkGroupBadges', 'threshold_type' => 'count'],
            'group_create' => ['method' => 'checkGroupBadges', 'threshold_type' => 'count'],
            'post' => ['method' => 'checkPostBadges', 'threshold_type' => 'count'],
            'likes_received' => ['method' => 'checkLikesBadges', 'threshold_type' => 'count'],
            'profile' => ['method' => 'checkProfileBadge', 'threshold_type' => 'count'],
            'membership' => ['method' => 'checkMembershipBadges', 'threshold_type' => 'duration_months'],
            'streak' => ['method' => 'checkStreakBadges', 'threshold_type' => 'count'],
            'level' => ['method' => 'checkLevelBadges', 'threshold_type' => 'count'],
            'special' => ['method' => null, 'threshold_type' => null],
            'vol_org' => ['method' => 'checkVolOrgBadges', 'threshold_type' => 'count'],
        ];

        foreach ($existing as &$badge) {
            $eval = $evalMap[$badge['type']] ?? ['method' => null, 'threshold_type' => 'count'];
            $badge['evaluation_method'] = $eval['method'];
            $badge['threshold_type'] = $eval['threshold_type'];
            $badge['badge_class'] = ($badge['type'] === 'special') ? 'special' : 'quantity';
            $badge['config_json'] = null;
            $badge['sort_order'] = ++$sortOrder;
        }
        unset($badge);

        // =====================================================================
        // NEW QUALITY BADGES (15) — the gamification redesign additions
        // =====================================================================

        $quality = [
            // RELIABILITY — rewards dependability, not volume
            [
                'key' => 'reliability_bronze', 'name' => 'Reliable Member',
                'icon' => "\xF0\x9F\xA4\x9D", 'type' => 'reliability', 'threshold' => 5,
                'msg' => 'Complete 5 transactions with less than 10% cancellation rate',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'common',
                'threshold_type' => 'rate', 'evaluation_method' => 'checkReliabilityBadges',
                'config_json' => json_encode(['min_transactions' => 5, 'max_cancellation_rate' => 0.10]),
            ],
            [
                'key' => 'reliability_silver', 'name' => 'Trusted Partner',
                'icon' => "\xF0\x9F\x9B\xA1\xEF\xB8\x8F", 'type' => 'reliability', 'threshold' => 15,
                'msg' => 'Complete 15 transactions with less than 5% cancellation rate',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'uncommon',
                'threshold_type' => 'rate', 'evaluation_method' => 'checkReliabilityBadges',
                'config_json' => json_encode(['min_transactions' => 15, 'max_cancellation_rate' => 0.05]),
            ],
            [
                'key' => 'reliability_gold', 'name' => 'Rock Solid',
                'icon' => "\xF0\x9F\x8F\x86", 'type' => 'reliability', 'threshold' => 30,
                'msg' => 'Complete 30 transactions with less than 2% cancellation rate',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'rare',
                'threshold_type' => 'rate', 'evaluation_method' => 'checkReliabilityBadges',
                'config_json' => json_encode(['min_transactions' => 30, 'max_cancellation_rate' => 0.02]),
            ],

            // BRIDGE BUILDER — rewards cross-category diversity of skills traded
            [
                'key' => 'bridge_builder_1', 'name' => 'Skill Explorer',
                'icon' => "\xF0\x9F\x8C\x89", 'type' => 'bridge_builder', 'threshold' => 3,
                'msg' => 'Trade services across 3 different skill categories',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'common',
                'threshold_type' => 'count', 'evaluation_method' => 'checkBridgeBuilderBadges',
                'config_json' => json_encode(['min_categories' => 3]),
            ],
            [
                'key' => 'bridge_builder_2', 'name' => 'Bridge Builder',
                'icon' => "\xF0\x9F\x8C\x89", 'type' => 'bridge_builder', 'threshold' => 7,
                'msg' => 'Trade services across 7 different skill categories',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'uncommon',
                'threshold_type' => 'count', 'evaluation_method' => 'checkBridgeBuilderBadges',
                'config_json' => json_encode(['min_categories' => 7]),
            ],
            [
                'key' => 'bridge_builder_3', 'name' => 'Renaissance Member',
                'icon' => "\xF0\x9F\x8C\x89", 'type' => 'bridge_builder', 'threshold' => 15,
                'msg' => 'Trade services across 15 different skill categories',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'rare',
                'threshold_type' => 'count', 'evaluation_method' => 'checkBridgeBuilderBadges',
                'config_json' => json_encode(['min_categories' => 15]),
            ],

            // MENTOR — rewards helping new members get started
            [
                'key' => 'mentor_1', 'name' => 'Welcoming Hand',
                'icon' => "\xF0\x9F\x8C\xB1", 'type' => 'mentor', 'threshold' => 1,
                'msg' => 'Help a new member complete their first transaction',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'common',
                'threshold_type' => 'count', 'evaluation_method' => 'checkMentorBadges',
                'config_json' => json_encode(['new_member_days' => 30]),
            ],
            [
                'key' => 'mentor_2', 'name' => 'Community Mentor',
                'icon' => "\xF0\x9F\x8E\x93", 'type' => 'mentor', 'threshold' => 5,
                'msg' => 'Help 5 new members complete their first transactions',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'uncommon',
                'threshold_type' => 'count', 'evaluation_method' => 'checkMentorBadges',
                'config_json' => json_encode(['new_member_days' => 30]),
            ],
            [
                'key' => 'mentor_3', 'name' => 'Master Guide',
                'icon' => "\xF0\x9F\xA7\xAD", 'type' => 'mentor', 'threshold' => 15,
                'msg' => 'Help 15 new members complete their first transactions',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'rare',
                'threshold_type' => 'count', 'evaluation_method' => 'checkMentorBadges',
                'config_json' => json_encode(['new_member_days' => 30]),
            ],

            // RECIPROCITY — rewards balanced giving and receiving (core timebanking value)
            [
                'key' => 'reciprocity_balanced', 'name' => 'Balanced Exchanger',
                'icon' => "\xE2\x9A\x96\xEF\xB8\x8F", 'type' => 'reciprocity', 'threshold' => 10,
                'msg' => 'Complete 10 transactions with a healthy give/receive balance',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'common',
                'threshold_type' => 'ratio', 'evaluation_method' => 'checkReciprocityBadges',
                'config_json' => json_encode(['min_transactions' => 10, 'min_ratio' => 0.3, 'max_ratio' => 3.0]),
            ],
            [
                'key' => 'reciprocity_harmonious', 'name' => 'Harmonious Trader',
                'icon' => "\xE2\x9A\x96\xEF\xB8\x8F", 'type' => 'reciprocity', 'threshold' => 25,
                'msg' => 'Complete 25 transactions maintaining a balanced give/receive ratio',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'uncommon',
                'threshold_type' => 'ratio', 'evaluation_method' => 'checkReciprocityBadges',
                'config_json' => json_encode(['min_transactions' => 25, 'min_ratio' => 0.4, 'max_ratio' => 2.5]),
            ],
            [
                'key' => 'reciprocity_exemplar', 'name' => 'Reciprocity Exemplar',
                'icon' => "\xE2\x9A\x96\xEF\xB8\x8F", 'type' => 'reciprocity', 'threshold' => 50,
                'msg' => 'Complete 50 transactions with near-perfect give/receive balance',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'rare',
                'threshold_type' => 'ratio', 'evaluation_method' => 'checkReciprocityBadges',
                'config_json' => json_encode(['min_transactions' => 50, 'min_ratio' => 0.5, 'max_ratio' => 2.0]),
            ],

            // COMMUNITY CHAMPION — rewards sustained multi-category participation over time
            [
                'key' => 'community_champion_1', 'name' => 'Emerging Champion',
                'icon' => "\xF0\x9F\x8F\x85", 'type' => 'community_champion', 'threshold' => 3,
                'msg' => 'Stay active across multiple categories for 3 months',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'uncommon',
                'threshold_type' => 'duration_months', 'evaluation_method' => 'checkCommunityChampionBadges',
                'config_json' => json_encode(['months' => 3, 'min_categories_per_month' => 2, 'min_activity_per_month' => 3]),
            ],
            [
                'key' => 'community_champion_2', 'name' => 'Community Anchor',
                'icon' => "\xE2\x9A\x93", 'type' => 'community_champion', 'threshold' => 6,
                'msg' => 'Stay active across multiple categories for 6 months',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'rare',
                'threshold_type' => 'duration_months', 'evaluation_method' => 'checkCommunityChampionBadges',
                'config_json' => json_encode(['months' => 6, 'min_categories_per_month' => 2, 'min_activity_per_month' => 3]),
            ],
            [
                'key' => 'community_champion_3', 'name' => 'Community Cornerstone',
                'icon' => "\xF0\x9F\x8F\x9B\xEF\xB8\x8F", 'type' => 'community_champion', 'threshold' => 12,
                'msg' => 'Stay active across multiple categories for 12 months',
                'badge_tier' => 'core', 'badge_class' => 'quality', 'rarity' => 'epic',
                'threshold_type' => 'duration_months', 'evaluation_method' => 'checkCommunityChampionBadges',
                'config_json' => json_encode(['months' => 12, 'min_categories_per_month' => 2, 'min_activity_per_month' => 3]),
            ],
        ];

        foreach ($quality as &$badge) {
            $badge['sort_order'] = ++$sortOrder;
        }
        unset($badge);

        return array_merge($existing, $quality);
    }
};
