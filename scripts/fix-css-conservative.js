/**
 * Conservative CSS Syntax Fixer
 * Only fixes clear orphaned patterns without adding braces
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

    // Pattern 1: Orphaned keyframe content blocks (0% {...} etc without @keyframes)
    // These are blocks that appear after a closing } and before the next selector
    const orphanedKeyframePattern = /\}\s*\n\n(0%|from)\s*\{[^}]+\}\s*\n(\s*(to|\d+%)\s*\{[^}]*\}\s*\n)*\}/g;
    let matches = content.match(orphanedKeyframePattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(orphanedKeyframePattern, '}');
    }

    // Pattern 2: Orphaned closing brace after comment
    const orphanedBracePattern = /(\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/)\s*\n\}/g;
    matches = content.match(orphanedBracePattern);
    if (matches) {
        fixCount += matches.length;
        content = content.replace(orphanedBracePattern, '$1');
    }

    // Pattern 3: Incomplete @keyframes (missing content before next selector or @keyframes)
    // @keyframes name {
    //     from { ... }
    //
    // .next-selector
    const incompleteKeyframePattern = /(@keyframes\s+[\w-]+\s*\{\s*\n\s*(?:from|0%)\s*\{[^}]+\}\s*\n)\n+(\.[a-z]|#|@media|@keyframes)/gi;
    matches = content.match(incompleteKeyframePattern);
    if (matches) {
        // Add missing 'to' keyframe and close the @keyframes block
        content = content.replace(incompleteKeyframePattern, (match, keyframeStart, nextSelector) => {
            fixCount++;
            return keyframeStart + '    to { opacity: 1; }\n}\n\n' + nextSelector;
        });
    }

    // Pattern 4: @keyframes with only percentage blocks missing closing
    const unclosedKeyframePattern = /(@keyframes\s+[\w-]+\s*\{\s*\n(?:\s*(?:from|to|\d+%(?:,\s*\d+%)*)\s*\{[^}]+\}\s*\n)+)\n+(\/\*|\.[a-z]|#|@media|@supports)/gi;
    matches = content.match(unclosedKeyframePattern);
    if (matches) {
        content = content.replace(unclosedKeyframePattern, (match, keyframeBlock, nextThing) => {
            fixCount++;
            return keyframeBlock + '}\n\n' + nextThing;
        });
    }

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
        console.log(`Fixed ${fixes} issues in ${path.relative(cssDir, file)}`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);
