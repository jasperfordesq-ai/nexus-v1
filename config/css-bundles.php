<?php
/**
 * CSS Bundle Configuration
 * Optimizes CSS loading with code-splitting and minification
 *
 * Performance targets:
 * - Critical CSS: <14KB (inlined in HTML)
 * - Main bundle: <50KB gzipped
 * - Feature bundles: Lazy-loaded on demand
 */

return [
    // Critical CSS - Always inlined in <head>
    'critical' => [
        'inline' => true,
        'files' => [
            'css/design-tokens.css',           // CSS custom properties
            'css/layout-skeleton.css',         // Above-the-fold structure
            'css/critical-components.css',     // Header, nav, fold content
        ]
    ],

    // Main bundle - Core styles loaded immediately
    'main' => [
        'files' => [
            'css/nexus-phoenix.css',           // Base framework
            'css/nexus-modern-header.css',     // Header system
            'css/post-box-home.css',           // Home layout
            'css/glass.css',                   // Glassmorphism utilities
        ],
        'minify' => true,
        'output' => 'css/bundles/main.min.css'
    ],

    // Mobile bundle - Loaded for mobile viewport only
    // Updated 2026-01-17: Removed fds-mobile.css (abandoned mobile app deleted)
    'mobile' => [
        'files' => [
            'css/nexus-mobile.css',
            'css/nexus-native-nav-v2.css',     // Keep only v2
            'css/civicone-mobile.css',
        ],
        'minify' => true,
        'output' => 'css/bundles/mobile.min.css',
        'condition' => '(max-width: 768px)'
    ],

    // Feature bundles - Lazy-loaded on demand
    // Updated 2026-01-17: Removed non-existent files (nexus-blog.css, nexus-achievements.css)
    'features' => [
        'admin' => [
            'files' => ['css/admin-gold-standard.css'],
            'output' => 'css/bundles/admin.min.css'
        ],
        // Blog bundle removed - nexus-blog.css doesn't exist (blog uses inline styles)
        'groups' => [
            'files' => ['css/nexus-groups.css'],
            'output' => 'css/bundles/groups.min.css'
        ],
        // Achievements bundle removed - nexus-achievements.css doesn't exist (uses inline styles)
        'home-enhanced' => [
            'files' => ['css/nexus-home.css'],
            'output' => 'css/bundles/home-enhanced.min.css'
        ]
    ],

    // Theme bundle - Removed 2026-01-17 (theme-dark.css and theme-light.css don't exist)
    // Theme switching is handled by data-theme attribute and CSS variables in nexus-phoenix.css
    // 'theme' => [
    //     'files' => [
    //         'css/theme-dark.css',
    //         'css/theme-light.css',
    //     ],
    //     'minify' => true,
    //     'output' => 'css/bundles/theme.min.css'
    // ],

    // Performance enhancements
    'optimizations' => [
        'remove_unused_css' => true,
        'merge_media_queries' => true,
        'minify_output' => true,
        'generate_source_maps' => true,
        'cache_busting' => true,
    ]
];
