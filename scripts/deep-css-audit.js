/**
 * Deep CSS Audit Script
 * Scans for issues that could cause layout breakage when making small changes:
 * - Duplicate selectors across files
 * - Conflicting property definitions
 * - !important overuse
 * - Overly broad selectors
 * - Missing scoping (selectors that affect multiple pages)
 * - Cascade/specificity conflicts
 */

const fs = require('fs');
const path = require('path');
const postcss = require('postcss');

const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');

// Track all selectors and their properties
const selectorMap = new Map();
const importantUsage = [];
const broadSelectors = [];
const duplicateSelectors = [];
const conflictingProperties = [];

// Broad selectors that affect many elements
const BROAD_SELECTOR_PATTERNS = [
    /^[a-z]+$/,           // Single element (div, span, p, etc)
    /^\*$/,               // Universal selector
    /^body\s/,            // body descendants
    /^html\s/,            // html descendants
    /^\.[a-z]{1,3}$/i,    // Very short class names
];

// Critical layout properties
const LAYOUT_PROPERTIES = [
    'display', 'position', 'float', 'clear',
    'width', 'height', 'max-width', 'max-height', 'min-width', 'min-height',
    'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
    'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
    'top', 'right', 'bottom', 'left',
    'flex', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content',
    'grid', 'grid-template-columns', 'grid-template-rows', 'grid-gap', 'gap',
    'overflow', 'overflow-x', 'overflow-y',
    'z-index', 'transform', 'visibility', 'opacity'
];

function getCssFiles(dir, files = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            if (entry.name === 'purged') continue;
            getCssFiles(fullPath, files);
        } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(fullPath);
        }
    }

    return files;
}

function isBroadSelector(selector) {
    // Check if selector is overly broad
    const trimmed = selector.trim();

    // Skip scoped selectors
    if (trimmed.includes('#') && trimmed.split('#').length > 1) return false;
    if (trimmed.includes('[data-theme')) return false;
    if (trimmed.includes('.civicone ') || trimmed.includes('.modern ')) return false;

    for (const pattern of BROAD_SELECTOR_PATTERNS) {
        if (pattern.test(trimmed)) return true;
    }

    // Check for unscoped element selectors
    if (/^[a-z]+\s*,/.test(trimmed)) return true;
    if (/^[a-z]+\s*$/.test(trimmed)) return true;

    return false;
}

function analyzeFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const relativePath = path.relative(cssDir, filePath);

    try {
        const root = postcss.parse(content, { from: filePath });

        root.walkRules(rule => {
            const selectors = rule.selector.split(',').map(s => s.trim());

            selectors.forEach(selector => {
                // Track selector usage
                if (!selectorMap.has(selector)) {
                    selectorMap.set(selector, []);
                }

                const properties = {};
                rule.walkDecls(decl => {
                    properties[decl.prop] = {
                        value: decl.value,
                        important: decl.important,
                        file: relativePath,
                        line: decl.source?.start?.line || 0
                    };

                    // Track !important usage
                    if (decl.important) {
                        importantUsage.push({
                            selector,
                            property: decl.prop,
                            value: decl.value,
                            file: relativePath,
                            line: decl.source?.start?.line || 0
                        });
                    }
                });

                selectorMap.get(selector).push({
                    file: relativePath,
                    line: rule.source?.start?.line || 0,
                    properties
                });

                // Check for broad selectors
                if (isBroadSelector(selector)) {
                    const layoutProps = Object.keys(properties).filter(p => LAYOUT_PROPERTIES.includes(p));
                    if (layoutProps.length > 0) {
                        broadSelectors.push({
                            selector,
                            file: relativePath,
                            line: rule.source?.start?.line || 0,
                            layoutProperties: layoutProps
                        });
                    }
                }
            });
        });
    } catch (e) {
        console.error(`Error parsing ${relativePath}: ${e.message}`);
    }
}

function findDuplicatesAndConflicts() {
    selectorMap.forEach((occurrences, selector) => {
        if (occurrences.length > 1) {
            // Check for actual duplicates (same selector in different files)
            const files = [...new Set(occurrences.map(o => o.file))];

            if (files.length > 1) {
                // Check for conflicting layout properties
                const layoutConflicts = {};

                occurrences.forEach(occ => {
                    Object.entries(occ.properties).forEach(([prop, info]) => {
                        if (LAYOUT_PROPERTIES.includes(prop)) {
                            if (!layoutConflicts[prop]) {
                                layoutConflicts[prop] = [];
                            }
                            layoutConflicts[prop].push({
                                value: info.value,
                                important: info.important,
                                file: info.file,
                                line: info.line
                            });
                        }
                    });
                });

                // Find actual conflicts (different values for same property)
                Object.entries(layoutConflicts).forEach(([prop, values]) => {
                    const uniqueValues = [...new Set(values.map(v => v.value))];
                    if (uniqueValues.length > 1) {
                        conflictingProperties.push({
                            selector,
                            property: prop,
                            definitions: values
                        });
                    }
                });

                duplicateSelectors.push({
                    selector,
                    files: files,
                    count: occurrences.length
                });
            }
        }
    });
}

// Main execution
console.log('🔍 Deep CSS Audit - Scanning for layout-breaking issues...\n');

const files = getCssFiles(cssDir);
console.log(`Scanning ${files.length} CSS files...\n`);

files.forEach(analyzeFile);
findDuplicatesAndConflicts();

