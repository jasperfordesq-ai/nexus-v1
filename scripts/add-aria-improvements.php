<?php
/**
 * Add ARIA Improvements to Interactive Elements
 * Enhances accessibility with proper ARIA labels, roles, and live regions
 */

$basePath = 'c:\Home Directory\views\modern';

$filesUpdated = 0;
$improvementsCount = 0;

// Pattern replacements for common interactive elements
$patterns = [
    // Like buttons
    [
        'search' => '<button class="nexus-like-btn',
        'replace' => '<button class="nexus-like-btn" role="button" aria-label="Like this post"',
        'condition' => function($line) { return strpos($line, 'aria-label') === false; }
    ],

    // Comment buttons
    [
        'search' => '<button class="nexus-comment-btn',
        'replace' => '<button class="nexus-comment-btn" role="button" aria-label="Comment on this post"',
        'condition' => function($line) { return strpos($line, 'aria-label') === false; }
    ],

    // Share buttons
    [
        'search' => '<button class="nexus-share-btn',
        'replace' => '<button class="nexus-share-btn" role="button" aria-label="Share this post"',
        'condition' => function($line) { return strpos($line, 'aria-label') === false; }
    ],

    // Dropdown menus
    [
        'search' => '<button class="dropdown-toggle',
        'replace' => '<button class="dropdown-toggle" aria-haspopup="true" aria-expanded="false"',
        'condition' => function($line) { return strpos($line, 'aria-haspopup') === false; }
    ],

    // Navigation items
    [
        'search' => '<nav class="',
        'replace' => '<nav role="navigation" aria-label="Main navigation" class="',
        'condition' => function($line) { return strpos($line, 'role=') === false && strpos($line, '<nav ') !== false; }
    ],

    // Search forms
    [
        'search' => 'type="search"',
        'replace' => 'type="search" aria-label="Search"',
        'condition' => function($line) { return strpos($line, 'aria-label') === false && strpos($line, 'type="search"') !== false; }
    ],

    // Modal dialogs
    [
        'search' => 'class="modal',
        'replace' => 'class="modal" role="dialog" aria-modal="true"',
        'condition' => function($line) { return strpos($line, 'role="dialog"') === false; }
    ],
];

// Get all PHP files in views/modern
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getPathname();
        $relativePath = str_replace($basePath . '\\', '', $filePath);

        $content = file_get_contents($filePath);
        $originalContent = $content;
        $fileImprovements = 0;

        foreach ($patterns as $pattern) {
            $lines = explode("\n", $content);
            $modifiedLines = [];

            foreach ($lines as $line) {
                if (strpos($line, $pattern['search']) !== false && $pattern['condition']($line)) {
                    $line = str_replace($pattern['search'], $pattern['replace'], $line);
                    $fileImprovements++;
                    $improvementsCount++;
                }
                $modifiedLines[] = $line;
            }

            $content = implode("\n", $modifiedLines);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $filesUpdated++;
            echo "✓ Updated: $relativePath ($fileImprovements improvements)\n";
        }
    }
}

echo "\n================================\n";
echo "Files updated: $filesUpdated\n";
echo "Total ARIA improvements: $improvementsCount\n";
echo "================================\n";
