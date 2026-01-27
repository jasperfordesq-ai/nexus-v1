#!/usr/bin/env node
/**
 * Phase 6B - Analyze RGBA/RGB/HSL patterns
 * Separates literal values from dynamic var-based patterns
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

const baseDir = path.join(__dirname, '..');

// Same exclusions as the linter
const exclude = [
  '**/*.min.css',
  '**/bundles/**',
  '**/_archived/**',
  '**/vendor/**',
  '**/civicone/**',
  '**/civicone-*.css',
  '**/nexus-civicone.css',
  '**/design-tokens.css',
  '**/desktop-design-tokens.css',
  '**/mobile-design-tokens.css',
  '**/modern-theme-tokens.css',
  '**/*.backup'
];

const files = glob.sync('httpdocs/assets/css/**/*.css', {
  cwd: baseDir,
  ignore: exclude,
  nodir: true
}).map(f => path.join(baseDir, f));

// Patterns
const literalRgbaPattern = /rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*[\d.]+\s*\)/gi;
const literalRgbPattern = /rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/gi;
const literalHslPattern = /hsla?\([^)]+\)/gi;
const dynamicRgbaPattern = /rgba\(\s*var\(--[^)]+\)\s*,\s*[\d.]+\s*\)/gi;
const dynamicRgbPattern = /rgb\(\s*var\(--[^)]+\)\s*\)/gi;

const literalCounts = {};
const dynamicCounts = { rgba: 0, rgb: 0 };
const fileStats = {};

for (const filePath of files) {
  const content = fs.readFileSync(filePath, 'utf8');
  const relPath = path.relative(baseDir, filePath);

  // Count literal rgba
  const literalRgbaMatches = content.match(literalRgbaPattern) || [];
  for (const match of literalRgbaMatches) {
    const normalized = match.toLowerCase().replace(/\s+/g, '');
    literalCounts[normalized] = literalCounts[normalized] || { count: 0, files: {} };
    literalCounts[normalized].count++;
    literalCounts[normalized].files[relPath] = (literalCounts[normalized].files[relPath] || 0) + 1;
  }

  // Count literal rgb (not rgba)
  const literalRgbMatches = (content.match(literalRgbPattern) || []).filter(m => !m.includes('var('));
  for (const match of literalRgbMatches) {
    const normalized = match.toLowerCase().replace(/\s+/g, '');
    literalCounts[normalized] = literalCounts[normalized] || { count: 0, files: {} };
    literalCounts[normalized].count++;
    literalCounts[normalized].files[relPath] = (literalCounts[normalized].files[relPath] || 0) + 1;
  }

  // Count literal hsl/hsla
  const literalHslMatches = (content.match(literalHslPattern) || []).filter(m => !m.includes('var('));
  for (const match of literalHslMatches) {
    const normalized = match.toLowerCase().replace(/\s+/g, '');
    literalCounts[normalized] = literalCounts[normalized] || { count: 0, files: {} };
    literalCounts[normalized].count++;
    literalCounts[normalized].files[relPath] = (literalCounts[normalized].files[relPath] || 0) + 1;
  }

  // Count dynamic patterns (allowed)
  const dynamicRgbaMatches = content.match(dynamicRgbaPattern) || [];
  const dynamicRgbMatches = content.match(dynamicRgbPattern) || [];
  dynamicCounts.rgba += dynamicRgbaMatches.length;
  dynamicCounts.rgb += dynamicRgbMatches.length;

  // Track per-file literal counts
  const fileLiteralCount = literalRgbaMatches.length + literalRgbMatches.length + literalHslMatches.length;
  if (fileLiteralCount > 0) {
    fileStats[relPath] = fileLiteralCount;
  }
}

// Sort by count
const sortedLiterals = Object.entries(literalCounts)
  .sort((a, b) => b[1].count - a[1].count);

const sortedFiles = Object.entries(fileStats)
  .sort((a, b) => b[1] - a[1]);

// Calculate totals
const totalLiteral = sortedLiterals.reduce((sum, [_, data]) => sum + data.count, 0);
const totalDynamic = dynamicCounts.rgba + dynamicCounts.rgb;

console.log('='.repeat(70));
console.log('PHASE 6B - RGBA/RGB/HSL PATTERN ANALYSIS');
console.log('='.repeat(70));
console.log('\n## SUMMARY\n');
console.log(`Total LITERAL rgba/rgb/hsl values: ${totalLiteral} (TARGET)`);
console.log(`Total DYNAMIC var-based patterns:  ${totalDynamic} (ALLOWED)`);
console.log(`  - rgba(var(--*-rgb), alpha): ${dynamicCounts.rgba}`);
console.log(`  - rgb(var(--*-rgb)):         ${dynamicCounts.rgb}`);

console.log('\n## TOP 30 LITERAL VALUES\n');
console.log('| Rank | Value | Count | Top Files |');
console.log('|------|-------|-------|-----------|');

sortedLiterals.slice(0, 30).forEach(([value, data], i) => {
  const topFiles = Object.entries(data.files)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 3)
    .map(([f, c]) => `${path.basename(f)}(${c})`)
    .join(', ');
  console.log(`| ${i + 1} | \`${value}\` | ${data.count} | ${topFiles} |`);
});

console.log('\n## TOP 20 FILES BY LITERAL COUNT\n');
console.log('| Rank | File | Literal Count |');
console.log('|------|------|---------------|');

sortedFiles.slice(0, 20).forEach(([file, count], i) => {
  console.log(`| ${i + 1} | ${path.basename(file)} | ${count} |`);
});

// Output JSON for further processing
const output = {
  summary: {
    totalLiteral,
    totalDynamic,
    dynamicBreakdown: dynamicCounts
  },
  top30Literals: sortedLiterals.slice(0, 30).map(([value, data]) => ({
    value,
    count: data.count,
    files: data.files
  })),
  top20Files: sortedFiles.slice(0, 20).map(([file, count]) => ({ file, count }))
};

fs.writeFileSync(
  path.join(__dirname, 'rgba-analysis.json'),
  JSON.stringify(output, null, 2)
);

console.log('\n\nJSON output written to scripts/rgba-analysis.json');
