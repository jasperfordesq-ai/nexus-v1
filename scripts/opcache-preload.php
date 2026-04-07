<?php
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
