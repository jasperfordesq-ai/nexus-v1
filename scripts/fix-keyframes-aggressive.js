/**
 * Aggressively fix ALL incomplete @keyframes blocks in CSS files
 *
 * This script finds @keyframes blocks that are missing their closing brace
 * and adds it back, along with a 50% or 'to' keyframe if missing.
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

    // Find all @keyframes blocks
    // Pattern: @keyframes name { ... } where ... may be incomplete
    const keyframesRegex = /@keyframes\s+(\w+)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/g;

    // First, let's handle incomplete @keyframes followed by a CSS selector
    // Pattern: @keyframes name {\n  ...keyframe content without closing brace...\n\n.selector or /* comment

    // This pattern matches @keyframes that are NOT properly closed
    // i.e., the content after the @keyframes { doesn't have a matching }
    const incompletePattern = /@keyframes\s+(\w+)\s*\{\s*\r?\n((?:\s*(?:\d+%(?:,\s*\d+%)*|from|to)\s*\{[^}]*\}\s*\r?\n)*)\r?\n(?=[@.#\[\/])/gm;

    let match;
    const replacements = [];

    // Reset regex
    incompletePattern.lastIndex = 0;

    while ((match = incompletePattern.exec(content)) !== null) {
        const name = match[1];
        const keyframeContent = match[2];
        const fullMatch = match[0];
        const startPos = match.index;
        const endPos = match.index + fullMatch.length;

        // Determine what keyframe to add
        let additionalKeyframe = '';
        if (keyframeContent.includes('from {') && !keyframeContent.includes('to {')) {
            additionalKeyframe = '    to { opacity: 1; transform: translateY(0); }\n';
        } else if (keyframeContent.includes('0%') && !keyframeContent.includes('50%') && !keyframeContent.includes('100% {')) {
            // Single 0% or 0%, 100% on same line - add 50%
            additionalKeyframe = '    50% { opacity: 0.8; }\n';
        } else if (!keyframeContent.includes('100%') && keyframeContent.includes('0%')) {
            additionalKeyframe = '    100% { opacity: 1; }\n';
        }

        // Build the fixed keyframe block
        const fixedBlock = `@keyframes ${name} {\n${keyframeContent}${additionalKeyframe}}\n\n`;

        replacements.push({
            start: startPos,
            end: endPos,
            replacement: fixedBlock
        });

        fixCount++;
    }

    // Apply replacements in reverse order to not mess up positions
    replacements.sort((a, b) => b.start - a.start);
    for (const r of replacements) {
        content = content.substring(0, r.start) + r.replacement + content.substring(r.end);
    }

    // Also handle completely empty @keyframes blocks
    content = content.replace(/@keyframes\s+\w+\s*\{\s*\}/g, '');

    // Handle single-line @keyframes with single keyframe missing close
    // Pattern: @keyframes name {\n    0% { ... }\n\n.class
    const singleLinePattern = /@keyframes\s+(\w+)\s*\{\s*\r?\n(\s*(?:(\d+%(?:,\s*\d+%)*)|from|to)\s*\{[^}]+\})\s*\r?\n\r?\n(?=[@.#\[\/\*])/gm;

    content = content.replace(singleLinePattern, (match, name, keyframeContent, percentages) => {
        // Determine what's missing
        let additionalKeyframe = '';
        if (match.includes('from') && !match.includes('to')) {
            additionalKeyframe = '    to { opacity: 1; }\n';
        } else if (match.includes('0%') && !match.includes('50%') && !match.includes('100% {')) {
            if (percentages && percentages.includes('100')) {
                // Already has 0%, 100% - just close it
                additionalKeyframe = '';
            } else {
                additionalKeyframe = '    100% { opacity: 1; }\n';
            }
        }

        fixCount++;
        return `@keyframes ${name} {\n${keyframeContent}\n${additionalKeyframe}}\n\n`;
    });

    // Clean up excessive newlines
    content = content.replace(/\n{3,}/g, '\n\n');

    if (content !== originalContent) {
        fs.writeFileSync(file, content);
        console.log(`Fixed ${fixCount} keyframes in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal keyframes fixed: ${totalFixes}`);
