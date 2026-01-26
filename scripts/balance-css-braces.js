/**
 * Balance CSS braces by removing extra closing braces
 * Only handles the case where there are more } than {
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

function balanceBraces(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;

    let opens = (content.match(/\{/g) || []).length;
    let closes = (content.match(/\}/g) || []).length;

    if (opens === closes) {
        return 0; // Already balanced
    }

    let fixCount = 0;

    if (closes > opens) {
        // Too many closing braces - remove extras from the end
        const extraBraces = closes - opens;

        // Remove orphaned closing braces that appear after comments or at end of file
        // Pattern: standalone } on its own line after a comment or blank line
        for (let i = 0; i < extraBraces; i++) {
            // Try to find orphaned } after comment
            const orphanPattern = /(\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/)\s*\r?\n\}/;
            if (orphanPattern.test(content)) {
                content = content.replace(orphanPattern, '$1');
                fixCount++;
                continue;
            }

            // Try to find orphaned } that appears after a complete rule
            // (a rule that ends with } followed by empty line then another })
            const afterRulePattern = /(\})\s*\r?\n\s*\r?\n\}/;
            if (afterRulePattern.test(content)) {
                content = content.replace(afterRulePattern, '$1\n');
                fixCount++;
                continue;
            }

            // Remove trailing } at end of file after proper content
            if (content.trim().endsWith('}') && content.trim().slice(-2) === '}}') {
                content = content.replace(/\}\s*$/, '');
                fixCount++;
                continue;
            }
        }
    } else {
        // Too few closing braces - add at end
        const missingBraces = opens - closes;
        for (let i = 0; i < missingBraces; i++) {
            content += '\n}';
            fixCount++;
        }
    }

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
    }

    return fixCount;
}

// Main execution
const files = getCssFiles(cssDir);
let totalFixes = 0;

files.forEach(file => {
    const fixes = balanceBraces(file);
    if (fixes > 0) {
        console.log(`Fixed ${fixes} braces in ${path.relative(cssDir, file)}`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);

// Verify
console.log('\nVerifying...');
let balanced = 0;
let stillUnbalanced = [];

files.forEach(file => {
    const content = fs.readFileSync(file, 'utf8');
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    if (opens === closes) {
        balanced++;
    } else {
        stillUnbalanced.push({ file: path.relative(cssDir, file), diff: opens - closes });
    }
});

console.log(`Balanced: ${balanced}/${files.length}`);
if (stillUnbalanced.length > 0 && stillUnbalanced.length <= 20) {
    console.log('\nStill unbalanced:');
    stillUnbalanced.forEach(f => console.log(`  ${f.file}: diff ${f.diff}`));
}
