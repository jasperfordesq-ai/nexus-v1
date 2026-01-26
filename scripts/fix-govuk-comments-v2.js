/**
 * Fix corrupted GOV.UK compliance comments v2
 * Removes incomplete vendor-prefix lines entirely
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

function getCssFiles(dir) {
    const files = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            if (entry.name === 'purged') continue;
            files.push(...getCssFiles(fullPath));
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Pattern 1: Lines like "-webkit-/* comment */" - remove entire line
    const vendorCommentPattern = /^\s*(-webkit-|-moz-|-ms-|-o-)\/\*[^*]*\*\/\s*$/gm;
    let matches = content.match(vendorCommentPattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(vendorCommentPattern, '');
    }

    // Pattern 2: Lines like "    -webkit-" alone (incomplete) - remove
    const incompleteVendorPattern = /^\s*(-webkit-|-moz-|-ms-|-o-)\s*$/gm;
    matches = content.match(incompleteVendorPattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(incompleteVendorPattern, '');
    }

    // Pattern 3: CSS property declarations that have broken comment mid-value
    // Like: backdrop-filter: /* comment */saturate(180%) blur(10px);
    // Convert to: /* backdrop-filter: saturate(180%) blur(10px); - removed for GOV.UK compliance */
    const brokenPropertyPattern = /^(\s*)([\w-]+):\s*\/\*[^*]*\*\/\s*([^;]+);/gm;
    matches = content.match(brokenPropertyPattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(brokenPropertyPattern, '$1/* $2: $3; - removed for GOV.UK compliance */');
    }

    // Pattern 4: Lines that start with comment then have CSS filter value (no property)
    // Like: /* comment */ saturate(180%);
    const commentThenValuePattern = /^\s*\/\*[^*]*\*\/\s*(saturate|blur|brightness|contrast|grayscale|hue-rotate|invert|opacity|sepia|drop-shadow)\([^)]*\)[^;]*;?\s*$/gm;
    matches = content.match(commentThenValuePattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(commentThenValuePattern, '');
    }

    // Pattern 5: Lines with -webkit- comment then value
    // Like: -webkit-/* comment */ saturate(180%);
    const vendorCommentValuePattern = /^\s*-webkit-\/\*[^*]*\*\/\s*[^;]+;?\s*$/gm;
    matches = content.match(vendorCommentValuePattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(vendorCommentValuePattern, '');
    }

    // Clean up multiple consecutive empty lines
    content = content.replace(/\n{3,}/g, '\n\n');

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        return fixCount;
    }

    return 0;
}

// Main
const files = getCssFiles(cssDir);
let totalFixes = 0;

files.forEach(file => {
    const fixes = fixFile(file);
    if (fixes > 0) {
        console.log(`Fixed ${fixes} issues in ${path.relative(cssDir, file)}`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);
