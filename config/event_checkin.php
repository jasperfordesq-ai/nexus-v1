<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

return [
    // Optional dedicated Ed25519 seed (base64 or base64:<value>). When absent,
    // the signer derives a domain-separated seed from APP_KEY so local/test
    // environments remain deterministic without persisting private material.
    'signing_seed' => env('EVENT_CHECKIN_SIGNING_SEED'),
    // JSON object of retired/secondary verification keys keyed by the 16-char
    // key id. Values are base64/base64url Ed25519 public keys; private keys are
    // never included in offline manifests.
    'verification_keys_json' => env('EVENT_CHECKIN_VERIFICATION_KEYS_JSON', '{}'),
    'signature_clock_skew_seconds' => (int) env('EVENT_CHECKIN_SIGNATURE_CLOCK_SKEW_SECONDS', 60),
    'credential_offline_grace_minutes' => (int) env('EVENT_CHECKIN_CREDENTIAL_GRACE_MINUTES', 1440),
    'device_ttl_minutes' => (int) env('EVENT_CHECKIN_DEVICE_TTL_MINUTES', 720),
    'device_max_ttl_minutes' => (int) env('EVENT_CHECKIN_DEVICE_MAX_TTL_MINUTES', 4320),
    'manifest_ttl_minutes' => (int) env('EVENT_CHECKIN_MANIFEST_TTL_MINUTES', 480),
    'manifest_max_ttl_minutes' => (int) env('EVENT_CHECKIN_MANIFEST_MAX_TTL_MINUTES', 1440),
    'offline_replay_window_minutes' => (int) env('EVENT_CHECKIN_OFFLINE_REPLAY_MINUTES', 1440),
    'future_clock_skew_minutes' => (int) env('EVENT_CHECKIN_FUTURE_CLOCK_SKEW_MINUTES', 5),
    'sync_batch_max_items' => (int) env('EVENT_CHECKIN_SYNC_BATCH_MAX_ITEMS', 500),
    'sync_claim_seconds' => (int) env('EVENT_CHECKIN_SYNC_CLAIM_SECONDS', 120),
    'sync_claim_max_attempts' => (int) env('EVENT_CHECKIN_SYNC_CLAIM_MAX_ATTEMPTS', 10),
];
