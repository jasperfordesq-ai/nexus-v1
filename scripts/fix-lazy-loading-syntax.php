<?php
/**
 * Script: Fix Lazy Loading Syntax Errors
 *
 * Fixes incorrect placement of loading="lazy" inside PHP ternary operators
 * Pattern: <?= ... ? loading="lazy">
 * Fix to: <?= ... ?>" loading="lazy">
 *
 * Usage: php scripts/fix-lazy-loading-syntax.php
 */

$viewsDir = __DIR__ . '/../views/modern';

function fixLazyLoadingInFile($filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changes = 0;

    // Pattern: Find <?= ... ? loading="lazy"> and fix it
    // This happens when lazy loading is inserted before the closing quote
    $pattern = '/(<\?=\s*[^>]*?\s*\?)\s*loading="lazy">([^<]*alt=[^>]*>)/';
    $replacement = '$1>$2';

    $content = preg_replace_callback($pattern, function($matches) use (&$changes) {
        $changes++;
        // Extract the full img tag to properly place loading="lazy"
        $beforeClosing = $matches[1];
        $afterQuote = $matches[2];

        // Place loading="lazy" before the final >
        if (preg_match('/(.*)(>)$/', $afterQuote, $m)) {
            return $beforeClosing . '>"' . ' loading="lazy"' . $m[2];
        }
        return $beforeClosing . '>" loading="lazy"' . $afterQuote;
    }, $content);

    // More specific pattern: Fix cases like src="<?= ... ? loading="lazy">"
    $pattern2 = '/(src=["\']<\?=\s*[^>]+?)\s*\?\s*loading="lazy">(["\'])/';
    $content = preg_replace_callback($pattern2, function($matches) use (&$changes) {
        $changes++;
        return $matches[1] . ' ?>' . $matches[2] . ' loading="lazy"';
    }, $content);

    // Save if changes were made
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return $changes;
    }

    return 0;
}

function processDirectory($dir) {
    $totalChanges = 0;
    $filesProcessed = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getPathname();

            // Check if file contains the error pattern
            $content = file_get_contents($filePath);
            if (strpos($content, '? loading="lazy">') !== false) {
                $changes = fixLazyLoadingInFile($filePath);
                if ($changes > 0) {
                    $filesProcessed++;
                    $totalChanges += $changes;
                    echo "✓ " . basename($filePath) . " ($changes fixes)\n";
                }
            }
        }
    }

    return [$filesProcessed, $totalChanges];
}

echo "Fixing lazy loading syntax errors in views/modern/\n";
echo str_repeat("=", 50) . "\n\n";

list($filesProcessed, $totalChanges) = processDirectory($viewsDir);

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Complete!\n";
echo "Files fixed: $filesProcessed\n";
echo "Errors corrected: $totalChanges\n";
?>
