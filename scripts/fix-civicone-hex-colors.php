<?php
/**
 * Fix Hardcoded Hex Colors in CivicOne CSS Files
 * Converts remaining hex colors to CSS variables from design-tokens.css
 *
 * Run: php scripts/fix-civicone-hex-colors.php
 */

$cssDir = __DIR__ . '/../httpdocs/assets/css/';

// Comprehensive color mappings from design-tokens.css
// Format: 'hex' => 'var(--variable)'
$colorMappings = [
    // GOV.UK Official Colors (Primary for CivicOne)
    '#00703c' => 'var(--color-govuk-green)',
    '#005a30' => 'var(--color-govuk-green-dark)',
    '#00796b' => 'var(--color-govuk-green-darker)',
    '#003078' => 'var(--color-govuk-blue)',
    '#1d70b8' => 'var(--color-govuk-blue-light)',
    '#5694ca' => 'var(--color-govuk-blue-lighter)',
    '#d4351c' => 'var(--color-govuk-red)',
    '#c2410c' => 'var(--color-govuk-red-dark)',
    '#b1b4b6' => 'var(--color-govuk-grey)',
    '#505a5f' => 'var(--color-govuk-dark-grey)',
    '#f3f2f1' => 'var(--color-govuk-light-grey)',
    '#ffdd00' => 'var(--color-govuk-yellow)',
    '#0b0c0c' => 'var(--color-govuk-black)',

    // Primary Palette
    '#eef2ff' => 'var(--color-primary-50)',
    '#e0e7ff' => 'var(--color-primary-100)',
    '#c7d2fe' => 'var(--color-primary-200)',
    '#a5b4fc' => 'var(--color-primary-300)',
    '#818cf8' => 'var(--color-primary-400)',
    '#6366f1' => 'var(--color-primary-500)',
    '#4f46e5' => 'var(--color-primary-600)',
    '#4338ca' => 'var(--color-primary-700)',
    '#3730a3' => 'var(--color-primary-800)',
    '#312e81' => 'var(--color-primary-900)',

    // Gray Palette
    '#f9fafb' => 'var(--color-gray-50)',
    '#f3f4f6' => 'var(--color-gray-100)',
    '#e5e7eb' => 'var(--color-gray-200)',
    '#d1d5db' => 'var(--color-gray-300)',
    '#9ca3af' => 'var(--color-gray-400)',
    '#6b7280' => 'var(--color-gray-500)',
    '#4b5563' => 'var(--color-gray-600)',
    '#374151' => 'var(--color-gray-700)',
    '#1f2937' => 'var(--color-gray-800)',
    '#111827' => 'var(--color-gray-900)',

    // Slate Palette
    '#f8fafc' => 'var(--color-slate-50)',
    '#f1f5f9' => 'var(--color-slate-100)',
    '#e2e8f0' => 'var(--color-slate-200)',
    '#cbd5e1' => 'var(--color-slate-300)',
    '#94a3b8' => 'var(--color-slate-400)',
    '#64748b' => 'var(--color-slate-500)',
    '#475569' => 'var(--color-slate-600)',
    '#334155' => 'var(--color-slate-700)',
    '#1e293b' => 'var(--color-slate-800)',
    '#0f172a' => 'var(--color-slate-900)',

    // Blue Palette
    '#eff6ff' => 'var(--color-blue-50)',
    '#dbeafe' => 'var(--color-blue-100)',
    '#93c5fd' => 'var(--color-blue-300)',
    '#60a5fa' => 'var(--color-blue-400)',
    '#3b82f6' => 'var(--color-blue-500)',
    '#2563eb' => 'var(--color-blue-600)',
    '#1d4ed8' => 'var(--color-blue-700)',
    '#1e3a8a' => 'var(--color-blue-800)',

    // Green Palette
    '#86efac' => 'var(--color-green-300)',
    '#4ade80' => 'var(--color-green-400)',
    '#22c55e' => 'var(--color-green-500)',
    '#16a34a' => 'var(--color-green-600)',
    '#15803d' => 'var(--color-green-700)',
    '#166534' => 'var(--color-green-800)',

    // Emerald Palette
    '#ecfdf5' => 'var(--color-emerald-50)',
    '#d1fae5' => 'var(--color-emerald-100)',
    '#a7f3d0' => 'var(--color-emerald-200)',
    '#6ee7b7' => 'var(--color-emerald-300)',
    '#34d399' => 'var(--color-emerald-400)',
    '#10b981' => 'var(--color-emerald-500)',
    '#059669' => 'var(--color-emerald-600)',
    '#047857' => 'var(--color-emerald-700)',
    '#065f46' => 'var(--color-emerald-800)',

    // Teal Palette
    '#5eead4' => 'var(--color-teal-300)',
    '#2dd4bf' => 'var(--color-teal-400)',
    '#14b8a6' => 'var(--color-teal-500)',
    '#0d9488' => 'var(--color-teal-600)',
    '#0f766e' => 'var(--color-teal-700)',
    '#115e59' => 'var(--color-teal-800)',

    // Cyan Palette
    '#67e8f9' => 'var(--color-cyan-300)',
    '#22d3ee' => 'var(--color-cyan-400)',
    '#06b6d4' => 'var(--color-cyan-500)',
    '#0891b2' => 'var(--color-cyan-600)',

    // Sky Palette
    '#0ea5e9' => 'var(--color-sky-500)',
    '#0284c7' => 'var(--color-sky-600)',
    '#0369a1' => 'var(--color-sky-700)',
    '#075985' => 'var(--color-sky-800)',
    '#0c4a6e' => 'var(--color-sky-900)',

    // Red Palette
    '#fef2f2' => 'var(--color-red-50)',
    '#fee2e2' => 'var(--color-red-100)',
    '#fca5a5' => 'var(--color-red-300)',
    '#f87171' => 'var(--color-red-400)',
    '#ef4444' => 'var(--color-red-500)',
    '#dc2626' => 'var(--color-red-600)',
    '#b91c1c' => 'var(--color-red-700)',

    // Rose Palette
    '#fda4af' => 'var(--color-rose-300)',
    '#fb7185' => 'var(--color-rose-400)',
    '#f43f5e' => 'var(--color-rose-500)',
    '#e11d48' => 'var(--color-rose-600)',
    '#be123c' => 'var(--color-rose-700)',
    '#9f1239' => 'var(--color-rose-800)',

    // Pink Palette
    '#fdf2f8' => 'var(--color-pink-50)',
    '#fce7f3' => 'var(--color-pink-100)',
    '#fbcfe8' => 'var(--color-pink-200)',
    '#f9a8d4' => 'var(--color-pink-300)',
    '#f472b6' => 'var(--color-pink-400)',
    '#ec4899' => 'var(--color-pink-500)',
    '#db2777' => 'var(--color-pink-600)',
    '#be185d' => 'var(--color-pink-700)',
    '#d53880' => 'var(--color-pink-600)', // GOV.UK pink

    // Purple Palette
    '#ddd6fe' => 'var(--color-purple-200)',
    '#c4b5fd' => 'var(--color-purple-300)',
    '#a855f7' => 'var(--color-purple-400)',
    '#8b5cf6' => 'var(--color-purple-500)',
    '#7c3aed' => 'var(--color-purple-600)',
    '#6d28d9' => 'var(--color-purple-700)',
    '#a78bfa' => 'var(--color-purple-400)',
    '#9333ea' => 'var(--color-purple-500)',

    // Indigo
    '#5b21b6' => 'var(--color-indigo-800)',
    '#1e1b4b' => 'var(--color-indigo-950)',

    // Orange Palette
    '#fdba74' => 'var(--color-orange-300)',
    '#f97316' => 'var(--color-orange-500)',
    '#ea580c' => 'var(--color-orange-600)',
    '#f47738' => 'var(--color-orange-500)', // GOV.UK orange

    // Amber/Yellow Palette
    '#fffbeb' => 'var(--color-amber-50)',
    '#fef3c7' => 'var(--color-amber-100)',
    '#fde68a' => 'var(--color-amber-200)',
    '#fcd34d' => 'var(--color-amber-300)',
    '#fbbf24' => 'var(--color-warning-400)',
    '#f59e0b' => 'var(--color-warning-500)',
    '#d97706' => 'var(--color-warning-600)',
    '#b45309' => 'var(--color-warning-700)',
    '#92400e' => 'var(--color-warning-800)',
    '#78350f' => 'var(--color-warning-900)',
    '#facc15' => 'var(--color-yellow-400)',
    '#eab308' => 'var(--color-yellow-500)',
    '#ca8a04' => 'var(--color-yellow-600)',
    '#fef08a' => 'var(--color-yellow-200)',

    // Lime Palette
    '#bef264' => 'var(--color-lime-300)',
    '#a3e635' => 'var(--color-lime-400)',
    '#84cc16' => 'var(--color-lime-500)',
    '#65a30d' => 'var(--color-lime-600)',
    '#d9f99d' => 'var(--color-lime-200)',

    // Zinc Palette
    '#71717a' => 'var(--color-zinc-500)',
    '#52525b' => 'var(--color-zinc-600)',
    '#3f3f46' => 'var(--color-zinc-700)',
    '#27272a' => 'var(--color-zinc-800)',
    '#18181b' => 'var(--color-zinc-900)',

    // Common colors
    '#ffffff' => 'var(--color-white)',
    '#fff' => 'var(--color-white)',
    '#000000' => 'var(--color-black)',
    '#000' => 'var(--color-black)',

    // Success colors
    '#dcfce7' => 'var(--color-success-100)',
    '#bbf7d0' => 'var(--color-emerald-200)', // Light green

    // Additional mappings for common civicone patterns
    '#fecaca' => 'var(--color-red-200, #fecaca)', // Light red border
    '#e8f0f8' => 'var(--color-blue-50)', // Light blue bg
    '#cce2d8' => 'var(--color-emerald-100)', // Light green
    '#e0f2fe' => 'var(--color-blue-100)', // Sky light
    '#fce4ec' => 'var(--color-pink-100)', // Pink light
    '#002d72' => 'var(--color-govuk-blue)', // Gov blue variant
    '#007b5f' => 'var(--color-govuk-green)', // HSE green

    // Dark mode colors
    '#1e1e2e' => 'var(--color-dark-surface)',
    '#1f1f1f' => 'var(--color-zinc-900)',
    '#2d1f2f' => 'var(--color-zinc-800)',
    '#4a2c4a' => 'var(--color-purple-900, #4a2c4a)',
    '#6e3619' => 'var(--color-warning-800)',
    '#6b1d4d' => 'var(--color-pink-800)',
    '#0c2d48' => 'var(--color-sky-900)',
];

