#!/usr/bin/env node

/**
 * Automated Color Migration Script
 * Migrates hardcoded hex colors to design tokens
 */

const fs = require('fs');
const path = require('path');

// Color mapping from hex to design token
const COLOR_MAP = {
    // Primary palette (indigo/blue)
    '#6366f1': 'var(--color-primary-500)',
    '#4f46e5': 'var(--color-primary-600)',
    '#4338ca': 'var(--color-primary-700)',
    '#3730a3': 'var(--color-primary-800)',
    '#818cf8': 'var(--color-primary-400)',
    '#a5b4fc': 'var(--color-primary-300)',
    '#c7d2fe': 'var(--color-primary-200)',
    '#e0e7ff': 'var(--color-primary-100)',
    '#eef2ff': 'var(--color-primary-50)',

    // Semantic colors
    '#10b981': 'var(--color-success)',
    '#f59e0b': 'var(--color-warning)',
    '#ef4444': 'var(--color-danger)',
    '#3b82f6': 'var(--color-info)',

    // Pink palette
    '#ec4899': 'var(--color-pink-500)',
    '#db2777': 'var(--color-pink-600)',
    '#be185d': 'var(--color-pink-700)',
    '#f472b6': 'var(--color-pink-400)',
    '#f9a8d4': 'var(--color-pink-300)',
    '#fbcfe8': 'var(--color-pink-200)',
    '#fce7f3': 'var(--color-pink-100)',
    '#fdf2f8': 'var(--color-pink-50)',

    // Purple palette
    '#8b5cf6': 'var(--color-purple-500)',
    '#a855f7': 'var(--color-purple-400)',

    // Orange palette
    '#f97316': 'var(--color-orange-500)',
    '#ea580c': 'var(--color-orange-600)',

    // Green palette
    '#22c55e': 'var(--color-green-500)',
    '#16a34a': 'var(--color-green-600)',
    '#15803d': 'var(--color-green-700)',
    '#166534': 'var(--color-green-800)',
    '#4ade80': 'var(--color-green-400)',
    '#86efac': 'var(--color-green-300)',

    // Lime palette
    '#84cc16': 'var(--color-lime-500)',
    '#65a30d': 'var(--color-lime-600)',
    '#a3e635': 'var(--color-lime-400)',
    '#bef264': 'var(--color-lime-300)',
    '#d9f99d': 'var(--color-lime-200)',

    // Gray palette
    '#f9fafb': 'var(--color-gray-50)',
    '#f3f4f6': 'var(--color-gray-100)',
    '#e5e7eb': 'var(--color-gray-200)',
    '#d1d5db': 'var(--color-gray-300)',
    '#9ca3af': 'var(--color-gray-400)',
    '#6b7280': 'var(--color-gray-500)',
    '#4b5563': 'var(--color-gray-600)',
    '#374151': 'var(--color-gray-700)',
    '#1f2937': 'var(--color-gray-800)',
    '#111827': 'var(--color-gray-900)',

    // Slate palette
    '#f8fafc': 'var(--color-slate-50)',
    '#f1f5f9': 'var(--color-slate-100)',
    '#e2e8f0': 'var(--color-slate-200)',
    '#cbd5e1': 'var(--color-slate-300)',
    '#94a3b8': 'var(--color-slate-400)',
    '#64748b': 'var(--color-slate-500)',
    '#475569': 'var(--color-slate-600)',
    '#334155': 'var(--color-slate-700)',
    '#1e293b': 'var(--color-slate-800)',
    '#0f172a': 'var(--color-slate-900)',

    // Blue palette
    '#60a5fa': 'var(--color-blue-400)',
    '#93c5fd': 'var(--color-blue-300)',
    '#2563eb': 'var(--color-blue-600)',
    '#1d4ed8': 'var(--color-blue-700)',
    '#1e3a8a': 'var(--color-blue-800)',

    // Cyan palette
    '#06b6d4': 'var(--color-cyan-500)',
    '#0891b2': 'var(--color-cyan-600)',

    // Amber palette
    '#fcd34d': 'var(--color-amber-300)',
    '#fbbf24': 'var(--color-amber-300)', // Duplicate mapping
    '#d97706': 'var(--color-amber-600)',
    '#b45309': 'var(--color-amber-700)',

    // Red palette
    '#dc2626': 'var(--color-red-600)',
    '#b91c1c': 'var(--color-red-700)',

    // HIGH-IMPACT COLORS - Smart Hybrid Migration Phase 2
    // ===================================================

    // Emerald palette
    '#d1fae5': 'var(--color-emerald-100)',
    '#a7f3d0': 'var(--color-emerald-200)',
    '#6ee7b7': 'var(--color-emerald-300)',
    '#34d399': 'var(--color-emerald-400)',
    '#059669': 'var(--color-emerald-600)',
    '#047857': 'var(--color-emerald-700)',
    '#065f46': 'var(--color-emerald-800)',

    // Teal palette
    '#5eead4': 'var(--color-teal-300)',
    '#2dd4bf': 'var(--color-teal-400)',
    '#14b8a6': 'var(--color-teal-500)',
    '#0d9488': 'var(--color-teal-600)',
    '#0f766e': 'var(--color-teal-700)',
    '#115e59': 'var(--color-teal-800)',

    // Purple variants
    '#ddd6fe': 'var(--color-purple-200)',
    '#c4b5fd': 'var(--color-purple-300)',
    '#a78bfa': 'var(--color-purple-400-alt)',
    '#9333ea': 'var(--color-purple-500-alt)',
    '#7c3aed': 'var(--color-purple-600)',
    '#6d28d9': 'var(--color-purple-700)',
    '#5b21b6': 'var(--color-indigo-800)',

    // Rose palette
    '#fda4af': 'var(--color-rose-300)',
    '#fb7185': 'var(--color-rose-400)',
    '#f43f5e': 'var(--color-rose-500)',
    '#e11d48': 'var(--color-rose-600)',
    '#be123c': 'var(--color-rose-700)',
    '#9f1239': 'var(--color-rose-800)',

    // Sky palette
    '#0ea5e9': 'var(--color-sky-500)',
    '#0284c7': 'var(--color-sky-600)',
    '#0369a1': 'var(--color-sky-700)',
    '#075985': 'var(--color-sky-800)',
    '#0c4a6e': 'var(--color-sky-900)',

    // Yellow palette
    '#fef08a': 'var(--color-yellow-200)',
    '#facc15': 'var(--color-yellow-400)',
    '#eab308': 'var(--color-yellow-500)',
    '#ca8a04': 'var(--color-yellow-600)',

    // Zinc palette
    '#71717a': 'var(--color-zinc-500)',
    '#52525b': 'var(--color-zinc-600)',
    '#3f3f46': 'var(--color-zinc-700)',
    '#27272a': 'var(--color-zinc-800)',
    '#18181b': 'var(--color-zinc-900)',

    // GOV.UK Design System
    '#0b0c0c': 'var(--color-govuk-black)',
    '#00703c': 'var(--color-govuk-green)',
    '#005a30': 'var(--color-govuk-green-dark)',
    '#00796b': 'var(--color-govuk-green-darker)',
    '#003078': 'var(--color-govuk-blue)',
    '#1d70b8': 'var(--color-govuk-blue-light)',
    '#5694ca': 'var(--color-govuk-blue-lighter)',
    '#d4351c': 'var(--color-govuk-red)',
    '#c2410c': 'var(--color-govuk-red-dark)',
    '#b1b4b6': 'var(--color-govuk-grey)',
    '#505a5f': 'var(--color-govuk-grey-dark)',

    // Brand colors
    '#ffdd00': 'var(--color-brand-yellow)',

    // Red variants
    '#fef2f2': 'var(--color-red-50)',
    '#fee2e2': 'var(--color-red-100)',
    '#fca5a5': 'var(--color-red-300)',
    '#f87171': 'var(--color-red-400)',

    // Blue variants
    '#eff6ff': 'var(--color-blue-50)',
    '#dbeafe': 'var(--color-blue-100)',
    '#1e3a8a': 'var(--color-blue-900)',
    '#1e1b4b': 'var(--color-indigo-950)',

    // Cyan variants
    '#67e8f9': 'var(--color-cyan-300)',
    '#22d3ee': 'var(--color-cyan-400)',

    // Emerald variants
    '#ecfdf5': 'var(--color-emerald-50)',

    // Amber/Orange variants
    '#fffbeb': 'var(--color-amber-50)',
    '#fef3c7': 'var(--color-amber-100)',
    '#fde68a': 'var(--color-amber-200)',
    '#fdba74': 'var(--color-orange-300)',

    // Neutral variants
    '#ffffff': 'var(--color-white)',
    '#000000': 'var(--color-black)',
    '#f3f2f1': 'var(--color-neutral-50)',
};

