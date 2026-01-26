/**
 * Final comprehensive CSS syntax fixer
 *
 * This script parses all CSS files and ensures every @keyframes block
 * is properly closed with the correct number of braces.
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

function fixKeyframes(content) {
    let fixCount = 0;

    // Split content by @keyframes blocks
    const keyframesRegex = /@keyframes\s+([\w-]+)\s*\{/g;
    let result = '';
    let lastIndex = 0;
    let match;

    while ((match = keyframesRegex.exec(content)) !== null) {
        const keyframeName = match[1];
        const keyframeStart = match.index;
        const blockStart = keyframeStart + match[0].length;

        // Add everything before this @keyframes
        result += content.slice(lastIndex, keyframeStart + match[0].length);

        // Find the content of this @keyframes block
        let braceCount = 1;
        let i = blockStart;
        let keyframeContent = '';

        while (i < content.length && braceCount > 0) {
            const char = content[i];
            if (char === '{') {
                braceCount++;
            } else if (char === '}') {
                braceCount--;
            }
            if (braceCount > 0) {
                keyframeContent += char;
            }
            i++;
        }

        // Check if we found a closing brace
        if (braceCount !== 0) {
            // Need to fix this @keyframes

            // Find where the keyframe content ends (before next CSS rule)
            const nextRuleMatch = keyframeContent.match(/\n\s*(?:[@.#\[]|\/\*\s*={3,})/);

            if (nextRuleMatch) {
                const validContent = keyframeContent.slice(0, nextRuleMatch.index);
                const restContent = keyframeContent.slice(nextRuleMatch.index);

                // Check what's in the valid content and add missing parts
                let fixedContent = validContent.trim();

                // Add missing keyframe if needed
                if (fixedContent.includes('from {') && !fixedContent.includes('to {')) {
                    fixedContent += '\n    to { opacity: 1; }';
                } else if (fixedContent.includes('0%') && !fixedContent.match(/100%\s*\{/)) {
                    // Has 0% but no 100%
                    if (!fixedContent.includes('50%')) {
                        fixedContent += '\n    50% { opacity: 0.8; }';
                    }
                    fixedContent += '\n    100% { opacity: 1; }';
                }

                result += '\n' + fixedContent + '\n}\n' + restContent;
                lastIndex = keyframeStart + match[0].length + keyframeContent.length;
                fixCount++;
            } else {
                // No next rule found, just close the keyframe
                let fixedContent = keyframeContent.trim();

                if (fixedContent.includes('from {') && !fixedContent.includes('to {')) {
                    fixedContent += '\n    to { opacity: 1; }';
                } else if (fixedContent.includes('0%') && !fixedContent.match(/100%\s*\{/)) {
                    if (!fixedContent.includes('50%')) {
                        fixedContent += '\n    50% { opacity: 0.8; }';
                    }
                }

                result += '\n' + fixedContent + '\n}\n';
                lastIndex = content.length;
                fixCount++;
            }
        } else {
            // Properly closed, just add the content + closing brace
            result += keyframeContent + '}';
            lastIndex = i;
        }
    }

    // Add any remaining content after the last @keyframes
    result += content.slice(lastIndex);

    return { content: result, fixCount };
}

const files = getAllCssFiles(cssDir);
let totalFixes = 0;

for (const file of files) {
    let content = fs.readFileSync(file, 'utf8');
    const originalContent = content;

    const { content: fixedContent, fixCount } = fixKeyframes(content);

    // Clean up excessive newlines
    let finalContent = fixedContent.replace(/\n{3,}/g, '\n\n');

    if (finalContent !== originalContent && fixCount > 0) {
        fs.writeFileSync(file, finalContent);
        console.log(`Fixed ${fixCount} keyframes in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal keyframes fixed: ${totalFixes}`);
