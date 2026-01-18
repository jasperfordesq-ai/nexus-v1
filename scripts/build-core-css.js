#!/usr/bin/env node

/**
 * Build Core CSS Bundle
 *
 * Creates a smaller core bundle with only essential styles.
 * Page-specific CSS loads separately for better cache efficiency.
 *
 * Usage: node scripts/build-core-css.js
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '../httpdocs/assets/css');

// CORE CSS - Used on every page (header, navigation, layout)
// Updated 2026-01-17: Consolidated polish files, using v2 nav only
const coreCSS = [
    'nexus-phoenix.min.css',           // Core framework
    'nexus-mobile.min.css',            // Mobile styles
    'nexus-modern-header.min.css',     // Header styles
    'nexus-loading-fix.min.css',       // Loading states
    'nexus-performance-patch.min.css', // Performance fixes
    'premium-search.min.css',          // Search bar
    'premium-dropdowns.min.css',       // Dropdowns
    'nexus-premium-mega-menu.min.css', // Mega menu
];

// NAVIGATION CSS - v2 only
const navCSS = [
    'nexus-native-nav-v2.min.css',     // Nav v2 (v1 removed)
];

// UI CSS - Consolidated polish and interactions
const uiCSS = [
    'nexus-polish.min.css',            // Consolidated polish
    'nexus-interactions.min.css',      // Consolidated interactions
    'nexus-shared-transitions.min.css', // Transitions
    'post-box-home.min.css',           // Post box styles
];

// Combine all core CSS
const allCoreCSS = [...coreCSS, ...navCSS, ...uiCSS];

console.log('🚀 Building Core CSS Bundle...\n');

let combined = '';
let totalSize = 0;

allCoreCSS.forEach(file => {
    const filePath = path.join(cssDir, file);
    if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, 'utf8');
        combined += `/* ${file} */\n${content}\n\n`;
        const size = Buffer.byteLength(content, 'utf8');
        totalSize += size;
        console.log(`✅ ${file}: ${(size/1024).toFixed(1)}KB`);
    } else {
        console.log(`⚠️  Skipping ${file} (not found)`);
    }
});

// Write core bundle
const outputPath = path.join(cssDir, 'core.min.css');
fs.writeFileSync(outputPath, combined);

console.log('\n' + '='.repeat(50));
console.log(`📦 Core bundle: ${(totalSize/1024).toFixed(1)}KB`);
console.log(`📁 Output: ${outputPath}`);
console.log('='.repeat(50));

console.log('\n📋 Page-specific CSS (load separately):');
console.log('   - nexus-mobile.min.css - Mobile styles');
console.log('   - post-box-home.min.css - Home page');
console.log('   - nexus-polish.min.css - Consolidated polish');
console.log('   - nexus-interactions.min.css - Consolidated interactions');
console.log('   - nexus-performance-patch.min.css - Performance fixes');
