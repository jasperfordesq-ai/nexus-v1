<?php
/**
 * Shared Component: Accessibility Helpers
 *
 * Reusable accessibility utilities for consistent ARIA and semantic HTML
 * Date: 2026-01-11
 */

// Skip to content link (include in header)
function renderSkipLink() {
    ?>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <?php
}

// Screen reader only text
function srOnly($text) {
    return '<span class="sr-only">' . htmlspecialchars($text) . '</span>';
}

// Accessible button with icon
function iconButton($icon, $label, $onclick = '', $additionalClasses = '', $additionalAttrs = '') {
    $classes = 'icon-btn ' . $additionalClasses;
    $onclickAttr = $onclick ? 'onclick="' . htmlspecialchars($onclick) . '"' : '';
    ?>
    <button type="button"
            class="<?= $classes ?>"
            aria-label="<?= htmlspecialchars($label) ?>"
            <?= $onclickAttr ?>
            <?= $additionalAttrs ?>>
        <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
        <span class="sr-only"><?= htmlspecialchars($label) ?></span>
    </button>
    <?php
}

// Accessible icon link
function iconLink($href, $icon, $label, $additionalClasses = '') {
    $classes = 'icon-link ' . $additionalClasses;
    ?>
    <a href="<?= htmlspecialchars($href) ?>"
       class="<?= $classes ?>"
       aria-label="<?= htmlspecialchars($label) ?>">
        <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
        <span class="sr-only"><?= htmlspecialchars($label) ?></span>
    </a>
    <?php
}

// Add global accessibility CSS
?>
