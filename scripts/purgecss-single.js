#!/usr/bin/env node
/**
 * PurgeCSS Single File Runner
 *
 * Workaround for PurgeCSS 7.x CLI bug on Windows with Node.js ESM loader.
 * Runs PurgeCSS programmatically to avoid the "Only URLs with a scheme" error.
 *
 * Usage:
 *   node scripts/purgecss-single.js <css-file-path>
 *   node scripts/purgecss-single.js httpdocs/assets/css/civicone-profile-show.css
 *
 * Output goes to httpdocs/assets/css/purged/ (same filename)
 */

const { PurgeCSS } = require('purgecss');
const fs = require('fs');
const path = require('path');

async function run() {
    const args = process.argv.slice(2);

    if (args.length === 0) {
        console.error('Usage: node scripts/purgecss-single.js <css-file-path>');
        console.error('Example: node scripts/purgecss-single.js httpdocs/assets/css/civicone-profile-show.css');
        process.exit(1);
    }

    const cssFile = args[0];

    // Verify file exists
    if (!fs.existsSync(cssFile)) {
        console.error(`Error: CSS file not found: ${cssFile}`);
        process.exit(1);
    }

    // Load config
    const configPath = path.join(__dirname, '..', 'purgecss.config.js');
    if (!fs.existsSync(configPath)) {
        console.error('Error: purgecss.config.js not found in project root');
        process.exit(1);
    }

    const config = require(configPath);

    console.log(`Purging: ${cssFile}`);

    const result = await new PurgeCSS().purge({
        content: config.content,
        css: [cssFile],
        safelist: config.safelist,
        fontFace: config.fontFace,
        keyframes: config.keyframes,
        variables: config.variables
    });

    // Ensure output directory exists
    const outputDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css', 'purged');
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    for (const r of result) {
        const filename = path.basename(r.file);
        const outputPath = path.join(outputDir, filename);

        // Get file sizes for comparison
        const originalSize = fs.statSync(cssFile).size;
        fs.writeFileSync(outputPath, r.css);
        const purgedSize = fs.statSync(outputPath).size;

        const savings = ((1 - purgedSize / originalSize) * 100).toFixed(1);

        console.log(`Output: ${outputPath}`);
        console.log(`Size: ${(originalSize / 1024).toFixed(1)}KB → ${(purgedSize / 1024).toFixed(1)}KB (${savings}% reduction)`);
    }
}

run().catch(err => {
    console.error('PurgeCSS error:', err.message);
    process.exit(1);
});
