<?php
/**
 * Timebanking Guide - Modern Theme
 */
$pageTitle = 'Timebanking Guide';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>


<div id="guide-wrapper">
    <div class="guide-inner">

        <div class="guide-header">
            <h1>Timebanking Guide</h1>
            <p>Give an hour, get an hour. It's that simple.</p>

            <div class="cta-buttons">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="<?= $basePath ?>/register" class="cta-btn primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Join Community
                    </a>
                <?php endif; ?>
                <a href="<?= $basePath ?>/how-it-works" class="cta-btn secondary">
                    <i class="fa-solid fa-circle-info"></i>
                    How It Works
                </a>
            </div>
        </div>

        <div class="guide-card">
            <h2>How It Works: 3 Simple Steps</h2>

            <div class="steps-grid">
                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-handshake"></i></div>
                    <h3>Give an Hour</h3>
                    <p>Share a skill you love — from practical help to a friendly chat or a lift to the shops.</p>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-clock"></i></div>
                    <h3>Earn a Credit</h3>
                    <p>You automatically earn one Time Credit for every hour you spend helping another member.</p>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-user-group"></i></div>
                    <h3>Get Help</h3>
                    <p>Spend your credit to get support, learn a new skill, or join a community work day.</p>
                </div>
            </div>
        </div>

        <div class="guide-card highlight">
            <h2>Our Fundamental Values</h2>
            <p style="text-align: center; color: var(--htb-text-muted); margin-bottom: 2rem; max-width: 700px; margin-left: auto; margin-right: auto;">
                We believe that true wealth is found in our connections with one another. Our community is built on these fundamental values:
            </p>

            <ul class="values-list">
                <li><strong>We Are All Assets:</strong> Every human being has something of value to contribute to their community.</li>
                <li><strong>Redefining Work:</strong> We honour the real work of family and community that often goes unrecognised.</li>
                <li><strong>Reciprocity:</strong> Helping works better as a two-way street. Both giving and receiving strengthens community bonds.</li>
                <li><strong>Social Networks:</strong> People flourish in community and suffer in isolation. We build connections that matter.</li>
            </ul>
        </div>

        <div class="guide-card" style="text-align: center;">
            <h2>Ready to Get Started?</h2>
            <p style="color: var(--htb-text-muted); margin-bottom: 1.5rem;">
                Join our community and start exchanging time and skills with your neighbours.
            </p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="<?= $basePath ?>/register" class="cta-btn primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; border-radius: 50px; font-size: 1rem; font-weight: 600; text-decoration: none; background: linear-gradient(135deg, var(--guide-theme) 0%, #7c3aed 100%); color: white; box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);">
                    <i class="fa-solid fa-arrow-right"></i>
                    Join Now
                </a>
            <?php else: ?>
                <a href="<?= $basePath ?>/listings" class="cta-btn primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 1rem 2rem; border-radius: 50px; font-size: 1rem; font-weight: 600; text-decoration: none; background: linear-gradient(135deg, var(--guide-theme) 0%, #7c3aed 100%); color: white; box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);">
                    <i class="fa-solid fa-arrow-right"></i>
                    Browse Listings
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