function migrateFile(filePath) {
    console.log(`\n📄 Processing: ${path.basename(filePath)}`);

    let content = fs.readFileSync(filePath, 'utf8');
    const originalContent = content;
    let replacementCount = 0;

    // Check if already migrated
    if (content.includes('Fully Migrated to Design Tokens') ||
        content.includes('Design Tokens: ✓')) {
        console.log('   ⏭️  Already migrated, skipping...');
        return { migrated: false, count: 0 };
    }

    // Replace each color
    for (const [hex, token] of Object.entries(COLOR_MAP)) {
        const regex = new RegExp(hex, 'gi');
        const matches = content.match(regex);
        if (matches) {
            content = content.replace(regex, token);
            replacementCount += matches.length;
        }
    }

    if (replacementCount === 0) {
        console.log('   ℹ️  No matching colors found');
        return { migrated: false, count: 0 };
    }

    // Update header comment
    const dateStr = '2026-01-21';
    if (content.match(/\/\*\*[\s\S]*?\*\//)) {
        // Update existing header
        content = content.replace(
            /(\/\*\*[\s\S]*?\*\/)/,
            (match) => {
                if (match.includes('Migrated to Design Tokens')) {
                    return match.replace(
                        /Migrated to Design Tokens:.*$/m,
                        `Fully Migrated to Design Tokens: ${dateStr} ✓`
                    );
                } else {
                    return match.replace(/\*\/$/, ` * Fully Migrated to Design Tokens: ${dateStr} ✓\n */`);
                }
            }
        );
    }

    // Write back
    fs.writeFileSync(filePath, content);
    console.log(`   ✅ Migrated ${replacementCount} color references`);

    return { migrated: true, count: replacementCount };
}

// Main execution
const cssDir = path.join(__dirname, '..', 'httpdocs', 'assets', 'css');
const files = process.argv.slice(2);

if (files.length === 0) {
    console.log('Usage: node migrate-colors-to-tokens.js <file1.css> <file2.css> ...');
    console.log('Example: node migrate-colors-to-tokens.js nexus-home.css nexus-phoenix.css');
    process.exit(1);
}

console.log('🚀 Starting color migration to design tokens...');
let totalMigrated = 0;
let totalFiles = 0;

for (const file of files) {
    const filePath = path.join(cssDir, file);
    if (fs.existsSync(filePath)) {
        const result = migrateFile(filePath);
        if (result.migrated) {
            totalFiles++;
            totalMigrated += result.count;
        }
    } else {
        console.log(`⚠️  File not found: ${file}`);
    }
}

console.log('\n' + '='.repeat(60));
console.log('✨ Migration Complete!');
console.log(`   Files migrated: ${totalFiles}`);
console.log(`   Total colors replaced: ${totalMigrated}`);
console.log('='.repeat(60) + '\n');
