#!/usr/bin/env node

/**
 * Deployment Version Bumper
 *
 * This script increments the deployment version to force cache refresh
 * for all users without them having to clear their browser cache.
 *
 * Usage:
 *   node scripts/bump-version.js [description]
 *
 * Example:
 *   node scripts/bump-version.js "Fixed federation scroll issue"
 */

const fs = require('fs');
const path = require('path');

const versionFile = path.join(__dirname, '..', 'config', 'deployment-version.php');

// Get current version
let currentVersion = '2026.01.19.001';
let buildNumber = 1;

if (fs.existsSync(versionFile)) {
    const content = fs.readFileSync(versionFile, 'utf8');
    const versionMatch = content.match(/'version'\s*=>\s*'([^']+)'/);
    if (versionMatch) {
        currentVersion = versionMatch[1];
        const parts = currentVersion.split('.');
        if (parts.length === 4) {
            buildNumber = parseInt(parts[3]) + 1;
        }
    }
}

// Generate new version
const now = new Date();
const year = now.getFullYear();
const month = String(now.getMonth() + 1).padStart(2, '0');
const day = String(now.getDate()).padStart(2, '0');
const newVersion = `${year}.${month}.${day}.${String(buildNumber).padStart(3, '0')}`;

// Get description from command line args
const description = process.argv.slice(2).join(' ') || 'Deployment update';

// Generate PHP file content
const phpContent = `<?php
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
    'version' => '${newVersion}', // Update this with each deployment
    'timestamp' => ${Math.floor(Date.now() / 1000)}, // Unix timestamp of last deployment
    'description' => '${description.replace(/'/g, "\\'")}'
];
`;

// Write the file
fs.writeFileSync(versionFile, phpContent);

console.log('✅ Version bumped successfully!');
console.log(`   Old: ${currentVersion}`);
console.log(`   New: ${newVersion}`);
console.log(`   Description: ${description}`);
console.log('');
console.log('💡 This will force all users to reload CSS/JS on next page load.');
