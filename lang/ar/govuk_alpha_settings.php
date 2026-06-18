<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'common' => [
        'back_to_settings' => 'Back to settings',
        'success_title' => 'Success',
        'error_title' => 'There is a problem',
        'save' => 'Save',
        'unknown_member' => 'Unknown member',
    ],

    'linked' => [
        'title' => 'Linked accounts',
        'caption' => 'Account settings',
        'description' => 'Link a family member, dependant or someone you care for to your account so you can help manage their activity. Linked members must approve the link before it becomes active.',
        'children_heading' => 'Accounts you manage',
        'children_description' => 'People you have linked to your account. You can change what each linked account can do, or remove the link.',
        'children_empty' => 'You do not manage any linked accounts yet.',
        'parents_heading' => 'Accounts that manage you',
        'parents_description' => 'People who have asked to help manage your account. Approve a request to let them act on your behalf, or remove the link.',
        'parents_empty' => 'No one has asked to manage your account.',
        'status_active' => 'Active',
        'status_pending' => 'Awaiting approval',
        'type_label' => 'Relationship',
        'types' => [
            'family' => 'Family member',
            'guardian' => 'Guardian',
            'carer' => 'Carer',
            'organization' => 'Organisation',
        ],
        'permissions_heading' => 'What this account can do',
        'permissions' => [
            'can_view_activity' => 'View their activity',
            'can_manage_listings' => 'Manage their listings',
            'can_transact' => 'Send and receive time credits',
            'can_view_messages' => 'View their messages',
        ],
        'permissions_none' => 'No permissions granted yet.',
        'save_permissions' => 'Save permissions',
        'approve_button' => 'Approve link',
        'revoke_button' => 'Remove link',
        'revoke_warning' => 'Removing a link cannot be undone. The other account will no longer be linked to yours.',
        'request_heading' => 'Link a new account',
        'request_description' => 'Enter the email address of the member you want to link. They must already have an account in this community.',
        'request_max' => 'You can manage up to :count linked accounts.',
        'email_label' => 'Email address',
        'email_hint' => 'For example, name@example.com',
        'request_button' => 'Send link request',
    ],

    'appearance' => [
        'title' => 'Appearance',
        'caption' => 'Account settings',
        'description' => 'Choose how this service looks for you. Your choice is saved to your account and applied wherever you sign in.',
        'theme_legend' => 'Theme',
        'themes' => [
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'Match my device',
        ],
        'theme_hints' => [
            'light' => 'Dark text on a light background.',
            'dark' => 'Light text on a dark background.',
            'system' => 'Follow the light or dark setting on your device.',
        ],
        'save' => 'Save appearance',
    ],

    'states' => [
        'link-requested' => 'Your link request has been sent. The other member must approve it.',
        'link-approved' => 'You have approved the link.',
        'link-revoked' => 'The link has been removed.',
        'link-permissions-saved' => 'Permissions updated.',
        'link-email-invalid' => 'Enter a valid email address.',
        'link-user-not-found' => 'We could not find a member with that email address in this community.',
        'link-self' => 'You cannot link your own account to itself.',
        'link-exists' => 'A link with this member already exists.',
        'link-max' => 'You have reached the maximum number of linked accounts.',
        'link-failed' => 'Sorry, we could not complete that request. Please try again.',
        'appearance-saved' => 'Your appearance settings have been saved.',
        'appearance-invalid' => 'Choose one of the available themes.',
        'appearance-failed' => 'Sorry, we could not save your appearance settings. Please try again.',
    ],
];
