<?php
/**
 * Timebanking Guide - Modern Theme
 */
$pageTitle = 'Timebanking Guide';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<style>
#guide-wrapper {
    --guide-theme: #8b5cf6;
    --guide-theme-rgb: 139, 92, 246;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #guide-wrapper {
        padding-top: 120px;
    }
}

#guide-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #guide-wrapper::before {
    background: linear-gradient(135deg,
        rgba(139, 92, 246, 0.08) 0%,
        rgba(124, 58, 237, 0.08) 50%,
        rgba(139, 92, 246, 0.08) 100%);
}

[data-theme="dark"] #guide-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(124, 58, 237, 0.1) 0%, transparent 50%);
}

#guide-wrapper .guide-inner {
    max-width: 1000px;
    margin: 0 auto;
}

#guide-wrapper .guide-header {
    text-align: center;
    margin-bottom: 3rem;
}

#guide-wrapper .guide-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#guide-wrapper .guide-header p {
    color: var(--htb-text-muted);
    font-size: 1.3rem;
    margin: 0 0 1.5rem 0;
}

#guide-wrapper .guide-header .cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

#guide-wrapper .guide-header .cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

#guide-wrapper .guide-header .cta-btn.primary {
    background: linear-gradient(135deg, var(--guide-theme) 0%, #7c3aed 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
}

#guide-wrapper .guide-header .cta-btn.secondary {
    background: rgba(139, 92, 246, 0.1);
    color: var(--guide-theme);
    border: 1px solid rgba(139, 92, 246, 0.2);
}

#guide-wrapper .guide-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
}

[data-theme="light"] #guide-wrapper .guide-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(139, 92, 246, 0.15);
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
}

[data-theme="dark"] #guide-wrapper .guide-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(139, 92, 246, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#guide-wrapper .guide-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1.5rem 0;
    text-align: center;
}

#guide-wrapper .steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

#guide-wrapper .step-item {
    text-align: center;
}

#guide-wrapper .step-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem auto;
    font-size: 2rem;
}

#guide-wrapper .step-item:nth-child(1) .step-icon {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

#guide-wrapper .step-item:nth-child(2) .step-icon {
    background: rgba(219, 39, 119, 0.1);
    color: #db2777;
}

#guide-wrapper .step-item:nth-child(3) .step-icon {
    background: rgba(22, 101, 52, 0.1);
    color: #166534;
}

#guide-wrapper .step-item h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
}

#guide-wrapper .step-item p {
    color: var(--htb-text-muted);
    line-height: 1.6;
    margin: 0;
}

#guide-wrapper .values-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-width: 800px;
    margin: 0 auto;
}

#guide-wrapper .values-list li {
    padding: 1rem 0;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#guide-wrapper .values-list li:last-child {
    border-bottom: none;
}

#guide-wrapper .values-list li strong {
    color: var(--htb-text-main);
}

#guide-wrapper .guide-card.highlight {
    border-left: 4px solid var(--guide-theme);
}

@media (max-width: 768px) {
    #guide-wrapper .guide-header h1 {
        font-size: 2rem;
    }

    #guide-wrapper .steps-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
