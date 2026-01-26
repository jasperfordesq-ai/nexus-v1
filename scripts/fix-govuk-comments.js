/**
 * Fix corrupted GOV.UK compliance comments
 * Patterns like: -webkit-[comment] or property[comment]
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

    // Pattern 1: -webkit-/* comment */ or -moz-/* comment */
    // Should become: /* -webkit-property comment */
    const vendorPrefixPattern = /(-webkit-|-moz-|-ms-|-o-)(\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/)/g;
    let matches = content.match(vendorPrefixPattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(vendorPrefixPattern, '/* $1property $2 */');
    }

    // Pattern 2: property: /* comment */; (missing value)
    // Just remove the whole line or convert to comment
    const emptyValuePattern = /^\s*([\w-]+):\s*(\/\*[^*]*\*\/)\s*;?\s*$/gm;
    matches = content.match(emptyValuePattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(emptyValuePattern, '    /* $1 $2 */');
    }

    // Pattern 3: Standalone -webkit- or -moz- at start of line (incomplete property)
    const standalonePrefixPattern = /^\s*(-webkit-|-moz-|-ms-|-o-)$/gm;
    matches = content.match(standalonePrefixPattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(standalonePrefixPattern, '');
    }

    // Pattern 4: Lines that start with a property value (no selector)
    // Like: border: 1px... when it should be inside a rule
    // This is harder to fix automatically - flag but don't auto-fix

    // Pattern 5: Orphaned closing comments without opening
    // Pattern: */ without a preceding /*
    // Actually this is fine - just part of multi-line comments

    // Pattern 6: Lines that are just "/* comment */" followed by a property on next line
    // with no selector - remove the orphaned property lines
    const orphanedPropertyPattern = /^(\s*)(border|padding|margin|font|color|background|display|width|height|opacity|transform|transition|animation|filter|backdrop-filter|box-shadow|text)([^{};]*);?\s*$/gm;
    // Only remove if not inside a rule (this is complex to detect)

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
