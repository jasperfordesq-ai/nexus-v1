/**
 * Fix unclosed CSS blocks
 * This script finds and fixes CSS rules that are missing closing braces
 */

const fs = require('fs');
const path = require('path');

const cssFile = process.argv[2];

if (!cssFile) {
    console.log('Usage: node fix-unclosed-blocks.js <css-file>');
    process.exit(1);
}

let content = fs.readFileSync(cssFile, 'utf8');
const originalContent = content;

// Pattern to find unclosed blocks: rule { properties... followed by another rule without }
// Match: .selector { properties... \n\n .next-selector {
// Should be: .selector { properties... } \n\n .next-selector {
const unclosedPattern = /(\{[^{}]+)(\n\n)(\/\*|\.|\#|@media|@keyframes|@supports|:root)/g;

let fixCount = 0;

content = content.replace(unclosedPattern, (match, block, newlines, nextThing) => {
    // Check if the block already ends with }
    if (block.trim().endsWith('}')) {
        return match;
    }
    fixCount++;
    return block + '\n}\n' + newlines.substring(1) + nextThing;
});

if (fixCount > 0) {
    fs.writeFileSync(cssFile, content, 'utf8');
    console.log(`Fixed ${fixCount} unclosed blocks in ${path.basename(cssFile)}`);
} else {
    console.log(`No unclosed blocks found in ${path.basename(cssFile)}`);
}

// Verify brace balance
const finalContent = fs.readFileSync(cssFile, 'utf8');
const opens = (finalContent.match(/\{/g) || []).length;
const closes = (finalContent.match(/\}/g) || []).length;
console.log(`Brace balance: ${opens} opens, ${closes} closes (${opens === closes ? 'OK' : 'MISMATCH'})`);
