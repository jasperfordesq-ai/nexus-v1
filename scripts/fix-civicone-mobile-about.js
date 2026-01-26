/**
 * Fix orphaned keyframe content in civicone-mobile-about.css
 * Remove GOV.UK compliance comment blocks that have orphaned keyframe content
 */

const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '..', 'httpdocs', 'assets', 'css', 'civicone-mobile-about.css');

let content = fs.readFileSync(filePath, 'utf8');
const originalContent = content;

// Pattern 1: Comment followed by keyframe percentage blocks
// /* Animation removed for GOV.UK compliance */
//     20% { ... }
//     40% { ... }
// }
const orphanedKeyframePattern = /\/\*\s*Animation removed for GOV\.UK compliance\s*\*\/\s*\n(\s*(?:from|to|\d+%)\s*\{[^}]*\}\s*\n)+\s*\}/gi;
let matches = content.match(orphanedKeyframePattern);
if (matches) {
    console.log(`Found ${matches.length} orphaned keyframe blocks (multi-line)`);
    content = content.replace(orphanedKeyframePattern, '/* Animation removed for GOV.UK compliance */');
}

// Pattern 2: Comment followed by single keyframe percentage
// /* Animation removed for GOV.UK compliance */
//     50% { background-position: 100% 50%; }
// }
const singleOrphanPattern = /\/\*\s*Animation removed for GOV\.UK compliance\s*\*\/\s*\n\s*(?:from|to|\d+%)\s*\{[^}]*\}\s*\n\}/gi;
matches = content.match(singleOrphanPattern);
if (matches) {
    console.log(`Found ${matches.length} orphaned keyframe blocks (single-line)`);
    content = content.replace(singleOrphanPattern, '/* Animation removed for GOV.UK compliance */');
}

// Pattern 3: Just the percentage block without the closing brace
// /* Animation removed for GOV.UK compliance */
//     50% { opacity: 0.6; }
// Next valid selector...
const orphanedPercentagePattern = /\/\*\s*Animation removed for GOV\.UK compliance\s*\*\/\s*\n(\s*(?:from|to|\d+%)\s*\{[^}]*\}\s*\n)+/gi;
matches = content.match(orphanedPercentagePattern);
if (matches) {
    console.log(`Found ${matches.length} remaining orphaned percentage blocks`);
    content = content.replace(orphanedPercentagePattern, '/* Animation removed for GOV.UK compliance */\n');
}

if (content !== originalContent) {
    fs.writeFileSync(filePath, content, 'utf8');
    console.log('File updated successfully');

    // Verify brace balance
    const opens = (content.match(/\{/g) || []).length;
    const closes = (content.match(/\}/g) || []).length;
    console.log(`Brace balance: ${opens} opens, ${closes} closes (diff: ${opens - closes})`);
} else {
    console.log('No changes made');
}
