#!/usr/bin/env node
/**
 * Phase 6B - RGBA literal replacement script
 * Replaces literal rgba values with existing effect tokens
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

const baseDir = path.join(__dirname, '..');

// Map of literal rgba -> effect token (existing tokens only)
const tokenMap = {
  // White with alpha
  'rgba(255, 255, 255, 0.05)': 'var(--effect-white-5)',
  'rgba(255, 255, 255, 0.08)': 'var(--effect-white-8)',
  'rgba(255, 255, 255, 0.1)': 'var(--effect-white-10)',
  'rgba(255, 255, 255, 0.12)': 'var(--effect-white-12)',
  'rgba(255, 255, 255, 0.15)': 'var(--effect-white-15)',
  'rgba(255, 255, 255, 0.2)': 'var(--effect-white-20)',
  'rgba(255, 255, 255, 0.25)': 'var(--effect-white-25)',
  'rgba(255, 255, 255, 0.3)': 'var(--effect-white-30)',
  'rgba(255, 255, 255, 0.35)': 'var(--effect-white-35)',
  'rgba(255, 255, 255, 0.4)': 'var(--effect-white-40)',
  'rgba(255, 255, 255, 0.45)': 'var(--effect-white-45)',
  'rgba(255, 255, 255, 0.5)': 'var(--effect-white-50)',
  'rgba(255, 255, 255, 0.55)': 'var(--effect-white-55)',
  'rgba(255, 255, 255, 0.6)': 'var(--effect-white-60)',
  'rgba(255, 255, 255, 0.65)': 'var(--effect-white-65)',
  'rgba(255, 255, 255, 0.7)': 'var(--effect-white-70)',
  'rgba(255, 255, 255, 0.75)': 'var(--effect-white-75)',
  'rgba(255, 255, 255, 0.8)': 'var(--effect-white-80)',
  'rgba(255, 255, 255, 0.85)': 'var(--effect-white-85)',
  'rgba(255, 255, 255, 0.9)': 'var(--effect-white-90)',
  'rgba(255, 255, 255, 0.92)': 'var(--effect-white-92)',
  'rgba(255, 255, 255, 0.95)': 'var(--effect-white-95)',
  'rgba(255, 255, 255, 0.98)': 'var(--effect-white-98)',

  // Black with alpha
  'rgba(0, 0, 0, 0.05)': 'var(--effect-black-5)',
  'rgba(0, 0, 0, 0.08)': 'var(--effect-black-8)',
  'rgba(0, 0, 0, 0.1)': 'var(--effect-black-10)',
  'rgba(0, 0, 0, 0.12)': 'var(--effect-black-12)',
  'rgba(0, 0, 0, 0.15)': 'var(--effect-black-15)',
  'rgba(0, 0, 0, 0.2)': 'var(--effect-black-20)',
  'rgba(0, 0, 0, 0.25)': 'var(--effect-black-25)',
  'rgba(0, 0, 0, 0.3)': 'var(--effect-black-30)',
  'rgba(0, 0, 0, 0.4)': 'var(--effect-black-40)',
  'rgba(0, 0, 0, 0.5)': 'var(--effect-black-50)',
  'rgba(0, 0, 0, 0.6)': 'var(--effect-black-60)',
  'rgba(0, 0, 0, 0.7)': 'var(--effect-black-70)',
  'rgba(0, 0, 0, 0.8)': 'var(--effect-black-80)',

  // Primary (99, 102, 241) - indigo
  'rgba(99, 102, 241, 0.05)': 'var(--effect-primary-5)',
  'rgba(99, 102, 241, 0.08)': 'var(--effect-primary-8)',
  'rgba(99, 102, 241, 0.1)': 'var(--effect-primary-10)',
  'rgba(99, 102, 241, 0.12)': 'var(--effect-primary-12)',
  'rgba(99, 102, 241, 0.15)': 'var(--effect-primary-15)',
  'rgba(99, 102, 241, 0.2)': 'var(--effect-primary-20)',
  'rgba(99, 102, 241, 0.25)': 'var(--effect-primary-25)',
  'rgba(99, 102, 241, 0.3)': 'var(--effect-primary-30)',
  'rgba(99, 102, 241, 0.35)': 'var(--effect-primary-35)',
  'rgba(99, 102, 241, 0.4)': 'var(--effect-primary-40)',
  'rgba(99, 102, 241, 0.45)': 'var(--effect-primary-45)',
  'rgba(99, 102, 241, 0.5)': 'var(--effect-primary-50)',

  // Purple (139, 92, 246)
  'rgba(139, 92, 246, 0.05)': 'var(--effect-purple-5)',
  'rgba(139, 92, 246, 0.08)': 'var(--effect-purple-8)',
  'rgba(139, 92, 246, 0.1)': 'var(--effect-purple-10)',
  'rgba(139, 92, 246, 0.12)': 'var(--effect-purple-12)',
  'rgba(139, 92, 246, 0.15)': 'var(--effect-purple-15)',
  'rgba(139, 92, 246, 0.2)': 'var(--effect-purple-20)',
  'rgba(139, 92, 246, 0.25)': 'var(--effect-purple-25)',
  'rgba(139, 92, 246, 0.3)': 'var(--effect-purple-30)',
  'rgba(139, 92, 246, 0.35)': 'var(--effect-purple-35)',
  'rgba(139, 92, 246, 0.4)': 'var(--effect-purple-40)',
  'rgba(139, 92, 246, 0.5)': 'var(--effect-purple-50)',

  // Slate (30, 41, 59)
  'rgba(30, 41, 59, 0.4)': 'var(--effect-slate-40)',
  'rgba(30, 41, 59, 0.5)': 'var(--effect-slate-50)',
  'rgba(30, 41, 59, 0.6)': 'var(--effect-slate-60)',
  'rgba(30, 41, 59, 0.7)': 'var(--effect-slate-70)',
  'rgba(30, 41, 59, 0.8)': 'var(--effect-slate-80)',
  'rgba(30, 41, 59, 0.85)': 'var(--effect-slate-85)',
  'rgba(30, 41, 59, 0.9)': 'var(--effect-slate-90)',
  'rgba(30, 41, 59, 0.95)': 'var(--effect-slate-95)',

  // Emerald (16, 185, 129)
  'rgba(16, 185, 129, 0.1)': 'var(--effect-emerald-10)',
  'rgba(16, 185, 129, 0.15)': 'var(--effect-emerald-15)',
  'rgba(16, 185, 129, 0.2)': 'var(--effect-emerald-20)',
  'rgba(16, 185, 129, 0.25)': 'var(--effect-emerald-25)',
  'rgba(16, 185, 129, 0.3)': 'var(--effect-emerald-30)',
  'rgba(16, 185, 129, 0.4)': 'var(--effect-emerald-40)',

  // Red (239, 68, 68)
  'rgba(239, 68, 68, 0.1)': 'var(--effect-red-10)',
  'rgba(239, 68, 68, 0.15)': 'var(--effect-red-15)',
  'rgba(239, 68, 68, 0.2)': 'var(--effect-red-20)',
  'rgba(239, 68, 68, 0.3)': 'var(--effect-red-30)',
  'rgba(239, 68, 68, 0.4)': 'var(--effect-red-40)',

  // Amber (245, 158, 11)
  'rgba(245, 158, 11, 0.1)': 'var(--effect-amber-10)',
  'rgba(245, 158, 11, 0.15)': 'var(--effect-amber-15)',
  'rgba(245, 158, 11, 0.2)': 'var(--effect-amber-20)',
  'rgba(245, 158, 11, 0.25)': 'var(--effect-amber-25)',
  'rgba(245, 158, 11, 0.3)': 'var(--effect-amber-30)',

  // Cyan (6, 182, 212)
  'rgba(6, 182, 212, 0.1)': 'var(--effect-cyan-10)',
  'rgba(6, 182, 212, 0.15)': 'var(--effect-cyan-15)',
  'rgba(6, 182, 212, 0.2)': 'var(--effect-cyan-20)',
  'rgba(6, 182, 212, 0.3)': 'var(--effect-cyan-30)',
  'rgba(6, 182, 212, 0.4)': 'var(--effect-cyan-40)',
  'rgba(6, 182, 212, 0.5)': 'var(--effect-cyan-50)',

  // Blue (59, 130, 246)
  'rgba(59, 130, 246, 0.1)': 'var(--effect-blue-10)',
  'rgba(59, 130, 246, 0.15)': 'var(--effect-blue-15)',
  'rgba(59, 130, 246, 0.2)': 'var(--effect-blue-20)',
  'rgba(59, 130, 246, 0.25)': 'var(--effect-blue-25)',
  'rgba(59, 130, 246, 0.3)': 'var(--effect-blue-30)',

  // Teal (20, 184, 166)
  'rgba(20, 184, 166, 0.1)': 'var(--effect-teal-10)',
  'rgba(20, 184, 166, 0.15)': 'var(--effect-teal-15)',
  'rgba(20, 184, 166, 0.2)': 'var(--effect-teal-20)',
  'rgba(20, 184, 166, 0.25)': 'var(--effect-teal-25)',

  // Pink (236, 72, 153)
  'rgba(236, 72, 153, 0.1)': 'var(--effect-pink-10)',
  'rgba(236, 72, 153, 0.15)': 'var(--effect-pink-15)',
};

// Exclusions (skip these files)
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
  '**/*.backup',
  '**/modern-bundle-compiled.css' // Generated file
];

