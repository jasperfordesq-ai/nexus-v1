<?php
/**
 * CivicOne CSS GOV.UK Refactoring Script
 *
 * Automatically refactors extracted CSS files to GOV.UK Design System compliance:
 * 1. Replace custom hex colors with GOV.UK/design-token variables
 * 2. Remove glassmorphism (backdrop-filter: blur())
 * 3. Convert arbitrary spacing to GOV.UK scale
 * 4. Remove custom animations (keep GOV.UK-approved transitions)
 *
 * Run: php scripts/refactor-civicone-css.php
 */

$cssDir = __DIR__ . '/../httpdocs/assets/css';
$pattern = 'civicone-*.css';

// GOV.UK Color mappings - hex to variable
$colorMappings = [
    // GOV.UK Primary colors
    '#00703c' => 'var(--color-govuk-green)',
    '#005a30' => 'var(--color-govuk-green-dark)',
    '#003078' => 'var(--color-govuk-blue)',
    '#1d70b8' => 'var(--color-govuk-blue-light)',
    '#5694ca' => 'var(--color-govuk-blue-lighter)',
    '#d4351c' => 'var(--color-govuk-red)',
    '#0b0c0c' => 'var(--color-govuk-black)',
    '#ffdd00' => 'var(--color-govuk-yellow)',
    '#b1b4b6' => 'var(--color-govuk-grey)',
    '#505a5f' => 'var(--color-govuk-grey-dark)',
    '#f3f2f1' => 'var(--color-govuk-light-grey)',

    // Indigo/Purple -> GOV.UK Blue
    '#6366f1' => 'var(--color-govuk-blue-light)',
    '#4f46e5' => 'var(--color-govuk-blue)',
    '#4338ca' => 'var(--color-govuk-blue)',
    '#3730a3' => 'var(--color-govuk-blue)',
    '#818cf8' => 'var(--color-govuk-blue-lighter)',
    '#a5b4fc' => 'var(--color-govuk-blue-lighter)',
    '#c7d2fe' => 'var(--color-govuk-light-grey)',
    '#e0e7ff' => 'var(--color-govuk-light-grey)',
    '#eef2ff' => 'var(--color-govuk-light-grey)',
    '#7c3aed' => 'var(--color-govuk-blue)',
    '#8b5cf6' => 'var(--color-govuk-blue-light)',

    // Pink -> GOV.UK Red (for errors/alerts)
    '#ec4899' => 'var(--color-govuk-red)',
    '#db2777' => 'var(--color-govuk-red)',
    '#be185d' => 'var(--color-govuk-red)',
    '#f472b6' => 'var(--color-govuk-red)',
    '#f9a8d4' => 'var(--color-govuk-light-grey)',
    '#fbcfe8' => 'var(--color-govuk-light-grey)',

    // Green -> GOV.UK Green
    '#10b981' => 'var(--color-govuk-green)',
    '#059669' => 'var(--color-govuk-green)',
    '#047857' => 'var(--color-govuk-green-dark)',
    '#22c55e' => 'var(--color-govuk-green)',
    '#16a34a' => 'var(--color-govuk-green)',
    '#15803d' => 'var(--color-govuk-green-dark)',
    '#34d399' => 'var(--color-govuk-green)',
    '#6ee7b7' => 'var(--color-govuk-green)',
    '#84cc16' => 'var(--color-govuk-green)',

    // Red/Rose -> GOV.UK Red
    '#ef4444' => 'var(--color-govuk-red)',
    '#dc2626' => 'var(--color-govuk-red)',
    '#b91c1c' => 'var(--color-govuk-red)',
    '#f43f5e' => 'var(--color-govuk-red)',
    '#e11d48' => 'var(--color-govuk-red)',
    '#fb7185' => 'var(--color-govuk-red)',

    // Orange/Amber -> GOV.UK Yellow or Red
    '#f59e0b' => 'var(--color-govuk-yellow)',
    '#d97706' => 'var(--color-govuk-yellow)',
    '#b45309' => 'var(--color-govuk-yellow)',
    '#f97316' => 'var(--color-govuk-yellow)',
    '#ea580c' => 'var(--color-govuk-yellow)',
    '#fbbf24' => 'var(--color-govuk-yellow)',

    // Blue/Sky/Cyan -> GOV.UK Blue
    '#3b82f6' => 'var(--color-govuk-blue-light)',
    '#2563eb' => 'var(--color-govuk-blue-light)',
    '#1d4ed8' => 'var(--color-govuk-blue)',
    '#0ea5e9' => 'var(--color-govuk-blue-light)',
    '#0284c7' => 'var(--color-govuk-blue-light)',
    '#06b6d4' => 'var(--color-govuk-blue-light)',
    '#14b8a6' => 'var(--color-govuk-blue-light)',

    // Gray scale -> GOV.UK grays
    '#f9fafb' => 'var(--color-govuk-light-grey)',
    '#f3f4f6' => 'var(--color-govuk-light-grey)',
    '#e5e7eb' => 'var(--color-govuk-grey)',
    '#d1d5db' => 'var(--color-govuk-grey)',
    '#9ca3af' => 'var(--color-govuk-grey-dark)',
    '#6b7280' => 'var(--color-govuk-grey-dark)',
    '#4b5563' => 'var(--color-govuk-grey-dark)',
    '#374151' => 'var(--color-govuk-black)',
    '#1f2937' => 'var(--color-govuk-black)',
    '#111827' => 'var(--color-govuk-black)',

    // Slate scale -> GOV.UK grays
    '#f8fafc' => 'var(--color-govuk-light-grey)',
    '#f1f5f9' => 'var(--color-govuk-light-grey)',
    '#e2e8f0' => 'var(--color-govuk-grey)',
    '#cbd5e1' => 'var(--color-govuk-grey)',
    '#94a3b8' => 'var(--color-govuk-grey-dark)',
    '#64748b' => 'var(--color-govuk-grey-dark)',
    '#475569' => 'var(--color-govuk-grey-dark)',
    '#334155' => 'var(--color-govuk-black)',
    '#1e293b' => 'var(--color-govuk-black)',
    '#0f172a' => 'var(--color-govuk-black)',

    // Common hex values
    '#fff' => 'var(--color-white)',
    '#ffffff' => 'var(--color-white)',
    '#000' => 'var(--color-govuk-black)',
    '#000000' => 'var(--color-govuk-black)',
];

