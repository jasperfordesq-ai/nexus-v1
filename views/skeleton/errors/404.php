<?php
/**
 * Skeleton Layout - 404 Error Page
 * Page not found
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
http_response_code(404);
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div style="text-align: center; padding: 4rem 1rem;">
    <div style="font-size: 6rem; font-weight: 700; color: var(--sk-link); margin-bottom: 1rem;">404</div>
    <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">Page Not Found</h1>
    <p style="color: #888; font-size: 1.125rem; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">
        Sorry, the page you're looking for doesn't exist or has been moved.
    </p>

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="<?= $basePath ?>/" class="sk-btn">
            <i class="fas fa-home"></i> Go Home
        </a>
        <a href="<?= $basePath ?>/listings" class="sk-btn sk-btn-outline">
            Browse Listings
        </a>
    </div>

    <!-- Helpful Links -->
    <div class="sk-card" style="max-width: 600px; margin: 3rem auto; text-align: left;">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">You might be looking for:</h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--sk-border);">
                <a href="<?= $basePath ?>/listings" style="color: var(--sk-link);">
                    <i class="fas fa-list"></i> Browse Listings
                </a>
            </li>
            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--sk-border);">
                <a href="<?= $basePath ?>/groups" style="color: var(--sk-link);">
                    <i class="fas fa-users"></i> Community Hubs
                </a>
            </li>
            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--sk-border);">
                <a href="<?= $basePath ?>/members" style="color: var(--sk-link);">
                    <i class="fas fa-user-friends"></i> Members Directory
                </a>
            </li>
            <li style="padding: 0.5rem 0;">
                <a href="<?= $basePath ?>/contact" style="color: var(--sk-link);">
                    <i class="fas fa-envelope"></i> Contact Us
                </a>
            </li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
