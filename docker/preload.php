<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OPcache preload script for Laravel.
 * Loaded at PHP startup (opcache.preload ini directive).
 * Preloads all classes in the Composer classmap into shared memory,
 * eliminating compilation overhead on the first request after deploy.
 */

$autoloadFile = '/var/www/html/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    return; // vendor/ not yet installed — skip preload
}

require_once $autoloadFile;

$classMap = require '/var/www/html/vendor/composer/autoload_classmap.php';
if (!is_array($classMap)) {
    return;
}

$preloaded = 0;
$skipped = 0;

foreach ($classMap as $class => $file) {
    if (!file_exists($file)) {
        $skipped++;
        continue;
    }
    try {
        opcache_compile_file($file);
        $preloaded++;
    } catch (Throwable $e) {
        // Some files (test stubs, etc.) may not be preloadable — skip silently
        $skipped++;
    }
}
