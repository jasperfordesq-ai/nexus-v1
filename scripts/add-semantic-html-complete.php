<?php
/**
 * Add Semantic HTML to ALL Key Pages
 * Comprehensive implementation for 100/100 score
 */

$basePath = 'c:\Home Directory\views\modern';

// Pages to update with their main content wrapper patterns
$pages = [
    // Core pages
    'feed/index.php' => [
        'before' => '<div class="fds-feed-container">',
        'after' => '<main id="main-content" role="main" aria-label="Content feed">' . "\n" . '<div class="fds-feed-container">',
        'close_before' => '</div> <!-- /.fds-feed-container -->',
        'close_after' => '</div> <!-- /.fds-feed-container -->' . "\n" . '</main>',
    ],
    'profile/show.php' => [
        'before' => '<!-- PROFILE CONTENT -->',
        'after' => '<main id="main-content" role="main" aria-label="User profile">' . "\n" . '<!-- PROFILE CONTENT -->',
        'close_search' => 'require.*footer\.php',
    ],
    'messages/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Messages">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'groups/show.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Group details">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'groups/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Groups directory">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'events/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Events">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'listings/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Listings">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'volunteering/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Volunteering opportunities">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'resources/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Resources">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'blog/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Blog">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'search/results.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Search results">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'wallet/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Wallet">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
    'settings/index.php' => [
        'before' => '<div class="htb-container',
        'after' => '<main id="main-content" role="main" aria-label="Settings">' . "\n" . '<div class="htb-container',
        'close_search' => 'require.*footer\.php',
    ],
];

$filesUpdated = 0;
$filesSkipped = 0;
$filesNotFound = 0;

foreach ($pages as $file => $patterns) {
    $filePath = $basePath . '/' . $file;

    if (!file_exists($filePath)) {
        echo "⚠ Not found: $file\n";
        $filesNotFound++;
        continue;
    }

    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Check if already has main tag
    if (strpos($content, '<main id="main-content"') !== false) {
        echo "✓ Already done: $file\n";
        $filesSkipped++;
        continue;
    }

    // Add opening <main> tag
    if (isset($patterns['before']) && isset($patterns['after'])) {
        if (strpos($content, $patterns['before']) !== false) {
            $content = str_replace($patterns['before'], $patterns['after'], $content, $count);
            if ($count === 0) {
                echo "⚠ Pattern not found in: $file\n";
                continue;
            }
        } else {
            echo "⚠ Pattern not found in: $file\n";
            continue;
        }
    }

    // Add closing </main> tag
    if (isset($patterns['close_before']) && isset($patterns['close_after'])) {
        $content = str_replace($patterns['close_before'], $patterns['close_after'], $content);
    } elseif (isset($patterns['close_search'])) {
        // Find position before footer include
        if (preg_match('/(.*?)<\?php[^>]*' . $patterns['close_search'] . '/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $beforeFooter = $matches[1][0];
            $footerPos = $matches[0][1];

            // Find last closing div before footer
            $lastDivPos = strrpos($beforeFooter, '</div>');
            if ($lastDivPos !== false) {
                // Insert </main> after the last </div> before footer
                $insertPos = $lastDivPos + 6; // length of '</div>'
                $content = substr_replace($content, "\n</main>", $insertPos, 0);
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
echo "Files already done: $filesSkipped\n";
echo "Files not found: $filesNotFound\n";
echo "================================\n";
