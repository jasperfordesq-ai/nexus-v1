#!/usr/bin/env node
/**
 * Quick PurgeCSS - Skip validation errors, just copy files for now
 * This is a temporary solution until CSS files are fully cleaned
 */

const fs = require('fs');
const path = require('path');

const configPath = path.resolve(__dirname, '..', 'purgecss.config.js');
const config = require(configPath);

const projectRoot = path.resolve(__dirname, '..');
const outputDir = path.resolve(projectRoot, config.output);

// Ensure output directory exists
fs.mkdirSync(outputDir, { recursive: true });

console.log('Quick CSS Copy (bypassing PurgeCSS validation errors)');
console.log('This will be replaced once CSS syntax errors are fixed');
console.log('');

let copied = 0;
for (const cssFile of config.css) {
    const sourcePath = path.resolve(projectRoot, cssFile);
    if (fs.existsSync(sourcePath)) {
        const fileName = path.basename(cssFile);
        const outputFileName = fileName.replace('.css', '.min.css');
        const outputPath = path.join(outputDir, outputFileName);

        // Just copy the file for now
        fs.copyFileSync(sourcePath, outputPath);
        console.log(`✓ ${fileName} → ${outputFileName}`);
        copied++;
    }
}

console.log('');
console.log(`Copied ${copied} CSS files to purged/ directory`);
console.log('');
console.log('NOTE: Run proper PurgeCSS once CSS syntax errors are fixed:');
console.log('  1. Fix <style> tags in CSS files');
console.log('  2. Fix selector syntax errors');
console.log('  3. Run: npm run build:css:purge');
