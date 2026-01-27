#!/usr/bin/env node
/**
 * Phase 6A - Round 2 bulk hex color replacement script
 * Targets remaining files with hex colors
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
  '#047857': 'var(--color-emerald-700)',
  '#8b5cf6': 'var(--color-purple-500)',
  '#7c3aed': 'var(--color-purple-600)',
  '#a78bfa': 'var(--color-purple-400-alt)',
  '#c4b5fd': 'var(--color-purple-300)',
  '#1e293b': 'var(--color-slate-800)',
  '#0f172a': 'var(--color-slate-900)',
  '#334155': 'var(--color-slate-700)',
  '#475569': 'var(--color-slate-600)',
  '#64748b': 'var(--color-slate-500)',
  '#94a3b8': 'var(--color-slate-400)',
  '#cbd5e1': 'var(--color-slate-300)',
  '#e2e8f0': 'var(--color-slate-200)',
  '#f1f5f9': 'var(--color-slate-100)',
  '#f8fafc': 'var(--color-slate-50)',
  '#ef4444': 'var(--color-red-500)',
  '#dc2626': 'var(--color-red-600)',
  '#b91c1c': 'var(--color-red-700)',
  '#991b1b': 'var(--color-red-800)',
  '#7f1d1d': 'var(--color-red-900)',
  '#fecaca': 'var(--color-red-200)',
  '#fca5a5': 'var(--color-red-300)',
  '#f87171': 'var(--color-red-400)',
  '#f59e0b': 'var(--color-amber-500)',
  '#fbbf24': 'var(--color-amber-400)',
  '#d97706': 'var(--color-amber-600)',
  '#b45309': 'var(--color-amber-700)',
  '#92400e': 'var(--color-amber-800)',
  '#3b82f6': 'var(--color-blue-500)',
  '#2563eb': 'var(--color-blue-600)',
  '#1d4ed8': 'var(--color-blue-700)',
  '#60a5fa': 'var(--color-blue-400)',
  '#93c5fd': 'var(--color-blue-300)',
  '#ec4899': 'var(--color-pink-500)',
  '#db2777': 'var(--color-pink-600)',
  '#f472b6': 'var(--color-pink-400)',
  '#06b6d4': 'var(--color-cyan-500)',
  '#22d3ee': 'var(--color-cyan-400)',
  '#14b8a6': 'var(--color-teal-500)',
  '#2dd4bf': 'var(--color-teal-400)',
  '#0d9488': 'var(--color-teal-600)',
  '#f97316': 'var(--color-orange-500)',
  '#ea580c': 'var(--color-orange-600)',
  '#fb923c': 'var(--color-orange-400)',
  '#16a34a': 'var(--color-green-600)',
  '#22c55e': 'var(--color-green-500)',
  '#4ade80': 'var(--color-green-400)',
};

// Additional files to process in round 2
const filesToProcess = [
  'httpdocs/assets/css/goals.css',
  'httpdocs/assets/css/groups.css',
  'httpdocs/assets/css/nexus-score.css',
  'httpdocs/assets/css/groups-show.css',
  'httpdocs/assets/css/static-pages.css',
  'httpdocs/assets/css/events-index.css',
  'httpdocs/assets/css/achievements.css',
  'httpdocs/assets/css/listings-show.css',
  'httpdocs/assets/css/help.css',
  'httpdocs/assets/css/notifications.css',
  'httpdocs/assets/css/nexus-native-nav-v2.css',
  'httpdocs/assets/css/blog-show.css',
  'httpdocs/assets/css/blog-show-temp.css',
  'httpdocs/assets/css/social-interactions.css',
  'httpdocs/assets/css/volunteering-critical.css',
  'httpdocs/assets/css/sidebar.css',
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
