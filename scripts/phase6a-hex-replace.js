#!/usr/bin/env node
/**
 * Phase 6A - Bulk hex color replacement script
 * Replaces common hex colors with CSS variable tokens
 */

const fs = require('fs');
const path = require('path');

// Token mappings (hex -> CSS variable)
const tokenMap = {
  '#fff': 'var(--color-white)',
  '#ffffff': 'var(--color-white)',
  '#000': 'var(--color-black)',
  '#000000': 'var(--color-black)',
  '#6366f1': 'var(--color-primary-500)',
  '#4f46e5': 'var(--color-primary-600)',
  '#4338ca': 'var(--color-primary-700)',
  '#818cf8': 'var(--color-primary-400)',
  '#a5b4fc': 'var(--color-primary-300)',
  '#374151': 'var(--color-gray-700)',
  '#6b7280': 'var(--color-gray-500)',
  '#4b5563': 'var(--color-gray-600)',
  '#9ca3af': 'var(--color-gray-400)',
  '#d1d5db': 'var(--color-gray-300)',
  '#e5e7eb': 'var(--color-gray-200)',
  '#f3f4f6': 'var(--color-gray-100)',
  '#f9fafb': 'var(--color-gray-50)',
  '#111827': 'var(--color-gray-900)',
  '#1f2937': 'var(--color-gray-800)',
  '#10b981': 'var(--color-emerald-500)',
  '#059669': 'var(--color-emerald-600)',
  '#34d399': 'var(--color-emerald-400)',
  '#8b5cf6': 'var(--color-purple-500)',
  '#7c3aed': 'var(--color-purple-600)',
  '#a78bfa': 'var(--color-purple-400-alt)',
  '#c4b5fd': 'var(--color-purple-300)',
  '#1e293b': 'var(--color-slate-800)',
  '#0f172a': 'var(--color-slate-900)',
  '#334155': 'var(--color-slate-700)',
  '#64748b': 'var(--color-slate-500)',
  '#94a3b8': 'var(--color-slate-400)',
  '#e2e8f0': 'var(--color-slate-200)',
  '#f1f5f9': 'var(--color-slate-100)',
  '#f8fafc': 'var(--color-slate-50)',
  '#ef4444': 'var(--color-red-500)',
  '#dc2626': 'var(--color-red-600)',
  '#b91c1c': 'var(--color-red-700)',
  '#991b1b': 'var(--color-red-800)',
  '#7f1d1d': 'var(--color-red-900)',
  '#f59e0b': 'var(--color-amber-500)',
  '#fbbf24': 'var(--color-amber-400)',
  '#d97706': 'var(--color-amber-600)',
  '#b45309': 'var(--color-amber-700)',
  '#3b82f6': 'var(--color-blue-500)',
  '#2563eb': 'var(--color-blue-600)',
  '#60a5fa': 'var(--color-blue-400)',
  '#ec4899': 'var(--color-pink-500)',
  '#db2777': 'var(--color-pink-600)',
  '#06b6d4': 'var(--color-cyan-500)',
  '#22d3ee': 'var(--color-cyan-400)',
  '#14b8a6': 'var(--color-teal-500)',
  '#2dd4bf': 'var(--color-teal-400)',
};

// Files to process
const filesToProcess = [
  'httpdocs/assets/css/cookie-banner.css',
  'httpdocs/assets/css/messages-index.css',
  'httpdocs/assets/css/members-directory-v1.6.css',
  'httpdocs/assets/css/cookie-preferences.css',
  'httpdocs/assets/css/master-dashboard.css',
  'httpdocs/assets/css/consent-required.css',
  'httpdocs/assets/css/pwa-install-modal.css',
  'httpdocs/assets/css/volunteering.css',
  'httpdocs/assets/css/federation.css',
  'httpdocs/assets/css/scattered-singles.css',
  'httpdocs/assets/css/nexus-home.css',
  'httpdocs/assets/css/components-library.css',
  'httpdocs/assets/css/moj-filter.css',
  'httpdocs/assets/css/dev-notice-modal.css',
  'httpdocs/assets/css/privacy-page.css',
  'httpdocs/assets/css/modern-settings.css',
  'httpdocs/assets/css/mobile-search-overlay.css',
  'httpdocs/assets/css/nexus-modern-header.css',
  'httpdocs/assets/css/feed-item.css',
  'httpdocs/assets/css/components.css',
  'httpdocs/assets/css/polls.css',
  'httpdocs/assets/css/mobile-select-sheet.css',
  'httpdocs/assets/css/mobile-sheets.css',
  'httpdocs/assets/css/search-results.css',
];

const baseDir = path.join(__dirname, '..');
let totalReplacements = 0;
const fileStats = [];

for (const relPath of filesToProcess) {
  const fullPath = path.join(baseDir, relPath);

  if (!fs.existsSync(fullPath)) {
    console.log(`[SKIP] ${relPath} - file not found`);
    continue;
  }

  let content = fs.readFileSync(fullPath, 'utf8');
  let fileReplacements = 0;
  const beforeCount = (content.match(/#[0-9a-fA-F]{3,6}\b/g) || []).length;

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
      file: path.basename(relPath),
      before: beforeCount,
      after: afterCount,
      replaced: fileReplacements
    });
    console.log(`[OK] ${path.basename(relPath)}: ${fileReplacements} replacements (${beforeCount} -> ${afterCount} hex)`);
  } else {
    console.log(`[NO-OP] ${path.basename(relPath)}: no matching hex values`);
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
