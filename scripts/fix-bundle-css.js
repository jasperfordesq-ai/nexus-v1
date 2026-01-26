/**
 * Fix bundle CSS files with duplicate/orphaned content
 *
 * These patterns are caused by GOV.UK compliance modifications
 * that corrupted keyframes and left orphaned content
 */

const fs = require('fs');
const path = require('path');

const bundleDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css', 'bundles');

// Pattern 1: Orphaned keyframe content (no @keyframes declaration)
// Matches: from { ... } or to { ... } or 0% { ... } etc at the start of a line
const orphanedKeyframePattern = /\n\n(from|to|\d+%(?:,\s*\d+%)*)[\s]*\{[^}]+\}\s*\n(\s*(from|to|\d+%(?:,\s*\d+%)*)[\s]*\{[^}]*\}\s*\n)*\}/g;

// Pattern 2: Incomplete to { opacity: 1; } on its own line followed by }
const incompleteToPattern = /\n\s*to\s*\{\s*opacity:\s*1;\s*\}\s*\n\}/g;

// Pattern 3: Orphaned closing brace after a comment
const orphanedBraceAfterComment = /\n\/\*[^*]*\*\/\n\}\n/g;

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixes = 0;

    // Fix pattern 1: Remove orphaned keyframe content blocks
    let match;
    while ((match = orphanedKeyframePattern.exec(content)) !== null) {
        // Find the match and remove it
        const before = content.slice(0, match.index);
        const after = content.slice(match.index + match[0].length);
        content = before + '\n' + after;
        fixes++;
        orphanedKeyframePattern.lastIndex = 0; // Reset regex
    }

    // Fix pattern 2: Remove incomplete 'to' blocks
    content = content.replace(incompleteToPattern, () => {
        fixes++;
        return '\n';
    });

    // Fix pattern 3: Remove orphaned braces after comments (but keep the comment)
    content = content.replace(/\n(\/\*[^*]*\*\/)\n\}\n/g, (match, comment) => {
        fixes++;
        return '\n' + comment + '\n';
    });

    // Clean up excessive newlines
    content = content.replace(/\n{4,}/g, '\n\n\n');

    if (content !== originalContent && fixes > 0) {
        fs.writeFileSync(filePath, content);
        console.log(`Fixed ${fixes} issues in ${path.basename(filePath)}`);
        return fixes;
    }
    return 0;
}

// Process all bundle CSS files
const files = fs.readdirSync(bundleDir).filter(f => f.endsWith('.css') && !f.endsWith('.min.css'));
let totalFixes = 0;

for (const file of files) {
    const filePath = path.join(bundleDir, file);
    totalFixes += fixFile(filePath);
}

console.log(`\nTotal fixes: ${totalFixes}`);
