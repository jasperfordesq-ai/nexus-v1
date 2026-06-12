<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    /*
     * Relying Party ID for passkeys. Must be a registrable suffix of (or equal
     * to) the browser origin's domain: project-nexus.ie in production,
     * localhost for local dev. Read via config so it survives config:cache —
     * reading $_ENV directly breaks once the config is cached and .env is no
     * longer parsed. When null, WebAuthnController falls back to HTTP_HOST.
     */
    'rp_id' => env('WEBAUTHN_RP_ID'),
];
