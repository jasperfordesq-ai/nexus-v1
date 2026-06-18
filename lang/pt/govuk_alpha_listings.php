<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    // ---- Navigation links (added by nav-consolidation) ----------------
    'nav' => [
        'view_analytics' => 'View listing analytics',
    ],
    // Gap #12 — owner-only listing analytics panel.
    'analytics' => [
        'title' => 'Listing analytics',
        'caption' => 'Insights',
        'description' => 'See how members are finding and responding to this listing.',
        'back_to_listing' => 'Back to listing',
        'view_listing' => 'View listing',
        'edit_listing' => 'Edit listing',
        'owner_only' => 'Only the listing owner can view these analytics.',
        'period_legend' => 'Time period',
        'period_hint' => 'Choose how many recent days to include.',
        'period_days' => 'Last :count days',
        'period_submit' => 'Update period',
        'no_data' => 'There is no analytics data for this listing yet. Check back once members start viewing it.',

        // Key metrics
        'key_metrics_heading' => 'Key metrics',
        'total_views' => 'Total views',
        'unique_viewers' => 'Unique viewers',
        'total_contacts' => 'Total contacts',
        'total_saves' => 'Times saved',
        'contact_rate' => 'Contact rate',
        'save_rate' => 'Save rate',
        'views_trend' => '7-day views trend',
        'trend_up' => 'Up :percent% on the previous 7 days',
        'trend_down' => 'Down :percent% on the previous 7 days',
        'trend_flat' => 'No change on the previous 7 days',

        // Views over time
        'views_over_time' => 'Views over time',
        'no_views_yet' => 'No views recorded in this period yet.',
        'views_on_date' => 'Views on :date',
        'count_column' => 'Count',

        // Contacts over time
        'contacts_over_time' => 'Contacts over time',
        'no_contacts_yet' => 'No contacts recorded in this period yet.',
        'contacts_on_date' => 'Contacts on :date',

        // Contact types breakdown
        'contact_types_heading' => 'How members got in touch',
        'no_contact_types' => 'No contacts have been made yet.',
        'contact_type_label' => 'Method',
        'contact_type_message' => 'Message',
        'contact_type_phone' => 'Phone',
        'contact_type_email' => 'Email',
        'contact_type_exchange_request' => 'Exchange request',

        // Dates
        'listing_created' => 'Listing created',
        'listing_expires' => 'Listing expires',
    ],
];
