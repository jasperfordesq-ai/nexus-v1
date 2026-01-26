/**
 * Fix unclosed @keyframes blocks
 *
 * Pattern: @keyframes name { from/0%/to { ... } followed by comment/selector
 * without a closing brace
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

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixes = 0;

    // Pattern: @keyframes name { from/to/percentage { ... } (without to or 100%)
    // followed by empty lines then a comment or selector
    // This means the keyframe is unclosed

    // More specific pattern: @keyframes followed by single keyframe (from or 0%)
    // then blank lines and a comment or new rule
    const unclosedKeyframePattern = /@keyframes\s+([\w-]+)\s*\{\s*\n\s*(from|to|\d+%(?:,\s*\d+%)*)\s*\{([^}]+)\}\s*\n\n+(?=\/\*|\.|::|@media|@keyframes|[a-zA-Z\[])/g;

    content = content.replace(unclosedKeyframePattern, (match, name, keyframe, props) => {
        fixes++;
        const indent = '    ';
        let toKeyframe = '';

        // Add appropriate complementary keyframe based on what we have
        if (keyframe === 'from') {
            // Parse the 'from' properties and create sensible 'to' values
            if (props.includes('opacity')) {
                toKeyframe = `${indent}to {\n${indent}${indent}opacity: 1;\n${indent}${indent}transform: translateX(0);\n${indent}}\n`;
            } else {
                toKeyframe = `${indent}to {\n${indent}${indent}opacity: 1;\n${indent}}\n`;
            }
        } else if (keyframe === 'to') {
            toKeyframe = `${indent}from {\n${indent}${indent}opacity: 0;\n${indent}}\n`;
        } else if (keyframe.includes('0%')) {
            if (props.includes('opacity')) {
                toKeyframe = `${indent}100% {\n${indent}${indent}opacity: 1;\n${indent}${indent}transform: none;\n${indent}}\n`;
            } else {
                toKeyframe = `${indent}100% {\n${indent}${indent}opacity: 1;\n${indent}}\n`;
            }
        }

        // Return fixed keyframe
        const originalPart = `${indent}${keyframe} {${props}}\n`;
        return `@keyframes ${name} {\n${originalPart}${toKeyframe}}\n\n`;
    });

    if (content !== originalContent && fixes > 0) {
        fs.writeFileSync(filePath, content);
        console.log(`Fixed ${fixes} unclosed keyframes in ${path.relative(cssDir, filePath)}`);
        return fixes;
    }
    return 0;
}

const files = getAllCssFiles(cssDir);
let totalFixes = 0;

for (const file of files) {
    totalFixes += fixFile(file);
}

console.log(`\nTotal fixes: ${totalFixes}`);
