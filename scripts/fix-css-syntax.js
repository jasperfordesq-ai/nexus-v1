/**
 * Fix malformed CSS syntax from GOV.UK compliance modifications
 * Aggressive bulk fix for orphaned keyframe content and broken patterns
 */

const fs = require('fs');
const path = require('path');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Get all CSS files
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
    let fixCount = 0;
    const originalContent = content;

    // === AGGRESSIVE FIX 1: Remove ANY orphaned keyframe content ===
    // Pattern: standalone percentage declarations not inside @keyframes
    // Matches: "    50% { ... }" or "    to { ... }" at line start
    const orphanedKeyframeContent = /^\s*((\d+%,?\s*)+|to|from)\s*\{[^}]*\}\s*$/gm;

    // But we need to be careful not to remove valid @keyframes content
    // So let's target specifically broken patterns

    // Fix 1: Remove "Animation removed" comment + any following keyframe-like content + orphan brace
    // This catches: /* Animation removed... */\n    50% { ... }\n}
    const animationRemovedPattern = /\/\*\s*Animation removed[^*]*\*\/\r?\n(\s*((\d+%,?\s*)+|to|from)\s*\{[^}]*\}\r?\n)*\s*\}/g;
    let matches = content.match(animationRemovedPattern);
    if (matches) {
        content = content.replace(animationRemovedPattern, '');
        fixCount += matches.length;
    }

    // Fix 2: Remove standalone keyframe percentages/to/from followed by } on next line
    // Pattern:     XX% { ... }\n}
    const standaloneKeyframe = /^\s*((\d+%,?\s*)+|to|from)\s*\{[^}]*\}\r?\n\s*\}/gm;
    matches = content.match(standaloneKeyframe);
    if (matches) {
        content = content.replace(standaloneKeyframe, '');
        fixCount += matches.length;
    }

    // Fix 3: Remove malformed -webkit- lines with comments
    const webkitPattern = /^\s*-webkit-\/\*[^*]*\*\/[^;\n]*;?\s*$/gm;
    matches = content.match(webkitPattern);
    if (matches) {
        content = content.replace(webkitPattern, '');
        fixCount += matches.length;
    }

    // Fix 4: Remove lines with orphaned filter functions after comment
    const orphanedFilterPattern = /^\s*\/\*[^*]*\*\/\s*(saturate|blur|brightness|contrast|grayscale|sepia|invert|opacity)\([^)]*\)[^;]*;?\s*$/gm;
    matches = content.match(orphanedFilterPattern);
    if (matches) {
        content = content.replace(orphanedFilterPattern, '');
        fixCount += matches.length;
    }

    // Fix 5: Remove lines with just comment and !important
    const importantOnlyPattern = /^\s*\/\*[^*]*\*\/\s*!important;?\s*$/gm;
    matches = content.match(importantOnlyPattern);
    if (matches) {
        content = content.replace(importantOnlyPattern, '');
        fixCount += matches.length;
    }

    // Fix 6: Remove lines with orphaned values after "removed" comments
    const orphanedValuePattern = /^\s*\/\*[^*]*removed[^*]*\*\/\s*[a-z0-9(][^;\n{]*;?\s*$/gm;
    matches = content.match(orphanedValuePattern);
    if (matches) {
        content = content.replace(orphanedValuePattern, '');
        fixCount += matches.length;
    }

    // Fix 7: Remove orphaned "Animation removed" comment + closing brace
    const orphanedBracePattern = /\/\*\s*Animation removed[^*]*\*\/\r?\n\s*\}/g;
    matches = content.match(orphanedBracePattern);
    if (matches) {
        content = content.replace(orphanedBracePattern, '');
        fixCount += matches.length;
    }

    // Fix 8: Remove HTML style tags from CSS files
    const styleTagPattern = /<\/?style[^>]*>/gi;
    matches = content.match(styleTagPattern);
    if (matches) {
        content = content.replace(styleTagPattern, '');
        fixCount += matches.length;
    }

    // Fix 9: Remove @supports rules with invalid conditions (comment inside)
    const supportsPattern = /@supports\s+not\s*\(\/\*[^*]*\*\/\)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/g;
    matches = content.match(supportsPattern);
    if (matches) {
        content = content.replace(supportsPattern, (match, inner) => {
            return `/* Fallback styles (backdrop-filter removed) */\n${inner.trim()}`;
        });
        fixCount += matches.length;
    }

    // Fix 10: Remove trailing ");" after comments - malformed property
    const trailingParenPattern = /\/\*[^*]*removed[^*]*\*\/\s*\);?\s*$/gm;
    matches = content.match(trailingParenPattern);
    if (matches) {
        content = content.replace(trailingParenPattern, '/* backdrop-filter removed */');
        fixCount += matches.length;
    }

    // Fix 11: GENERIC - Fix ANY incomplete @keyframes with only one keyframe and no closing brace
    // Pattern: @keyframes name {\n    XX% { ... }\n\n.nextclass
    // This is a catch-all for any @keyframes block that has content but is missing the closing brace

    // First, let's find incomplete @keyframes and complete them
    // Pattern: @keyframes name { single-keyframe-line }\n\n(. or # or @ or / or [)
    const incompleteKeyframesGeneric = /@keyframes\s+(\w+)\s*\{\s*\r?\n(\s*((\d+%(?:,\s*\d+%)*|\bfrom\b|\bto\b)\s*\{[^}]+\}\s*\r?\n)+)\r?\n(?=[@.#\[\/])/gm;
    matches = content.match(incompleteKeyframesGeneric);
    if (matches) {
        content = content.replace(incompleteKeyframesGeneric, (match, name, keyframeContent) => {
            // Check if there's already a closing brace - if not, add one
            const trimmed = match.trim();
            if (!trimmed.endsWith('}')) {
                // Add 50% placeholder keyframe if only 0%/100% exists, then close
                if (keyframeContent.includes('0%') && !keyframeContent.includes('50%')) {
                    return `@keyframes ${name} {\n${keyframeContent.trimEnd()}\n    50% { opacity: 0.8; }\n}\n\n`;
                }
                return `@keyframes ${name} {\n${keyframeContent.trimEnd()}\n}\n\n`;
            }
            return match;
        });
        fixCount += matches.length;
    }

    // Fix 12: Fix incomplete @keyframes XXXGradientShift (missing 50% keyframe and closing)
    const incompleteGradientShift = /@keyframes\s+(\w+)GradientShift\s*\{\s*\r?\n\s*0%,\s*100%\s*\{\s*background-position:\s*0%\s*50%;\s*\}\s*\r?\n\r?\n(?=@keyframes|[#.\[@\/])/gm;
    matches = content.match(incompleteGradientShift);
    if (matches) {
        content = content.replace(incompleteGradientShift, (match, name) => {
            return `@keyframes ${name}GradientShift {\n    0%, 100% { background-position: 0% 50%; }\n    50% { background-position: 100% 50%; }\n}\n\n`;
        });
        fixCount += matches.length;
    }

    // Fix 13: Fix incomplete @keyframes XXXFadeInUp (missing "to" keyframe and closing)
    const incompleteFadeInUp = /@keyframes\s+(\w+)FadeInUp\s*\{\s*\r?\n\s*from\s*\{\s*opacity:\s*0;\s*transform:\s*translateY\(20px\);\s*\}\s*\r?\n\r?\n(?=[#.\[@\/])/gm;
    matches = content.match(incompleteFadeInUp);
    if (matches) {
        content = content.replace(incompleteFadeInUp, (match, name) => {
            return `@keyframes ${name}FadeInUp {\n    from { opacity: 0; transform: translateY(20px); }\n    to { opacity: 1; transform: translateY(0); }\n}\n\n`;
        });
        fixCount += matches.length;
    }

    // Fix 14: Empty @keyframes blocks (just @keyframes name { } with whitespace)
    const emptyKeyframes = /@keyframes\s+\w+\s*\{\s*\}/g;
    matches = content.match(emptyKeyframes);
    if (matches) {
        content = content.replace(emptyKeyframes, '');
        fixCount += matches.length;
    }

    // Fix 15: @keyframes inside @media that are incomplete - remove the whole @keyframes
    const incompleteKeyframesInMedia = /@keyframes\s+\w+\s*\{\s*\r?\n\s*\}\s*\r?\n/g;
    matches = content.match(incompleteKeyframesInMedia);
    if (matches) {
        content = content.replace(incompleteKeyframesInMedia, '');
        fixCount += matches.length;
    }

    // Fix 16: Very specific pattern - @keyframes with single-line content followed immediately by class
    // Pattern: @keyframes name {\n    0%, 100% { ... }\n\n.class {
    const singleLineKeyframe = /@keyframes\s+(\w+)\s*\{\s*\r?\n(\s*(?:\d+%(?:,\s*\d+%)*|\bfrom\b|\bto\b)\s*\{[^}]+\})\s*\r?\n\r?\n(?=\.)/gm;
    matches = content.match(singleLineKeyframe);
    if (matches) {
        content = content.replace(singleLineKeyframe, (match, name, keyframeContent) => {
            return `@keyframes ${name} {\n${keyframeContent}\n    50% { opacity: 0.8; }\n}\n\n`;
        });
        fixCount += matches.length;
    }

    // Fix 16: Clean up double/triple newlines left behind
    content = content.replace(/\n{3,}/g, '\n\n');

    if (content !== originalContent) {
        fs.writeFileSync(file, content);
        console.log(`Fixed ${fixCount} issues in ${path.relative(cssDir, file)}`);
        totalFixes += fixCount;
    }
}

console.log(`\nTotal fixes: ${totalFixes}`);
