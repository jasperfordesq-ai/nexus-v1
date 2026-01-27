#!/usr/bin/env node
/**
 * Phase 6A - Round 3 bulk hex color replacement script
 * Focus on #fff, #000, #111 and similar shorthand hex values
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Token mappings for shorthand hex values
const tokenMap = {
  '#fff': 'var(--color-white)',
  '#000': 'var(--color-black)',
  '#6366f1': 'var(--color-primary-500)',
  '#f59e0b': 'var(--color-amber-500)',
  '#92400e': 'var(--color-amber-800)',
  '#fecaca': 'var(--color-red-200)',
  '#fb923c': 'var(--color-orange-400)',
  '#a78bfa': 'var(--color-purple-400-alt)',
};

// Get all CSS files in the Modern scope (excluding minified, bundles, civicone, tokens)
const baseDir = path.join(__dirname, '..');
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

const files = [];
for (const pattern of include) {
  const matches = glob.sync(pattern, {
    cwd: baseDir,
    ignore: exclude,
    nodir: true
  });
  files.push(...matches.map(f => path.join(baseDir, f)));
}

let totalReplacements = 0;
const fileStats = [];

for (const fullPath of files) {
  let content = fs.readFileSync(fullPath, 'utf8');
  let fileReplacements = 0;
  const beforeCount = (content.match(/#[0-9a-fA-F]{3,6}\b/g) || []).length;

  if (beforeCount === 0) continue; // Skip files with no hex colors

  for (const [hex, token] of Object.entries(tokenMap)) {
    // Create case-insensitive regex for hex value
    const regex = new RegExp(hex.replace('#', '\\#'), 'gi');
    const matches = content.match(regex);
    if (matches) {
      content = content.replace(regex, token);
      fileReplacements += matches.length;
    }
  }

  if (fileReplacements > 0) {
    fs.writeFileSync(fullPath, content);
    const afterCount = (content.match(/#[0-9a-fA-F]{3,6}\b/g) || []).length;
    totalReplacements += fileReplacements;
    fileStats.push({
      file: path.basename(fullPath),
      before: beforeCount,
      after: afterCount,
      replaced: fileReplacements
    });
    console.log(`[OK] ${path.basename(fullPath)}: ${fileReplacements} replacements (${beforeCount} -> ${afterCount} hex)`);
  }
}

console.log('\n' + '='.repeat(60));
console.log(`Total replacements: ${totalReplacements}`);
console.log(`Files modified: ${fileStats.length}`);
console.log('='.repeat(60));
