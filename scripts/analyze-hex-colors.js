#!/usr/bin/env node
/**
 * Analyze hex color usage in Modern legacy CSS files
 * Phase 6A - Hex Warning Reduction
 *
 * Matches the same scope as lint-modern-colors.js
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Match the linter's configuration
const include = ['httpdocs/assets/css/**/*.css'];
const exclude = [
  '**/*.min.css',
  '**/bundles/**',
  '**/_archived/**',
  '**/vendor/**',
  '**/civicone/**',
  '**/civicone-*.css',
  '**/design-tokens.css',
  '**/desktop-design-tokens.css',
  '**/mobile-design-tokens.css',
  '**/modern-theme-tokens.css',
  '**/*.backup'
];

// Get files matching the linter's scope
const baseDir = path.join(__dirname, '..');
const files = [];

for (const pattern of include) {
  const matches = glob.sync(pattern, {
    cwd: baseDir,
    ignore: exclude,
    nodir: true
  });
  files.push(...matches.map(f => path.join(baseDir, f)));
}

// Count hex values
const hexCounts = {};
const hexFiles = {};
const fileHexCounts = {};

for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');
  const basename = path.basename(file);

  // Match both 6-digit and 3-digit hex codes
  const hexMatches = content.match(/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/g) || [];

  if (hexMatches.length > 0) {
    fileHexCounts[basename] = (fileHexCounts[basename] || 0) + hexMatches.length;

    for (const hex of hexMatches) {
      const normalized = hex.toLowerCase();
      hexCounts[normalized] = (hexCounts[normalized] || 0) + 1;
      if (!hexFiles[normalized]) hexFiles[normalized] = new Set();
      hexFiles[normalized].add(basename);
    }
  }
}

// Sort by count
const sortedHex = Object.entries(hexCounts)
  .sort((a, b) => b[1] - a[1])
  .slice(0, 40);

const sortedFiles = Object.entries(fileHexCounts)
  .sort((a, b) => b[1] - a[1])
  .slice(0, 30);

console.log('PHASE 6A - HEX COLOR ANALYSIS');
console.log('Scope: Modern legacy files (matches lint-modern-colors.js)');
console.log('='.repeat(80));
console.log('');

console.log('TOP 30 FILES WITH HEX WARNINGS');
console.log('-'.repeat(60));
console.log('| Rank | File | Count |');
console.log('|------|------|-------|');
sortedFiles.forEach(([file, count], i) => {
  console.log(`| ${i + 1} | ${file} | ${count} |`);
});

console.log('');
console.log('TOP 40 HEX VALUES');
console.log('-'.repeat(60));
console.log('| Rank | Hex Code | Count | Top Files |');
console.log('|------|----------|-------|-----------|');

sortedHex.forEach(([hex, count], i) => {
  const topFiles = Array.from(hexFiles[hex]).slice(0, 3).join(', ');
  console.log(`| ${i + 1} | ${hex} | ${count} | ${topFiles} |`);
});

console.log('');
console.log('SUMMARY');
console.log('-'.repeat(60));
console.log('Total unique hex values:', Object.keys(hexCounts).length);
console.log('Total hex occurrences:', Object.values(hexCounts).reduce((a, b) => a + b, 0));
console.log('Files with hex colors:', Object.keys(fileHexCounts).length);
console.log('Files analyzed:', files.length);

