/**
 * Remove orphaned closing braces
 * Only targets files that have specific "Unexpected }" errors
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Only process these specific files that have "Unexpected }" errors
const targetFiles = [
    'civicone-govuk-all.css',
    'civicone-profile-all.css',
    'civicone-ai-index.css',
    'civicone-blog.css',
    'civicone-bundle-compiled.css',
    'civicone-dev-banner.css',
    'civicone-events-calendar.css',
    'civicone-feed-show.css',
    'civicone-goals-edit.css',
    'civicone-groups-my-groups.css',
    'civicone-legal-volunteer-license.css',
    'civicone-mobile-about.css',
    'civicone-mobile.css',
    'civicone-onboarding-index.css',
    'civicone-org-ui-components.css',
    'civicone-organizations-members.css',
    'civicone-pages-our-story.css',
    'civicone-pages-privacy.css',
    'civicone-polls-edit.css',
    'civicone-privacy.css',
    'civicone-resources-form.css',
    'civicone-volunteering-certificate.css',
    'civicone-volunteering-edit-org.css',
    'civicone-volunteering-organizations.css'
];

function findFiles(dir, targets) {
    const found = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            if (entry.name === 'purged') continue;
            found.push(...findFiles(fullPath, targets));
        } else if (targets.includes(entry.name)) {
            found.push(fullPath);
        }
    }

    return found;
}

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let fixCount = 0;

    // Count braces to see if we have excess closing braces
    let opens = (content.match(/\{/g) || []).length;
    let closes = (content.match(/\}/g) || []).length;

    if (closes <= opens) {
        return 0; // No excess closing braces
    }

    const excessBraces = closes - opens;
    console.log(`  ${path.basename(filePath)}: ${closes} closes, ${opens} opens (excess: ${excessBraces})`);

    // Pattern 1: Remove orphaned } after comment followed by empty line
    // /* comment */
    // }
    for (let i = 0; i < excessBraces && closes > opens; i++) {
        const pattern1 = /(\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\/)\s*\r?\n\}/;
        if (pattern1.test(content)) {
            content = content.replace(pattern1, '$1');
            closes--;
            fixCount++;
            continue;
        }

        // Pattern 2: Remove } that appears after an empty line following a complete rule
        const pattern2 = /(\})\s*\r?\n\s*\r?\n\s*\}/;
        if (pattern2.test(content)) {
            content = content.replace(pattern2, '$1\n');
            closes--;
            fixCount++;
            continue;
        }

        // Pattern 3: Remove } at the very end after another }
        if (content.trim().endsWith('}}')) {
            content = content.replace(/\}\s*$/, '');
            closes--;
            fixCount++;
            continue;
        }

        // Pattern 4: Remove standalone } on its own line that isn't needed
        // This is risky so we stop here
        break;
    }

    if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
    }

    return fixCount;
}

// Main
const files = findFiles(cssDir, targetFiles);
let totalFixes = 0;

console.log(`Found ${files.length} target files\n`);

files.forEach(file => {
    const fixes = fixFile(file);
    if (fixes > 0) {
        console.log(`  Fixed ${fixes} orphan braces`);
        totalFixes += fixes;
    }
});

console.log(`\nTotal fixes: ${totalFixes}`);
