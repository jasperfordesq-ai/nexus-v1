<?php
/**
 * Hero Rendering Helper
 * Include this partial in page templates to render the hero
 *
 * Usage in page templates:
 *
 * <?php
 * // Option 1: Let auto-resolve determine hero from route
 * require __DIR__ . '/../../layouts/civicone/header.php';
 * require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
 * ?>
 *
 * <?php
 * // Option 2: Override specific hero properties
 * $hero = ['title' => 'Custom Title', 'lead' => 'Custom lead paragraph'];
 * require __DIR__ . '/../../layouts/civicone/header.php';
 * require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
 * ?>
 *
 * @version 1.0.0
 * @since 2026-01-21
 */

// Auto-resolve hero if not already set
if (!isset($hero)) {
    // Use namespaced class (autoloaded via composer)
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
    // Remove query string
    $currentPath = strtok($currentPath, '?');

    // Get base configuration from route
    $hero = \App\Helpers\HeroResolver::resolve($currentPath);
}

// Allow controller/page-specific overrides to be merged
if (isset($heroOverrides) && is_array($heroOverrides)) {
    $hero = array_merge($hero ?? [], $heroOverrides);
}

// Render hero partial if config exists and has title
if (isset($hero) && is_array($hero) && !empty($hero['title'])) {
    require __DIR__ . '/page-hero.php';
}
