<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'insights' => [
        'title' => 'Activity insights',
        'heading' => 'Activity insights',
        'caption' => 'Your activity',
        'intro' => 'A detailed view of your contributions, skills and recent activity across the community.',
        'back_to_activity' => 'Back to activity',

        // Headline stats
        'stats_title' => 'At a glance',
        'stat_hours_given' => 'Hours given',
        'stat_hours_received' => 'Hours received',
        'stat_connections' => 'Connections',
        'stat_exchanges' => 'Exchanges',

        // Two-column section headings
        'activity_column_title' => 'Activity',
        'sidebar_title' => 'Your stats and skills',

        // Monthly chart (dual bar: given vs received)
        'chart_title' => 'Monthly activity',
        'chart_intro' => 'Hours given and received over the last 12 months.',
        'chart_given' => 'Given',
        'chart_received' => 'Received',
        'chart_aria' => 'Monthly hours given and received over the last 12 months.',
        'chart_month_aria' => ':label: :given hours given, :received hours received.',
        'chart_empty' => 'No monthly activity to show yet.',

        // Recent activity timeline
        'timeline_title' => 'Recent activity',
        'timeline_empty_title' => 'No activity yet',
        'timeline_empty' => 'Once you start giving and receiving help, posting and connecting, your activity will appear here.',

        // Activity type badges (maps MemberActivityService activity_type values)
        'type_post' => 'Post',
        'type_gave_hours' => 'Gave hours',
        'type_received_hours' => 'Received hours',
        'type_comment' => 'Comment',
        'type_connection' => 'Connection',
        'type_event_rsvp' => 'Event',
        'type_listing' => 'Listing',
        'type_message' => 'Message',
        'type_review' => 'Review',
        'type_activity' => 'Activity',

        // Quick stats sidebar card
        'quick_stats_title' => 'Quick stats',
        'quick_groups_joined' => 'Groups joined',
        'quick_posts_30d' => 'Posts (last 30 days)',
        'quick_comments_30d' => 'Comments (last 30 days)',
        'quick_likes_given_30d' => 'Likes given (last 30 days)',
        'quick_likes_received_30d' => 'Likes received (last 30 days)',
        'quick_net_balance' => 'Net balance',
        'net_balance_positive' => '+:value hours',
        'net_balance_negative' => ':value hours',
        'net_balance_positive_meaning' => 'You have received more help than you have given.',
        'net_balance_negative_meaning' => 'You have given more help than you have received.',
        'net_balance_even_meaning' => 'Your hours given and received are balanced.',

        // Skills card
        'skills_title' => 'My skills',
        'skills_empty' => 'You have not added any skills yet.',
        'skill_offering' => 'Offering',
        'skill_requesting' => 'Requesting',
        'skill_endorsements' => 'Endorsed :count times',
        'skill_endorsements_short' => ':count endorsements',
        'skills_summary' => ':offering offered, :requesting requested.',
    ],
];
