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
    <style>
    .skip-link {
        position: absolute;
        top: -40px;
        left: 0;
        background: #000;
        color: #fff;
        padding: 8px 16px;
        text-decoration: none;
        z-index: 10000;
        border-radius: 0 0 4px 0;
        font-weight: 600;
    }
    .skip-link:focus {
        top: 0;
        outline: 3px solid #6366f1;
        outline-offset: 2px;
    }
    </style>
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
<style>
/* Screen reader only utility class */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

/* Focus visible for keyboard navigation */
*:focus-visible {
    outline: 3px solid #6366f1;
    outline-offset: 2px;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

/* Remove outline for mouse users */
*:focus:not(:focus-visible) {
    outline: none;
}

/* Better button/link focus */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 3px solid #6366f1;
    outline-offset: 2px;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

/* Minimum touch target sizes for mobile */
@media (max-width: 900px) {
    button,
    a.btn,
    input[type="button"],
    input[type="submit"] {
        min-height: 44px;
        min-width: 44px;
    }
}
</style>
