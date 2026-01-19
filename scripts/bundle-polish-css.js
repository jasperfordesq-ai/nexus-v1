#!/usr/bin/env node

/**
 * Bundle Visual Polish CSS Files
 * Combines multiple small CSS files into a single optimized bundle
 *
 * Usage: node scripts/bundle-polish-css.js
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '../httpdocs/assets/css');
const bundleDir = path.join(cssDir, 'bundles');

// Ensure bundle directory exists
if (!fs.existsSync(bundleDir)) {
    fs.mkdirSync(bundleDir, { recursive: true });
}

// Visual polish files to bundle (order matters for CSS cascade)
const polishFiles = [
    'loading-skeletons.css',
    'empty-states.css',
    'image-lazy-load.css',
    'hover-interactions.css',
    'focus-rings.css',
    'micro-interactions.css',
    'modal-polish.css',
];

// Enhancement files to bundle
const enhancementFiles = [
    'responsive-forms.css',
    'responsive-tables.css',
    'accessibility.css',
    'feed-action-pills.css',
    'ai-chat-widget.css',
];

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

// Bundle a set of files
function bundleFiles(files, outputName, description) {
    console.log(`\n📦 Creating ${outputName}...`);

    let bundleContent = `/**\n * ${description}\n * Auto-generated bundle - DO NOT EDIT\n * Generated: ${new Date().toISOString()}\n */\n\n`;
    let totalOriginal = 0;
    let filesIncluded = 0;

    files.forEach(file => {
        const filePath = path.join(cssDir, file);
        if (fs.existsSync(filePath)) {
            const content = fs.readFileSync(filePath, 'utf8');
            totalOriginal += Buffer.byteLength(content, 'utf8');
            bundleContent += `/* === ${file} === */\n${content}\n\n`;
            filesIncluded++;
            console.log(`   ✅ Added ${file}`);
        } else {
            console.log(`   ⚠️  Skipped ${file} (not found)`);
        }
    });

    // Write unminified bundle
    const bundlePath = path.join(bundleDir, outputName);
    fs.writeFileSync(bundlePath, bundleContent);

    // Create minified version
    const minified = minifyCSS(bundleContent);
    const minBundlePath = path.join(bundleDir, outputName.replace('.css', '.min.css'));
    fs.writeFileSync(minBundlePath, minified);

    const minifiedSize = Buffer.byteLength(minified, 'utf8');
    const savings = ((totalOriginal - minifiedSize) / totalOriginal * 100).toFixed(1);

    console.log(`   📊 ${filesIncluded} files → ${(totalOriginal/1024).toFixed(1)}KB → ${(minifiedSize/1024).toFixed(1)}KB (${savings}% smaller)`);

    return { files: filesIncluded, original: totalOriginal, minified: minifiedSize };
}

console.log('🚀 Bundling Visual Polish CSS...');

// Create polish bundle
const polishStats = bundleFiles(
    polishFiles,
    'polish.css',
    'Visual Polish Bundle - Loading states, empty states, lazy loading, interactions'
);

// Create enhancements bundle
const enhanceStats = bundleFiles(
    enhancementFiles,
    'enhancements.css',
    'Enhancements Bundle - Responsive, accessibility, extracted components'
);

// Summary
console.log('\n' + '='.repeat(50));
console.log('📊 Bundle Summary:');
console.log(`   polish.min.css: ${(polishStats.minified/1024).toFixed(1)}KB (${polishStats.files} files)`);
console.log(`   enhancements.min.css: ${(enhanceStats.minified/1024).toFixed(1)}KB (${enhanceStats.files} files)`);
console.log(`   Total reduction: ${polishStats.files + enhanceStats.files} HTTP requests → 2 requests`);
console.log('='.repeat(50));
