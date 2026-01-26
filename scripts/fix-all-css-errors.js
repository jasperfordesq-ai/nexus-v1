/**
 * Comprehensive CSS Error Fixer
 * Iteratively runs all fix scripts until no more issues found
 */

const { execSync } = require('child_process');
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
            // Include bundles this time
            if (entry.name === 'purged') continue;
            files.push(...getCssFiles(fullPath));
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

// Pattern fixes
function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Fix 1: Unclosed selectors (missing closing brace before next selector)
    // Pattern: property: value;\n\n(/* or .selector or #selector or @media)
    const unclosedSelector = /(:\s*[^;{}]+;\s*(?:\/\*[^*]*\*\/)?)\r?\n\r?\n(\/\*|\.[a-z]|#[a-z]|@media|@keyframes|@supports|::|:root)/gi;
    let matches = content.match(unclosedSelector);
    if (matches) {
        content = content.replace(unclosedSelector, (m, prop, next) => {
            if (prop.trim().endsWith('}')) return m;
            fixCount++;
            return prop + '\n}\n\n' + next;
        });
    }

    // Fix 2: Orphaned keyframe content (percentage blocks without @keyframes)
    const orphanedKeyframes = /\}\s*\r?\n\r?\n(0%|from)\s*\{[^}]+\}\s*\r?\n(\s*(to|\d+%)\s*\{[^}]*\}\s*\r?\n)*\}/g;
    matches = content.match(orphanedKeyframes);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(orphanedKeyframes, '}');
    }

    // Fix 3: Incomplete @keyframes (single keyframe then next selector)
    const incompleteKeyframes = /(@keyframes\s+[\w-]+\s*\{\s*\r?\n\s*(?:from|0%)\s*\{[^}]+\}\s*\r?\n)\r?\n+(\/\*|\.[a-z]|#|@media|@keyframes)/gi;
    matches = content.match(incompleteKeyframes);
    if (matches) {
        content = content.replace(incompleteKeyframes, (m, kf, next) => {
            fixCount++;
            return kf + '    to { opacity: 1; }\n}\n\n' + next;
        });
    }

    // Fix 4: Unclosed @keyframes
    const unclosedKeyframes = /(@keyframes\s+[\w-]+\s*\{\s*\r?\n(?:\s*(?:from|to|\d+%(?:,\s*\d+%)*)\s*\{[^}]+\}\s*\r?\n)+)\r?\n+(\/\*|\.[a-z]|#|@media|@supports)/gi;
    matches = content.match(unclosedKeyframes);
    if (matches) {
        content = content.replace(unclosedKeyframes, (m, kf, next) => {
            fixCount++;
            return kf + '}\n\n' + next;
        });
    }

    // Fix 5: Orphaned closing brace after comment
    const orphanedBrace = /(\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/)\s*\r?\n\}/g;
    matches = content.match(orphanedBrace);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(orphanedBrace, '$1');
    }

    // Fix 6: Standalone orphaned percentage/to blocks
    const standaloneOrphans = /\r?\n\r?\n\s*(to|from|\d+%)\s*\{[^}]*\}\s*\r?\n\}/g;
    matches = content.match(standaloneOrphans);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(standaloneOrphans, '\n');
    }

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        return fixCount;
    }

    return 0;
}

// Main execution
let iteration = 0;
let totalFixesThisIteration;
const maxIterations = 10;

do {
    iteration++;
    totalFixesThisIteration = 0;
    console.log(`\n=== Iteration ${iteration} ===`);

    const files = getCssFiles(cssDir);
    files.forEach(file => {
        const fixes = fixFile(file);
        if (fixes > 0) {
            console.log(`Fixed ${fixes} issues in ${path.relative(cssDir, file)}`);
            totalFixesThisIteration += fixes;
        }
    });

    console.log(`Total fixes this iteration: ${totalFixesThisIteration}`);
} while (totalFixesThisIteration > 0 && iteration < maxIterations);

console.log(`\n=== Complete after ${iteration} iterations ===`);

// Verify brace balance
console.log('\nVerifying brace balance...');
let errorCount = 0;
const files = getCssFiles(cssDir);
files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8');
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    if (opens !== closes) {
        console.log(`WARNING: ${path.relative(cssDir, file)}: ${opens} opens, ${closes} closes (diff: ${opens - closes})`);
        errorCount++;
    }
});

if (errorCount === 0) {
    console.log('All files have balanced braces!');
} else {
    console.log(`\n${errorCount} files still have brace mismatches`);
}
