/**
 * Fix HTML tags accidentally left in CSS files
 * These were introduced when extracting CSS from PHP files
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Files known to have HTML tag issues
const filesToFix = [
    'civicone-events-edit.css',
    'civicone-groups-edit.css',
    'events-show.css',
    'federation.css',
    'goals.css',
    'groups.css',
    'scattered-singles.css',
    'polls.css',
    'resources.css'
];

function fixFile(filePath) {
    if (!fs.existsSync(filePath)) {
        console.log(`Skipping (not found): ${path.basename(filePath)}`);
        return 0;
    }

    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Remove </style> tags (with optional whitespace before)
    const closeStylePattern = /\s*<\/style>\s*/gi;
    const closeMatches = content.match(closeStylePattern);
    if (closeMatches) {
        fixCount += closeMatches.length;
        content = content.replace(closeStylePattern, '\n');
    }

    // Remove <style> tags (with optional attributes and whitespace)
    const openStylePattern = /\s*<style[^>]*>\s*/gi;
    const openMatches = content.match(openStylePattern);
    if (openMatches) {
        fixCount += openMatches.length;
        content = content.replace(openStylePattern, '\n');
    }

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`Fixed ${fixCount} HTML tags in ${path.basename(filePath)}`);
        return fixCount;
    }

    return 0;
}

let totalFixes = 0;

filesToFix.forEach(file => {
    const filePath = path.join(cssDir, file);
    totalFixes += fixFile(filePath);
});

console.log(`\nTotal HTML tags removed: ${totalFixes}`);

// Also check for any other CSS files that might have this issue
console.log('\nScanning all CSS files for remaining HTML tags...');

function scanDir(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            if (entry.name === 'purged') continue;
            scanDir(fullPath);
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            const content = fs.readFileSync(fullPath, 'utf8');
            if (/<\/?style/i.test(content)) {
                // Check if it's in a comment (allowed)
                const lines = content.split('\n');
                let hasIssue = false;
                lines.forEach((line, idx) => {
                    if (/<\/?style/i.test(line) && !line.includes('* ') && !line.includes('// ')) {
                        hasIssue = true;
                        console.log(`  ${path.relative(cssDir, fullPath)}:${idx + 1}: ${line.trim().substring(0, 60)}`);
                    }
                });
            }
        }
    }
}

scanDir(cssDir);