// GOV.UK Spacing mappings - px values to GOV.UK scale
$spacingMappings = [
    // Direct mappings
    '0px' => '0',
    '2px' => 'var(--space-1)',      // 4px is smallest GOV.UK, 2px rounds to 1
    '4px' => 'var(--space-1)',
    '5px' => 'var(--space-1)',
    '6px' => 'var(--space-2)',
    '8px' => 'var(--space-2)',
    '10px' => 'var(--space-3)',
    '12px' => 'var(--space-3)',
    '14px' => 'var(--space-4)',
    '15px' => 'var(--space-4)',
    '16px' => 'var(--space-4)',
    '18px' => 'var(--space-5)',
    '20px' => 'var(--space-5)',
    '22px' => 'var(--space-6)',
    '24px' => 'var(--space-6)',
    '28px' => 'var(--space-8)',
    '30px' => 'var(--space-8)',
    '32px' => 'var(--space-8)',
    '36px' => 'var(--space-10)',
    '40px' => 'var(--space-10)',
    '44px' => 'var(--space-12)',
    '48px' => 'var(--space-12)',
    '56px' => 'var(--space-16)',
    '60px' => 'var(--space-16)',
    '64px' => 'var(--space-16)',
    '72px' => 'var(--space-20)',
    '80px' => 'var(--space-20)',
];

// Statistics
$stats = [
    'files_processed' => 0,
    'files_modified' => 0,
    'colors_replaced' => 0,
    'glassmorphism_removed' => 0,
    'spacing_replaced' => 0,
    'animations_removed' => 0,
];

// Get all CSS files
$files = glob($cssDir . '/' . $pattern);

// Exclude minified and backup files
$files = array_filter($files, function($file) {
    return !str_contains($file, '.min.css')
        && !str_contains($file, '-backup')
        && !str_contains($file, 'legacy');
});

