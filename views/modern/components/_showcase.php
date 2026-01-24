<?php
/**
 * Component Library Showcase
 *
 * Renders ALL components on a single page for easy browsing.
 * Access via: /dev/component-showcase
 *
 * @updated 2026-01-24
 */

require_once __DIR__ . '/_init.php';

// Get base path for assets
$basePath = \Nexus\Core\TenantContext::getBasePath();

/**
 * Safely include a component file, showing placeholder if missing
 */
function safeInclude($path, $vars = []) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        extract($vars);
        include $fullPath;
        return true;
    } else {
        echo '<div class="showcase__demo-text">File not found: ' . htmlspecialchars($path) . '</div>';
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Component Library Showcase - All 77 Components</title>

    <!-- Core Framework CSS -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/design-tokens.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/nexus-phoenix.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/core.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components.min.css">

    <!-- Component CSS Bundles -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-navigation.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-buttons.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-forms.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-cards.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-modals.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/components-notifications.min.css">

    <!-- Utilities & Polish -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/utilities-polish.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/bundles/enhancements.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/social-interactions.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/components.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/partials.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/avatar-placeholders.min.css">

    <!-- Card & Glass Styles -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/glass.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/card-hover-states.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/post-card.min.css">

    <!-- Component Library Specific -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/modern/components-library.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--color-background, #f8fafc);
            color: var(--color-text, #1a1a1a);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .showcase {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-6, 24px);
        }

        .showcase__header {
            text-align: center;
            padding: var(--space-10, 48px) var(--space-6, 24px);
            margin-bottom: var(--space-8, 32px);
            background: linear-gradient(135deg, var(--color-primary-600, #4f46e5) 0%, var(--color-primary-700, #4338ca) 100%);
            border-radius: var(--radius-xl, 16px);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .showcase__header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .showcase__title {
            font-size: 2.75rem;
            font-weight: 700;
            margin: 0 0 var(--space-2, 8px);
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .showcase__subtitle {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.9);
            margin: 0;
            position: relative;
        }

        .showcase__stats {
            display: flex;
            justify-content: center;
            gap: var(--space-4, 16px);
            margin-top: var(--space-5, 20px);
            position: relative;
        }

        .showcase__stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: var(--space-3, 12px) var(--space-5, 20px);
            border-radius: var(--radius-lg, 8px);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }

        .showcase__stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            display: block;
        }

        .showcase__stat-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .showcase__toc {
            background: var(--color-surface, #fff);
            padding: var(--space-5, 20px);
            border-radius: var(--radius-xl, 16px);
            border: 1px solid var(--color-border, #e5e5e5);
            margin-bottom: var(--space-8, 32px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .showcase__toc-title {
            font-size: 0.8rem;
            font-weight: 600;
            margin: 0 0 var(--space-3, 12px);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-muted, #64748b);
        }

        .showcase__toc-list {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-2, 8px);
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .showcase__toc-item a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: var(--space-2, 8px) var(--space-4, 16px);
            background: var(--color-surface-alt, #f8fafc);
            border-radius: var(--radius-lg, 8px);
            color: var(--color-text, #1e293b);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .showcase__toc-item a:hover {
            background: var(--color-primary-50, #eef2ff);
            color: var(--color-primary-700, #4338ca);
            border-color: var(--color-primary-200, #c7d2fe);
            transform: translateY(-1px);
        }

        .showcase__toc-item a i {
            opacity: 0.7;
        }

        .showcase__category {
            margin-bottom: var(--space-10, 40px);
        }

        .showcase__category-header {
            display: flex;
            align-items: center;
            gap: var(--space-3, 12px);
            padding: var(--space-4, 16px) var(--space-5, 20px);
            background: var(--color-surface, #fff);
            border-radius: var(--radius-lg, 12px);
            border: 1px solid var(--color-border, #e5e5e5);
            margin-bottom: var(--space-5, 20px);
            position: sticky;
            top: 12px;
            z-index: 10;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }

        .showcase__category-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary-500, #6366f1), var(--color-primary-600, #4f46e5));
            color: white;
            border-radius: var(--radius-lg, 10px);
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .showcase__category-title {
            font-size: 1.35rem;
            font-weight: 600;
            margin: 0;
            color: var(--color-text, #1e293b);
        }

        .showcase__category-count {
            margin-left: auto;
            background: var(--color-primary-50, #eef2ff);
            color: var(--color-primary-700, #4338ca);
            padding: var(--space-1, 4px) var(--space-4, 16px);
            border-radius: var(--radius-full, 9999px);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .showcase__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--space-4, 16px);
        }

        .showcase__component {
            background: var(--color-surface, #fff);
            border-radius: var(--radius-lg, 12px);
            border: 1px solid var(--color-border, #e5e5e5);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .showcase__component:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 8px 24px rgba(0,0,0,0.06);
            transform: translateY(-2px);
            border-color: var(--color-primary-200, #c7d2fe);
        }

        .showcase__component-header {
            padding: var(--space-3, 12px) var(--space-4, 16px);
            background: linear-gradient(to right, var(--color-surface-alt, #f8fafc), var(--color-surface, #fff));
            border-bottom: 1px solid var(--color-border, #e5e5e5);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .showcase__component-name {
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0;
            color: var(--color-text, #1e293b);
        }

        .showcase__component-file {
            font-size: 0.7rem;
            color: var(--color-primary-600, #4f46e5);
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            background: var(--color-primary-50, #eef2ff);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .showcase__component-preview {
            padding: var(--space-5, 20px);
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(0,0,0,0.01) 10px,
                rgba(0,0,0,0.01) 20px
            );
        }

        .showcase__component-preview--left {
            justify-content: flex-start;
        }

        .showcase__component-preview--full {
            display: block;
        }

        .showcase__demo-text {
            color: var(--color-text-muted, #94a3b8);
            font-size: 0.875rem;
            font-style: italic;
        }

        .showcase__back-to-top {
            position: fixed;
            bottom: var(--space-6, 24px);
            right: var(--space-6, 24px);
            width: 48px;
            height: 48px;
            background: var(--color-primary-600, #4f46e5);
            color: white;
            border: none;
            border-radius: var(--radius-full, 9999px);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }

        .showcase__back-to-top:hover {
            background: var(--color-primary-700, #4338ca);
            transform: translateY(-2px);
        }

        /* Component-specific overrides for demo */
        .showcase__component-preview .component-hero,
        .showcase__component-preview .component-section {
            width: 100%;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Scroll margin for sticky header */
        section[id] {
            scroll-margin-top: 80px;
        }

        @media (max-width: 768px) {
            .showcase__grid {
                grid-template-columns: 1fr;
            }

            .showcase__stats {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }

            .showcase__stat {
                min-width: 100px;
            }

            .showcase__header {
                padding: var(--space-6, 24px);
            }

            .showcase__title {
                font-size: 1.75rem;
            }

            .showcase__subtitle {
                font-size: 1rem;
            }

            .showcase__toc-list {
                gap: var(--space-2, 8px);
            }

            .showcase__toc-item a {
                padding: var(--space-1, 4px) var(--space-3, 12px);
                font-size: 0.8rem;
            }

            .showcase__category-header {
                top: 8px;
                padding: var(--space-3, 12px);
            }

            .showcase__category-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .showcase__category-title {
                font-size: 1.1rem;
            }
        }

        /* Print styles */
        @media print {
            .showcase__back-to-top,
            .showcase__toc {
                display: none;
            }

            .showcase__category-header {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="showcase">
        <header class="showcase__header">
            <h1 class="showcase__title">Component Library Showcase</h1>
            <p class="showcase__subtitle">All 77 reusable components in the Modern theme library</p>
            <div class="showcase__stats">
                <div class="showcase__stat">
                    <div class="showcase__stat-value">77</div>
                    <div class="showcase__stat-label">Components</div>
                </div>
                <div class="showcase__stat">
                    <div class="showcase__stat-value">12</div>
                    <div class="showcase__stat-label">Categories</div>
                </div>
                <div class="showcase__stat">
                    <div class="showcase__stat-value">0</div>
                    <div class="showcase__stat-label">Pages Using (yet)</div>
                </div>
            </div>
        </header>

        <!-- Table of Contents -->
        <nav class="showcase__toc">
            <h2 class="showcase__toc-title">Jump to Category</h2>
            <ul class="showcase__toc-list">
                <li class="showcase__toc-item"><a href="#layout"><i class="fa-solid fa-layer-group"></i> Layout (5)</a></li>
                <li class="showcase__toc-item"><a href="#navigation"><i class="fa-solid fa-compass"></i> Navigation (6)</a></li>
                <li class="showcase__toc-item"><a href="#cards"><i class="fa-solid fa-id-card"></i> Cards (10)</a></li>
                <li class="showcase__toc-item"><a href="#forms"><i class="fa-solid fa-keyboard"></i> Forms (15)</a></li>
                <li class="showcase__toc-item"><a href="#buttons"><i class="fa-solid fa-hand-pointer"></i> Buttons (3)</a></li>
                <li class="showcase__toc-item"><a href="#feedback"><i class="fa-solid fa-bell"></i> Feedback (6)</a></li>
                <li class="showcase__toc-item"><a href="#media"><i class="fa-solid fa-image"></i> Media (5)</a></li>
                <li class="showcase__toc-item"><a href="#data"><i class="fa-solid fa-table"></i> Data (4)</a></li>
                <li class="showcase__toc-item"><a href="#interactive"><i class="fa-solid fa-hand-sparkles"></i> Interactive (7)</a></li>
                <li class="showcase__toc-item"><a href="#social"><i class="fa-solid fa-share-nodes"></i> Social (3)</a></li>
                <li class="showcase__toc-item"><a href="#shared"><i class="fa-solid fa-puzzle-piece"></i> Shared (2)</a></li>
                <li class="showcase__toc-item"><a href="#nexus"><i class="fa-solid fa-chart-line"></i> Nexus Score (6)</a></li>
            </ul>
        </nav>

        <!-- LAYOUT COMPONENTS -->
        <section class="showcase__category" id="layout">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-layer-group"></i></div>
                <h2 class="showcase__category-title">Layout</h2>
                <span class="showcase__category-count">5 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Hero -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Hero</h3>
                        <span class="showcase__component-file">layout/hero.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $title = 'Welcome';
                        $subtitle = 'Hero subtitle text';
                        $icon = 'rocket';
                        $variant = 'compact';
                        $buttons = [];
                        $badge = [];
                        $class = '';
                        include __DIR__ . '/layout/hero.php';
                        ?>
                    </div>
                </div>

                <!-- Section -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Section</h3>
                        <span class="showcase__component-file">layout/section.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $title = 'Section Title';
                        $subtitle = 'Section description';
                        $icon = 'cube';
                        $content = '<p>Section content goes here</p>';
                        $actions = [];
                        $variant = 'card';
                        include __DIR__ . '/layout/section.php';
                        ?>
                    </div>
                </div>

                <!-- Container -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Container</h3>
                        <span class="showcase__component-file">layout/container.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $size = 'md';
                        $class = '';
                        ob_start();
                        ?>
                        <div class="showcase__demo-box">Container content (max-width controlled)</div>
                        <?php
                        $slot = ob_get_clean();
                        include __DIR__ . '/layout/container.php';
                        ?>
                    </div>
                </div>

                <!-- Grid -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Grid</h3>
                        <span class="showcase__component-file">layout/grid.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $cols = 3;
                        $gap = 'sm';
                        $items = ['Item 1', 'Item 2', 'Item 3'];
                        include __DIR__ . '/layout/grid.php';
                        ?>
                    </div>
                </div>

                <!-- Sidebar Layout -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Sidebar Layout</h3>
                        <span class="showcase__component-file">layout/sidebar-layout.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Two-column layout with sidebar</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- NAVIGATION COMPONENTS -->
        <section class="showcase__category" id="navigation">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-compass"></i></div>
                <h2 class="showcase__category-title">Navigation</h2>
                <span class="showcase__category-count">6 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Breadcrumb -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Breadcrumb</h3>
                        <span class="showcase__component-file">navigation/breadcrumb.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--left">
                        <?php
                        $items = [
                            ['label' => 'Home', 'href' => '#'],
                            ['label' => 'Components', 'href' => '#'],
                            ['label' => 'Breadcrumb', 'href' => '']
                        ];
                        $separator = '/';
                        $class = '';
                        include __DIR__ . '/navigation/breadcrumb.php';
                        ?>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Tabs</h3>
                        <span class="showcase__component-file">navigation/tabs.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $tabs = [
                            ['id' => 'tab1', 'label' => 'Overview', 'icon' => 'home'],
                            ['id' => 'tab2', 'label' => 'Details', 'icon' => 'info-circle'],
                            ['id' => 'tab3', 'label' => 'Settings', 'icon' => 'cog']
                        ];
                        $activeTab = 'tab1';
                        $variant = 'default';
                        include __DIR__ . '/navigation/tabs.php';
                        ?>
                    </div>
                </div>

                <!-- Pills -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Pills</h3>
                        <span class="showcase__component-file">navigation/pills.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--left">
                        <?php
                        $items = [
                            ['id' => 'all', 'label' => 'All'],
                            ['id' => 'active', 'label' => 'Active'],
                            ['id' => 'archived', 'label' => 'Archived']
                        ];
                        $active = 'all';
                        $size = 'md';
                        include __DIR__ . '/navigation/pills.php';
                        ?>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Pagination</h3>
                        <span class="showcase__component-file">navigation/pagination.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $currentPage = 3;
                        $totalPages = 10;
                        $baseUrl = '#page=';
                        $maxVisible = 5;
                        include __DIR__ . '/navigation/pagination.php';
                        ?>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Filter Bar</h3>
                        <span class="showcase__component-file">navigation/filter-bar.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $filters = [
                            ['id' => 'all', 'label' => 'All', 'count' => 42],
                            ['id' => 'offers', 'label' => 'Offers', 'count' => 28],
                            ['id' => 'requests', 'label' => 'Requests', 'count' => 14]
                        ];
                        $active = 'all';
                        $showSearch = false;
                        include __DIR__ . '/navigation/filter-bar.php';
                        ?>
                    </div>
                </div>

                <!-- Dropdown -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Dropdown</h3>
                        <span class="showcase__component-file">navigation/dropdown.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $trigger = '<button class="component-btn component-btn--secondary">Options <i class="fa-solid fa-chevron-down"></i></button>';
                        $items = [
                            ['label' => 'Edit', 'href' => '#', 'icon' => 'pen'],
                            ['label' => 'Delete', 'href' => '#', 'icon' => 'trash']
                        ];
                        $align = 'left';
                        include __DIR__ . '/navigation/dropdown.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- BUTTONS COMPONENTS -->
        <section class="showcase__category" id="buttons">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-hand-pointer"></i></div>
                <h2 class="showcase__category-title">Buttons</h2>
                <span class="showcase__category-count">3 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Button -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Button</h3>
                        <span class="showcase__component-file">buttons/button.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__button-grid">
                            <?php
                            foreach (['primary', 'secondary', 'outline', 'ghost', 'danger'] as $v) {
                                $variant = $v;
                                $label = ucfirst($v);
                                $icon = '';
                                $size = 'md';
                                $disabled = false;
                                $loading = false;
                                $href = '';
                                $type = 'button';
                                $class = '';
                                include __DIR__ . '/buttons/button.php';
                                echo ' ';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Icon Button -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Icon Button</h3>
                        <span class="showcase__component-file">buttons/icon-button.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $icon = 'heart';
                        $label = 'Like';
                        $variant = 'ghost';
                        $size = 'md';
                        include __DIR__ . '/buttons/icon-button.php';
                        echo ' ';
                        $icon = 'share';
                        $label = 'Share';
                        include __DIR__ . '/buttons/icon-button.php';
                        echo ' ';
                        $icon = 'bookmark';
                        $label = 'Save';
                        include __DIR__ . '/buttons/icon-button.php';
                        ?>
                    </div>
                </div>

                <!-- Button Group -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Button Group</h3>
                        <span class="showcase__component-file">buttons/button-group.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $buttons = [
                            ['label' => 'Day', 'value' => 'day'],
                            ['label' => 'Week', 'value' => 'week', 'active' => true],
                            ['label' => 'Month', 'value' => 'month']
                        ];
                        $name = 'period';
                        $variant = 'outline';
                        include __DIR__ . '/buttons/button-group.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- FORMS COMPONENTS -->
        <section class="showcase__category" id="forms">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                <h2 class="showcase__category-title">Forms</h2>
                <span class="showcase__category-count">15 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Input -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Input</h3>
                        <span class="showcase__component-file">forms/input.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_input';
                        $type = 'text';
                        $value = '';
                        $placeholder = 'Enter text...';
                        $icon = 'search';
                        $disabled = false;
                        $required = false;
                        $label = '';
                        $error = '';
                        include __DIR__ . '/forms/input.php';
                        ?>
                    </div>
                </div>

                <!-- Textarea -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Textarea</h3>
                        <span class="showcase__component-file">forms/textarea.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_textarea';
                        $value = '';
                        $placeholder = 'Enter longer text...';
                        $rows = 3;
                        $autoResize = false;
                        include __DIR__ . '/forms/textarea.php';
                        ?>
                    </div>
                </div>

                <!-- Select -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Select</h3>
                        <span class="showcase__component-file">forms/select.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_select';
                        $options = ['opt1' => 'Option 1', 'opt2' => 'Option 2', 'opt3' => 'Option 3'];
                        $selected = '';
                        $placeholder = 'Choose an option';
                        include __DIR__ . '/forms/select.php';
                        ?>
                    </div>
                </div>

                <!-- Checkbox -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Checkbox</h3>
                        <span class="showcase__component-file">forms/checkbox.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--left">
                        <?php
                        $name = 'demo_checkbox';
                        $label = 'Accept terms and conditions';
                        $checked = false;
                        $description = 'You must agree to continue';
                        include __DIR__ . '/forms/checkbox.php';
                        ?>
                    </div>
                </div>

                <!-- Radio -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Radio</h3>
                        <span class="showcase__component-file">forms/radio.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--left">
                        <?php
                        $name = 'demo_radio';
                        $options = [
                            ['value' => 'small', 'label' => 'Small'],
                            ['value' => 'medium', 'label' => 'Medium'],
                            ['value' => 'large', 'label' => 'Large']
                        ];
                        $selected = 'medium';
                        include __DIR__ . '/forms/radio.php';
                        ?>
                    </div>
                </div>

                <!-- Toggle Switch -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Toggle Switch</h3>
                        <span class="showcase__component-file">forms/toggle-switch.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--left">
                        <?php
                        $name = 'demo_toggle';
                        $label = 'Enable notifications';
                        $checked = true;
                        $size = 'md';
                        include __DIR__ . '/forms/toggle-switch.php';
                        ?>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">File Upload</h3>
                        <span class="showcase__component-file">forms/file-upload.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_file';
                        $accept = 'image/*';
                        $multiple = false;
                        $maxSize = 5242880;
                        include __DIR__ . '/forms/file-upload.php';
                        ?>
                    </div>
                </div>

                <!-- Date Picker -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Date Picker</h3>
                        <span class="showcase__component-file">forms/date-picker.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_date';
                        $label = 'Select date';
                        $value = '';
                        $min = '';
                        $max = '';
                        $required = false;
                        $format = 'default';
                        include __DIR__ . '/forms/date-picker.php';
                        ?>
                    </div>
                </div>

                <!-- Time Picker -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Time Picker</h3>
                        <span class="showcase__component-file">forms/time-picker.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_time';
                        $label = 'Select time';
                        $value = '';
                        $min = '';
                        $max = '';
                        $step = 60;
                        $show12Hour = true;
                        include __DIR__ . '/forms/time-picker.php';
                        ?>
                    </div>
                </div>

                <!-- Range Slider -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Range Slider</h3>
                        <span class="showcase__component-file">forms/range-slider.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_range';
                        $label = 'Distance (km)';
                        $value = 50;
                        $min = 0;
                        $max = 100;
                        $step = 1;
                        $showValue = true;
                        $color = 'primary';
                        include __DIR__ . '/forms/range-slider.php';
                        ?>
                    </div>
                </div>

                <!-- Rich Text Editor -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Rich Text Editor</h3>
                        <span class="showcase__component-file">forms/rich-text-editor.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_rte';
                        $label = '';
                        $value = '';
                        $placeholder = 'Start typing...';
                        $minHeight = 100;
                        $maxHeight = 200;
                        $variant = 'minimal';
                        include __DIR__ . '/forms/rich-text-editor.php';
                        ?>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Search Input</h3>
                        <span class="showcase__component-file">forms/search-input.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $name = 'demo_search';
                        $value = '';
                        $placeholder = 'Search...';
                        $showClear = true;
                        $size = 'md';
                        include __DIR__ . '/forms/search-input.php';
                        ?>
                    </div>
                </div>

                <!-- Form Group -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Form Group</h3>
                        <span class="showcase__component-file">forms/form-group.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $label = 'Email Address';
                        $for = 'demo_email';
                        $error = '';
                        $hint = 'We will never share your email';
                        $required = true;
                        ob_start();
                        ?>
                        <input type="email" id="demo_email" class="component-input" placeholder="you@example.com">
                        <?php
                        $slot = ob_get_clean();
                        include __DIR__ . '/forms/form-group.php';
                        ?>
                    </div>
                </div>

                <!-- Search Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Search Card</h3>
                        <span class="showcase__component-file">forms/search-card.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $title = 'Find Services';
                        $placeholder = 'What are you looking for?';
                        $action = '#';
                        $filters = [];
                        include __DIR__ . '/forms/search-card.php';
                        ?>
                    </div>
                </div>

                <!-- Tag Input (not yet created) -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Tag Input</h3>
                        <span class="showcase__component-file">forms/tag-input.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Component not yet created</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CARDS COMPONENTS -->
        <section class="showcase__category" id="cards">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-square"></i></div>
                <h2 class="showcase__category-title">Cards</h2>
                <span class="showcase__category-count">10 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Card Base -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Card (Base)</h3>
                        <span class="showcase__component-file">cards/card.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $header = '<strong>Card Title</strong>';
                        $body = 'This is the card body content.';
                        $footer = '<small>Card footer</small>';
                        $variant = 'default';
                        $href = '';
                        include __DIR__ . '/cards/card.php';
                        ?>
                    </div>
                </div>

                <!-- Stat Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Stat Card</h3>
                        <span class="showcase__component-file">cards/stat-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $label = 'Total Users';
                        $value = '1,234';
                        $icon = 'users';
                        $trend = 'up';
                        $trendValue = '+12%';
                        include __DIR__ . '/cards/stat-card.php';
                        ?>
                    </div>
                </div>

                <!-- Member Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Member Card</h3>
                        <span class="showcase__component-file">cards/member-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $user = [
                            'id' => 1,
                            'first_name' => 'Jane',
                            'last_name' => 'Smith',
                            'avatar_url' => '',
                            'bio' => 'Community volunteer',
                            'skills' => ['Gardening', 'Teaching']
                        ];
                        $showBio = true;
                        $showSkills = true;
                        include __DIR__ . '/cards/member-card.php';
                        ?>
                    </div>
                </div>

                <!-- Listing Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Listing Card</h3>
                        <span class="showcase__component-file">cards/listing-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $listing = [
                            'id' => 1,
                            'title' => 'Guitar Lessons',
                            'description' => 'Learn guitar basics',
                            'type' => 'offer',
                            'image_url' => '',
                            'hours' => 2
                        ];
                        $compact = false;
                        $showUser = false;
                        include __DIR__ . '/cards/listing-card.php';
                        ?>
                    </div>
                </div>

                <!-- Event Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Event Card</h3>
                        <span class="showcase__component-file">cards/event-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $event = [
                            'id' => 1,
                            'title' => 'Community Meetup',
                            'description' => 'Monthly gathering',
                            'start_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                            'location' => 'City Hall',
                            'attendees' => [
                                ['name' => 'Jane Doe', 'avatar' => ''],
                                ['name' => 'John Smith', 'avatar' => '']
                            ],
                            'attendee_count' => 24
                        ];
                        $showAttendees = true;
                        $showRsvp = true;
                        include __DIR__ . '/cards/event-card.php';
                        ?>
                    </div>
                </div>

                <!-- Group Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Group Card</h3>
                        <span class="showcase__component-file">cards/group-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $group = [
                            'id' => 1,
                            'name' => 'Gardeners Club',
                            'description' => 'For plant lovers',
                            'member_count' => 45,
                            'image_url' => ''
                        ];
                        $showMembers = true;
                        include __DIR__ . '/cards/group-card.php';
                        ?>
                    </div>
                </div>

                <!-- Achievement Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Achievement Card</h3>
                        <span class="showcase__component-file">cards/achievement-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $badge = [
                            'name' => 'First Steps',
                            'description' => 'Complete your profile',
                            'icon' => 'star',
                            'progress' => 75
                        ];
                        $showProgress = true;
                        $locked = false;
                        include __DIR__ . '/cards/achievement-card.php';
                        ?>
                    </div>
                </div>

                <!-- Volunteer Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Volunteer Card</h3>
                        <span class="showcase__component-file">cards/volunteer-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $opportunity = [
                            'id' => 1,
                            'title' => 'Food Bank Helper',
                            'organization' => 'Local Charity',
                            'location' => 'Downtown',
                            'hours_needed' => 4
                        ];
                        $showOrg = true;
                        include __DIR__ . '/cards/volunteer-card.php';
                        ?>
                    </div>
                </div>

                <!-- Resource Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Resource Card</h3>
                        <span class="showcase__component-file">cards/resource-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $resource = [
                            'id' => 1,
                            'title' => 'Volunteer Guide',
                            'type' => 'PDF',
                            'size' => '2.4 MB',
                            'downloads' => 156
                        ];
                        $showDownload = true;
                        include __DIR__ . '/cards/resource-card.php';
                        ?>
                    </div>
                </div>

                <!-- Post Card -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Post Card</h3>
                        <span class="showcase__component-file">cards/post-card.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $post = [
                            'id' => 1,
                            'content' => 'Great community event today!',
                            'likes' => 42,
                            'comments' => 8,
                            'created_at' => '2 hours ago'
                        ];
                        $showActions = true;
                        include __DIR__ . '/cards/post-card.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEEDBACK COMPONENTS -->
        <section class="showcase__category" id="feedback">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-bell"></i></div>
                <h2 class="showcase__category-title">Feedback</h2>
                <span class="showcase__category-count">6 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Alert -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Alert</h3>
                        <span class="showcase__component-file">feedback/alert.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $type = 'info';
                        $message = 'This is an informational alert message.';
                        $dismissible = true;
                        $title = '';
                        include __DIR__ . '/feedback/alert.php';
                        ?>
                    </div>
                </div>

                <!-- Badge -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Badge</h3>
                        <span class="showcase__component-file">media/badge.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        foreach (['primary', 'success', 'warning', 'danger'] as $v) {
                            $variant = $v;
                            $label = ucfirst($v);
                            $size = 'md';
                            $icon = '';
                            include __DIR__ . '/media/badge.php';
                            echo ' ';
                        }
                        ?>
                    </div>
                </div>

                <!-- Toast -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Toast</h3>
                        <span class="showcase__component-file">feedback/toast.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $type = 'success';
                        $message = 'Changes saved successfully!';
                        $duration = 5000;
                        include __DIR__ . '/feedback/toast.php';
                        ?>
                    </div>
                </div>

                <!-- Progress -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Progress Bar</h3>
                        <span class="showcase__component-file">data/progress-bar.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $percent = 65;
                        $label = 'Progress';
                        $showPercent = true;
                        $color = 'primary';
                        $size = 'md';
                        include __DIR__ . '/data/progress-bar.php';
                        ?>
                    </div>
                </div>

                <!-- Spinner -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Loading Spinner</h3>
                        <span class="showcase__component-file">feedback/loading-spinner.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $size = 'md';
                        $variant = 'spinner';
                        $message = 'Loading...';
                        include __DIR__ . '/feedback/loading-spinner.php';
                        ?>
                    </div>
                </div>

                <!-- Empty State -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Empty State</h3>
                        <span class="showcase__component-file">feedback/empty-state.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $icon = 'inbox';
                        $title = 'No results found';
                        $description = 'Try adjusting your search criteria';
                        $action = '';
                        $actionLabel = '';
                        include __DIR__ . '/feedback/empty-state.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- MEDIA COMPONENTS -->
        <section class="showcase__category" id="media">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-photo-film"></i></div>
                <h2 class="showcase__category-title">Media</h2>
                <span class="showcase__category-count">5 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Avatar -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Avatar</h3>
                        <span class="showcase__component-file">media/avatar.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        foreach (['sm', 'md', 'lg', 'xl'] as $s) {
                            $size = $s;
                            $src = '';
                            $name = 'John Doe';
                            $status = '';
                            include __DIR__ . '/media/avatar.php';
                            echo ' ';
                        }
                        ?>
                    </div>
                </div>

                <!-- Image -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Image</h3>
                        <span class="showcase__component-file">media/image.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $src = 'https://via.placeholder.com/200x150';
                        $alt = 'Placeholder image';
                        $aspectRatio = '4:3';
                        $rounded = true;
                        include __DIR__ . '/media/image.php';
                        ?>
                    </div>
                </div>

                <!-- Gallery -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Gallery</h3>
                        <span class="showcase__component-file">media/gallery.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Image gallery with lightbox</div>
                    </div>
                </div>

                <!-- Video Embed -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Video Embed</h3>
                        <span class="showcase__component-file">media/video-embed.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">YouTube/Vimeo embed</div>
                    </div>
                </div>

                <!-- Code Block -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Code Block</h3>
                        <span class="showcase__component-file">media/code-block.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $code = "<?php\necho 'Hello World';";
                        $language = 'php';
                        $title = 'example.php';
                        $showLineNumbers = true;
                        $showCopy = true;
                        $wrap = false;
                        include __DIR__ . '/media/code-block.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- DATA COMPONENTS -->
        <section class="showcase__category" id="data">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-table"></i></div>
                <h2 class="showcase__category-title">Data Display</h2>
                <span class="showcase__category-count">4 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Table -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Table</h3>
                        <span class="showcase__component-file">data/table.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $headers = [
                            ['key' => 'name', 'label' => 'Name'],
                            ['key' => 'email', 'label' => 'Email'],
                            ['key' => 'role', 'label' => 'Role']
                        ];
                        $rows = [
                            ['name' => 'John', 'email' => 'john@example.com', 'role' => 'Admin'],
                            ['name' => 'Jane', 'email' => 'jane@example.com', 'role' => 'User']
                        ];
                        $variant = 'striped';
                        $hoverable = true;
                        $compact = false;
                        include __DIR__ . '/data/table.php';
                        ?>
                    </div>
                </div>

                <!-- List -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">List</h3>
                        <span class="showcase__component-file">data/list.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $items = [
                            ['label' => 'Item one', 'icon' => 'check'],
                            ['label' => 'Item two', 'icon' => 'check'],
                            ['label' => 'Item three', 'icon' => 'check']
                        ];
                        $variant = 'divided';
                        $hoverable = true;
                        include __DIR__ . '/data/list.php';
                        ?>
                    </div>
                </div>

                <!-- Timeline Item -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Timeline Item</h3>
                        <span class="showcase__component-file">data/timeline-item.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $icon = 'check';
                        $title = 'Task completed';
                        $content = 'Profile setup finished';
                        $time = '2 hours ago';
                        $variant = 'success';
                        include __DIR__ . '/data/timeline-item.php';
                        ?>
                    </div>
                </div>

                <!-- Key Value -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Key Value</h3>
                        <span class="showcase__component-file">data/key-value.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <div class="showcase__demo-text">Component not yet created</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- INTERACTIVE COMPONENTS -->
        <section class="showcase__category" id="interactive">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-hand-sparkles"></i></div>
                <h2 class="showcase__category-title">Interactive</h2>
                <span class="showcase__category-count">7 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Accordion -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Accordion</h3>
                        <span class="showcase__component-file">interactive/accordion.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $items = [
                            ['id' => 'acc1', 'title' => 'Section 1', 'content' => 'Content for section 1', 'expanded' => true],
                            ['id' => 'acc2', 'title' => 'Section 2', 'content' => 'Content for section 2'],
                            ['id' => 'acc3', 'title' => 'Section 3', 'content' => 'Content for section 3']
                        ];
                        $allowMultiple = false;
                        $variant = 'bordered';
                        include __DIR__ . '/interactive/accordion.php';
                        ?>
                    </div>
                </div>

                <!-- Tooltip -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Tooltip</h3>
                        <span class="showcase__component-file">interactive/tooltip.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $content = 'This is a tooltip!';
                        $position = 'top';
                        $trigger = '<button class="component-btn component-btn--secondary">Hover me</button>';
                        include __DIR__ . '/interactive/tooltip.php';
                        ?>
                    </div>
                </div>

                <!-- Copy Button -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Copy Button</h3>
                        <span class="showcase__component-file">interactive/copy-button.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $text = 'Text to copy';
                        $label = 'Copy';
                        $successLabel = 'Copied!';
                        include __DIR__ . '/interactive/copy-button.php';
                        ?>
                    </div>
                </div>

                <!-- Share Button -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Share Button</h3>
                        <span class="showcase__component-file">interactive/share-button.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $url = 'https://example.com';
                        $title = 'Check this out!';
                        $networks = ['twitter', 'facebook', 'linkedin'];
                        include __DIR__ . '/interactive/share-button.php';
                        ?>
                    </div>
                </div>

                <!-- Star Rating -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Star Rating</h3>
                        <span class="showcase__component-file">interactive/star-rating.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <?php
                        $value = 4;
                        $readonly = false;
                        $size = 'md';
                        include __DIR__ . '/interactive/star-rating.php';
                        ?>
                    </div>
                </div>

                <!-- Poll Voting -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Poll Voting</h3>
                        <span class="showcase__component-file">interactive/poll-voting.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $options = [
                            ['id' => 1, 'text' => 'Option A', 'votes' => 45],
                            ['id' => 2, 'text' => 'Option B', 'votes' => 30],
                            ['id' => 3, 'text' => 'Option C', 'votes' => 25]
                        ];
                        $showResults = true;
                        $userVote = null;
                        include __DIR__ . '/interactive/poll-voting.php';
                        ?>
                    </div>
                </div>

                <!-- Draggable List -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Draggable List</h3>
                        <span class="showcase__component-file">interactive/draggable-list.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $items = [
                            ['id' => 1, 'content' => 'Item 1'],
                            ['id' => 2, 'content' => 'Item 2'],
                            ['id' => 3, 'content' => 'Item 3']
                        ];
                        $name = 'order';
                        $showHandle = true;
                        $showRemove = false;
                        $variant = 'default';
                        include __DIR__ . '/interactive/draggable-list.php';
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- SOCIAL COMPONENTS -->
        <section class="showcase__category" id="social">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-users"></i></div>
                <h2 class="showcase__category-title">Social</h2>
                <span class="showcase__category-count">3 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Comment Section -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Comment Section</h3>
                        <span class="showcase__component-file">social/comment-section.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Full comment thread with replies</div>
                    </div>
                </div>

                <!-- Notification Item -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Notification Item</h3>
                        <span class="showcase__component-file">social/notification-item.php</span>
                    </div>
                    <div class="showcase__component-preview showcase__component-preview--full">
                        <?php
                        $notification = [
                            'type' => 'like',
                            'message' => 'John liked your post',
                            'time' => '5 min ago',
                            'read' => false
                        ];
                        $unread = true;
                        include __DIR__ . '/social/notification-item.php';
                        ?>
                    </div>
                </div>

                <!-- Profile Header -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Profile Header</h3>
                        <span class="showcase__component-file">social/profile-header.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">User profile header with stats</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SHARED COMPONENTS -->
        <section class="showcase__category" id="shared">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <h2 class="showcase__category-title">Shared (Reusable)</h2>
                <span class="showcase__category-count">2 components</span>
            </div>
            <div class="showcase__grid">
                <!-- Post Card (Shared) -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Post Card</h3>
                        <span class="showcase__component-file">shared/post-card.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Reusable post card for feed/profile</div>
                    </div>
                </div>

                <!-- Accessibility Helpers -->
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Accessibility Helpers</h3>
                        <span class="showcase__component-file">shared/accessibility-helpers.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Helper functions for a11y</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- NEXUS SCORE COMPONENTS -->
        <section class="showcase__category" id="nexus">
            <div class="showcase__category-header">
                <div class="showcase__category-icon"><i class="fa-solid fa-star"></i></div>
                <h2 class="showcase__category-title">Nexus Score</h2>
                <span class="showcase__category-count">6 components</span>
            </div>
            <div class="showcase__grid">
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Achievement Showcase</h3>
                        <span class="showcase__component-file">achievement-showcase.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Badge & achievement display</div>
                    </div>
                </div>
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Nexus Leaderboard</h3>
                        <span class="showcase__component-file">nexus-leaderboard.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Community rankings</div>
                    </div>
                </div>
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Nexus Score Widget</h3>
                        <span class="showcase__component-file">nexus-score-widget.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Compact score display</div>
                    </div>
                </div>
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Nexus Score Dashboard</h3>
                        <span class="showcase__component-file">nexus-score-dashboard.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Full score breakdown</div>
                    </div>
                </div>
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Nexus Score Charts</h3>
                        <span class="showcase__component-file">nexus-score-charts.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Score visualizations</div>
                    </div>
                </div>
                <div class="showcase__component">
                    <div class="showcase__component-header">
                        <h3 class="showcase__component-name">Org UI Components</h3>
                        <span class="showcase__component-file">org-ui-components.php</span>
                    </div>
                    <div class="showcase__component-preview">
                        <div class="showcase__demo-text">Organization widgets</div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <button class="showcase__back-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Back to top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

</body>
</html>