// Files to process (civicone source CSS files only, not minified)
$patterns = [
    'civicone-*.css',
    'nexus-civicone.css',
];

$totalFiles = 0;
$totalReplacements = 0;
$skippedFiles = [];

echo "=== CivicOne Hex Color Migration ===\n\n";

foreach ($patterns as $pattern) {
    $files = glob($cssDir . $pattern);

    foreach ($files as $file) {
        // Skip minified files and bundles (we'll regenerate those)
        if (strpos($file, '.min.css') !== false) continue;
        if (strpos($file, '/bundles/') !== false) continue;
        if (strpos($file, '/purged/') !== false) continue;

        $content = file_get_contents($file);
        $originalContent = $content;
        $fileReplacements = 0;

        // Apply each color mapping
        foreach ($colorMappings as $hex => $variable) {
            // Case-insensitive replacement
            $hexLower = strtolower($hex);
            $hexUpper = strtoupper($hex);

            // Count and replace
            $before = $content;
            $content = str_ireplace($hex, $variable, $content);

            if ($content !== $before) {
                $count = substr_count($before, $hexLower) + substr_count($before, $hexUpper) +
                         substr_count($before, ucfirst(strtolower($hex)));
                $fileReplacements += max(1, $count);
            }
        }

        // Only write if changes were made
        if ($content !== $originalContent) {
            file_put_contents($file, $content);
            $totalFiles++;
            $totalReplacements += $fileReplacements;
            echo "✓ " . basename($file) . " - $fileReplacements replacements\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Files modified: $totalFiles\n";
echo "Total replacements: $totalReplacements\n";
echo "\n⚠️  Remember to rebuild minified CSS: npm run build:css\n";
