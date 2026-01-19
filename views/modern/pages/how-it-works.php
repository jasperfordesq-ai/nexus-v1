<?php
/**
 * How It Works - Modern Theme
 */
$pageTitle = 'How It Works';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>


<div id="howitworks-wrapper">
    <div class="hiw-inner">

        <div class="hiw-header">
            <h1>How It Works</h1>
            <p>Timebanking is a community currency system where time is the money. Everyone's hour is worth the same, regardless of the service provided.</p>
        </div>

        <div class="hiw-card">
            <div class="steps-grid">

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-heart"></i></div>
                    <h3>1. Give Time</h3>
                    <p>Offer your skills or help to a neighbour. Whether it's gardening, teaching, or tech support, your contribution matters.</p>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-clock"></i></div>
                    <h3>2. Earn Credits</h3>
                    <p>For every hour you give, you earn 1 Time Credit. It's banked automatically in your digital wallet.</p>
                </div>

                <div class="step-item">
                    <div class="step-icon"><i class="fa-solid fa-handshake"></i></div>
                    <h3>3. Get Help</h3>
                    <p>Spend your credits to receive help from others. Learn a new language, get a ride, or find a pet sitter.</p>
                </div>

            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="cta-section">
                    <a href="<?= $basePath ?>/register" class="cta-btn">
                        <i class="fa-solid fa-user-plus"></i>
                        Join the Community
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
