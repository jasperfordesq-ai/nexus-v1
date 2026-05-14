<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Prerender engine configuration.
 *
 * `ttl` maps route patterns to maximum snapshot age in seconds. The
 * auto-recache cron enqueues a low-priority re-render for any snapshot whose
 * age exceeds the TTL for its matched pattern. Most specific pattern wins.
 *
 * Pattern semantics:
 *   `/blog/*`   — direct children of /blog (e.g. /blog/foo, NOT /blog/foo/bar)
 *   `/blog/**`  — every descendant of /blog
 *   `/`         — homepage only (exact match)
 *   `default`   — fallback for routes not matched by anything else
 */
return [
    'ttl' => [
        // Homepage refreshes often — anything below it can change.
        '/'             => 6 * 3600,

        // Index pages bounce when their underlying collections change.
        '/blog'         => 12 * 3600,
        '/listings'     => 6 * 3600,
        '/events'       => 6 * 3600,
        '/jobs'         => 6 * 3600,
        '/volunteering' => 12 * 3600,
        '/marketplace'  => 6 * 3600,
        '/groups'       => 24 * 3600,
        '/resources'    => 7 * 24 * 3600,
        '/organisations'=> 7 * 24 * 3600,
        '/ideation'     => 24 * 3600,
        '/kb'           => 24 * 3600,

        // Individual content items — refresh weekly unless a content-change
        // hook explicitly invalidates them sooner.
        '/blog/**'      => 7 * 24 * 3600,
        '/listings/*'   => 24 * 3600,
        '/events/*'     => 24 * 3600,
        '/jobs/*'       => 24 * 3600,
        '/marketplace/*'=> 24 * 3600,
        '/marketplace/category/*' => 7 * 24 * 3600,
        '/groups/*'     => 7 * 24 * 3600,
        '/resources/*'  => 14 * 24 * 3600,
        '/organisations/*' => 14 * 24 * 3600,
        '/kb/*'         => 7 * 24 * 3600,
        '/page/*'       => 7 * 24 * 3600,
        '/volunteering/opportunities/*' => 24 * 3600,
        '/ideation/*'   => 7 * 24 * 3600,

        // Legal / static pages — rarely change, refresh monthly.
        '/about'        => 30 * 24 * 3600,
        '/help'         => 30 * 24 * 3600,
        '/contact'      => 30 * 24 * 3600,
        '/faq'          => 30 * 24 * 3600,
        '/terms'        => 30 * 24 * 3600,
        '/privacy'      => 30 * 24 * 3600,
        '/cookies'      => 30 * 24 * 3600,
        '/accessibility'=> 30 * 24 * 3600,
        '/acceptable-use' => 30 * 24 * 3600,
        '/community-guidelines' => 30 * 24 * 3600,
        '/trust-and-safety' => 30 * 24 * 3600,
        '/timebanking-guide' => 30 * 24 * 3600,
        '/changelog'    => 7 * 24 * 3600,
        '/features'     => 30 * 24 * 3600,

        // Safety net for routes we forgot to enumerate.
        'default'       => 7 * 24 * 3600,
    ],

    // Shared secret for the /invalidate webhook. External systems POST with
    // either Bearer <token> OR an X-Nexus-Signature: <hex-HMAC-SHA256> header
    // over the raw body. Empty string disables the external path; the admin
    // session fallback still works for the in-app UI.
    'webhook_token' => env('PRERENDER_WEBHOOK_TOKEN', ''),

    'auto_recache' => [
        // Cap the work the cron generates so a single tick can't blow up the
        // queue. The cron itself runs at a fixed interval (see deploy notes);
        // these caps bound the per-tick fan-out.
        'max_tenants_per_run'      => 10,
        'max_routes_per_tenant'    => 50,
        // Minimum age before we'll auto-enqueue a recache, even if the TTL
        // says stale. Stops flapping after a content-change hook fires.
        'min_stale_seconds'        => 5 * 60,
        // Skip enqueueing if there's already a queued or running job for the
        // tenant. Prevents pile-up if processor is slow.
        'skip_if_tenant_has_active_job' => true,
    ],
];
