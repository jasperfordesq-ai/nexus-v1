<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// OPCache preload script — loads frequently-used classes into shared memory.
// This file is referenced by php.ini opcache.preload setting.
// In development, we skip preloading to avoid issues with hot-reloading.

if (php_sapi_name() !== 'cli') {
    // Only preload in web context if the autoloader exists
    $autoloader = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloader)) {
        require $autoloader;
    }
}
