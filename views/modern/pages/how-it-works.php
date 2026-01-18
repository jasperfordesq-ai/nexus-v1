<?php
/**
 * How It Works - Modern Theme
 */
$pageTitle = 'How It Works';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<style>
#howitworks-wrapper {
    --hiw-theme: #3b82f6;
    --hiw-theme-rgb: 59, 130, 246;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #howitworks-wrapper {
        padding-top: 120px;
    }
}

#howitworks-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #howitworks-wrapper::before {
    background: linear-gradient(135deg,
        rgba(59, 130, 246, 0.08) 0%,
        rgba(99, 102, 241, 0.08) 50%,
        rgba(59, 130, 246, 0.08) 100%);
}

[data-theme="dark"] #howitworks-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
}

#howitworks-wrapper .hiw-inner {
    max-width: 1000px;
    margin: 0 auto;
}

#howitworks-wrapper .hiw-header {
    text-align: center;
    margin-bottom: 3rem;
}

#howitworks-wrapper .hiw-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#howitworks-wrapper .hiw-header p {
    color: var(--htb-text-muted);
    font-size: 1.25rem;
    max-width: 700px;
    margin: 0 auto;
    line-height: 1.6;
}

#howitworks-wrapper .hiw-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 3rem;
    margin-bottom: 2rem;
}

[data-theme="light"] #howitworks-wrapper .hiw-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(59, 130, 246, 0.15);
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
}

[data-theme="dark"] #howitworks-wrapper .hiw-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(59, 130, 246, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#howitworks-wrapper .steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2.5rem;
}

#howitworks-wrapper .step-item {
    text-align: center;
}

#howitworks-wrapper .step-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem auto;
    font-size: 2.5rem;
}

#howitworks-wrapper .step-item:nth-child(1) .step-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

#howitworks-wrapper .step-item:nth-child(2) .step-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

#howitworks-wrapper .step-item:nth-child(3) .step-icon {
    background: rgba(249, 115, 22, 0.1);
    color: #f97316;
}

#howitworks-wrapper .step-item h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#howitworks-wrapper .step-item p {
    color: var(--htb-text-muted);
    line-height: 1.6;
    margin: 0;
}

#howitworks-wrapper .cta-section {
    text-align: center;
    margin-top: 2rem;
}

#howitworks-wrapper .cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 600;
    text-decoration: none;
    background: linear-gradient(135deg, var(--hiw-theme) 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
    transition: all 0.3s ease;
}

#howitworks-wrapper .cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.5);
}

@media (max-width: 768px) {
    #howitworks-wrapper .hiw-header h1 {
        font-size: 2rem;
    }

    #howitworks-wrapper .hiw-card {
        padding: 2rem;
    }

    #howitworks-wrapper .steps-grid {
        gap: 2rem;
    }
}
</style>

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
