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

// Check if running in pre-commit hook (check only staged files)
$isPreCommit = getenv('GIT_AUTHOR_DATE') || isset($_SERVER['GIT_AUTHOR_DATE']);
$filesToCheck = [];

if ($isPreCommit || (isset($argv[1]) && $argv[1] === '--staged')) {
    // Get list of staged PHP files
    exec('git diff --cached --name-only --diff-filter=ACM', $stagedFiles);
    $filesToCheck = array_filter($stagedFiles, function($file) {
        return str_ends_with($file, '.php')
            && file_exists($file)
            && !str_starts_with($file, 'scripts/');  // Exclude scripts directory
    });

    if (empty($filesToCheck)) {
        echo "\n✅ \033[32mNo PHP files staged for commit.\033[0m\n\n";
        exit(0);
    }
} else {
    // Scan all files in views directory
    $scanDirs = ['views'];
}

// Patterns that are allowed (truly dynamic values)
$allowedPatterns = [
    // Dynamic color from variable
    '/style="color:\s*<\?=.*?\?>;?"/',
    // Calculated width/height from variable
    '/style="(?:width|height):\s*<\?=.*?\?\>[^"]*"/',
    // Background position from variable
    '/style="background-position:\s*<\?=.*?\?>;?"/',
    // Transform with calculation
    '/style="transform:\s*<\?=.*?\?>;?"/',
    // Truly conditional styles (e.g., conditional display based on variable)
    '/style="<\?=.*?\?>"/',
    // CSS custom properties with PHP values (e.g., style="--progress: PHP_VALUE")
    '/style="--[a-z-]+:\s*<\?=.*?\?\>/',
    // Concatenated PHP dynamic styles (e.g., echo "style=x" . $var)
    '/style="[^"]*\'\s*\.\s*/',
    // Animation delay/duration from PHP (e.g., style="animation-delay: $i * 0.1s")
    '/style="animation-(?:delay|duration):\s*<\?=.*?\?\>/',
    // Conditional display from PHP (e.g., style="display: ternary_expr")
    '/style="display:\s*<\?=.*?\?\>/',
    // Width/height percentage from PHP long tags (e.g., width: php echo $x percent)
    '/style="(?:width|height):\s*<\?php\s+echo\s+/',
    // Left/right position from PHP (e.g., left: php echo $x percent)
    '/style="(?:left|right|top|bottom):\s*<\?(?:=|php\s+echo)\s*/',
    // CSS custom properties with PHP long tags (style="--var: php echo)
    '/style="--[a-z-]+:\s*<\?php\s+echo\s+/',
    // Background color from PHP (e.g., background-color: php echo $color)
    '/style="background(?:-color)?:\s*<\?(?:=|php\s+echo)\s*/',
];

echo "\n🔍 Scanning PHP files for inline style violations...\n";
echo "📋 Per CLAUDE.md: inline styles only allowed for truly dynamic values\n\n";

if (!empty($filesToCheck)) {
    // Check only specific files (staged files)
    foreach ($filesToCheck as $filePath) {
        $filePath = trim($filePath);
        if (!file_exists($filePath)) {
            continue;
        }

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
} else {
    // Scan directories (full scan mode)
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
