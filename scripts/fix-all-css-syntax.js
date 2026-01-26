/**
 * Comprehensive CSS Syntax Fixer
 * Fixes multiple types of CSS syntax errors across all files
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Get all CSS files recursively
function getCssFiles(dir) {
    const files = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            // Skip bundles, purged, and minified directories
            if (entry.name === 'bundles' || entry.name === 'purged') continue;
            files.push(...getCssFiles(fullPath));
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

// Fix a single CSS file
function fixCssFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Pattern 1: Missing closing brace before next selector (but not inside @keyframes or @media)
    // Looks for: property;\n\n.next-selector { (without } before it)
    const missingBracePattern = /(;\s*)\n\n(\.[a-z]|\#[a-z]|@media|@keyframes|@supports)/gi;

    // We need to be smarter - check if we're inside an unclosed block
    const lines = content.split('\n');
    const fixedLines = [];
    let braceDepth = 0;
    let inComment = false;

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];

        // Track multi-line comments
        if (line.includes('/*') && !line.includes('*/')) {
            inComment = true;
        }
        if (line.includes('*/')) {
            inComment = false;
        }

        if (!inComment) {
            // Count braces
            const opens = (line.match(/\{/g) || []).length;
            const closes = (line.match(/\}/g) || []).length;

            // Check if this line starts a new rule but previous rule isn't closed
            const startsNewRule = /^(\.[a-z]|#[a-z]|@media|@keyframes|@supports|:root)/i.test(line.trim()) && line.includes('{');
            const prevLine = fixedLines.length > 0 ? fixedLines[fixedLines.length - 1] : '';
            const prevNonEmpty = [...fixedLines].reverse().find(l => l.trim().length > 0);

            if (startsNewRule && braceDepth > 0 && prevNonEmpty && !prevNonEmpty.trim().endsWith('}') && !prevNonEmpty.trim().endsWith('{')) {
                // Need to close the previous block
                fixedLines.push('}');
                braceDepth--;
                fixCount++;
            }

            braceDepth += opens - closes;
        }

        fixedLines.push(line);
    }

    // Add missing closing braces at end of file
    while (braceDepth > 0) {
        fixedLines.push('}');
        braceDepth--;
        fixCount++;
    }

    content = fixedLines.join('\n');

    // Pattern 2: Remove orphaned keyframe content (0% { } without @keyframes)
    const orphanedKeyframes = /\n\n(0%|from)\s*\{[^}]+\}\s*\n(\s*(to|\d+%)\s*\{[^}]*\}\s*\n)+\}/g;
    const orphanedMatches = content.match(orphanedKeyframes);
    if (orphanedMatches) {
        fixCount += orphanedMatches.length;
        content = content.replace(orphanedKeyframes, '\n');
    }

    // Pattern 3: Fix standalone orphaned closing braces after comments
    const orphanedBrace = /(\/\*[^*]*\*\/)\s*\n\}/g;
    const braceMatches = content.match(orphanedBrace);
    if (braceMatches) {
        fixCount += braceMatches.length;
        content = content.replace(orphanedBrace, '$1');
    }

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        return fixCount;
    }

    return 0;
}

// Main execution
const files = getCssFiles(cssDir);
let totalFixes = 0;

files.forEach(file => {
    const fixes = fixCssFile(file);
    if (fixes > 0) {
        console.log(`Fixed ${fixes} issues in ${path.relative(cssDir, file)}`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);

// Verify by checking brace balance in all files
let errorCount = 0;
files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8');
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    if (opens !== closes) {
        console.log(`WARNING: Brace mismatch in ${path.relative(cssDir, file)}: ${opens} opens, ${closes} closes`);
        errorCount++;
    }
});

if (errorCount === 0) {
    console.log('\nAll files have balanced braces!');
} else {
    console.log(`\n${errorCount} files still have brace mismatches`);
}
