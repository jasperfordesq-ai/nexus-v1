/**
 * Deep Site Audit
 * Scans for issues causing visual flashes, broken references, and errors
 */

const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');
const viewsDir = path.join(projectRoot, 'views');
const cssDir = path.join(projectRoot, 'httpdocs', 'assets', 'css');
const jsDir = path.join(projectRoot, 'httpdocs', 'assets', 'js');

const issues = {
    missingCssFiles: [],
    missingJsFiles: [],
    mixedMinNonMin: [],
    fouc: [], // Flash of Unstyled Content indicators
    duplicateLoads: [],
    jsErrors: [],
    cssLoadOrder: [],
    brokenReferences: []
};

// Get all PHP files
function getPhpFiles(dir, files = []) {
    try {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        for (const entry of entries) {
            const fullPath = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                if (!entry.name.startsWith('.') && entry.name !== 'node_modules') {
                    getPhpFiles(fullPath, files);
                }
            } else if (entry.name.endsWith('.php')) {
                files.push(fullPath);
            }
        }
    } catch (e) {}
    return files;
}

// Check if file exists
function fileExists(filePath) {
    const fullPath = path.join(projectRoot, 'httpdocs', filePath.replace(/^\//, ''));
    return fs.existsSync(fullPath);
}

// Scan PHP files for CSS/JS references
function scanPhpFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const relativePath = path.relative(projectRoot, filePath);

    // Find CSS references
    const cssRefs = content.matchAll(/href=["']([^"']*\.css[^"']*)["']/gi);
    const jsRefs = content.matchAll(/src=["']([^"']*\.js[^"']*)["']/gi);

    const cssFiles = [];
    const jsFiles = [];

    for (const match of cssRefs) {
        let url = match[1];
        // Skip external URLs
        if (url.startsWith('http') || url.startsWith('//')) continue;
        // Remove query strings and PHP variables
        url = url.split('?')[0].replace(/<\?.*?\?>/g, '');
        if (url.includes('<?')) continue; // Skip complex PHP
        cssFiles.push(url);
    }

    for (const match of jsRefs) {
        let url = match[1];
        if (url.startsWith('http') || url.startsWith('//')) continue;
        url = url.split('?')[0].replace(/<\?.*?\?>/g, '');
        if (url.includes('<?')) continue;
        jsFiles.push(url);
    }

    // Check for missing files
    cssFiles.forEach(css => {
        if (!fileExists(css)) {
            issues.missingCssFiles.push({ file: relativePath, css });
        }
    });

    jsFiles.forEach(js => {
        if (!fileExists(js)) {
            issues.missingJsFiles.push({ file: relativePath, js });
        }
    });

    // Check for mixed .min.css and .css loading
    const hasMin = cssFiles.some(f => f.includes('.min.css'));
    const hasNonMin = cssFiles.some(f => f.endsWith('.css') && !f.includes('.min.css'));
    if (hasMin && hasNonMin && relativePath.includes('layouts')) {
        issues.mixedMinNonMin.push({
            file: relativePath,
            minified: cssFiles.filter(f => f.includes('.min.css')),
            nonMinified: cssFiles.filter(f => !f.includes('.min.css'))
        });
    }

    // Check for FOUC indicators
    // 1. CSS loaded in body instead of head
    if (content.includes('<body') && content.includes('<link rel="stylesheet"')) {
        const bodyStart = content.indexOf('<body');
        const linkAfterBody = content.indexOf('<link rel="stylesheet"', bodyStart);
        if (linkAfterBody > bodyStart) {
            issues.fouc.push({
                file: relativePath,
                issue: 'CSS loaded after <body> tag - causes flash'
            });
        }
    }

    // 2. No critical CSS preload
    if (relativePath.includes('header') && !content.includes('rel="preload"')) {
        issues.fouc.push({
            file: relativePath,
            issue: 'No CSS preloading - may cause render delay'
        });
    }

    // 3. Async CSS loading that may cause flash
    if (content.includes('media="print" onload="this.media=\'all\'"')) {
        issues.fouc.push({
            file: relativePath,
            issue: 'Async CSS loading pattern - may cause FOUC'
        });
    }

    // Check for duplicate CSS loads
    const cssCount = {};
    cssFiles.forEach(css => {
        const baseName = path.basename(css).replace('.min', '');
        cssCount[baseName] = (cssCount[baseName] || 0) + 1;
    });
    Object.entries(cssCount).forEach(([name, count]) => {
        if (count > 1) {
            issues.duplicateLoads.push({
                file: relativePath,
                css: name,
                count
            });
        }
    });
}

// Scan JS files for common errors
function scanJsFile(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const relativePath = path.relative(projectRoot, filePath);

        // Check for common JS issues
        const patterns = [
            { pattern: /document\.write\(/g, issue: 'document.write() can cause page reflow' },
            { pattern: /\.innerHTML\s*=\s*[^;]+;/g, issue: 'innerHTML assignment may cause reflow' },
            { pattern: /window\.onload\s*=/g, issue: 'window.onload override may conflict' },
            { pattern: /setTimeout\([^,]+,\s*0\)/g, issue: 'setTimeout(fn, 0) timing issues' },
            { pattern: /catch\s*\(\s*\)\s*\{/g, issue: 'Empty catch block - errors silently swallowed' },
            { pattern: /\.style\.\w+\s*=/g, issue: 'Direct style manipulation (potential layout thrash)' }
        ];

        patterns.forEach(({ pattern, issue }) => {
            const matches = content.match(pattern);
            if (matches && matches.length > 3) {
                issues.jsErrors.push({
                    file: relativePath,
                    issue,
                    count: matches.length
                });
            }
        });
    } catch (e) {}
}

// Get JS files
function getJsFiles(dir, files = []) {
    try {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        for (const entry of entries) {
            const fullPath = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                getJsFiles(fullPath, files);
            } else if (entry.name.endsWith('.js') && !entry.name.endsWith('.min.js')) {
                files.push(fullPath);
            }
        }
    } catch (e) {}
    return files;
}

// Analyze CSS load order in layouts
function analyzeLayoutCssOrder() {
    const layoutFiles = [
        'views/layouts/modern/header.php',
        'views/layouts/modern/partials/css-loader.php',
        'views/layouts/civicone/partials/assets-css.php',
        'views/layouts/civicone/partials/head-meta.php'
    ];

    layoutFiles.forEach(layoutFile => {
        const fullPath = path.join(projectRoot, layoutFile);
        if (!fs.existsSync(fullPath)) return;

        const content = fs.readFileSync(fullPath, 'utf8');
        const cssRefs = [...content.matchAll(/href=["'][^"']*\/([^/"']+\.css)/gi)];

        // Check if design-tokens loads first
        const tokenIndex = cssRefs.findIndex(m => m[1].includes('design-tokens'));
        const phoenixIndex = cssRefs.findIndex(m => m[1].includes('phoenix'));

        if (tokenIndex > 0) {
            issues.cssLoadOrder.push({
                file: layoutFile,
                issue: `design-tokens.css loads at position ${tokenIndex + 1}, should be first`
            });
        }

        // Check for critical files loading late
        const criticalFiles = ['design-tokens', 'nexus-phoenix', 'core'];
        criticalFiles.forEach(critical => {
            const idx = cssRefs.findIndex(m => m[1].includes(critical));
            if (idx > 5) {
                issues.cssLoadOrder.push({
                    file: layoutFile,
                    issue: `${critical} loads at position ${idx + 1}, may cause flash`
                });
            }
        });
    });
}

// Check for CSS files that reference missing assets
function checkCssReferences() {
    const cssFiles = [];
    function getCssFilesRecursive(dir) {
        try {
            const entries = fs.readdirSync(dir, { withFileTypes: true });
            for (const entry of entries) {
                const fullPath = path.join(dir, entry.name);
                if (entry.isDirectory() && entry.name !== 'purged') {
                    getCssFilesRecursive(fullPath);
                } else if (entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
                    cssFiles.push(fullPath);
                }
            }
        } catch (e) {}
    }
    getCssFilesRecursive(cssDir);

    cssFiles.slice(0, 50).forEach(file => { // Check first 50 for speed
        try {
            const content = fs.readFileSync(file, 'utf8');
            const relativePath = path.relative(projectRoot, file);

            // Check for url() references
            const urlRefs = content.matchAll(/url\(['"]?([^'")]+)['"]?\)/gi);
            for (const match of urlRefs) {
                const url = match[1];
                if (url.startsWith('data:') || url.startsWith('http') || url.startsWith('#')) continue;

                // Resolve relative path
                const cssFileDir = path.dirname(file);
                const assetPath = path.resolve(cssFileDir, url.split('?')[0]);

                if (!fs.existsSync(assetPath) && !url.includes('..')) {
                    issues.brokenReferences.push({
                        file: relativePath,
                        reference: url
                    });
                }
            }
        } catch (e) {}
    });
}

// Main
console.log('🔍 Deep Site Audit - Scanning for issues...\n');

// Scan PHP files in views/layouts specifically
const layoutPhpFiles = getPhpFiles(path.join(viewsDir, 'layouts'));
console.log(`Scanning ${layoutPhpFiles.length} layout PHP files...`);
layoutPhpFiles.forEach(scanPhpFile);

// Scan key JS files
const jsFiles = getJsFiles(jsDir);
console.log(`Scanning ${jsFiles.length} JavaScript files...`);
jsFiles.forEach(scanJsFile);

// Analyze CSS load order
console.log('Analyzing CSS load order...');
analyzeLayoutCssOrder();

// Check CSS references
console.log('Checking CSS asset references...');
checkCssReferences();

// Report
console.log('\n' + '='.repeat(70));
console.log('🚨 MISSING CSS FILES');
console.log('='.repeat(70));
if (issues.missingCssFiles.length === 0) {
    console.log('✅ No missing CSS files referenced\n');
} else {
    const unique = [...new Set(issues.missingCssFiles.map(i => i.css))];
    unique.forEach(css => {
        const files = issues.missingCssFiles.filter(i => i.css === css).map(i => i.file);
        console.log(`\n❌ ${css}`);
        console.log(`   Referenced in: ${files.slice(0, 3).join(', ')}${files.length > 3 ? ` (+${files.length - 3} more)` : ''}`);
    });
    console.log(`\n📊 Total missing: ${unique.length}\n`);
}

console.log('='.repeat(70));
console.log('⚡ FLASH OF UNSTYLED CONTENT (FOUC) RISKS');
console.log('='.repeat(70));
if (issues.fouc.length === 0) {
    console.log('✅ No FOUC risks detected\n');
} else {
    issues.fouc.forEach(f => {
        console.log(`\n⚠️  ${f.file}`);
        console.log(`   ${f.issue}`);
    });
    console.log(`\n📊 Total FOUC risks: ${issues.fouc.length}\n`);
}

console.log('='.repeat(70));
console.log('📋 CSS LOAD ORDER ISSUES');
console.log('='.repeat(70));
if (issues.cssLoadOrder.length === 0) {
    console.log('✅ CSS load order looks correct\n');
} else {
    issues.cssLoadOrder.forEach(i => {
        console.log(`\n⚠️  ${i.file}`);
        console.log(`   ${i.issue}`);
    });
    console.log(`\n📊 Total load order issues: ${issues.cssLoadOrder.length}\n`);
}

console.log('='.repeat(70));
console.log('🔀 MIXED MINIFIED/NON-MINIFIED CSS');
console.log('='.repeat(70));
if (issues.mixedMinNonMin.length === 0) {
    console.log('✅ Consistent CSS loading (no mixing)\n');
} else {
    issues.mixedMinNonMin.forEach(m => {
        console.log(`\n⚠️  ${m.file}`);
        console.log(`   Minified: ${m.minified.length} files`);
        console.log(`   Non-minified: ${m.nonMinified.length} files`);
    });
    console.log(`\n📊 Total files with mixed loading: ${issues.mixedMinNonMin.length}\n`);
}

console.log('='.repeat(70));
console.log('🔄 DUPLICATE CSS LOADS');
console.log('='.repeat(70));
if (issues.duplicateLoads.length === 0) {
    console.log('✅ No duplicate CSS loads detected\n');
} else {
    issues.duplicateLoads.forEach(d => {
        console.log(`\n⚠️  ${d.css} loaded ${d.count}x in ${d.file}`);
    });
    console.log(`\n📊 Total duplicates: ${issues.duplicateLoads.length}\n`);
}

console.log('='.repeat(70));
console.log('⚠️  JAVASCRIPT ISSUES');
console.log('='.repeat(70));
if (issues.jsErrors.length === 0) {
    console.log('✅ No significant JS issues detected\n');
} else {
    issues.jsErrors.forEach(e => {
        console.log(`\n⚠️  ${e.file}`);
        console.log(`   ${e.issue} (${e.count} occurrences)`);
    });
    console.log(`\n📊 Total JS issues: ${issues.jsErrors.length}\n`);
}

console.log('='.repeat(70));
console.log('🔗 BROKEN ASSET REFERENCES IN CSS');
console.log('='.repeat(70));
if (issues.brokenReferences.length === 0) {
    console.log('✅ No broken asset references\n');
} else {
    const byFile = {};
    issues.brokenReferences.forEach(r => {
        if (!byFile[r.file]) byFile[r.file] = [];
        byFile[r.file].push(r.reference);
    });
    Object.entries(byFile).slice(0, 10).forEach(([file, refs]) => {
        console.log(`\n⚠️  ${file}`);
        refs.slice(0, 3).forEach(r => console.log(`   Missing: ${r}`));
        if (refs.length > 3) console.log(`   ... and ${refs.length - 3} more`);
    });
    console.log(`\n📊 Total broken references: ${issues.brokenReferences.length}\n`);
}

// Summary
console.log('='.repeat(70));
console.log('📊 AUDIT SUMMARY');
console.log('='.repeat(70));
const totalIssues =
    issues.missingCssFiles.length +
    issues.fouc.length +
    issues.cssLoadOrder.length +
    issues.mixedMinNonMin.length +
    issues.duplicateLoads.length +
    issues.jsErrors.length +
    issues.brokenReferences.length;

console.log(`   Missing CSS files:     ${[...new Set(issues.missingCssFiles.map(i => i.css))].length}`);
console.log(`   FOUC risks:            ${issues.fouc.length}`);
console.log(`   CSS load order:        ${issues.cssLoadOrder.length}`);
console.log(`   Mixed min/non-min:     ${issues.mixedMinNonMin.length}`);
console.log(`   Duplicate loads:       ${issues.duplicateLoads.length}`);
console.log(`   JS issues:             ${issues.jsErrors.length}`);
console.log(`   Broken references:     ${issues.brokenReferences.length}`);
console.log('   ' + '-'.repeat(30));
console.log(`   TOTAL ISSUES:          ${totalIssues}`);
console.log('='.repeat(70));
