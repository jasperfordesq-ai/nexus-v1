<?php
// Federation Transactions Enable Required - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Enable Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Transactions');
Nexus\Core\SEO::setDescription('Enable federation settings to send and receive hours from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="enable-wrapper">

        <style>
            /* Offline Banner */
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #enable-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 600px;
                margin: 60px auto;
                text-align: center;
            }

            .enable-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
                padding: 60px 40px;
            }

            [data-theme="dark"] .enable-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .enable-icon {
                width: 100px;
                height: 100px;
                margin: 0 auto 30px auto;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .enable-icon i {
                font-size: 3rem;
                color: #8b5cf6;
            }

            .enable-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0 0 15px 0;
            }

            .enable-message {
                font-size: 1rem;
                color: var(--htb-text-muted);
                line-height: 1.7;
                margin: 0 0 30px 0;
            }

            .enable-btn {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 14px 28px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 14px;
                font-weight: 700;
                font-size: 0.95rem;
                transition: all 0.3s ease;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
            }

            .enable-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
            }

            .info-note {
                margin-top: 30px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
                text-align: left;
            }

            .info-note h4 {
                color: var(--htb-text-main);
                font-size: 0.9rem;
                margin: 0 0 8px 0;
            }

            .info-note ul {
                margin: 0;
                padding-left: 20px;
            }

            .info-note li {
                margin-bottom: 4px;
            }

            /* Touch Targets & Focus */
            .enable-btn {
                min-height: 44px;
            }

            .enable-btn:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }
        </style>

        <div class="enable-card">
            <div class="enable-icon">
                <i class="fa-solid fa-exchange-alt"></i>
            </div>

            <h1 class="enable-title">Enable Federated Transactions</h1>

            <p class="enable-message">
                To send and receive hours from members of partner timebanks,
                you need to enable federated transactions in your settings.
            </p>

            <a href="<?= $basePath ?>/settings#federation" class="enable-btn">
                <i class="fa-solid fa-cog"></i>
                Go to Federation Settings
            </a>

            <div class="info-note">
                <h4><i class="fa-solid fa-info-circle" style="color: #8b5cf6; margin-right: 6px;"></i>What are Federated Transactions?</h4>
                <ul>
                    <li>Exchange hours with members from partner timebanks</li>
                    <li>Hours are transferred between your balances</li>
                    <li>Transactions are recorded in both timebanks</li>
                    <li>You control who can send you hours</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
