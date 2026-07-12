<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    'title' => 'Lifecycle history',
    'description' => 'An immutable record of publication and operational changes for this event.',
    'link' => 'Lifecycle history',
    'back_to_event' => 'Back to event',
    'immutable_explanation' => 'This audit history is append-only. Existing entries cannot be changed or deleted.',
    'empty_title' => 'No lifecycle changes yet',
    'empty_description' => 'Changes will appear here after the event lifecycle is updated.',
    'list_label' => 'Event lifecycle changes',
    'version' => 'Version :version',
    'immutable' => 'Immutable',
    'recorded_at' => 'Recorded at',
    'timestamp_unknown' => 'Time not recorded',
    'publication_label' => 'Publication',
    'operational_label' => 'Operational status',
    'transition' => ':from to :to',
    'actor_label' => 'Changed by',
    'unknown_actor' => 'Member :id',
    'reason_label' => 'Reason',
    'evidence_title' => 'Operational evidence',
    'notifications_suppressed' => 'Duplicate notifications were suppressed for this series change.',
    'load_more' => 'View older history',
    'pagination_label' => 'Lifecycle history pages',
    'states' => [
        'publication' => [
            'draft' => 'Draft',
            'pending_review' => 'Pending review',
            'published' => 'Published',
            'archived' => 'Archived',
        ],
        'operational' => [
            'scheduled' => 'Scheduled',
            'postponed' => 'Postponed',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
        ],
    ],
    'cascade' => [
        'reminders_cancelled' => 'Reminder schedules cancelled: :count',
        'waitlist_cancelled' => 'Waitlist entries cancelled: :count',
        'registrations_cancelled' => 'Registrations cancelled: :count',
    ],
    'series' => [
        'template' => 'Recurring template :id',
        'occurrence' => 'Occurrence of recurring template :id',
    ],
];