// Report: Conflicting Properties (Most Critical)
console.log('=' .repeat(70));
console.log('🚨 CRITICAL: Conflicting Layout Properties');
console.log('   Same selector, different values across files');
console.log('=' .repeat(70));

if (conflictingProperties.length === 0) {
    console.log('✅ No conflicting layout properties found\n');
} else {
    // Sort by number of conflicts
    conflictingProperties.sort((a, b) => b.definitions.length - a.definitions.length);

    // Show top 30 most problematic
    conflictingProperties.slice(0, 30).forEach(conflict => {
        console.log(`\n❌ ${conflict.selector}`);
        console.log(`   Property: ${conflict.property}`);
        conflict.definitions.forEach(def => {
            const imp = def.important ? ' !important' : '';
            console.log(`   - ${def.value}${imp} (${def.file}:${def.line})`);
        });
    });

    console.log(`\n📊 Total conflicting properties: ${conflictingProperties.length}\n`);
}

// Report: Broad Selectors with Layout Properties
console.log('=' .repeat(70));
console.log('⚠️  WARNING: Broad Selectors Affecting Layout');
console.log('   These can cause cascading issues across pages');
console.log('=' .repeat(70));

if (broadSelectors.length === 0) {
    console.log('✅ No problematic broad selectors found\n');
} else {
    // Group by file
    const byFile = {};
    broadSelectors.forEach(bs => {
        if (!byFile[bs.file]) byFile[bs.file] = [];
        byFile[bs.file].push(bs);
    });

    Object.entries(byFile).slice(0, 20).forEach(([file, selectors]) => {
        console.log(`\n📁 ${file}`);
        selectors.slice(0, 5).forEach(s => {
            console.log(`   Line ${s.line}: "${s.selector}" → ${s.layoutProperties.join(', ')}`);
        });
        if (selectors.length > 5) {
            console.log(`   ... and ${selectors.length - 5} more`);
        }
    });

    console.log(`\n📊 Total broad selectors with layout properties: ${broadSelectors.length}\n`);
}

// Report: Duplicate Selectors
console.log('=' .repeat(70));
console.log('📋 Duplicate Selectors Across Files');
console.log('   Same selector defined in multiple files');
console.log('=' .repeat(70));

// Filter to only show selectors that appear in 3+ files
const significantDuplicates = duplicateSelectors.filter(d => d.files.length >= 3);

if (significantDuplicates.length === 0) {
    console.log('✅ No significant duplicates (3+ files) found\n');
} else {
    significantDuplicates.sort((a, b) => b.files.length - a.files.length);

    significantDuplicates.slice(0, 25).forEach(dup => {
        console.log(`\n"${dup.selector}" (${dup.files.length} files, ${dup.count} total)`);
        dup.files.slice(0, 5).forEach(f => console.log(`   - ${f}`));
        if (dup.files.length > 5) {
            console.log(`   ... and ${dup.files.length - 5} more files`);
        }
    });

    console.log(`\n📊 Total selectors in 3+ files: ${significantDuplicates.length}\n`);
}

// Report: !important Usage
console.log('=' .repeat(70));
console.log('⚡ !important Usage on Layout Properties');
console.log('   Overuse can cause cascade conflicts');
console.log('=' .repeat(70));

const layoutImportant = importantUsage.filter(i => LAYOUT_PROPERTIES.includes(i.property));

if (layoutImportant.length === 0) {
    console.log('✅ No !important on layout properties\n');
} else {
    // Group by file
    const byFile = {};
    layoutImportant.forEach(imp => {
        if (!byFile[imp.file]) byFile[imp.file] = [];
        byFile[imp.file].push(imp);
    });

    // Sort by count
    const sorted = Object.entries(byFile).sort((a, b) => b[1].length - a[1].length);

    sorted.slice(0, 15).forEach(([file, usages]) => {
        console.log(`\n📁 ${file} (${usages.length} !important)`);
        usages.slice(0, 3).forEach(u => {
            console.log(`   ${u.selector} { ${u.property}: ${u.value} !important }`);
        });
        if (usages.length > 3) {
            console.log(`   ... and ${usages.length - 3} more`);
        }
    });

    console.log(`\n📊 Total !important on layout properties: ${layoutImportant.length}\n`);
}

// Summary
console.log('=' .repeat(70));
console.log('📊 AUDIT SUMMARY');
console.log('=' .repeat(70));
console.log(`   Files scanned:              ${files.length}`);
console.log(`   Unique selectors:           ${selectorMap.size}`);
console.log(`   Conflicting properties:     ${conflictingProperties.length}`);
console.log(`   Broad layout selectors:     ${broadSelectors.length}`);
console.log(`   Duplicates (3+ files):      ${significantDuplicates.length}`);
console.log(`   !important on layout:       ${layoutImportant.length}`);
console.log('=' .repeat(70));

// Risk assessment
const riskScore =
    (conflictingProperties.length * 3) +
    (broadSelectors.length * 1) +
    (significantDuplicates.length * 2) +
    (layoutImportant.length * 0.5);

if (riskScore < 50) {
    console.log('\n✅ LOW RISK: CSS architecture is relatively clean');
} else if (riskScore < 150) {
    console.log('\n⚠️  MEDIUM RISK: Some issues may cause layout problems');
} else {
    console.log('\n🚨 HIGH RISK: Significant CSS architecture issues detected');
}

console.log(`   Risk Score: ${riskScore.toFixed(0)}\n`);
