/**
 * Fix CSS block issues - missing/extra closing braces
 * Analyzes brace depth and adds missing braces where needed
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
            if (entry.name === 'purged') continue;
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

    // Split into lines for analysis
    const lines = content.split(/\r?\n/);
    const fixedLines = [];
    let braceDepth = 0;
    let inMultilineComment = false;
    let fixCount = 0;

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];
        const trimmedLine = line.trim();

        // Track multi-line comments
        if (trimmedLine.includes('/*') && !trimmedLine.includes('*/')) {
            inMultilineComment = true;
        }
        if (trimmedLine.includes('*/')) {
            inMultilineComment = false;
        }

        // Skip comment-only lines for brace counting
        if (inMultilineComment || trimmedLine.startsWith('/*') || trimmedLine.startsWith('*') || trimmedLine.startsWith('//')) {
            fixedLines.push(line);
            continue;
        }

        // Count braces on this line
        const openBraces = (line.match(/\{/g) || []).length;
        const closeBraces = (line.match(/\}/g) || []).length;

        // Check for pattern: rule starts but we're already inside a block that should be closed
        // Pattern: selector { when braceDepth > 0 and previous line doesn't end with { or }
        const startsNewRule = /^[.#@:\[].*\{/.test(trimmedLine) || /^[a-z-]+\s*\{/.test(trimmedLine);
        const prevLine = fixedLines.length > 0 ? fixedLines[fixedLines.length - 1].trim() : '';

        if (startsNewRule && braceDepth > 0) {
            // Check if previous line looks like it should have ended a rule
            if (prevLine && !prevLine.endsWith('{') && !prevLine.endsWith('}') && !prevLine.startsWith('/*') && !prevLine.startsWith('*')) {
                // Insert closing brace before this line
                fixedLines.push('}');
                braceDepth--;
                fixCount++;
            }
        }

        // Update brace depth
        braceDepth += openBraces - closeBraces;

        // Check for unexpected closing brace (would make depth negative)
        if (braceDepth < 0) {
            // Skip this extra closing brace
            line = line.replace(/\}/, '/* removed extra } */');
            braceDepth = 0;
            fixCount++;
        }

        fixedLines.push(line);
    }

    // Add any missing closing braces at end
    while (braceDepth > 0) {
        fixedLines.push('}');
        braceDepth--;
        fixCount++;
    }

    // Remove any extra closing braces from the end
    while (fixedLines.length > 0 && fixedLines[fixedLines.length - 1].trim() === '}') {
        // Check if removing would unbalance
        const testContent = fixedLines.join('\n');
        const testOpens = (testContent.match(/\{/g) || []).length;
        const testCloses = (testContent.match(/\}/g) || []).length;

        if (testCloses > testOpens) {
            fixedLines.pop();
            fixCount++;
        } else {
            break;
        }
    }

    content = fixedLines.join('\n');

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        return fixCount;
    }

    return 0;
}

// Main execution
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

// Verify
console.log('\nVerifying brace balance...');
let balanced = 0;
let unbalanced = 0;

files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8');
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    if (opens === closes) {
        balanced++;
    } else {
        console.log(`  ${path.relative(cssDir, file)}: ${opens} opens, ${closes} closes (diff: ${opens - closes})`);
        unbalanced++;
    }
});

console.log(`\nBalanced: ${balanced}, Unbalanced: ${unbalanced}`);
