<?php

/**
 * Component: Sidebar Layout
 *
 * Two-column layout with sidebar and main content area.
 *
 * @param string $sidebar Sidebar content
 * @param string $content Main content
 * @param string $sidebarWidth Sidebar width (default: '320px')
 * @param string $sidebarPosition 'left' or 'right' (default: 'left')
 * @param string $class Additional CSS classes
 * @param bool $stickyScidebar Make sidebar sticky (default: true)
 */

$sidebar = $sidebar ?? '';
$content = $content ?? '';
$sidebarWidth = $sidebarWidth ?? '320px';
$sidebarPosition = $sidebarPosition ?? 'left';
$class = $class ?? '';
$stickySidebar = $stickySidebar ?? true;

$cssClass = trim('sidebar-layout ' . $class);

// Determine width class
$widthClass = 'sidebar-layout__sidebar--w-320'; // default
if ($sidebarWidth === '280px') $widthClass = 'sidebar-layout__sidebar--w-280';
elseif ($sidebarWidth === '300px') $widthClass = 'sidebar-layout__sidebar--w-300';
elseif ($sidebarWidth === '350px') $widthClass = 'sidebar-layout__sidebar--w-350';

$sidebarClass = 'sidebar-layout__sidebar ' . $widthClass;
if ($stickySidebar) $sidebarClass .= ' sidebar-layout__sidebar--sticky';
?>

<div class="<?= e($cssClass) ?>">
    <?php if ($sidebarPosition === 'left'): ?>
        <aside class="<?= e($sidebarClass) ?>">
            <?= $sidebar ?>
        </aside>
        <main class="sidebar-layout__content">
            <?= $content ?>
        </main>
    <?php else: ?>
        <main class="sidebar-layout__content">
            <?= $content ?>
        </main>
        <aside class="<?= e($sidebarClass) ?>">
            <?= $sidebar ?>
        </aside>
    <?php endif; ?>
</div>
