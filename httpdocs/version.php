<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Build version endpoint — returns git SHA, deploy timestamp, and container info.
 * Does NOT load the full application.
 *
 * Usage: curl https://api.project-nexus.ie/version.php
 *
 * The .build-version file is written by safe-deploy.sh after each successful deploy.
 * In development (no .build-version file), falls back to reading git directly.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$deployDir = dirname(__DIR__);
// .build-version lives in httpdocs/ so it's visible inside the Docker container
// (httpdocs/ is bind-mounted, but the project root is not)
$versionFile = __DIR__ . '/.build-version';

$version = [
    'service' => 'nexus-php-api',
    'commit' => 'unknown',
    'commit_message' => '',
    'deployed_at' => '',
    'environment' => getenv('APP_ENV') ?: 'production',
    'php_version' => PHP_VERSION,
];

// Try reading the build version file (written by safe-deploy.sh)
if (file_exists($versionFile)) {
    $data = json_decode(file_get_contents($versionFile), true);
    if ($data) {
        $version = array_merge($version, $data);
    }
} else {
    // Fallback: read git directly (development mode)
    $gitDir = $deployDir . '/.git';
    if (is_dir($gitDir)) {
        $commit = trim(shell_exec("cd \"$deployDir\" && git rev-parse HEAD 2>/dev/null") ?? '');
        $message = trim(shell_exec("cd \"$deployDir\" && git log -1 --format='%s' 2>/dev/null") ?? '');
        if ($commit) {
            $version['commit'] = $commit;
            $version['commit_short'] = substr($commit, 0, 8);
            $version['commit_message'] = $message;
            $version['deployed_at'] = 'development';
        }
    }
}

echo json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
