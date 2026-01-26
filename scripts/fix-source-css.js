/**
 * Fix orphaned keyframe content in source CSS files
 * This script removes orphaned keyframe content (0% { ... } without @keyframes)
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Get all CSS files that are not bundles or minified
function getCssFiles(dir) {
    const files = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            // Skip bundles and purged directories
            if (entry.name === 'bundles' || entry.name === 'purged') continue;
            files.push(...getCssFiles(fullPath));
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

// Patterns to fix
const patterns = [
    // Pattern 1: Orphaned keyframe content after closing brace - most common
    // }
    //
    // 0% { ... }
    //     to { ... }
    // }
    {
        pattern: /\}\s*\n\n(0%|from|\d+%)\s*\{[^}]+\}\s*\n(\s*(to|from|\d+%)\s*\{[^}]*\}\s*\n)*\}/g,
        replacement: '}'
    },

    // Pattern 2: Standalone orphaned blocks (not after a closing brace)
    {
        pattern: /\n\n(0%|from)\s*\{[^}]+\}\s*\n(\s*(to|\d+%)\s*\{[^}]*\}\s*\n)+\}/g,
        replacement: '\n'
    },

    // Pattern 3: Single orphaned closing brace after comment
    {
        pattern: /(\/\*[^*]*\*\/)\s*\n\}/g,
        replacement: '$1'
    }
];

const files = getCssFiles(cssDir);
let totalFixes = 0;

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    for (const { pattern, replacement } of patterns) {
        const matches = content.match(pattern);
        if (matches) {
            fixCount += matches.length;
            content = content.replace(pattern, replacement);
        }
    }

    if (content !== originalContent) {
        fs.writeFileSync(file, content, 'utf8');
        console.log(`Fixed ${fixCount} issues in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
});

console.log(`\nTotal source file fixes: ${totalFixes}`);
