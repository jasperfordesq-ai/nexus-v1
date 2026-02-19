<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Clear all caches
 */

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
} else {
    echo "⚠️ OPcache not available\n";
}

// Clear menu cache from database
require_once __DIR__ . '/bootstrap.php';

try {
    $db = \Nexus\Core\Database::getConnection();
    $stmt = $db->query("DELETE FROM menu_cache");
    echo "✅ Menu cache cleared from database\n";
} catch (\Exception $e) {
    echo "⚠️ Could not clear menu cache: " . $e->getMessage() . "\n";
}

// Clear session
session_start();
session_destroy();
echo "✅ Session cleared\n";

echo "\n🎉 All caches cleared! Refresh your browser.\n";
