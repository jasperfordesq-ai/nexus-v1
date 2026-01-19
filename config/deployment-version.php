<?php
/**
 * Deployment Version - Auto Cache Busting
 *
 * This file contains a version number that gets updated with each deployment.
 * All CSS/JS files use this version to force browser cache refresh.
 *
 * Update this number whenever you deploy major changes to force all users
 * to reload assets without clearing their cache.
 */

return [
    'version' => '2026.01.19.003', // Update this with each deployment
    'timestamp' => 1768841339, // Unix timestamp of last deployment
    'description' => 'Fix htaccess cache settings to honor query string versions'
];
