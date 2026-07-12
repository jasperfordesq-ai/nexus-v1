<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Negotiated Events read contract
    |--------------------------------------------------------------------------
    |
    | Existing clients remain on the legacy projection unless they explicitly
    | send X-Events-Contract: 2. Version 2 is additive at the endpoint level but
    | intentionally changes conflicting fields such as `location` from a string
    | to a structured object, so it must never be selected implicitly during the
    | compatibility window.
    |
    */

    'contract' => [
        'legacy_version' => 1,
        'canonical_version' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Online meeting access window
    |--------------------------------------------------------------------------
    |
    | Organisers and tenant administrators can always manage configured links.
    | Confirmed attendees receive them only inside this bounded window. Both
    | negotiated projections use the same fail-closed policy.
    |
    */

    'online_access' => [
        'reveal_before_minutes' => (int) env('EVENTS_ONLINE_REVEAL_BEFORE_MINUTES', 30),
        'grace_after_minutes' => (int) env('EVENTS_ONLINE_GRACE_AFTER_MINUTES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event attendance credits
    |--------------------------------------------------------------------------
    |
    | Automatic event-attendance credit transfers are deliberately disabled.
    | The historical implementation charged whichever organiser or tenant
    | administrator performed check-in and used mutable RSVP state as its
    | idempotency claim. No value other than "off" is currently supported;
    | unknown values fail closed until the immutable claim-ledger flow exists.
    |
    */

    'attendance_credit_mode' => env('EVENTS_ATTENDANCE_CREDIT_MODE', 'off'),

    'attendance' => [
        'opens_before_minutes' => (int) env('EVENTS_ATTENDANCE_OPENS_BEFORE_MINUTES', 30),
        'closes_after_hours' => (int) env('EVENTS_ATTENDANCE_CLOSES_AFTER_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical registration and timed waitlist offers
    |--------------------------------------------------------------------------
    |
    | Capacity registration is independent of engagement (`interested`). The
    | canonical services dual-read/write the legacy tables during rollout, but
    | timed offers remain disabled until notification and client acceptance
    | flows are integrated. Allocation keys are reserved for future segmented
    | pools and therefore fail closed today.
    |
    */

    'registration' => [
        'default_capacity_pool_key' => env('EVENTS_DEFAULT_CAPACITY_POOL_KEY', 'event'),
        'allow_allocation_keys' => env('EVENTS_REGISTRATION_ALLOW_ALLOCATION_KEYS', false),
        'legacy_dual_read' => env('EVENTS_REGISTRATION_LEGACY_DUAL_READ', true),
        'legacy_dual_write' => env('EVENTS_REGISTRATION_LEGACY_DUAL_WRITE', true),
        'timed_waitlist_offers_enabled' => env('EVENTS_TIMED_WAITLIST_OFFERS_ENABLED', false),
        'offer_ttl_minutes' => (int) env('EVENTS_WAITLIST_OFFER_TTL_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event safety enforcement rollout
    |--------------------------------------------------------------------------
    |
    | off keeps the accepted safety evidence model available for configuration
    | and attendee actions without changing participation. shadow evaluates and
    | records privacy-safe decision telemetry. enforce makes every canonical and
    | compatibility participation writer fail closed on deny/unavailable. A
    | malformed global or tenant value is never treated as off implicitly.
    |
    */

    'safety' => [
        'enforcement_mode' => env('EVENTS_SAFETY_ENFORCEMENT_MODE', 'off'),
        'guardian_consent_ttl_days' => (int) env('EVENTS_GUARDIAN_CONSENT_TTL_DAYS', 30),
        'max_review_page_size' => (int) env('EVENTS_SAFETY_MAX_REVIEW_PAGE_SIZE', 50),
        'guardian_delivery_envelope' => [
            'active_key_version' => env(
                'EVENTS_GUARDIAN_DELIVERY_KEY_VERSION',
                'app-key-v1',
            ),
            'active_key' => env('EVENTS_GUARDIAN_DELIVERY_KEY'),
            'fallback_to_app_key' => env(
                'EVENTS_GUARDIAN_DELIVERY_FALLBACK_TO_APP_KEY',
                true,
            ),
            'previous_keys' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurrence engine rollout
    |--------------------------------------------------------------------------
    |
    | Version 2 uses a standards-based RRULE adapter and deterministic concrete
    | occurrence identities. It remains opt-in while existing clients continue
    | to depend on the legacy recurrence materialiser. Expansion is deliberately
    | bounded even for an RRULE without COUNT or UNTIL.
    |
    */

    'recurrence' => [
        'engine_v2_enabled' => env('EVENTS_RECURRENCE_V2_ENABLED', false),
        'max_occurrences' => (int) env('EVENTS_RECURRENCE_MAX_OCCURRENCES', 366),
        'max_horizon_years' => (int) env('EVENTS_RECURRENCE_MAX_HORIZON_YEARS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendar projections and subscription feeds
    |--------------------------------------------------------------------------
    |
    | Calendar feeds export only the canonical privacy-safe projection. Feed
    | secrets are generated per member and stored as hashes; these settings
    | bound query horizons and the number of simultaneously active secrets.
    |
    */

    'calendar' => [
        'max_range_days' => (int) env('EVENTS_CALENDAR_MAX_RANGE_DAYS', 366),
        'tenant_feed_past_days' => (int) env('EVENTS_CALENDAR_TENANT_PAST_DAYS', 30),
        'tenant_feed_future_days' => (int) env('EVENTS_CALENDAR_TENANT_FUTURE_DAYS', 366),
        'personal_feed_past_days' => (int) env('EVENTS_CALENDAR_PERSONAL_PAST_DAYS', 365),
        'personal_feed_future_days' => (int) env('EVENTS_CALENDAR_PERSONAL_FUTURE_DAYS', 730),
        'max_active_feed_tokens' => (int) env('EVENTS_CALENDAR_MAX_ACTIVE_TOKENS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Versioned reminder schedule rollout
    |--------------------------------------------------------------------------
    |
    | Canonical is the fresh-install default and owns durable rule, schedule,
    | and outbox delivery. Legacy and shadow remain explicit rollback/comparison
    | overrides for existing installations. Unknown modes must be rejected by
    | the orchestration layer rather than guessed here.
    |
    */

    'reminders' => [
        'mode' => env('EVENTS_REMINDER_MODE', 'canonical'),
        'default_enabled' => env('EVENTS_REMINDER_DEFAULT_ENABLED', true),
        'default_cadence' => env('EVENTS_REMINDER_DEFAULT_CADENCE', 'off'),
        'default_channels' => [
            'email' => true,
            'in_app' => true,
            'web_push' => true,
            'fcm' => true,
            'realtime' => true,
        ],
        'default_offsets_minutes' => array_values(array_filter(
            array_map(
                'intval',
                explode(',', (string) env('EVENTS_REMINDER_DEFAULT_OFFSETS_MINUTES', '1440,60')),
            ),
            static fn (int $minutes): bool => $minutes > 0,
        )),
        'minimum_offset_minutes' => (int) env('EVENTS_REMINDER_MINIMUM_OFFSET_MINUTES', 5),
        'maximum_offset_minutes' => (int) env('EVENTS_REMINDER_MAXIMUM_OFFSET_MINUTES', 525600),
        'max_rules_per_event' => (int) env('EVENTS_REMINDER_MAX_RULES_PER_EVENT', 10),
        'catch_up_horizon_minutes' => (int) env('EVENTS_REMINDER_CATCH_UP_HORIZON_MINUTES', 1440),
        'batch_size' => (int) env('EVENTS_REMINDER_BATCH_SIZE', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy-governed Event analytics
    |--------------------------------------------------------------------------
    |
    | Operational totals are derived from canonical domain ledgers. This flag
    | controls only optional view/registration-start funnel facts, which also
    | require a current per-user analytics consent record. It remains disabled
    | until the client capture and privacy-withdrawal hooks are integrated.
    |
    */

    'analytics' => [
        'optional_capture_enabled' => env('EVENTS_ANALYTICS_OPTIONAL_CAPTURE_ENABLED', false),
        'retention_days' => (int) env('EVENTS_ANALYTICS_RETENTION_DAYS', 365),
        'late_after_hours' => (int) env('EVENTS_ANALYTICS_LATE_AFTER_HOURS', 24),
        'max_late_days' => (int) env('EVENTS_ANALYTICS_MAX_LATE_DAYS', 30),
        'max_future_minutes' => (int) env('EVENTS_ANALYTICS_MAX_FUTURE_MINUTES', 5),
        'privacy_threshold' => (int) env('EVENTS_ANALYTICS_PRIVACY_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event notification cutover
    |--------------------------------------------------------------------------
    |
    | Outbox-authoritative is the fresh-install default. Direct keeps the
    | established delivery path authoritative and shadow_outbox records
    | non-claimable comparison rows when an existing installation explicitly
    | chooses either override. The consumer can still be disabled separately
    | as an emergency operational stop.
    |
    */

    'notification_delivery' => [
        'mode' => env('EVENTS_NOTIFICATION_DELIVERY_MODE', 'outbox_authoritative'),
        'consumer_enabled' => env('EVENTS_NOTIFICATION_OUTBOX_CONSUMER_ENABLED', true),
        'batch_size' => (int) env('EVENTS_NOTIFICATION_OUTBOX_BATCH_SIZE', 50),
        'channels' => ['email', 'in_app', 'push'],
        'max_attempts' => (int) env('EVENTS_NOTIFICATION_OUTBOX_MAX_ATTEMPTS', 5),
        'base_retry_seconds' => (int) env('EVENTS_NOTIFICATION_OUTBOX_BASE_RETRY_SECONDS', 60),
        'max_retry_seconds' => (int) env('EVENTS_NOTIFICATION_OUTBOX_MAX_RETRY_SECONDS', 3600),
        'stale_claim_minutes' => (int) env('EVENTS_NOTIFICATION_OUTBOX_STALE_CLAIM_MINUTES', 10),
    ],

];
