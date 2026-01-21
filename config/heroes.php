<?php
/**
 * CivicOne Hero Configuration
 * Maps routes to hero settings following Section 9C of CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md
 *
 * Version: 1.0.0
 * Last Updated: 2026-01-21
 */

return [
    // ==========================================
    // Homepage / Feed
    // ==========================================
    '/' => [
        'variant' => 'banner',
        'title' => 'Welcome to Your Community',
        'lead' => 'Connect, collaborate, and make a difference in your local area.',
        'cta' => [
            'text' => 'Get started',
            'url' => '/join',
        ],
    ],
    '/feed' => [
        'variant' => 'page',
        'title' => 'Community Pulse',
        'lead' => 'Stay connected with your community\'s latest updates and activities.',
    ],

    // ==========================================
    // Members
    // ==========================================
    '/members' => [
        'variant' => 'page',
        'title' => 'Members Directory',
        'lead' => 'Connect with community members and discover their skills and interests.',
    ],
    '/members/show' => [
        'variant' => 'page',
        // Title will be set dynamically by controller: $member['name']
        // Lead will be set dynamically: $member['headline'] or null
    ],

    // ==========================================
    // Groups
    // ==========================================
    '/groups' => [
        'variant' => 'page',
        'title' => 'Groups Directory',
        'lead' => 'Join groups and connect with people who share your interests.',
    ],
    '/groups/show' => [
        'variant' => 'page',
        // Title will be set dynamically: $group['name']
        // Lead will be set dynamically: $group['description_short'] or null
    ],
    '/groups/create' => [
        'variant' => 'page',
        'title' => 'Create a Group',
        'lead' => 'Start a new community group and bring people together.',
    ],

    // ==========================================
    // Volunteering
    // ==========================================
    '/volunteering' => [
        'variant' => 'page',
        'title' => 'Volunteering Opportunities',
        'lead' => 'Find volunteering opportunities and make a difference in your community.',
    ],
    '/volunteering/show' => [
        'variant' => 'page',
        // Title will be set dynamically: $opportunity['title']
    ],
    '/volunteering/create' => [
        'variant' => 'page',
        'title' => 'Post a Volunteering Opportunity',
        'lead' => 'Share a volunteering opportunity with the community.',
    ],

    // ==========================================
    // Listings (Offers/Requests)
    // ==========================================
    '/listings' => [
        'variant' => 'page',
        'title' => 'Listings',
        'lead' => 'Browse offers and requests from community members.',
    ],
    '/listings/show' => [
        'variant' => 'page',
        // Title will be set dynamically: $listing['title']
    ],
    '/listings/create' => [
        'variant' => 'page',
        'title' => 'Create a Listing',
        'lead' => 'Share an offer or make a request with the community.',
    ],
    '/listings/edit' => [
        'variant' => 'page',
        'title' => 'Edit Listing',
    ],

    // ==========================================
    // Events
    // ==========================================
    '/events' => [
        'variant' => 'page',
        'title' => 'Events',
        'lead' => 'Discover upcoming events and activities in your community.',
    ],
    '/events/show' => [
        'variant' => 'page',
        // Title will be set dynamically: $event['title']
    ],
    '/events/create' => [
        'variant' => 'page',
        'title' => 'Create an Event',
        'lead' => 'Share an upcoming event with the community.',
    ],

    // ==========================================
    // Dashboard / Account
    // ==========================================
    '/dashboard' => [
        'variant' => 'page',
        // Title will be set dynamically: "Welcome, {$user['name']}"
        'lead' => 'Your personal dashboard and activity summary.',
    ],

    // ==========================================
    // Profile
    // ==========================================
    '/profile/show' => [
        'variant' => 'page',
        // Title will be set dynamically: $user['name']
    ],
    '/profile/edit' => [
        'variant' => 'page',
        'title' => 'Edit Profile',
        'lead' => 'Update your profile information and settings.',
    ],
    '/settings' => [
        'variant' => 'page',
        'title' => 'Account Settings',
        'lead' => 'Manage your account preferences and privacy settings.',
    ],

    // ==========================================
    // Messages
    // ==========================================
    '/messages' => [
        'variant' => 'page',
        'title' => 'Messages',
        'lead' => 'View and manage your private messages.',
    ],

    // ==========================================
    // Notifications
    // ==========================================
    '/notifications' => [
        'variant' => 'page',
        'title' => 'Notifications',
        'lead' => 'Stay up to date with your community activity.',
    ],

    // ==========================================
    // Wallet
    // ==========================================
    '/wallet' => [
        'variant' => 'page',
        'title' => 'Wallet',
        'lead' => 'View your points balance and transaction history.',
    ],

    // ==========================================
    // Federation (Partner Communities)
    // ==========================================
    '/federation' => [
        'variant' => 'banner',
        'title' => 'Partner Communities',
        'lead' => 'Discover and connect with members from partner communities.',
        'cta' => [
            'text' => 'Explore communities',
            'url' => '/federation/members',
        ],
    ],
    '/federation/members' => [
        'variant' => 'page',
        'title' => 'Federated Members',
        'lead' => 'Browse members from partner communities.',
    ],
    '/federation/listings' => [
        'variant' => 'page',
        'title' => 'Federated Listings',
        'lead' => 'Browse offers and requests from partner communities.',
    ],
    '/federation/events' => [
        'variant' => 'page',
        'title' => 'Federated Events',
        'lead' => 'Discover events happening across partner communities.',
    ],
    '/federation/groups' => [
        'variant' => 'page',
        'title' => 'Federated Groups',
        'lead' => 'Explore groups from partner communities.',
    ],
    '/federation/messages' => [
        'variant' => 'page',
        'title' => 'Federation Messages',
        'lead' => 'Messages with members from partner communities.',
    ],
    '/federation/transactions' => [
        'variant' => 'page',
        'title' => 'Federation Transactions',
        'lead' => 'View your cross-community transaction history.',
    ],

    // ==========================================
    // Help / Support
    // ==========================================
    '/help' => [
        'variant' => 'page',
        'title' => 'Help Center',
        'lead' => 'Find answers to common questions and get support.',
    ],

    // ==========================================
    // Legal Pages
    // ==========================================
    '/privacy' => [
        'variant' => 'page',
        'title' => 'Privacy Policy',
    ],
    '/terms' => [
        'variant' => 'page',
        'title' => 'Terms of Service',
    ],
    '/accessibility' => [
        'variant' => 'page',
        'title' => 'Accessibility Statement',
    ],

    // ==========================================
    // Fallback (Default)
    // ==========================================
    '_default' => [
        'variant' => 'page',
        // Title will be set by controller or derived from page title variable
        // No lead paragraph by default
    ],
];
