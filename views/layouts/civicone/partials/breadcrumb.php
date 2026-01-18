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

<style>
    .civic-breadcrumb {
        padding: 12px 0;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }

    .civic-breadcrumb-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .civic-breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .civic-breadcrumb-item:not(:last-child)::after {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-right: 2px solid var(--civic-text-secondary, #4B5563);
        border-bottom: 2px solid var(--civic-text-secondary, #4B5563);
        transform: rotate(-45deg);
    }

    .civic-breadcrumb-link {
        color: var(--civic-brand, #00796B);
        text-decoration: underline;
        font-weight: 500;
    }

    .civic-breadcrumb-link:hover {
        text-decoration: none;
        color: var(--civic-brand-dark, #005a4f);
    }

    .civic-breadcrumb-link:focus {
        outline: 3px solid var(--civic-brand, #00796B);
        outline-offset: 2px;
    }

    .civic-breadcrumb-current {
        color: var(--civic-text-main, #1F2937);
        font-weight: 600;
    }

    /* Dark mode */
    body.dark-mode .civic-breadcrumb-link {
        color: #60A5FA;
    }

    body.dark-mode .civic-breadcrumb-current {
        color: #E5E7EB;
    }

    body.dark-mode .civic-breadcrumb-item:not(:last-child)::after {
        border-color: #9CA3AF;
    }

    /* Mobile */
    @media (max-width: 600px) {
        .civic-breadcrumb {
            font-size: 0.85rem;
        }
    }
</style>

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
