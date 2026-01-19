#!/usr/bin/env node

/**
 * CSS Minify Script (No Purging)
 *
 * Just minifies CSS files without removing any selectors.
 * Safe to use - won't break styles.
 *
 * Usage: node scripts/minify-css.js
 */

const fs = require('fs');
const path = require('path');

// Simple CSS minifier
function minifyCSS(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '')  // Remove comments
        .replace(/\s+/g, ' ')               // Collapse whitespace
        .replace(/\s*{\s*/g, '{')           // Remove space around {
        .replace(/\s*}\s*/g, '}')           // Remove space around }
        .replace(/\s*;\s*/g, ';')           // Remove space around ;
        .replace(/\s*:\s*/g, ':')           // Remove space around :
        .replace(/\s*,\s*/g, ',')           // Remove space around ,
        .replace(/;}/g, '}')                // Remove last semicolon
        .trim();
}

// CSS files to minify
// Updated 2026-01-18: Added civicone theme files
const cssFiles = [
    // Core framework
    'nexus-phoenix.css',
    'nexus-mobile.css',
    'nexus-shared-transitions.css',
    'post-box-home.css',
    // Header and loading
    'nexus-loading-fix.css',
    'nexus-performance-patch.css',
    'nexus-modern-header.css',
    // Premium components
    'premium-search.css',
    'premium-dropdowns.css',
    'nexus-premium-mega-menu.css',
    // Consolidated polish (replaces 5 separate files)
    'nexus-polish.css',
    'nexus-interactions.css',
    // Navigation (v2 only)
    'nexus-native-nav-v2.css',
    // CivicOne theme
    'nexus-civicone.css',
    'civicone-mobile.css',
    'civicone-native.css',
    'civicone-drawer.css',
    // Extracted component CSS
    'feed-filter.css',
    'dashboard.css',
    'mobile-sheets.css',
    'social-interactions.css',
    'federation-realtime.css',
    'compose-multidraw.css',
    // Bundled CSS
    'civicone-bundle-compiled.css',
    // Admin area (combined)
    'admin-gold-standard.css',
];

const cssDir = path.join(__dirname, '../httpdocs/assets/css');

console.log('🚀 Minifying CSS files...\n');

let totalOriginal = 0;
let totalMinified = 0;

cssFiles.forEach(file => {
    const inputPath = path.join(cssDir, file);
    const outputPath = path.join(cssDir, file.replace('.css', '.min.css'));

    if (!fs.existsSync(inputPath)) {
        console.log(`⚠️  Skipping ${file} (not found)`);
        return;
    }

    const original = fs.readFileSync(inputPath, 'utf8');
    const minified = minifyCSS(original);

    fs.writeFileSync(outputPath, minified);

    const originalSize = Buffer.byteLength(original, 'utf8');
    const minifiedSize = Buffer.byteLength(minified, 'utf8');
    const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

    totalOriginal += originalSize;
    totalMinified += minifiedSize;

    console.log(`✅ ${file}: ${(originalSize/1024).toFixed(1)}KB → ${(minifiedSize/1024).toFixed(1)}KB (${savings}% smaller)`);
});

console.log('\n' + '='.repeat(50));
console.log(`📊 Total: ${(totalOriginal/1024).toFixed(1)}KB → ${(totalMinified/1024).toFixed(1)}KB`);
console.log(`💾 Savings: ${((totalOriginal - totalMinified)/1024).toFixed(1)}KB (${((totalOriginal - totalMinified)/totalOriginal * 100).toFixed(1)}%)`);
console.log('='.repeat(50));
