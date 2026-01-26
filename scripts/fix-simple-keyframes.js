/**
 * Simple but thorough @keyframes fixer
 *
 * Finds ALL @keyframes blocks that are followed by a CSS selector
 * without a closing brace and fixes them.
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

    // Pattern 1: @keyframes with single keyframe line followed by class/rule
    // @keyframes name {\n    XX% { ... }\n\n.class
    const pattern1 = /@keyframes\s+([\w-]+)\s*\{\s*\n(\s*(from|to|\d+%(?:,\s*\d+%)*)\s*\{[^}]+\})\s*\n\n(?=[\.\#\[\*@a-zA-Z])/g;
    content = content.replace(pattern1, (match, name, keyframeContent) => {
        fixCount++;
        // Add completion based on content
        const trimmed = keyframeContent.trim();
        let completion = '';
        if (trimmed.includes('from {') && !trimmed.includes('to {')) {
            completion = '    to { opacity: 1; }\n';
        } else if (trimmed.includes('0%') && !trimmed.match(/100%\s*\{/)) {
            completion = '    100% { opacity: 1; }\n';
        }
        return `@keyframes ${name} {\n${keyframeContent}\n${completion}}\n\n`;
    });

    // Pattern 2: @keyframes with multiple keyframe lines followed by class/rule
    // @keyframes name {\n    0% {...}\n    50% {...}\n\n.class
    const pattern2 = /@keyframes\s+([\w-]+)\s*\{\s*\n((?:\s*(?:from|to|\d+%(?:,\s*\d+%)*)\s*\{[^}]+\}\s*\n)+)\n(?=[\.\#\[\*@a-zA-Z])/g;
    content = content.replace(pattern2, (match, name, keyframeContent) => {
        // Check if already has a closing brace somewhere
        const lines = keyframeContent.split('\n');
        const lastNonEmpty = lines.filter(l => l.trim()).pop() || '';
        if (lastNonEmpty.trim().endsWith('}')) {
            // Content is complete, just missing the keyframes closing brace
            fixCount++;
            return `@keyframes ${name} {\n${keyframeContent}}\n\n`;
        }
        return match;
    });

    // Pattern 3: @keyframes with multi-line keyframe (0% { ... } with newlines inside)
    // @keyframes name {\n    0% {\n        ...\n    }\n\n.class
    const pattern3 = /@keyframes\s+([\w-]+)\s*\{\s*\n(\s*(?:from|to|\d+%(?:,\s*\d+%)*)\s*\{\s*\n(?:[^}]*\n)*\s*\})\s*\n\n(?=[\.\#\[\*@a-zA-Z\/])/g;
    content = content.replace(pattern3, (match, name, keyframeContent) => {
        fixCount++;
        const trimmed = keyframeContent.trim();
        let completion = '';
        if (trimmed.includes('from {') && !trimmed.includes('to {')) {
            completion = '    to { opacity: 1; }\n';
        } else if (trimmed.includes('0%') && !trimmed.match(/100%\s*\{/)) {
            completion = '    100% { opacity: 1; }\n';
        }
        return `@keyframes ${name} {\n${keyframeContent}\n${completion}}\n\n`;
    });

    // Pattern 4: Multi-line multi-keyframe @keyframes missing close
    const pattern4 = /@keyframes\s+([\w-]+)\s*\{\s*\n((?:\s*(?:from|to|\d+%(?:,\s*\d+%)*)\s*\{\s*\n(?:[^}]*\n)*\s*\}\s*\n)+)\n(?=[\.\#\[\*@a-zA-Z\/])/g;
    content = content.replace(pattern4, (match, name, keyframeContent) => {
        fixCount++;
        return `@keyframes ${name} {\n${keyframeContent}}\n\n`;
    });

    // Clean up
    content = content.replace(/\n{3,}/g, '\n\n');

    if (content !== originalContent && fixCount > 0) {
        fs.writeFileSync(file, content);
        console.log(`Fixed ${fixCount} keyframes in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal keyframes fixed: ${totalFixes}`);
