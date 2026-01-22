#!/usr/bin/env node
/**
 * CSS WCAG/GOV.UK Refactoring Script
 * Automatically fixes common spacing and color issues in civicone CSS files
 *
 * Usage: node scripts/css-wcag-refactor.js [--dry-run] [--file=path]
 *
 * Options:
 *   --dry-run    Show what would change without modifying files
 *   --file=path  Process only the specified file
 *   --report     Generate detailed report only
 */

const fs = require('fs');
const path = require('path');

// Configuration
const CSS_DIR = path.join(__dirname, '../httpdocs/assets/css');
const CIVICONE_PATTERN = /^civicone-(?!govuk-).*\.css$/;

// Spacing replacements (non-standard px → var tokens)
const SPACING_MAP = {
    // Common non-standard values → nearest GOV.UK token
    '2px': 'var(--space-0-5)',   // 2px → 2px token
    '3px': 'var(--space-1)',     // 3px → 4px (nearest)
    '5px': 'var(--space-1)',     // 5px → 4px (nearest)
    '6px': 'var(--space-1-5)',   // 6px → 6px token
    '10px': 'var(--space-2-5)',  // 10px → 10px token
    '12px': 'var(--space-3)',    // 12px → 12px token
    '14px': 'var(--space-3-5)',  // 14px → 14px token
    '15px': 'var(--space-4)',    // 15px → 16px (nearest)
    '18px': 'var(--space-4-5)',  // 18px → 18px token
    '20px': 'var(--space-5)',    // 20px → 20px token
    '22px': 'var(--space-5-5)',  // 22px → 22px token
    '24px': 'var(--space-6)',    // 24px → 24px token
    '28px': 'var(--space-7)',    // 28px → 28px token
    '32px': 'var(--space-8)',    // 32px → 32px token
    '36px': 'var(--space-9)',    // 36px → 36px token
    '40px': 'var(--space-10)',   // 40px → 40px token
    '44px': 'var(--space-11)',   // 44px → 44px token
    '48px': 'var(--space-12)',   // 48px → 48px token
    '56px': 'var(--space-14)',   // 56px → 56px token
    '64px': 'var(--space-16)',   // 64px → 64px token
    '80px': 'var(--space-20)',   // 80px → 80px token
};

// Color replacements (hardcoded hex → var tokens)
// Based on actual usage analysis from civicone CSS files
const COLOR_MAP = {
    // GOV.UK Core colors (highest priority - accessibility)
    '#0b0c0c': 'var(--color-govuk-black)',
    '#0B0C0C': 'var(--color-govuk-black)',
    '#1d70b8': 'var(--color-govuk-blue)',
    '#1D70B8': 'var(--color-govuk-blue)',
    '#00703c': 'var(--color-govuk-green)',
    '#00703C': 'var(--color-govuk-green)',
    '#d4351c': 'var(--color-govuk-red)',
    '#D4351C': 'var(--color-govuk-red)',
    '#ffdd00': 'var(--color-govuk-yellow)',
    '#FFDD00': 'var(--color-govuk-yellow)',
    '#b1b4b6': 'var(--color-govuk-mid-grey)',
    '#B1B4B6': 'var(--color-govuk-mid-grey)',
    '#505a5f': 'var(--color-govuk-dark-grey)',
    '#505A5F': 'var(--color-govuk-dark-grey)',
    '#f3f2f1': 'var(--color-govuk-light-grey)',
    '#F3F2F1': 'var(--color-govuk-light-grey)',

    // Primary/Indigo (brand colors - 128 occurrences)
    '#6366f1': 'var(--color-primary-500)',
    '#4f46e5': 'var(--color-primary-600)',
    '#7c3aed': 'var(--color-primary-700)',
    '#a5b4fc': 'var(--color-primary-300)',

    // Purple/Violet
    '#8b5cf6': 'var(--color-purple-500)',

    // Success/Emerald (87 occurrences)
    '#10b981': 'var(--color-success-500)',
    '#059669': 'var(--color-success-600)',
    '#047857': 'var(--color-success-700)',
    '#34d399': 'var(--color-success-400)',
    '#dcfce7': 'var(--color-success-100)',

    // Error/Red (53 occurrences)
    '#ef4444': 'var(--color-error-500)',
    '#dc2626': 'var(--color-error-600)',
    '#d32f2f': 'var(--color-error-600)',
    '#D32F2F': 'var(--color-error-600)',

    // Warning/Amber (46 occurrences)
    '#f59e0b': 'var(--color-warning-500)',
    '#fbbf24': 'var(--color-warning-400)',
    '#d97706': 'var(--color-warning-600)',
    '#b45309': 'var(--color-warning-700)',
    '#92400e': 'var(--color-warning-800)',
    '#78350f': 'var(--color-warning-900)',

    // Slate/Gray scale (Tailwind naming - high usage)
    '#0f172a': 'var(--color-slate-900)',
    '#1e293b': 'var(--color-slate-800)',
    '#334155': 'var(--color-slate-700)',
    '#475569': 'var(--color-slate-600)',
    '#64748b': 'var(--color-slate-500)',
    '#94a3b8': 'var(--color-slate-400)',
    '#cbd5e1': 'var(--color-slate-300)',
    '#e2e8f0': 'var(--color-slate-200)',
    '#f1f5f9': 'var(--color-slate-100)',
    '#f8fafc': 'var(--color-slate-50)',

    // Gray scale (Tailwind)
    '#111827': 'var(--color-gray-900)',
    '#1f2937': 'var(--color-gray-800)',
    '#374151': 'var(--color-gray-700)',
    '#6b7280': 'var(--color-gray-500)',
    '#9ca3af': 'var(--color-gray-400)',
    '#e5e7eb': 'var(--color-gray-200)',
    '#f3f4f6': 'var(--color-gray-100)',
    '#f9fafb': 'var(--color-gray-50)',

    // Blue
    '#3b82f6': 'var(--color-blue-500)',

    // Pink/Rose
    '#ec4899': 'var(--color-pink-500)',
    '#db2777': 'var(--color-pink-600)',
    '#96206d': 'var(--color-pink-800)',

    // Teal/Cyan
    '#4fd1c5': 'var(--color-teal-400)',
    '#14b8a6': 'var(--color-teal-500)',
    '#0d9488': 'var(--color-teal-600)',
    '#06b6d4': 'var(--color-cyan-500)',

    // Dark theme specific
    '#1e1e2e': 'var(--color-dark-surface)',
};

