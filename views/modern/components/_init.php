<?php

/**
 * Component Library Initialization
 *
 * Include this file to get helper functions for rendering components.
 *
 * Usage:
 *   require_once __DIR__ . '/../components/_init.php';
 *   render('layout/hero', ['title' => 'Events', 'icon' => 'calendar']);
 */

define('COMPONENTS_PATH', __DIR__);

/**
 * Render a component and return as string
 *
 * @param string $component Component path relative to components/ (e.g., 'layout/hero')
 * @param array $params Parameters to pass to component
 * @return string Rendered HTML
 */
function component(string $component, array $params = []): string
{
    // Extract params to local scope
    extract($params);

    // Capture output
    ob_start();
    include COMPONENTS_PATH . '/' . $component . '.php';
    return ob_get_clean();
}

/**
 * Echo a component directly
 *
 * @param string $component Component path relative to components/ (e.g., 'layout/hero')
 * @param array $params Parameters to pass to component
 */
function render(string $component, array $params = []): void
{
    echo component($component, $params);
}

/**
 * Escape HTML output safely
 *
 * @param string|null $value Value to escape
 * @return string Escaped value
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Build CSS class string from array
 *
 * @param array $classes Array of class names (falsy values are filtered out)
 * @return string Space-separated class string
 */
function classes(array $classes): string
{
    return implode(' ', array_filter($classes));
}

/**
 * Build HTML attributes string from array
 *
 * @param array $attributes Key-value pairs of attributes
 * @return string HTML attributes string
 */
function attrs(array $attributes): string
{
    $html = [];
    foreach ($attributes as $key => $value) {
        if ($value === true) {
            $html[] = e($key);
        } elseif ($value !== false && $value !== null) {
            $html[] = e($key) . '="' . e($value) . '"';
        }
    }
    return implode(' ', $html);
}