// Target files - top 20 source files by literal count (excluding generated)
const targetFiles = [
  'httpdocs/assets/css/goals.css',
  'httpdocs/assets/css/groups.css',
  'httpdocs/assets/css/groups-show.css',
  'httpdocs/assets/css/static-pages.css',
  'httpdocs/assets/css/nexus-score.css',
  'httpdocs/assets/css/admin-gold-standard.css',
  'httpdocs/assets/css/events-index.css',
  'httpdocs/assets/css/achievements.css',
  'httpdocs/assets/css/listings-show.css',
  'httpdocs/assets/css/help.css',
  'httpdocs/assets/css/polls.css',
  'httpdocs/assets/css/nexus-phoenix.css',
  'httpdocs/assets/css/notifications.css',
  'httpdocs/assets/css/modern-settings-holographic.css',
  'httpdocs/assets/css/components.css',
  'httpdocs/assets/css/admin-header.css',
  'httpdocs/assets/css/listings-create.css',
  'httpdocs/assets/css/nexus-modern-header.css',
  'httpdocs/assets/css/resources.css',
  'httpdocs/assets/css/blog-index.css',
];

let totalReplacements = 0;
const fileStats = [];

// Normalize rgba string for matching (handle varying whitespace)
function normalizeRgba(str) {
  return str.replace(/\s+/g, ' ').trim();
}