// Border radius replacements
const RADIUS_MAP = {
    '2px': 'var(--radius-sm)',
    '4px': 'var(--radius-sm)',
    '6px': 'var(--radius-base)',
    '8px': 'var(--radius-md)',
    '10px': 'var(--radius-md)',
    '12px': 'var(--radius-lg)',
    '16px': 'var(--radius-xl)',
    '20px': 'var(--radius-2xl)',
    '24px': 'var(--radius-2xl)',
};

// Track statistics
let stats = {
    filesProcessed: 0,
    filesModified: 0,
    spacingFixes: 0,
    colorFixes: 0,
    radiusFixes: 0,
    errors: [],
};

/**
 * Process a single CSS file
 */
function processFile(filePath, dryRun = false) {
    const fileName = path.basename(filePath);

    // Skip minified, backup, and govuk files
    if (fileName.endsWith('.min.css') ||
        fileName.includes('.backup') ||
        fileName.includes('.temp') ||
        fileName.startsWith('civicone-govuk-')) {
        return null;
    }

    let content;
    try {
        content = fs.readFileSync(filePath, 'utf8');
    } catch (err) {
        stats.errors.push({ file: fileName, error: err.message });
        return null;
    }

    const originalContent = content;
    let fileSpacingFixes = 0;
    let fileColorFixes = 0;
    let fileRadiusFixes = 0;

    // Fix spacing values (only in property values, not in var() definitions)
    for (const [oldVal, newVal] of Object.entries(SPACING_MAP)) {
        // Match spacing values in common properties
        const spacingProps = ['padding', 'margin', 'gap', 'top', 'right', 'bottom', 'left', 'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height'];

        for (const prop of spacingProps) {
            // Match property: value patterns, avoid replacing inside var() or already-tokenized values
            const regex = new RegExp(`(${prop}[^:]*:\\s*[^;]*?)(?<!var\\([^)]*)(\\b${oldVal.replace('px', '')}px\\b)(?![^(]*\\))`, 'gi');
            const matches = content.match(regex);
            if (matches) {
                content = content.replace(regex, `$1${newVal}`);
                fileSpacingFixes += matches.length;
            }
        }
    }

    // Fix color values (hex to var)
    // Simple approach: match hex colors in common CSS properties
    const colorProps = ['color', 'background', 'background-color', 'border', 'border-color',
                        'border-top', 'border-right', 'border-bottom', 'border-left',
                        'outline', 'outline-color', 'box-shadow', 'text-shadow', 'fill', 'stroke'];

    for (const [oldVal, newVal] of Object.entries(COLOR_MAP)) {
        // Escape # for regex
        const escapedColor = oldVal.replace('#', '\\#');

        // Match the color when it's not already inside a var()
        // Simple approach: just match the hex color directly
        const regex = new RegExp(escapedColor + '(?![0-9a-fA-F])', 'gi');
        const matches = content.match(regex);
        if (matches) {
            // Don't replace if already tokenized (contains var() nearby)
            const beforeReplace = content;
            content = content.replace(regex, (match, offset) => {
                // Check if this is inside a var() - look backwards for 'var('
                const before = content.substring(Math.max(0, offset - 30), offset);
                if (before.includes('var(') && !before.includes(')')) {
                    return match; // Keep original
                }
                return newVal;
            });
            // Count actual replacements
            if (content !== beforeReplace) {
                fileColorFixes += matches.length;
            }
        }
    }

    // Fix border-radius values
    for (const [oldVal, newVal] of Object.entries(RADIUS_MAP)) {
        const regex = new RegExp(`(border-radius[^:]*:\\s*[^;]*?)(?<!var\\([^)]*)(\\b${oldVal.replace('px', '')}px\\b)(?![^(]*\\))`, 'gi');
        const matches = content.match(regex);
        if (matches) {
            content = content.replace(regex, `$1${newVal}`);
            fileRadiusFixes += matches.length;
        }
    }

    stats.filesProcessed++;
    stats.spacingFixes += fileSpacingFixes;
    stats.colorFixes += fileColorFixes;
    stats.radiusFixes += fileRadiusFixes;

    if (content !== originalContent) {
        stats.filesModified++;

        if (!dryRun) {
            try {
                fs.writeFileSync(filePath, content, 'utf8');
            } catch (err) {
                stats.errors.push({ file: fileName, error: err.message });
            }
        }

        return {
            file: fileName,
            spacingFixes: fileSpacingFixes,
            colorFixes: fileColorFixes,
            radiusFixes: fileRadiusFixes,
            modified: true
        };
    }

    return {
        file: fileName,
        spacingFixes: 0,
        colorFixes: 0,
        radiusFixes: 0,
        modified: false
    };
}

