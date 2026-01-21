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
    'version' => '2.0.1.' . time(), // Use actual timestamp to force cache bust
    'timestamp' => time(), // Unix timestamp of last deployment
    'description' => 'NUCLEAR CACHE BUST v2.0.1: Dark Mode button size fix + dropdown overflow fix'
];
