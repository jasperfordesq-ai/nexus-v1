<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'privacy' => [
        'title' => 'Private and resilient check-in',
        'body' => 'Signed attendee codes contain no name, email address or phone number. You can type a code below without using a camera.',
        'no_wallet' => 'Attendance actions never change balances or award time credits.',
    ],
    'code' => [
        'title' => 'Enter a signed attendee code',
        'intro' => 'Use this online fallback when a camera or offline staff device is unavailable. Manual name search remains available further down the page.',
        'label' => 'Attendee code',
        'hint' => 'Paste the complete code beginning with nqx2_. The code is not stored in the audit log.',
        'action' => 'Attendance action',
        'reason' => 'Correction reason',
        'reason_hint' => 'A reason is required when undoing an action. Do not include sensitive information.',
        'confirm' => 'I have checked the attendee and selected the intended action.',
        'submit' => 'Apply signed-code action',
    ],
    'actions' => [
        'check_in' => 'Check in',
        'check_out' => 'Check out',
        'no_show' => 'Mark as no-show',
        'undo' => 'Undo the latest action',
    ],
    'attendee' => [
        'manage_link' => 'Manage my check-in code',
        'title' => 'Your event check-in code',
        'intro' => 'Create a signed code to show event staff on screen or as a printed copy.',
        'privacy' => 'The code identifies only this event registration. It contains no name, email address or phone number.',
        'notice_issued' => 'Your new check-in code is shown below.',
        'notice_replaced' => 'Your previous code no longer works. The replacement is shown below.',
        'notice_revoked' => 'Your check-in code was revoked.',
        'notice_already_active' => 'An active code already exists. Replace it if your copy is unavailable.',
        'notice_invalid' => 'Confirm the requested action and try again.',
        'notice_failed' => 'The check-in code could not be changed. Refresh and try again.',
        'status_heading' => 'Code status',
        'status_active' => 'Active',
        'status_rotated' => 'Replaced',
        'status_revoked' => 'Revoked',
        'status_expired' => 'Expired',
        'expires' => 'Expires :date',
        'one_shot_heading' => 'Save this code now',
        'one_shot' => 'For security, the complete code is shown only when it is created or replaced.',
        'code_label' => 'Signed check-in code',
        'code_hint' => 'Select and copy the complete code beginning with nqx2_.',
        'print_hint' => 'You can print this page or save an accessible copy. Keep the code private until check-in.',
        'print' => 'Print this code',
        'issue_confirm' => 'I understand that the complete code will be shown once.',
        'issue' => 'Create check-in code',
        'replace' => 'Replace copied or lost code',
        'replace_hint' => 'Replacing the code immediately invalidates every saved or printed copy.',
        'replace_confirm' => 'I understand that my current code will stop working.',
        'revoke' => 'Revoke code',
        'reason' => 'Reason for revocation',
        'reason_hint' => 'Record a short operational reason without sensitive information.',
        'revoke_confirm' => 'I understand that this code will stop working immediately.',
    ],
    'device' => [
        'lost' => 'If a staff device is lost, revoke it immediately in the standard events workspace. Continue here with manual name or signed-code check-in.',
    ],
];
