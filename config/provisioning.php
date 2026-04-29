<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG44 — Self-service regional node provisioning configuration.
 *
 * Platform-level feature flag. The public application form is hidden until
 * `public_form_enabled` is true, regardless of any per-tenant settings.
 */
return [
    /*
     * When false, the public application form returns 503 and the React
     * route renders a "coming soon" message. Default: false.
     */
    'public_form_enabled' => env('PROVISIONING_PUBLIC_FORM_ENABLED', false),

    /*
     * Hosted-mode versus isolated-node provisioning. v1 only supports
     * hosted (we run their tenant on our infrastructure). Isolated-node
     * deployment (their own Docker stack) is documented but out-of-scope.
     */
    'mode' => env('PROVISIONING_MODE', 'hosted'),

    /*
     * Optional notification recipients when a new request lands.
     */
    'notify_on_submit' => array_filter(explode(',', (string) env('PROVISIONING_NOTIFY_EMAILS', ''))),
];
