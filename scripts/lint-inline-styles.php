#!/usr/bin/env php
<?php
/**
 * Inline Style Linter for PHP Files
 *
 * Enforces CLAUDE.md rule: NEVER use inline style="" attributes
 * except for truly dynamic values (e.g., calculated widths)
 *
 * Usage: php scripts/lint-inline-styles.php
 * Exit Code: 0 = passed, 1 = violations found
 */

$exitCode = 0;
$violationCount = 0;
$fileCount = 0;

// Directories to scan
$scanDirs = ['views'];

// Patterns that are allowed (truly dynamic values)
$allowedPatterns = [
    // Dynamic color from variable
    '/style="color:\s*<\?=.*?\?>;?"/',
    // Calculated width/height from variable
    '/style="(?:width|height):\s*<\?=.*?\?>;?"/',
    // Background position from variable
    '/style="background-position:\s*<\?=.*?\?>;?"/',
    // Transform with calculation
    '/style="transform:\s*<\?=.*?\?>;?"/',
    // Truly conditional styles (e.g., conditional display based on variable)
    '/style="<\?=.*?\?>"/',
];

echo "\n🔍 Scanning PHP files for inline style violations...\n";
echo "📋 Per CLAUDE.md: inline styles only allowed for truly dynamic values\n\n";

foreach ($scanDirs as $dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getPathname();
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        // Find all style attributes
        preg_match_all('/style="[^"]*"/', $content, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $styleAttr = $match[0];
            $offset = $match[1];

            // Check if this is an allowed pattern
            $isAllowed = false;
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $styleAttr)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                // Find line number
                $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;
                $line = $lines[$lineNum - 1];

                // Extract just the relevant part
                $snippet = trim(substr($line, max(0, strpos($line, 'style=') - 10), 80));
                if (strlen($line) > 80) {
                    $snippet .= '...';
                }

                if ($violationCount === 0) {
                    echo "❌ VIOLATIONS FOUND:\n\n";
                }

                echo "  File: \033[33m{$filePath}\033[0m\n";
                echo "  Line: \033[33m{$lineNum}\033[0m\n";
                echo "  Code: \033[90m{$snippet}\033[0m\n";
                echo "  \033[31m✗ Static inline style detected\033[0m\n";
                echo "  💡 Fix: Extract to CSS class in /httpdocs/assets/css/\n\n";

                $violationCount++;
                $fileCount++;
                $exitCode = 1;
            }
        }
    }
}

if ($violationCount === 0) {
    echo "✅ \033[32mNo violations found!\033[0m All inline styles are properly dynamic or extracted to CSS.\n\n";
} else {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 SUMMARY:\n";
    echo "  Total violations: \033[31m{$violationCount}\033[0m\n";
    echo "  Files affected: \033[31m{$fileCount}\033[0m\n\n";
    echo "🔧 NEXT STEPS:\n";
    echo "  1. Extract static styles to CSS files in /httpdocs/assets/css/\n";
    echo "  2. Replace inline styles with CSS classes\n";
    echo "  3. Load CSS files via layout headers (views/layouts/*/header.php)\n";
    echo "  4. Add new CSS files to purgecss.config.js\n\n";
    echo "📖 See CLAUDE.md for full CSS organization rules\n\n";
}

exit($exitCode);
