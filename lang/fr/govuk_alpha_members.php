<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'nav' => [
        'reputation' => 'Reputation and recognition',
    ],

    'insights' => [
        // Page chrome
        'title' => 'Reputation and recognition - :name',
        'heading' => 'Reputation and recognition',
        'unknown_member' => 'this member',
        'back_to_profile' => 'Back to profile',
        'intro_own' => 'A summary of your standing in the community: your NEXUS score, activity totals, verifications and earned badges.',
        'intro_other' => 'A summary of :name&rsquo;s standing in the community: NEXUS score, activity totals, verifications and earned badges.',

        // NEXUS score
        'nexus_score_title' => 'NEXUS score',
        'nexus_score_out_of' => 'out of 1,000',
        'nexus_percentile' => 'Top :percentile% of members in this community.',
        'nexus_percentile_aria' => 'Ranked in the top :percentile per cent of members',
        'nexus_own_hint' => 'Your NEXUS score reflects your engagement, contribution quality and impact across the community.',
        'nexus_empty' => 'No NEXUS score has been calculated yet. Take part in the community to build one.',

        // NEXUS tiers (match NexusScoreService thresholds)
        'tier_novice' => 'Novice',
        'tier_beginner' => 'Beginner',
        'tier_developing' => 'Developing',
        'tier_intermediate' => 'Intermediate',
        'tier_proficient' => 'Proficient',
        'tier_advanced' => 'Advanced',
        'tier_expert' => 'Expert',
        'tier_elite' => 'Elite',
        'tier_legendary' => 'Legendary',

        // Stats grid
        'stats_title' => 'Activity',
        'stat_hours_given' => 'Hours given',
        'stat_hours_received' => 'Hours received',
        'stat_listings' => 'Active listings',
        'stat_groups' => 'Groups joined',
        'stat_events' => 'Events attended',
        'stat_connections' => 'Connections',
        'stat_reviews' => 'Reviews received',
        'stat_rating' => 'Average rating',
        'stat_level' => 'Level',
        'stat_xp' => 'Experience points',

        // Verification badges
        'verification_title' => 'Verifications',
        'verification_intro' => 'Checks completed for this member.',
        'verification_empty' => 'No verifications have been recorded yet.',
        'verified_on' => 'Verified on :date',
        'verification_type_email_verified' => 'Email verified',
        'verification_type_phone_verified' => 'Phone verified',
        'verification_type_id_verified' => 'ID verified',
        'verification_type_address_verified' => 'Address verified',
        'verification_type_admin_verified' => 'Admin verified',
        'verification_type_background_check' => 'Background check verified',
        'verification_type_organization_vouched' => 'Organisation vouched',
        'verification_type_peer_endorsed' => 'Peer endorsed',

        // Earned badges
        'badges_title' => 'Earned badges',
        'badges_empty' => 'No badges earned yet.',
    ],
];
