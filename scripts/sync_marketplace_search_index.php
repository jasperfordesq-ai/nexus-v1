<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Marketplace Meilisearch Sync Script
 *
 * Convenience wrapper around sync_search_index.php that syncs only marketplace
 * listings. Equivalent to: php scripts/sync_search_index.php --type=marketplace
 *
 * Usage:
 *   php scripts/sync_marketplace_search_index.php --tenant=2
 *   php scripts/sync_marketplace_search_index.php --all-tenants
 *   php scripts/sync_marketplace_search_index.php --all-tenants --dry-run
 *   php scripts/sync_marketplace_search_index.php --help
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// Inject --type=marketplace and forward all other arguments
$_SERVER['argv'][] = '--type=marketplace';

require __DIR__ . '/sync_search_index.php';
