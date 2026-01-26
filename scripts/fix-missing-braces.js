/**
 * Fix missing closing braces in CSS files
 * Pattern: property;\n\n/*comment or .selector (without closing brace before)
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
            if (entry.name === 'bundles' || entry.name === 'purged') continue;
            files.push(...getCssFiles(fullPath));
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

function fixCssFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Pattern: property-value;\n\n/* or .selector (without } before)
    // Match: something: value;\n\n(/* or . or # or @media etc)
    // Should add } before the \n\n
    const missingBracePattern = /(:\s*[^;{}]+;\s*(?:\/\*[^*]*\*\/)?)\r?\n\r?\n(\/\*|\.[a-z]|#[a-z]|@media|@keyframes|@supports)/gi;

    content = content.replace(missingBracePattern, (match, propValue, nextThing) => {
        // Check if there's already a closing brace
        if (propValue.trim().endsWith('}')) {
            return match;
        }
        fixCount++;
        return propValue + '\n}\n\n' + nextThing;
    });

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        return fixCount;
    }

    return 0;
}

const files = getCssFiles(cssDir);
let totalFixes = 0;

files.forEach(file => {
    const fixes = fixCssFile(file);
    if (fixes > 0) {
        console.log(`Fixed ${fixes} missing braces in ${path.relative(cssDir, file)}`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);

// Verify by checking brace balance
let errorCount = 0;
files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8');
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    if (opens !== closes) {
        console.log(`WARNING: Brace mismatch in ${path.relative(cssDir, file)}: ${opens} opens, ${closes} closes (diff: ${opens - closes})`);
        errorCount++;
    }
});

if (errorCount === 0) {
    console.log('\nAll files have balanced braces!');
} else {
    console.log(`\n${errorCount} files still have brace mismatches`);
}
