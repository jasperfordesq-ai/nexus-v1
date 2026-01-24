#!/usr/bin/env node

/**
 * CSS Build Script
 *
 * This script:
 * 1. Runs PurgeCSS to remove unused CSS
 * 2. Minifies the purged CSS
 * 3. Reports savings
 *
 * Usage: node scripts/build-css.js
 */

const { PurgeCSS } = require('purgecss');
const fs = require('fs');
const path = require('path');

const config = require('../purgecss.config.js');

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

async function buildCSS() {
    console.log('🚀 Starting CSS build...\n');

    // Ensure output directory exists
    const outputDir = path.join(__dirname, '..', config.output);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    let totalOriginal = 0;
    let totalPurged = 0;
    let totalMinified = 0;

    // Process each CSS file
    for (const cssFile of config.css) {
        const fullPath = path.join(__dirname, '..', cssFile);

        if (!fs.existsSync(fullPath)) {
            console.log(`⚠️  Skipping ${path.basename(cssFile)} (not found)`);
            continue;
        }

        const originalContent = fs.readFileSync(fullPath, 'utf8');
        const originalSize = Buffer.byteLength(originalContent, 'utf8');
        totalOriginal += originalSize;

        try {
            // Run PurgeCSS
            const purgeCSSResult = await new PurgeCSS().purge({
                content: config.content.map(p => path.join(__dirname, '..', p)),
                css: [{ raw: originalContent, extension: 'css' }],
                safelist: config.safelist,
                fontFace: config.fontFace,
                keyframes: config.keyframes,
                variables: config.variables,
            });

            const purgedContent = purgeCSSResult[0].css;
            const purgedSize = Buffer.byteLength(purgedContent, 'utf8');
            totalPurged += purgedSize;

            // Minify
            const minifiedContent = minifyCSS(purgedContent);
            const minifiedSize = Buffer.byteLength(minifiedContent, 'utf8');
            totalMinified += minifiedSize;

            // Save purged and minified version
            const baseName = path.basename(cssFile, '.css');
            const outputPath = path.join(outputDir, `${baseName}.min.css`);
            fs.writeFileSync(outputPath, minifiedContent);

            // Also copy to the same directory as the source file (for direct use)
            const sourceDir = path.dirname(path.join(__dirname, '..', cssFile));
            const directOutputPath = path.join(sourceDir, `${baseName}.min.css`);
            fs.writeFileSync(directOutputPath, minifiedContent);

            // Calculate savings
            const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

            console.log(`✅ ${baseName}.css`);
            console.log(`   Original: ${(originalSize / 1024).toFixed(1)}KB → Purged: ${(purgedSize / 1024).toFixed(1)}KB → Minified: ${(minifiedSize / 1024).toFixed(1)}KB (${savings}% smaller)`);

        } catch (err) {
            console.error(`❌ Error processing ${cssFile}: ${err.message}`);
        }
    }

    // Summary
    console.log('\n' + '='.repeat(60));
    console.log('📊 Summary:');
    console.log(`   Original total:  ${(totalOriginal / 1024).toFixed(1)}KB`);
    console.log(`   After purge:     ${(totalPurged / 1024).toFixed(1)}KB`);
    console.log(`   After minify:    ${(totalMinified / 1024).toFixed(1)}KB`);
    console.log(`   Total savings:   ${((totalOriginal - totalMinified) / 1024).toFixed(1)}KB (${((totalOriginal - totalMinified) / totalOriginal * 100).toFixed(1)}%)`);
    console.log('='.repeat(60));
    console.log(`\n✨ Purged CSS files saved to: ${config.output}`);
}

buildCSS().catch(err => {
    console.error('Build failed:', err);
    process.exit(1);
});
