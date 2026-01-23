<?php
// CMS Page Template
$hero_title = $page['title'];
$hero_subtitle = ""; // Optional: Add subtitle column to pages table later if needed
$hero_gradient = 'htb-hero-gradient-teal';

// Use SEO Engine
\Nexus\Core\SEO::setTitle($page['title']);
// description could be a snippet of content or a column
\Nexus\Core\SEO::setDescription(substr(strip_tags($page['content']), 0, 160));

// SECURITY: Sanitize HTML content to prevent XSS attacks
$sanitizedContent = \Nexus\Helpers\HtmlSanitizer::sanitize($page['content'] ?? '');
?>

<div class="htb-container" style="padding: 40px 20px; background: white; min-height: 500px;">

    <!-- Admin Edit Link (Optional) -->
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div style="margin-bottom: 20px; text-align: right;">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/pages/builder?id=<?= (int)$page['id'] ?>" class="htb-btn htb-btn-secondary" style="font-size: 0.8rem;">Edit Page</a>
        </div>
    <?php endif; ?>

    <div class="cms-content" style="font-size: 1.1rem; line-height: 1.8; color: #374151;">
        <?= $sanitizedContent ?>
    </div>
</div>