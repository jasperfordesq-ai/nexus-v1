<?php
/**
 * Admin Breadcrumbs Component
 * Automatically generates breadcrumbs from current URL and navigation config
 */

use Nexus\Core\TenantContext;

// Load navigation config
$adminNavigation = require __DIR__ . '/../../../../config/admin-navigation.php';

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');

// Remove base path from current path for matching
$relativePath = str_replace($basePath, '', $currentPathClean);

/**
 * Build breadcrumbs array from navigation config
 */
function buildAdminBreadcrumbs($navigation, $currentPath, $basePath) {
    $breadcrumbs = [];

    // Always start with Admin Dashboard
    $breadcrumbs[] = [
        'label' => 'Admin',
        'url' => $basePath . '/admin',
        'icon' => 'fa-gauge-high',
        'isHome' => true,
    ];

    // If we're on the dashboard, just return that
    if ($currentPath === '/admin' || $currentPath === '/admin/') {
        $breadcrumbs[0]['isCurrent'] = true;
        return $breadcrumbs;
    }

    // Search through navigation to find matching item
    foreach ($navigation as $groupKey => $group) {
        // Skip dashboard single item (already added)
        if (isset($group['single']) && $group['single']) {
            continue;
        }

        if (!isset($group['sections'])) continue;

        foreach ($group['sections'] as $sectionKey => $section) {
            $sectionMatched = false;
            $matchedChild = null;

            // Check children first (more specific match)
            if (isset($section['children'])) {
                foreach ($section['children'] as $child) {
                    $childPath = $child['url'];
                    // Check for exact match or prefix match
                    if ($currentPath === $childPath ||
                        (strpos($currentPath, $childPath) === 0 && strlen($childPath) > strlen('/admin/'))) {
                        $sectionMatched = true;
                        $matchedChild = $child;
                        break;
                    }
                }
            }

            // Check section URL
            if (!$sectionMatched && isset($section['url'])) {
                if ($currentPath === $section['url'] || strpos($currentPath, $section['url']) === 0) {
                    $sectionMatched = true;
                }
            }

            if ($sectionMatched) {
                // Add group label
                $breadcrumbs[] = [
                    'label' => $group['label'],
                    'url' => null, // Groups don't have URLs
                    'icon' => null,
                    'isGroup' => true,
                ];

                // Add section
                $breadcrumbs[] = [
                    'label' => $section['label'],
                    'url' => $basePath . ($section['url'] ?? '#'),
                    'icon' => $section['icon'],
                ];

                // Add matched child if different from section
                if ($matchedChild) {
                    $breadcrumbs[] = [
                        'label' => $matchedChild['label'],
                        'url' => $basePath . $matchedChild['url'],
                        'icon' => $matchedChild['icon'],
                        'isCurrent' => true,
                    ];
                } else {
                    // Mark section as current
                    $breadcrumbs[count($breadcrumbs) - 1]['isCurrent'] = true;
                }

                return $breadcrumbs;
            }
        }
    }

    // If no match found, try to generate from URL segments
    $segments = explode('/', trim($currentPath, '/'));
    if (count($segments) > 1) {
        // Skip 'admin' segment
        array_shift($segments);

        foreach ($segments as $i => $segment) {
            $label = ucwords(str_replace(['-', '_'], ' ', $segment));
            $isLast = ($i === count($segments) - 1);

            $breadcrumbs[] = [
                'label' => $label,
                'url' => $isLast ? null : $basePath . '/admin/' . implode('/', array_slice($segments, 0, $i + 1)),
                'icon' => null,
                'isCurrent' => $isLast,
            ];
        }
    }

    return $breadcrumbs;
}

$breadcrumbs = buildAdminBreadcrumbs($adminNavigation, $relativePath, $basePath);
$totalCrumbs = count($breadcrumbs);
?>

<?php if ($totalCrumbs > 1): ?>
<nav class="admin-breadcrumbs" aria-label="Breadcrumb">
    <ol class="admin-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php
            $isFirst = ($index === 0);
            $isLast = ($index === $totalCrumbs - 1);
            $isCurrent = !empty($crumb['isCurrent']);
            $isGroup = !empty($crumb['isGroup']);
            $isHome = !empty($crumb['isHome']);

            // Collapse middle items on mobile (show first, last, and one before last)
            $isCollapsible = ($totalCrumbs > 3 && $index > 0 && $index < $totalCrumbs - 2);
            ?>

            <li class="admin-breadcrumbs-item <?= $isCollapsible ? 'admin-breadcrumbs-collapse' : '' ?>"
                itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">

                <?php if ($isHome && !$isCurrent): ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>"
                       class="admin-breadcrumbs-home"
                       itemprop="item"
                       title="Admin Dashboard">
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <meta itemprop="name" content="<?= htmlspecialchars($crumb['label']) ?>">
                    </a>
                <?php elseif ($isCurrent || $isGroup || empty($crumb['url'])): ?>
                    <span class="admin-breadcrumbs-current" itemprop="name">
                        <?php if (!empty($crumb['icon']) && !$isGroup): ?>
                            <i class="fa-solid <?= htmlspecialchars($crumb['icon']) ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($crumb['label']) ?>
                    </span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>"
                       class="admin-breadcrumbs-link"
                       itemprop="item">
                        <?php if (!empty($crumb['icon'])): ?>
                            <i class="fa-solid <?= htmlspecialchars($crumb['icon']) ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span itemprop="name"><?= htmlspecialchars($crumb['label']) ?></span>
                    </a>
                <?php endif; ?>

                <meta itemprop="position" content="<?= $index + 1 ?>">
            </li>

            <?php if (!$isLast): ?>
                <li class="admin-breadcrumbs-separator <?= $isCollapsible ? 'admin-breadcrumbs-collapse' : '' ?>" aria-hidden="true">
                    <i class="fa-solid fa-chevron-right"></i>
                </li>
            <?php endif; ?>

            <?php // Show ellipsis for collapsed items on mobile ?>
            <?php if ($index === 0 && $totalCrumbs > 3): ?>
                <li class="admin-breadcrumbs-ellipsis admin-breadcrumbs-separator" aria-hidden="true">
                    <span>...</span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