/**
 * Process all civicone CSS files
 */
function processAllFiles(dryRun = false) {
    const files = fs.readdirSync(CSS_DIR)
        .filter(f => CIVICONE_PATTERN.test(f))
        .map(f => path.join(CSS_DIR, f));

    const results = [];

    for (const file of files) {
        const result = processFile(file, dryRun);
        if (result) {
            results.push(result);
        }
    }

    return results;
}

/**
 * Generate report
 */
function generateReport(results, dryRun) {
    console.log('\n========================================');
    console.log('CSS WCAG/GOV.UK Refactoring Report');
    console.log('========================================\n');

    if (dryRun) {
        console.log('MODE: Dry Run (no files modified)\n');
    }

    console.log(`Files processed: ${stats.filesProcessed}`);
    console.log(`Files modified:  ${stats.filesModified}`);
    console.log(`\nFixes applied:`);
    console.log(`  - Spacing:      ${stats.spacingFixes}`);
    console.log(`  - Colors:       ${stats.colorFixes}`);
    console.log(`  - Border-radius: ${stats.radiusFixes}`);
    console.log(`  - TOTAL:        ${stats.spacingFixes + stats.colorFixes + stats.radiusFixes}\n`);

    if (results.filter(r => r.modified).length > 0) {
        console.log('Modified files:');
        results
            .filter(r => r.modified)
            .sort((a, b) => (b.spacingFixes + b.colorFixes + b.radiusFixes) - (a.spacingFixes + a.colorFixes + a.radiusFixes))
            .forEach(r => {
                console.log(`  ${r.file}: ${r.spacingFixes} spacing, ${r.colorFixes} colors, ${r.radiusFixes} radius`);
            });
    }

    if (stats.errors.length > 0) {
        console.log('\nErrors:');
        stats.errors.forEach(e => console.log(`  ${e.file}: ${e.error}`));
    }

    console.log('\n========================================\n');
}

// Main execution
const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const reportOnly = args.includes('--report');
const fileArg = args.find(a => a.startsWith('--file='));

console.log('CSS WCAG/GOV.UK Refactoring Script');
console.log('----------------------------------');

if (fileArg) {
    const filePath = fileArg.split('=')[1];
    const fullPath = path.isAbsolute(filePath) ? filePath : path.join(CSS_DIR, filePath);
    const result = processFile(fullPath, dryRun || reportOnly);
    generateReport(result ? [result] : [], dryRun || reportOnly);
} else {
    const results = processAllFiles(dryRun || reportOnly);
    generateReport(results, dryRun || reportOnly);
}

if (!dryRun && !reportOnly && stats.filesModified > 0) {
    console.log('Files have been modified. Run `npm run css:minify` to regenerate minified versions.');
}