// Token mapping recommendations
console.log('');
console.log('TOKEN MAPPING RECOMMENDATIONS');
console.log('-'.repeat(60));
const tokenMap = {
  '#fff': { token: 'var(--color-white)', note: 'Direct alias - white shorthand' },
  '#ffffff': { token: 'var(--color-white)', note: 'Direct alias - white' },
  '#000': { token: 'var(--color-black)', note: 'Direct alias - black shorthand' },
  '#000000': { token: 'var(--color-black)', note: 'Direct alias - black' },
  '#6366f1': { token: 'var(--color-primary-500)', note: 'Primary indigo' },
  '#4f46e5': { token: 'var(--color-primary-600)', note: 'Primary indigo dark' },
  '#374151': { token: 'var(--color-gray-700)', note: 'Gray 700' },
  '#6b7280': { token: 'var(--color-gray-500)', note: 'Gray 500' },
  '#e5e7eb': { token: 'var(--color-gray-200)', note: 'Gray 200' },
  '#10b981': { token: 'var(--color-emerald-500)', note: 'Emerald/Success' },
  '#1f2937': { token: 'var(--color-gray-800)', note: 'Gray 800' },
  '#8b5cf6': { token: 'var(--color-purple-500)', note: 'Purple 500' },
  '#1e293b': { token: 'var(--color-slate-800)', note: 'Slate 800' },
  '#ef4444': { token: 'var(--color-red-500)', note: 'Red/Danger' },
  '#64748b': { token: 'var(--color-slate-500)', note: 'Slate 500' },
  '#9ca3af': { token: 'var(--color-gray-400)', note: 'Gray 400' },
  '#4b5563': { token: 'var(--color-gray-600)', note: 'Gray 600' },
  '#111827': { token: 'var(--color-gray-900)', note: 'Gray 900' },
  '#059669': { token: 'var(--color-emerald-600)', note: 'Emerald 600' },
  '#f59e0b': { token: 'var(--color-amber-500)', note: 'Amber/Warning' },
  '#94a3b8': { token: 'var(--color-slate-400)', note: 'Slate 400' },
  '#f3f4f6': { token: 'var(--color-gray-100)', note: 'Gray 100' },
  '#f1f5f9': { token: 'var(--color-slate-100)', note: 'Slate 100' },
  '#e2e8f0': { token: 'var(--color-slate-200)', note: 'Slate 200' },
  '#818cf8': { token: 'var(--color-primary-400)', note: 'Primary 400' },
  '#f8fafc': { token: 'var(--color-slate-50)', note: 'Slate 50' },
  '#34d399': { token: 'var(--color-emerald-400)', note: 'Emerald 400' },
  '#f9fafb': { token: 'var(--color-gray-50)', note: 'Gray 50' },
  '#dc2626': { token: 'var(--color-red-600)', note: 'Red 600' },
  '#0f172a': { token: 'var(--color-slate-900)', note: 'Slate 900' },
  '#fbbf24': { token: 'var(--color-amber-400)', note: 'Amber 400' },
  '#334155': { token: 'var(--color-slate-700)', note: 'Slate 700' },
  '#a78bfa': { token: 'var(--color-purple-400-alt)', note: 'Purple 400 alt' },
  '#3b82f6': { token: 'var(--color-blue-500)', note: 'Blue 500/Info' },
  '#d1d5db': { token: 'var(--color-gray-300)', note: 'Gray 300' },
  '#7c3aed': { token: 'var(--color-purple-600)', note: 'Purple 600' },
  '#4338ca': { token: 'var(--color-primary-700)', note: 'Primary 700' },
  '#ec4899': { token: 'var(--color-pink-500)', note: 'Pink 500' },
  '#a5b4fc': { token: 'var(--color-primary-300)', note: 'Primary 300' },
};

let potentialReduction = 0;
console.log('| Hex | Count | Token | Note |');
console.log('|-----|-------|-------|------|');
for (const [hex, count] of sortedHex) {
  if (tokenMap[hex]) {
    console.log(`| ${hex} | ${count} | ${tokenMap[hex].token} | ${tokenMap[hex].note} |`);
    potentialReduction += count;
  }
}

console.log('');
console.log(`Potential reduction with mapped tokens: ${potentialReduction} hex occurrences`);
console.log(`This represents ${((potentialReduction / Object.values(hexCounts).reduce((a, b) => a + b, 0)) * 100).toFixed(1)}% of total hex warnings`);

// Save JSON for processing
const jsonOutput = {
  summary: {
    uniqueHexValues: Object.keys(hexCounts).length,
    totalOccurrences: Object.values(hexCounts).reduce((a, b) => a + b, 0),
    filesWithHex: Object.keys(fileHexCounts).length,
    filesAnalyzed: files.length,
    potentialReduction
  },
  topFiles: sortedFiles.map(([file, count]) => ({ file, count })),
  top40Hex: sortedHex.map(([hex, count], i) => ({
    rank: i + 1,
    hex,
    count,
    topFiles: Array.from(hexFiles[hex]).slice(0, 5),
    hasToken: !!tokenMap[hex],
    suggestedToken: tokenMap[hex]?.token || null
  })),
  tokenMap,
  allHexCounts: hexCounts
};

fs.writeFileSync(
  path.join(__dirname, 'hex-color-analysis.json'),
  JSON.stringify(jsonOutput, null, 2)
);
console.log('\nJSON output saved to scripts/hex-color-analysis.json');
