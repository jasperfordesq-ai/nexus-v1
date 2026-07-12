<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'capacity_label' => 'Session capacity',
    'capacity_hint' => 'Leave blank for no limit. Session places do not change event registration or tickets.',
    'capacity_unlimited' => ':registered registered · no limit',
    'capacity_limited' => ':registered of :limit registered',
    'resources_title' => 'Session resources',
    'resources_hint' => 'Add HTTPS links in display order. Streams and recordings must be limited to registered attendees or staff.',
    'resource_number' => 'Resource :number',
    'resource_type' => 'Resource type',
    'resource_visibility' => 'Who can access it',
    'resource_title' => 'Resource title',
    'resource_url' => 'Secure HTTPS URL',
    'resource_url_hint' => 'Use a full address beginning with https://.',
    'resource_types' => ['link' => 'Link', 'document' => 'Document', 'slides' => 'Slides', 'download' => 'Download', 'stream' => 'Live stream', 'recording' => 'Recording'],
    'opens_new_window' => 'Opens in a new window',
    'resource_unavailable' => 'Link unavailable',
    'registered_success' => 'You are registered for the session.',
    'withdrawn_success' => 'You have withdrawn from the session.',
    'register_action' => 'Register for session',
    'withdraw_action' => 'Withdraw from session',
    'registered_state' => 'Registered for this session',
    'ineligible_state' => 'Your event registration is no longer eligible for this session.',
    'full_state' => 'This session is full.',
    'session_full_error' => 'This session has no places left.',
    'eligibility_error' => 'Confirm your event registration before registering for a session.',
];
