#!/usr/bin/env node

/**
 * CSS Dependency Audit Script
 *
 * Scans CSS files to identify:
 * 1. Which files depend on design-tokens.css variables
 * 2. Files with undefined variable references
 * 3. Duplicate variable definitions (conflicts)
 * 4. !important usage (fragility indicator)
 *
 * Usage: node scripts/css-dependency-audit.js [--verbose]
 */

const fs = require('fs');
const path = require('path');
const { glob } = require('glob');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');
const verbose = process.argv.includes('--verbose');

// Track findings
const findings = {
    dependentFiles: [],
    undefinedVars: {},
    duplicateVars: {},
    importantUsage: {},
    zIndexHardcoded: {},
    colorHardcoded: {}
};

// Known variable definitions (will be populated from design-tokens.css)
let definedVariables = new Set();

/**
 * Extract all CSS variable definitions from a file
 */
function extractDefinitions(content) {
    const definitions = new Set();
    const matches = content.matchAll(/(--[\w-]+)\s*:/g);
    for (const match of matches) {
        definitions.add(match[1]);
    }
    return definitions;
}

/**
 * Extract all CSS variable usages from a file
 */
function extractUsages(content) {
    const usages = new Set();
    const matches = content.matchAll(/var\((--[\w-]+)/g);
    for (const match of matches) {
        usages.add(match[1]);
    }
    return usages;
}

/**
 * Count !important occurrences in a file
 */
function countImportant(content) {
    return (content.match(/!important/g) || []).length;
}

/**
 * Find hardcoded z-index values
 */
function findHardcodedZIndex(content, filename) {
    const issues = [];
    // Match z-index: followed by a number (not a var())
    const matches = content.matchAll(/z-index:\s*(\d+)(?!\s*!)/g);
    for (const match of matches) {
        const value = parseInt(match[1]);
        if (value > 10) { // Ignore small values like z-index: 1
            issues.push({ value, line: getLineNumber(content, match.index) });
        }
    }
    return issues;
}

/**
 * Find hardcoded color values (hex, rgb, rgba)
 */
function findHardcodedColors(content) {
    const issues = [];

    // Hex colors (but not in variable definitions or comments)
    const hexMatches = content.matchAll(/(?<![-\w])#([a-fA-F0-9]{3,8})(?![a-fA-F0-9])/g);
    for (const match of hexMatches) {
        // Skip if in a comment or variable definition
        const contextStart = Math.max(0, match.index - 100);
        const context = content.substring(contextStart, match.index);
        if (!context.includes('/*') && !context.includes('--')) {
            issues.push({ type: 'hex', value: '#' + match[1] });
        }
    }

    return issues;
}

/**
 * Get line number from character position
 */
function getLineNumber(content, position) {
    return content.substring(0, position).split('\n').length;
}

/**
 * Analyze a single CSS file
 */
function analyzeFile(filePath) {
    const relativePath = path.relative(cssDir, filePath);
    const content = fs.readFileSync(filePath, 'utf8');

    // Skip minified files and purged folder
    if (relativePath.includes('.min.css') || relativePath.includes('purged/')) {
        return;
    }

    const definitions = extractDefinitions(content);
    const usages = extractUsages(content);

    // Check for undefined variables
    const undefinedVars = [];
    for (const varName of usages) {
        if (!definedVariables.has(varName)) {
            undefinedVars.push(varName);
        }
    }

    if (undefinedVars.length > 0) {
        findings.undefinedVars[relativePath] = undefinedVars;
    }

    // Track files that depend on design tokens
    if (usages.size > 0) {
        findings.dependentFiles.push({
            file: relativePath,
            usageCount: usages.size,
            dependsOn: Array.from(usages).slice(0, 5) // First 5 for brevity
        });
    }

    // Check for duplicate definitions (redefinitions of design tokens)
    const duplicates = [];
    for (const varName of definitions) {
        if (definedVariables.has(varName) && !relativePath.includes('design-tokens')) {
            duplicates.push(varName);
        }
    }

    if (duplicates.length > 0) {
        findings.duplicateVars[relativePath] = duplicates;
    }

    // Count !important usage
    const importantCount = countImportant(content);
    if (importantCount > 0) {
        findings.importantUsage[relativePath] = importantCount;
    }

    // Check for hardcoded z-index
    const zIndexIssues = findHardcodedZIndex(content, relativePath);
    if (zIndexIssues.length > 0) {
        findings.zIndexHardcoded[relativePath] = zIndexIssues;
    }

    // Check for hardcoded colors (only report files with many)
    const colorIssues = findHardcodedColors(content);
    if (colorIssues.length > 10) { // Only report files with many hardcoded colors
        findings.colorHardcoded[relativePath] = colorIssues.length;
    }
}

async function main() {
    console.log('🔍 CSS Dependency Audit');
    console.log('========================\n');

    // First, load all definitions from design-tokens.css
    const designTokensPath = path.join(cssDir, 'design-tokens.css');
    if (fs.existsSync(designTokensPath)) {
        const content = fs.readFileSync(designTokensPath, 'utf8');
        definedVariables = extractDefinitions(content);
        console.log(`📊 Found ${definedVariables.size} CSS variables in design-tokens.css\n`);
    } else {
        console.error('❌ design-tokens.css not found!');
        process.exit(1);
    }

    // Also load from desktop and mobile tokens
    const additionalTokenFiles = [
        'desktop-design-tokens.css',
        'mobile-design-tokens.css'
    ];

    for (const file of additionalTokenFiles) {
        const filePath = path.join(cssDir, file);
        if (fs.existsSync(filePath)) {
            const content = fs.readFileSync(filePath, 'utf8');
            const defs = extractDefinitions(content);
            for (const d of defs) {
                definedVariables.add(d);
            }
        }
    }

    // Scan all CSS files
    const cssFiles = await glob('**/*.css', {
        cwd: cssDir,
        ignore: ['**/*.min.css', 'purged/**']
    });

    console.log(`📁 Scanning ${cssFiles.length} CSS files...\n`);

    for (const file of cssFiles) {
        analyzeFile(path.join(cssDir, file));
    }

    // Report findings
    console.log('═══════════════════════════════════════════');
    console.log('           DEPENDENCY REPORT');
    console.log('═══════════════════════════════════════════\n');

    // 1. Files with undefined variables (CRITICAL)
    const undefinedCount = Object.keys(findings.undefinedVars).length;
    if (undefinedCount > 0) {
        console.log(`❌ FILES WITH UNDEFINED VARIABLES: ${undefinedCount}`);
        console.log('   These files reference CSS variables that don\'t exist!\n');
        for (const [file, vars] of Object.entries(findings.undefinedVars)) {
            console.log(`   📄 ${file}`);
            console.log(`      Missing: ${vars.slice(0, 5).join(', ')}${vars.length > 5 ? '...' : ''}`);
        }
        console.log('');
    } else {
        console.log('✅ No undefined variable references found\n');
    }

    // 2. Files redefining design tokens (WARNING)
    const duplicateCount = Object.keys(findings.duplicateVars).length;
    if (duplicateCount > 0) {
        console.log(`⚠️  FILES REDEFINING DESIGN TOKENS: ${duplicateCount}`);
        console.log('   These files override variables from design-tokens.css!\n');
        for (const [file, vars] of Object.entries(findings.duplicateVars)) {
            console.log(`   📄 ${file}`);
            console.log(`      Redefines: ${vars.slice(0, 3).join(', ')}${vars.length > 3 ? '...' : ''}`);
        }
        console.log('');
    } else {
        console.log('✅ No conflicting variable redefinitions\n');
    }

    // 3. !important usage summary
    const totalImportant = Object.values(findings.importantUsage).reduce((a, b) => a + b, 0);
    console.log(`📊 !IMPORTANT USAGE: ${totalImportant} total instances`);
    if (totalImportant > 1000) {
        console.log('   ⚠️  High !important count indicates CSS specificity problems\n');
    }

    // Top offenders
    const sortedImportant = Object.entries(findings.importantUsage)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);

    if (sortedImportant.length > 0) {
        console.log('   Top 5 files by !important count:');
        for (const [file, count] of sortedImportant) {
            console.log(`      ${count.toString().padStart(4)} - ${file}`);
        }
        console.log('');
    }

    // 4. Hardcoded z-index (WARNING)
    const zIndexCount = Object.keys(findings.zIndexHardcoded).length;
    if (zIndexCount > 0) {
        console.log(`⚠️  FILES WITH HARDCODED Z-INDEX: ${zIndexCount}`);
        console.log('   Should use var(--z-*) from design-tokens.css\n');
        if (verbose) {
            for (const [file, issues] of Object.entries(findings.zIndexHardcoded)) {
                console.log(`   📄 ${file}: ${issues.map(i => `z-index:${i.value}`).join(', ')}`);
            }
            console.log('');
        }
    }

    // 5. Dependency summary
    console.log('═══════════════════════════════════════════');
    console.log('         DEPENDENCY SUMMARY');
    console.log('═══════════════════════════════════════════\n');

    const dependentCount = findings.dependentFiles.length;
    console.log(`📁 ${dependentCount} files depend on design-tokens.css variables`);

    // Most dependent files
    const sortedDependent = findings.dependentFiles
        .sort((a, b) => b.usageCount - a.usageCount)
        .slice(0, 10);

    console.log('\n   Top 10 most dependent files:');
    for (const { file, usageCount } of sortedDependent) {
        console.log(`      ${usageCount.toString().padStart(3)} vars - ${file}`);
    }

    // Final summary
    console.log('\n═══════════════════════════════════════════');
    const hasErrors = undefinedCount > 0;
    const hasWarnings = duplicateCount > 0 || zIndexCount > 0 || totalImportant > 3000;

    if (hasErrors) {
        console.log('❌ AUDIT FOUND CRITICAL ISSUES');
        console.log('\n💡 Fix undefined variables before deploying!');
        process.exit(1);
    } else if (hasWarnings) {
        console.log('⚠️  AUDIT FOUND WARNINGS');
        console.log('\n💡 Consider addressing warnings to improve CSS maintainability');
        process.exit(0);
    } else {
        console.log('✅ CSS ARCHITECTURE IS HEALTHY');
        process.exit(0);
    }
}

main().catch(err => {
    console.error('Error running audit:', err);
    process.exit(1);
});
