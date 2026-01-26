#!/usr/bin/env node

/**
 * Analyze undefined CSS variables
 * Groups them by prefix to understand patterns
 */

const fs = require('fs');
const path = require('path');
const { globSync } = require('glob');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Get all CSS variables used
const allUsages = new Map();

const files = globSync('**/*.css', { cwd: cssDir, ignore: ['**/*.min.css', 'purged/**'] });

for (const file of files) {
    const content = fs.readFileSync(path.join(cssDir, file), 'utf8');
    const matches = content.matchAll(/var\((--[\w-]+)/g);
    for (const m of matches) {
        const varName = m[1];
        if (!allUsages.has(varName)) {
            allUsages.set(varName, []);
        }
        allUsages.get(varName).push(file);
    }
}

// Get defined variables from all token files
const tokenFiles = [
    'design-tokens.css',
    'desktop-design-tokens.css',
    'mobile-design-tokens.css'
];

const defined = new Set();
for (const tokenFile of tokenFiles) {
    const filePath = path.join(cssDir, tokenFile);
    if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, 'utf8');
        const defMatches = content.matchAll(/(--[\w-]+)\s*:/g);
        for (const m of defMatches) {
            defined.add(m[1]);
        }
    }
}

// Find undefined and group by prefix
const undefinedVars = [];
for (const [varName, fileList] of allUsages) {
    if (!defined.has(varName)) {
        undefinedVars.push({ name: varName, count: fileList.length, files: fileList });
    }
}

// Group by prefix
const byPrefix = {};
for (const item of undefinedVars) {
    const match = item.name.match(/^--([\w]+)/);
    const prefix = match ? match[1] : 'other';
    if (!byPrefix[prefix]) byPrefix[prefix] = [];
    byPrefix[prefix].push(item);
}

// Sort and print
console.log('UNDEFINED CSS VARIABLES BY PREFIX');
console.log('==================================\n');

const sortedPrefixes = Object.entries(byPrefix)
    .sort((a, b) => b[1].length - a[1].length);

for (const [prefix, vars] of sortedPrefixes) {
    vars.sort((a, b) => b.count - a.count);
    console.log(`\n## --${prefix}-* (${vars.length} variables)\n`);

    for (const item of vars.slice(0, 20)) {
        console.log(`  ${item.name}: used in ${item.count} files`);
    }
    if (vars.length > 20) {
        console.log(`  ... and ${vars.length - 20} more`);
    }
}

// Summary
console.log('\n\n==================================');
console.log('SUMMARY');
console.log('==================================');
console.log(`Total undefined variables: ${undefinedVars.length}`);
console.log(`Total defined variables: ${defined.size}`);
console.log('\nBy category:');
for (const [prefix, vars] of sortedPrefixes) {
    console.log(`  --${prefix}-*: ${vars.length} undefined`);
}
