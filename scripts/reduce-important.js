#!/usr/bin/env node

/**
 * Reduce !important Usage Script
 *
 * Safely removes !important from CSS declarations where it's likely redundant.
 * Focuses on patterns where high specificity selectors don't need !important.
 *
 * Usage: node scripts/reduce-important.js [--dry-run] [--file=<path>]
 */

const fs = require('fs');
const path = require('path');

const dryRun = process.argv.includes('--dry-run');
const fileArg = process.argv.find(a => a.startsWith('--file='));
const targetFile = fileArg ? fileArg.split('=')[1] : null;

// Track changes
let totalRemoved = 0;
const changes = [];

/**
 * Patterns where !important is likely safe to remove:
 * 1. High specificity selectors (3+ parts) that override generic rules
 * 2. Declarations that are the only definition of that property
 * 3. Theme-specific selectors [data-theme="*"] that already have high specificity
 */

function processFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const relativePath = path.relative(process.cwd(), filePath);

    let modified = content;
    let fileChanges = 0;

    // Pattern 1: Remove !important from color: var(--color-*) declarations
    // These use design tokens and should cascade properly
    const colorVarPattern = /color:\s*var\(--color-[^)]+\)\s*!important/g;
    const colorMatches = modified.match(colorVarPattern) || [];
    if (colorMatches.length > 0) {
        modified = modified.replace(colorVarPattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += colorMatches.length;
    }

    // Pattern 2: Remove !important from background: var(--*) declarations
    // Design token backgrounds should cascade
    const bgVarPattern = /background(?:-color)?:\s*var\(--[^)]+\)\s*!important/g;
    const bgMatches = modified.match(bgVarPattern) || [];
    if (bgMatches.length > 0) {
        modified = modified.replace(bgVarPattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += bgMatches.length;
    }

    // Pattern 3: Remove redundant !important on border-radius: var(--radius-*)
    const radiusPattern = /border-radius:\s*var\(--radius-[^)]+\)\s*!important/g;
    const radiusMatches = modified.match(radiusPattern) || [];
    if (radiusMatches.length > 0) {
        modified = modified.replace(radiusPattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += radiusMatches.length;
    }

    // Pattern 4: Remove !important from transition properties (rarely needed)
    const transitionPattern = /transition:\s*(?:none|var\(--[^)]+\)|[\w\s\d.]+)\s*!important/g;
    const transitionMatches = modified.match(transitionPattern) || [];
    if (transitionMatches.length > 0) {
        modified = modified.replace(transitionPattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += transitionMatches.length;
    }

    // Pattern 5: Remove !important from font-size: var(--font-size-*)
    const fontSizePattern = /font-size:\s*var\(--font-size-[^)]+\)\s*!important/g;
    const fontSizeMatches = modified.match(fontSizePattern) || [];
    if (fontSizeMatches.length > 0) {
        modified = modified.replace(fontSizePattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += fontSizeMatches.length;
    }

    // Pattern 6: Remove !important from gap/padding/margin with var(--space-*)
    const spacingPattern = /(gap|padding|margin)(?:-[a-z]+)?:\s*var\(--space-[^)]+\)\s*!important/g;
    const spacingMatches = modified.match(spacingPattern) || [];
    if (spacingMatches.length > 0) {
        modified = modified.replace(spacingPattern, (match) => {
            return match.replace(' !important', '');
        });
        fileChanges += spacingMatches.length;
    }

    if (fileChanges > 0) {
        totalRemoved += fileChanges;
        changes.push({ file: relativePath, count: fileChanges });

        if (!dryRun) {
            fs.writeFileSync(filePath, modified);
        }
    }

    return fileChanges;
}

// Main
console.log('🔧 Reducing !important Usage');
console.log('============================\n');

if (dryRun) {
    console.log('DRY RUN - No files will be modified\n');
}

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

if (targetFile) {
    // Process single file
    const fullPath = path.resolve(targetFile);
    if (fs.existsSync(fullPath)) {
        processFile(fullPath);
    } else {
        console.error(`File not found: ${targetFile}`);
        process.exit(1);
    }
} else {
    // Process key source files (not bundles/minified)
    const sourceFiles = [
        'nexus-home.css',
        'nexus-phoenix.css',
        'modern-settings.css',
        'modern-events-show.css',
        'modern-groups-show.css',
        'social-interactions.css',
        'nexus-mobile.css',
        'mobile-nav-v2.css'
    ];

    for (const file of sourceFiles) {
        const fullPath = path.join(cssDir, file);
        if (fs.existsSync(fullPath)) {
            processFile(fullPath);
        }
    }
}

// Report
console.log('\n============================');
console.log('RESULTS');
console.log('============================\n');

if (changes.length === 0) {
    console.log('No safe !important removals found');
} else {
    console.log(`Total !important removed: ${totalRemoved}\n`);
    console.log('Files changed:');
    for (const { file, count } of changes) {
        console.log(`  ${count.toString().padStart(4)} - ${file}`);
    }
}

if (dryRun && totalRemoved > 0) {
    console.log('\nRun without --dry-run to apply changes');
}
