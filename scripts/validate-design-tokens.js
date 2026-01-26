#!/usr/bin/env node

/**
 * Design Tokens Validation Script
 *
 * This script validates that design-tokens files are not corrupted.
 * Run this after CSS builds to catch issues immediately.
 *
 * Usage: node scripts/validate-design-tokens.js
 *
 * What it checks:
 * 1. File existence and size
 * 2. Required CSS variables present
 * 3. Color palette completeness
 * 4. Spacing scale completeness
 * 5. Z-index scale completeness
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Files to validate
const tokenFiles = [
    { name: 'design-tokens.css', minSize: 25000 },
    { name: 'design-tokens.min.css', minSize: 10000 },
    { name: 'desktop-design-tokens.css', minSize: 1000 },
    { name: 'mobile-design-tokens.css', minSize: 1000 },
];

// Required CSS variables (expanded list)
const requiredVariables = {
    spacing: [
        '--space-0', '--space-1', '--space-2', '--space-3', '--space-4',
        '--space-5', '--space-6', '--space-8', '--space-10', '--space-12'
    ],
    colors: [
        '--color-primary-500', '--color-primary-600',
        '--color-gray-500', '--color-gray-600',
        '--color-success', '--color-warning', '--color-danger',
        '--color-white', '--color-black'
    ],
    zIndex: [
        '--z-base', '--z-header', '--z-sticky', '--z-navbar',
        '--z-dropdown', '--z-modal', '--z-tooltip'
    ],
    typography: [
        '--font-size-sm', '--font-size-base', '--font-size-lg',
        '--font-weight-normal', '--font-weight-semibold', '--font-weight-bold'
    ],
    borders: [
        '--radius-sm', '--radius-base', '--radius-md', '--radius-lg', '--radius-full'
    ]
};

let hasErrors = false;
let hasWarnings = false;

function log(type, message) {
    const prefix = {
        error: '❌ ERROR:',
        warn: '⚠️  WARNING:',
        success: '✅',
        info: '   '
    };
    console.log(`${prefix[type] || ''} ${message}`);
}

function validateFile(fileConfig) {
    const filePath = path.join(cssDir, fileConfig.name);

    console.log(`\n📄 Checking ${fileConfig.name}...`);

    // Check existence
    if (!fs.existsSync(filePath)) {
        log('error', `File does not exist: ${fileConfig.name}`);
        hasErrors = true;
        return;
    }

    // Check size
    const stats = fs.statSync(filePath);
    const sizeKB = (stats.size / 1024).toFixed(1);

    if (stats.size < fileConfig.minSize) {
        log('error', `File is too small: ${sizeKB}KB (expected >${fileConfig.minSize / 1024}KB)`);
        log('info', `This may indicate PurgeCSS corruption.`);
        hasErrors = true;
        return;
    }

    log('success', `File size: ${sizeKB}KB`);

    // Only do deep validation on main tokens file
    if (fileConfig.name === 'design-tokens.css') {
        const content = fs.readFileSync(filePath, 'utf8');

        // Check each category
        for (const [category, vars] of Object.entries(requiredVariables)) {
            const missing = vars.filter(v => !content.includes(v));

            if (missing.length > 0) {
                log('error', `Missing ${category} variables: ${missing.join(', ')}`);
                hasErrors = true;
            } else {
                log('success', `${category}: All ${vars.length} variables present`);
            }
        }

        // Count total variables
        const varCount = (content.match(/--[\w-]+:/g) || []).length;
        log('info', `Total CSS variables defined: ${varCount}`);

        if (varCount < 300) {
            log('warn', `Expected 300+ variables, found only ${varCount}`);
            hasWarnings = true;
        }
    }
}

function validateCssLoaderOrder() {
    console.log('\n📋 Checking CSS loader order...');

    const cssLoaderPath = path.join(__dirname, '..', 'views', 'layouts', 'modern', 'partials', 'css-loader.php');

    if (!fs.existsSync(cssLoaderPath)) {
        log('warn', 'css-loader.php not found');
        hasWarnings = true;
        return;
    }

    const content = fs.readFileSync(cssLoaderPath, 'utf8');

    // Check scroll-fix-emergency is last sync CSS
    const lastSyncMatch = content.match(/syncCss\([^)]+\)[\s\S]*$/);
    if (lastSyncMatch && lastSyncMatch[0].includes('scroll-fix-emergency')) {
        log('success', 'scroll-fix-emergency.css loads last (correct)');
    } else {
        log('error', 'scroll-fix-emergency.css should be the last sync CSS!');
        hasErrors = true;
    }

    // Check modern-pages loads before page-specific
    const modernPagesPos = content.indexOf('modern-pages.css');
    const pageLoaderPos = content.indexOf('page-css-loader.php');

    if (modernPagesPos > -1 && pageLoaderPos > -1 && modernPagesPos < pageLoaderPos) {
        log('success', 'modern-pages.css loads before page-specific CSS (correct)');
    } else {
        log('error', 'modern-pages.css should load before page-css-loader.php!');
        hasErrors = true;
    }
}

function validateHeaderTokensFirst() {
    console.log('\n📋 Checking header.php token loading...');

    const headerPath = path.join(__dirname, '..', 'views', 'layouts', 'modern', 'header.php');

    if (!fs.existsSync(headerPath)) {
        log('warn', 'header.php not found');
        hasWarnings = true;
        return;
    }

    const content = fs.readFileSync(headerPath, 'utf8');

    // Find the <head> section
    const headMatch = content.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
    if (!headMatch) {
        log('warn', 'Could not find <head> section in header.php');
        hasWarnings = true;
        return;
    }

    const headContent = headMatch[1];

    // Strip PHP tags first to simplify parsing
    // Replace <?= ... ?> and <?php ... ?> with placeholder
    const cleanedHead = headContent.replace(/<\?(?:php|=)[^?]*\?>/gi, '[PHP]');

    // Find all stylesheet links in order
    // Match link tags with rel="stylesheet"
    const stylesheetMatches = cleanedHead.matchAll(/<link[^>]+rel=["']stylesheet["'][^>]*>/gi);
    const stylesheets = Array.from(stylesheetMatches).map(m => m[0]);

    if (stylesheets.length === 0) {
        log('warn', 'No stylesheets found in <head>');
        hasWarnings = true;
        return;
    }

    // For the first stylesheet, we need to check the ORIGINAL content
    // Find position of first stylesheet in cleaned content, then map back to original
    const firstSheetClean = stylesheets[0];
    const firstSheetPos = cleanedHead.indexOf(firstSheetClean);

    // Get surrounding original content to check for design-tokens.css
    // Search original head content for design-tokens.css near the first link
    const firstLinkPos = headContent.indexOf('<link rel="stylesheet"');
    if (firstLinkPos === -1) {
        log('warn', 'Could not find first stylesheet position');
        hasWarnings = true;
        return;
    }

    // Get the full first link line (until end of tag)
    const lineEnd = headContent.indexOf('>', firstLinkPos) + 1;
    const firstFullLink = headContent.substring(firstLinkPos, lineEnd + 100); // Extra for multi-line

    if (firstFullLink.includes('design-tokens.css')) {
        log('success', 'design-tokens.css is first stylesheet in header.php');
    } else {
        log('error', 'design-tokens.css must be the FIRST stylesheet loaded!');
        // Extract filename from the link
        const cssMatch = firstFullLink.match(/\/([^\/?"']+\.css)/);
        const filename = cssMatch ? cssMatch[1] : 'unknown';
        log('info', `First stylesheet found: ${filename}`);
        hasErrors = true;
    }
}

// Main execution
console.log('🔍 CSS Architecture Validation');
console.log('================================\n');

// Validate token files
for (const fileConfig of tokenFiles) {
    validateFile(fileConfig);
}

// Validate load order
validateCssLoaderOrder();
validateHeaderTokensFirst();

// Summary
console.log('\n================================');
if (hasErrors) {
    console.log('❌ VALIDATION FAILED');
    console.log('\n💡 To fix corrupted files, run: npm run minify:css');
    console.log('📖 See docs/CSS_LOAD_ORDER.md for load order requirements');
    process.exit(1);
} else if (hasWarnings) {
    console.log('⚠️  VALIDATION PASSED WITH WARNINGS');
    process.exit(0);
} else {
    console.log('✅ ALL VALIDATIONS PASSED');
    process.exit(0);
}