echo "CivicOne CSS GOV.UK Refactoring\n";
echo "================================\n\n";
echo "Found " . count($files) . " CSS files to process\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);
    $originalContent = $content;
    $fileStats = ['colors' => 0, 'glass' => 0, 'spacing' => 0, 'animations' => 0];

    // 1. Replace colors
    foreach ($colorMappings as $hex => $variable) {
        $pattern = '/' . preg_quote($hex, '/') . '/i';
        $count = 0;
        $content = preg_replace($pattern, $variable, $content, -1, $count);
        $fileStats['colors'] += $count;
    }

    // 2. Remove glassmorphism (backdrop-filter with blur)
    // Replace with solid background
    $glassPatterns = [
        '/backdrop-filter:\s*blur\([^)]+\);?/i' => '/* backdrop-filter removed for GOV.UK compliance */',
        '/-webkit-backdrop-filter:\s*blur\([^)]+\);?/i' => '',
    ];
    foreach ($glassPatterns as $pattern => $replacement) {
        $count = 0;
        $content = preg_replace($pattern, $replacement, $content, -1, $count);
        $fileStats['glass'] += $count;
    }

    // 3. Replace arbitrary spacing with GOV.UK scale
    // Only for padding/margin/gap properties
    foreach ($spacingMappings as $px => $variable) {
        // Match padding, margin, gap with specific px value
        $escapedPx = preg_quote($px, '/');
        $patterns = [
            "/(padding(?:-(?:top|right|bottom|left))?\s*:\s*)$escapedPx/i",
            "/(margin(?:-(?:top|right|bottom|left))?\s*:\s*)$escapedPx/i",
            "/(gap\s*:\s*)$escapedPx/i",
            "/(row-gap\s*:\s*)$escapedPx/i",
            "/(column-gap\s*:\s*)$escapedPx/i",
        ];
        foreach ($patterns as $pattern) {
            $count = 0;
            $content = preg_replace($pattern, '$1' . $variable, $content, -1, $count);
            $fileStats['spacing'] += $count;
        }
    }

    // 4. Remove custom @keyframes animations (except GOV.UK approved ones)
    // Keep: govuk-* animations, simple opacity/transform transitions
    $animationPattern = '/@keyframes\s+(?!govuk-)[a-zA-Z_-]+\s*\{[^}]*(?:\{[^}]*\}[^}]*)*\}/s';
    $count = 0;
    $content = preg_replace($animationPattern, '/* Animation removed for GOV.UK compliance */', $content, -1, $count);
    $fileStats['animations'] += $count;

    // Remove animation: properties that reference removed animations
    // (but keep transition: which is GOV.UK approved)
    $animationPropertyPattern = '/animation(?:-name)?\s*:\s*(?!none|inherit|initial|unset)[^;]+;/i';
    $count = 0;
    $content = preg_replace($animationPropertyPattern, '/* animation removed for GOV.UK compliance */', $content, -1, $count);
    $fileStats['animations'] += $count;

    // Update stats
    $stats['colors_replaced'] += $fileStats['colors'];
    $stats['glassmorphism_removed'] += $fileStats['glass'];
    $stats['spacing_replaced'] += $fileStats['spacing'];
    $stats['animations_removed'] += $fileStats['animations'];
    $stats['files_processed']++;

    // Only write if content changed
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        $stats['files_modified']++;
        echo "✓ $filename: {$fileStats['colors']} colors, {$fileStats['glass']} glass, {$fileStats['spacing']} spacing, {$fileStats['animations']} animations\n";
    }
}

echo "\n================================\n";
echo "Summary:\n";
echo "Files processed: {$stats['files_processed']}\n";
echo "Files modified: {$stats['files_modified']}\n";
echo "Colors replaced: {$stats['colors_replaced']}\n";
echo "Glassmorphism removed: {$stats['glassmorphism_removed']}\n";
echo "Spacing values replaced: {$stats['spacing_replaced']}\n";
echo "Animations removed: {$stats['animations_removed']}\n";
echo "\nTotal fixes: " . ($stats['colors_replaced'] + $stats['glassmorphism_removed'] + $stats['spacing_replaced'] + $stats['animations_removed']) . "\n";
