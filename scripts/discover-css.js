#!/usr/bin/env node
/**
 * CSS Discovery Tool
 * Finds all CSS files and checks if they're in purgecss.config.js
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

const projectRoot = path.resolve(__dirname, '..');
const configPath = path.join(projectRoot, 'purgecss.config.js');

// Load current config
const config = require(configPath);
const configuredFiles = new Set(config.css);

// Find all CSS files (excluding node_modules, vendor, purged, etc.)
const allCssFiles = glob.sync('httpdocs/assets/css/**/*.css', {
    cwd: projectRoot,
    ignore: [
        '**/purged/**',
        '**/*.min.css',
        '**/node_modules/**',
        '**/vendor/**',
        '**/_archive/**',
        '**/_archived/**',
        '**/bundles/**'  // Bundle files are compiled from others
    ]
}).map(f => f.replace(/\\/g, '/'));

console.log('===========================================');
console.log('  CSS Discovery Report');
console.log('===========================================');
console.log('');

// Check which files are missing
const missingFiles = [];
const foundFiles = [];

for (const file of allCssFiles) {
    if (configuredFiles.has(file)) {
        foundFiles.push(file);
    } else {
        missingFiles.push(file);
    }
}

console.log(`Total CSS files found: ${allCssFiles.length}`);
console.log(`Configured in purgecss.config.js: ${foundFiles.length}`);
console.log(`Missing from config: ${missingFiles.length}`);
console.log('');

if (missingFiles.length > 0) {
    console.log('⚠️  Files NOT in purgecss.config.js:');
    console.log('');
    for (const file of missingFiles) {
        console.log(`  - ${file}`);
    }
    console.log('');
    console.log('Add these to purgecss.config.js to include them in the build.');
} else {
    console.log('✓ All CSS files are configured!');
}

console.log('');
console.log('===========================================');
