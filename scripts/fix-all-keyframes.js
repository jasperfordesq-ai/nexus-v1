/**
 * Fix ALL incomplete @keyframes blocks
 *
 * This script parses CSS files and fixes any @keyframes blocks
 * that are missing their closing braces.
 *
 * Strategy: Find @keyframes { followed by keyframe content,
 * but where the closing } is missing (next line starts a new rule)
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

    // Split into lines for analysis
    const lines = content.split('\n');
    const newLines = [];
    let inKeyframes = false;
    let keyframeName = '';
    let keyframeContent = [];
    let braceDepth = 0;
    let keyframeStartLine = -1;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        // Check if starting a new @keyframes
        const keyframeMatch = trimmed.match(/^@keyframes\s+(\w+[-\w]*)\s*\{?\s*$/);
        if (keyframeMatch) {
            // If we were already in a keyframes (incomplete), close it first
            if (inKeyframes) {
                // Add missing close
                if (keyframeContent.length > 0) {
                    // Check if the last content has only 0% or from, add complimentary
                    const lastContent = keyframeContent.join('\n');
                    let additionalKeyframe = '';
                    if (lastContent.includes('from {') && !lastContent.includes('to {')) {
                        additionalKeyframe = '    to { opacity: 1; }';
                    } else if ((lastContent.includes('0%') || lastContent.includes('0%, 100%')) &&
                        !lastContent.includes('50%') &&
                        !lastContent.match(/100%\s*\{[^}]+\}/)) {
                        additionalKeyframe = '    50% { opacity: 0.8; }';
                    }

                    if (additionalKeyframe) {
                        newLines.push(additionalKeyframe);
                    }
                    newLines.push('}');
                    newLines.push('');
                    fixCount++;
                }
            }

            // Start new keyframes
            inKeyframes = true;
            keyframeName = keyframeMatch[1];
            keyframeContent = [];
            braceDepth = 1; // Opening brace of @keyframes
            keyframeStartLine = i;

            // Check if the line already includes the opening brace
            if (!trimmed.endsWith('{')) {
                newLines.push(line + ' {');
            } else {
                newLines.push(line);
            }
            continue;
        }

        if (inKeyframes) {
            // Count braces
            const openBraces = (line.match(/\{/g) || []).length;
            const closeBraces = (line.match(/\}/g) || []).length;
            braceDepth += openBraces - closeBraces;

            // Check if this line is a new CSS rule (selector or comment that starts new block)
            // that indicates the @keyframes wasn't closed
            const isNewRule = (
                trimmed.match(/^[.#\[]/) || // Class, ID, or attribute selector
                trimmed.match(/^[a-z][a-z-]*[^{]*\{/) || // Element selector
                trimmed.match(/^@media\s/) || // Media query
                trimmed.match(/^@keyframes\s/) || // Another keyframes
                trimmed.match(/^@supports\s/) || // Supports query
                trimmed.match(/^\/\*\s*={3,}/) // Section comment
            );

            // If we hit a new rule and braceDepth is 1, the @keyframes wasn't closed
            if (isNewRule && braceDepth === 1 && keyframeContent.length > 0) {
                // Close the keyframes
                const lastContent = keyframeContent.join('\n');
                let additionalKeyframe = '';
                if (lastContent.includes('from {') && !lastContent.includes('to {')) {
                    additionalKeyframe = '    to { opacity: 1; }';
                } else if ((lastContent.includes('0%') || lastContent.includes('0%, 100%')) &&
                    !lastContent.includes('50%') &&
                    !lastContent.match(/100%\s*\{[^}]+\}/)) {
                    additionalKeyframe = '    50% { opacity: 0.8; }';
                }

                if (additionalKeyframe) {
                    newLines.push(additionalKeyframe);
                }
                newLines.push('}');
                newLines.push('');
                fixCount++;

                inKeyframes = false;
                keyframeContent = [];
                braceDepth = 0;
            }

            if (inKeyframes) {
                // Still inside keyframes, add to content
                keyframeContent.push(line);
                newLines.push(line);

                // Check if keyframes is properly closed
                if (braceDepth === 0) {
                    inKeyframes = false;
                    keyframeContent = [];
                }
                continue;
            }
        }

        // Not in keyframes or keyframes was just closed
        newLines.push(line);
    }

    // Handle case where file ends while still in keyframes
    if (inKeyframes && keyframeContent.length > 0) {
        const lastContent = keyframeContent.join('\n');
        let additionalKeyframe = '';
        if (lastContent.includes('from {') && !lastContent.includes('to {')) {
            additionalKeyframe = '    to { opacity: 1; }';
        } else if ((lastContent.includes('0%') || lastContent.includes('0%, 100%')) &&
            !lastContent.includes('50%') &&
            !lastContent.match(/100%\s*\{[^}]+\}/)) {
            additionalKeyframe = '    50% { opacity: 0.8; }';
        }

        if (additionalKeyframe) {
            newLines.push(additionalKeyframe);
        }
        newLines.push('}');
        fixCount++;
    }

    const newContent = newLines.join('\n');

    if (newContent !== originalContent && fixCount > 0) {
        fs.writeFileSync(file, newContent);
        console.log(`Fixed ${fixCount} keyframes in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal keyframes fixed: ${totalFixes}`);