for (const relPath of targetFiles) {
  const fullPath = path.join(baseDir, relPath);

  if (!fs.existsSync(fullPath)) {
    console.log(`[SKIP] ${relPath} - file not found`);
    continue;
  }

  let content = fs.readFileSync(fullPath, 'utf8');
  let fileReplacements = 0;

  // Count rgba before
  const beforeCount = (content.match(/rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)/gi) || []).length;

  for (const [literal, token] of Object.entries(tokenMap)) {
    // Extract r, g, b, a from literal
    const match = literal.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
    if (!match) continue;

    const [, r, g, b, a] = match;
    // Build regex that handles whitespace variations
    const regex = new RegExp(
      `rgba\\(\\s*${r}\\s*,\\s*${g}\\s*,\\s*${b}\\s*,\\s*${a.replace('.', '\\.')}\\s*\\)`,
      'gi'
    );
    const matches = content.match(regex);

    if (matches) {
      content = content.replace(regex, token);
      fileReplacements += matches.length;
    }
  }

  if (fileReplacements > 0) {
    fs.writeFileSync(fullPath, content);
    const afterCount = (content.match(/rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)/gi) || []).length;
    totalReplacements += fileReplacements;
    fileStats.push({
      file: path.basename(relPath),
      before: beforeCount,
      after: afterCount,
      replaced: fileReplacements
    });
    console.log(`[OK] ${path.basename(relPath)}: ${fileReplacements} replacements (${beforeCount} -> ${afterCount} rgba)`);
  } else {
    console.log(`[NO-OP] ${path.basename(relPath)}: no matching rgba values`);
  }
}

console.log('\n' + '='.repeat(60));
console.log(`Total replacements: ${totalReplacements}`);
console.log(`Files modified: ${fileStats.length}`);
console.log('='.repeat(60));

// Output summary
console.log('\nFile Summary:');
console.log('| File | Before | After | Replaced |');
console.log('|------|--------|-------|----------|');
for (const stat of fileStats) {
  console.log(`| ${stat.file} | ${stat.before} | ${stat.after} | ${stat.replaced} |`);
}
