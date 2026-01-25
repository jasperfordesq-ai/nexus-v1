#!/usr/bin/env node
/**
 * PurgeCSS Runner
 * Wrapper script to run PurgeCSS with proper path handling
 */

const { PurgeCSS } = require('purgecss');
const path = require('path');
const fs = require('fs');

// Load config
const configPath = path.resolve(__dirname, '..', 'purgecss.config.js');
const config = require(configPath);

// Resolve paths relative to project root
const projectRoot = path.resolve(__dirname, '..');
config.output = path.resolve(projectRoot, config.output);

// Run PurgeCSS - process files individually to handle errors gracefully
(async () => {
    console.log('Running PurgeCSS...');
    console.log(`Processing ${config.css.length} CSS files...`);
    console.log('');

    const startTime = Date.now();
    let savedTotal = 0;
    let successCount = 0;
    let errorCount = 0;
    const errors = [];

    // Ensure output directory exists
    fs.mkdirSync(config.output, { recursive: true });

    // Process each CSS file individually
    for (const cssFile of config.css) {
        const fileName = path.basename(cssFile);
        const outputFileName = fileName.replace('.css', '.min.css');
        const outputPath = path.join(config.output, outputFileName);

        try {
            // Process single file
            const singleConfig = {
                ...config,
                css: [cssFile]
            };

            const results = await new PurgeCSS().purge(singleConfig);

            if (results.length > 0) {
                const result = results[0];

                // Write file
                fs.writeFileSync(outputPath, result.css);

                // Calculate savings
                const filePath = path.resolve(projectRoot, cssFile);
                if (fs.existsSync(filePath)) {
                    const original = fs.statSync(filePath).size;
                    const purged = Buffer.byteLength(result.css);
                    savedTotal += (original - purged);
                }

                console.log(`✓ ${fileName}`);
                successCount++;
            }
        } catch (error) {
            console.log(`✗ ${fileName} - ${error.reason || error.message}`);
            errorCount++;
            errors.push({ file: cssFile, error: error.message, line: error.line });
        }
    }

    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
    const savedMB = (savedTotal / 1048576).toFixed(2);

    console.log('');
    console.log(`Completed in ${duration}s`);
    console.log(`Success: ${successCount}, Errors: ${errorCount}`);
    console.log(`Saved ~${savedMB} MB`);
    console.log('');
    console.log(`Output: ${config.output}`);

    if (errorCount > 0) {
        console.log('\nFiles with errors:');
        errors.forEach(e => console.log(`  - ${e.file}${e.line ? `:${e.line}` : ''}`));
    }
})();
