<?php
/**
 * Add Semantic HTML to Key Pages
 * Wraps main content areas with <main id="main-content" role="main">
 */

$basePath = 'c:\Home Directory\views\modern';

// Pages to update with their content wrapper patterns
$pages = [
    // Already done: 'home.php'
    'feed/index.php' => [
        'start' => '<div class="fds-feed-container">',
        'start_replace' => '<main id="main-content" role="main">' . "\n" . '<div class="fds-feed-container">',
        'end' => '</div> <!-- /.fds-feed-container -->',
        'end_replace' => '</div> <!-- /.fds-feed-container -->' . "\n" . '</main>',
    ],
    'profile/show.php' => [
        'start' => '<div class="htb-container-full">',
        'start_replace' => '<main id="main-content" role="main">' . "\n" . '<div class="htb-container-full">',
        'end_pattern' => true, // Use regex to find last closing div before footer
    ],
    'messages/index.php' => [
        'start' => '<div class="htb-container',
        'start_replace' => '<main id="main-content" role="main">' . "\n" . '<div class="htb-container',
        'end_pattern' => true,
    ],
    'groups/show.php' => [
        'start' => '<div class="htb-container',
        'start_replace' => '<main id="main-content" role="main">' . "\n" . '<div class="htb-container',
        'end_pattern' => true,
    ],
];

$filesUpdated = 0;

foreach ($pages as $file => $patterns) {
    $filePath = $basePath . '/' . $file;

    if (!file_exists($filePath)) {
        echo "⚠ Skipped (not found): $file\n";
        continue;
    }

    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Check if already has main tag
    if (strpos($content, '<main id="main-content"') !== false) {
        echo "✓ Already done: $file\n";
        continue;
    }

    // Add opening <main> tag
    if (isset($patterns['start']) && isset($patterns['start_replace'])) {
        $content = str_replace($patterns['start'], $patterns['start_replace'], $content);
    }

    // Add closing </main> tag
    if (isset($patterns['end'])) {
        $content = str_replace($patterns['end'], $patterns['end_replace'], $content);
    } elseif (isset($patterns['end_pattern']) && $patterns['end_pattern'] === true) {
        // Find the last </div> before the footer include
        if (preg_match('/(.*)<\/div>\s*<\?php\s*(?:\/\/.*\n)?.*require.*footer\.php/s', $content, $matches)) {
            $beforeFooter = $matches[1];
            $lastDivPos = strrpos($beforeFooter, '</div>');
            if ($lastDivPos !== false) {
                $content = substr_replace($content, '</div>' . "\n" . '</main>', $lastDivPos, 6);
            }
        }
    }

    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        $filesUpdated++;
        echo "✓ Updated: $file\n";
    } else {
        echo "⚠ No changes: $file\n";
    }
}

echo "\n================================\n";
echo "Files updated: $filesUpdated\n";
echo "================================\n";
