<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'errors' => [
        'vetting_required' => 'This member requires additional vetting before you can interact with them. Please complete the required checks in your profile to continue.',
        'vetting_check_failed' => 'We could not verify your vetting status just now. Please try again shortly.',
        'statement_required' => 'A Child Safeguarding Statement PDF is required before you can declare that this community works with children or vulnerable adults. Please upload one to continue.',
        'invalid_file' => 'The uploaded file could not be read. Please try again with a valid PDF.',
        'pdf_required' => 'The safeguarding statement must be a PDF file.',
        'file_too_large' => 'The safeguarding statement file is too large. The maximum size is 10MB.',
        'storage_failed' => 'We could not save the uploaded file. Please try again.',
        'statement_missing' => 'No safeguarding statement is on file for this community.',
        'file_missing' => 'The safeguarding statement file could not be found on the server. Please upload it again.',
        'revoke_failed' => 'We could not revoke that preference. It may already have been revoked.',
    ],
    'confirmation' => [
        'title' => 'Your safeguarding preferences have been saved',
        'intro' => 'Thank you for sharing this. Here is a summary of what you chose, who can see it, and what activates as a result.',
        'your_selections' => 'Your selections',
        'no_selections' => 'You did not select any safeguarding options.',
        'who_can_see_heading' => 'Who can see this',
        'who_can_see_body' => 'Only the community coordinators and administrators can see these preferences. Other members cannot. All access is logged.',
        'what_activates_heading' => 'What activates as a result',
        'activation_broker_review' => 'A coordinator will review messages you send and receive.',
        'activation_match_approval' => 'A coordinator will approve matches involving you before they are suggested to the other member.',
        'activation_discovery_hidden' => 'You will be hidden from discovery for members who have not completed the required vetting.',
        'activation_notification' => 'A coordinator has been notified and will be in touch to discuss how we can help.',
        'activation_none' => 'No automatic protections activate from these selections. Your preferences are recorded for coordinator awareness.',
        'revoke_heading' => 'How to change or revoke these at any time',
        'revoke_body' => 'You can review or revoke any of these preferences any time from your profile settings. You do not need to ask an administrator to do this.',
        'revoke_cta' => 'Go to safeguarding settings',
        'continue_cta' => 'Continue',
    ],
    'settings' => [
        'page_title' => 'Safeguarding preferences',
        'intro' => 'Review or revoke the safeguarding preferences you set during onboarding. Your coordinators can see these but other members cannot.',
        'no_preferences' => 'You have no active safeguarding preferences. You can set these at any time from the safeguarding help page.',
        'selected_on' => 'Selected on :date',
        'revoke_button' => 'Revoke',
        'revoke_confirm_title' => 'Revoke this preference?',
        'revoke_confirm_body' => 'This preference will no longer apply to your account. Your coordinators will be notified of the change.',
        'revoke_confirm_yes' => 'Yes, revoke',
        'revoke_confirm_no' => 'Keep it',
        'revoked_toast' => 'Preference revoked.',
        'revoke_error_toast' => 'Something went wrong. Please try again.',
    ],
    'review' => [
        'reminder_subject' => 'Please review your safeguarding preferences',
        'reminder_title' => 'Time to review your safeguarding preferences',
        'reminder_body' => 'It has been over a year since you set your safeguarding preferences for :community. Please take a moment to review them and confirm they still apply, or revoke any that no longer do.',
        'reminder_cta' => 'Review preferences',
        'escalation_subject' => 'Member safeguarding review outstanding',
        'escalation_title' => 'Annual safeguarding review outstanding',
        'escalation_body' => ':name has not responded to a request to review their safeguarding preferences in 30 days. Their preferences remain active — the member has the right to keep them. Please reach out directly if you would like to check in.',
        'escalation_cta' => 'View member in safeguarding dashboard',
    ],
];
