/**
 * Clean up duplicate orphaned keyframe content left by previous fix scripts
 *
 * Patterns to remove:
 * - Orphaned "to { ... }" or "50% { ... }" lines not inside @keyframes
 * - Double closing braces
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

function getAllCssFiles(dir, files = []) {
    const items = fs.readdirSync(dir);
    for (const item of items) {
        const fullPath = path.join(dir, item);
        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            getAllCssFiles(fullPath, files);
        } else if (item.endsWith('.css') && !item.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }
    return files;
}

const files = getAllCssFiles(cssDir);
let totalFixes = 0;

for (const file of files) {
    let content = fs.readFileSync(file, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Remove orphaned keyframe lines that appear after a properly closed @keyframes
    // Pattern: }\n\n    to { ... }\n}\n
    const orphanedToPattern = /\}\s*\n\s*\n\s*(to|50%|100%)\s*\{[^}]+\}\s*\n\s*\}/g;
    let match = content.match(orphanedToPattern);
    if (match) {
        content = content.replace(orphanedToPattern, '}');
        fixCount += match.length;
    }

    // Remove standalone keyframe content not inside @keyframes
    // Pattern: line starts with "    to {" or "    50% {" and is not preceded by @keyframes line
    // This needs line-by-line analysis
    const lines = content.split('\n');
    const newLines = [];
    let inKeyframes = false;
    let braceCount = 0;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        // Track @keyframes blocks
        if (trimmed.match(/^@keyframes\s+/)) {
            inKeyframes = true;
            braceCount = 0;
        }

        if (inKeyframes) {
            braceCount += (line.match(/\{/g) || []).length;
            braceCount -= (line.match(/\}/g) || []).length;

            if (braceCount === 0 && i > 0) {
                inKeyframes = false;
            }
        }

        // Check for orphaned keyframe content (not inside @keyframes)
        if (!inKeyframes) {
            // Orphaned keyframe percentages or to/from
            if (trimmed.match(/^(to|\d+%|from)\s*\{/) && !trimmed.match(/^@/)) {
                // Skip this line and any following closing brace
                fixCount++;
                // Also skip the closing brace if it's on the next line
                if (i + 1 < lines.length && lines[i + 1].trim() === '}') {
                    i++; // Skip the closing brace too
                }
                continue;
            }

            // Orphaned closing brace after an empty line following a closed block
            if (trimmed === '}' && i > 0) {
                const prevLine = lines[i - 1].trim();
                const prevPrevLine = i > 1 ? lines[i - 2].trim() : '';
                if (prevLine === '' && (prevPrevLine === '}' || prevPrevLine.endsWith('}'))) {
                    fixCount++;
                    continue; // Skip this orphaned brace
                }
            }
        }

        newLines.push(line);
    }

    content = newLines.join('\n');

    // Clean up excessive newlines
    content = content.replace(/\n{3,}/g, '\n\n');

    if (content !== originalContent && fixCount > 0) {
        fs.writeFileSync(file, content);
        console.log(`Cleaned ${fixCount} duplicates in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal duplicates cleaned: ${totalFixes}`);
