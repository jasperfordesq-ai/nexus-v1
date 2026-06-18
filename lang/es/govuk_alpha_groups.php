<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'common' => [
        'back_to_group' => 'Back to group',
        'error_title' => 'There is a problem',
        'success_title' => 'Success',
        'warning' => 'Warning',
    ],

    // ---- Invite members -------------------------------------------------
    'invite' => [
        'title' => 'Invite members',
        'caption' => 'Invite people to :group',
        'intro' => 'Invite people to join this group by sharing a link or sending email invitations.',
        'link_heading' => 'Share an invite link',
        'link_description' => 'Generate a link anyone can use to join this group. The link expires automatically.',
        'expiry_label' => 'Link expiry in days (optional)',
        'expiry_hint' => 'Leave blank for the default. Choose a number between 1 and 90.',
        'generate_button' => 'Generate invite link',
        'generated_heading' => 'Your invite link',
        'generated_hint' => 'Copy this link and share it. It is also listed under pending invites below.',
        'email_heading' => 'Invite by email',
        'email_description' => 'Send invitations to one or more email addresses.',
        'emails_label' => 'Email addresses',
        'emails_hint' => 'Enter one or more email addresses, separated by commas or new lines. Maximum 50 per request.',
        'message_label' => 'Personal message (optional)',
        'message_hint' => 'This message is included in the invitation email.',
        'send_button' => 'Send invitations',
        'pending_heading' => 'Pending invitations',
        'pending_empty' => 'There are no pending invitations.',
        'pending_type_link' => 'Invite link',
        'pending_type_email' => 'Email invite',
        'pending_email' => 'Email',
        'pending_invited_by' => 'Invited by',
        'pending_expires' => 'Expires',
        'pending_sent' => 'Sent',
        'revoke_button' => 'Revoke',
        'revoke_aria' => 'Revoke this invitation',
    ],

    // ---- Notification preferences --------------------------------------
    'notifications' => [
        'title' => 'Notification preferences',
        'caption' => 'Notifications for :group',
        'intro' => 'Choose how often you hear about activity in this group and which channels are used.',
        'frequency_legend' => 'How often do you want to be notified?',
        'frequency_instant' => 'Instant — notify me as activity happens',
        'frequency_digest' => 'Digest — group activity into a summary',
        'frequency_muted' => 'Muted — do not notify me about this group',
        'channels_legend' => 'Notification channels',
        'channels_hint' => 'Channels apply when notifications are not muted.',
        'email_label' => 'Email notifications',
        'push_label' => 'Push notifications',
        'save_button' => 'Save preferences',
    ],

    // ---- Avatar + cover images -----------------------------------------
    'image' => [
        'title' => 'Group images',
        'caption' => 'Images for :group',
        'intro' => 'Upload a group avatar and a cover image. Each upload replaces the current image.',
        'avatar_heading' => 'Group avatar',
        'avatar_description' => 'A small square image shown next to the group name.',
        'avatar_current_alt' => 'Current group avatar',
        'avatar_none' => 'No avatar has been set.',
        'avatar_label' => 'Upload a new avatar',
        'avatar_hint' => 'Use a JPG, PNG, GIF or WEBP image.',
        'avatar_submit' => 'Save avatar',
        'cover_heading' => 'Cover image',
        'cover_description' => 'A wide banner image shown at the top of the group page.',
        'cover_current_alt' => 'Current group cover image',
        'cover_none' => 'No cover image has been set.',
        'cover_label' => 'Upload a new cover image',
        'cover_hint' => 'Use a JPG, PNG, GIF or WEBP image.',
        'cover_submit' => 'Save cover image',
    ],

    // ---- Status banners (flash messages) -------------------------------
    'states' => [
        'invite-link-created' => 'A new invite link was generated.',
        'invite-link-failed' => 'The invite link could not be generated. Please try again.',
        'invite-emails-sent' => 'The invitations have been sent.',
        'invite-emails-required' => 'Enter at least one email address.',
        'invite-emails-too-many' => 'You can invite up to 50 email addresses at a time.',
        'invite-email-failed' => 'The invitations could not be sent. Please try again.',
        'invite-revoked' => 'The invitation has been revoked.',
        'invite-revoke-failed' => 'The invitation could not be revoked.',
        'invite-forbidden' => 'You do not have permission to invite members to this group.',
        'prefs-saved' => 'Your notification preferences have been saved.',
        'prefs-failed' => 'Your notification preferences could not be saved. Please try again.',
        'avatar-updated' => 'The group avatar has been updated.',
        'cover-updated' => 'The cover image has been updated.',
        'image-missing' => 'Choose an image to upload.',
        'image-failed' => 'The image could not be uploaded. Please try again.',
    ],
];
