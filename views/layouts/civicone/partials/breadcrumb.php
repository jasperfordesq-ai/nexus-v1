<?php
/**
 * CivicOne Breadcrumb Navigation Component
 * WCAG 2.1 AA Compliant
 *
 * Usage:
 *   $breadcrumbs = [
 *       ['label' => 'Home', 'url' => '/'],
 *       ['label' => 'Listings', 'url' => '/listings'],
 *       ['label' => 'Current Page'] // No URL = current page
 *   ];
 *   require 'partials/breadcrumb.php';
 */

if (!isset($breadcrumbs) || !is_array($breadcrumbs) || empty($breadcrumbs)) return;

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Breadcrumb CSS (extracted per CLAUDE.md) -->
<link rel="stylesheet" href="/assets/css/civicone-breadcrumb.css">

<!-- Breadcrumb removed inline style block -->

<nav class="civic-breadcrumb" aria-label="Breadcrumb navigation">
    <ol class="civic-breadcrumb-list">
        <?php
        $totalItems = count($breadcrumbs);
        foreach ($breadcrumbs as $index => $crumb):
            $isLast = ($index === $totalItems - 1);
        ?>
            <li class="civic-breadcrumb-item">
                <?php if (!$isLast && isset($crumb['url'])): ?>
                    <a href="<?= $basePath . $crumb['url'] ?>" class="civic-breadcrumb-link">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </a>
                <?php else: ?>
                    <span class="civic-breadcrumb-current" aria-current="page">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
