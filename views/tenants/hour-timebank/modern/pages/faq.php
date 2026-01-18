<?php
// Phoenix View: FAQ - Gold Standard v6.1
$pageTitle = 'Frequently Asked Questions';
$hideHero = true;

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   Theme Color: Cyan (#0891b2)
   ============================================ */

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
@keyframes goldFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#faq-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.faq-quick-btn:active,
.faq-cta-btn:active,
.faq-help-card:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.faq-quick-btn,
.faq-cta-btn {
    min-height: 44px !important;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.faq-quick-btn:focus-visible,
.faq-cta-btn:focus-visible,
.faq-help-card:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(8, 145, 178, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .faq-quick-btn,
    .faq-cta-btn {
        min-height: 48px !important;
    }
}
</style>

<style>
/* ========================================
   FAQ - GLASSMORPHISM 2025
   Theme: Cyan (#0891b2)
   ======================================== */

#faq-glass-wrapper {
    --faq-theme: #0891b2;
    --faq-theme-rgb: 8, 145, 178;
    --faq-theme-light: #06b6d4;
    --faq-theme-dark: #0e7490;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

@media (max-width: 900px) {
    #faq-glass-wrapper {
        margin-top: 56px;
        padding-top: 1rem;
    }
}

#faq-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #faq-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(8, 145, 178, 0.08) 0%,
        rgba(6, 182, 212, 0.08) 25%,
        rgba(34, 211, 238, 0.08) 50%,
        rgba(103, 232, 249, 0.08) 75%,
        rgba(8, 145, 178, 0.08) 100%);
    background-size: 400% 400%;
    animation: faqGradientShift 15s ease infinite;
}

[data-theme="dark"] #faq-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(8, 145, 178, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(34, 211, 238, 0.05) 0%, transparent 70%);
}

@keyframes faqGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#faq-glass-wrapper .faq-inner {
    max-width: 1000px;
    margin: 0 auto;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#faq-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(8, 145, 178, 0.12) 0%,
        rgba(6, 182, 212, 0.12) 50%,
        rgba(34, 211, 238, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(8, 145, 178, 0.1);
    max-width: 1000px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #faq-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(8, 145, 178, 0.15) 0%,
        rgba(6, 182, 212, 0.15) 50%,
        rgba(34, 211, 238, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#faq-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #0891b2, #06b6d4, #22d3ee);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#faq-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#faq-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#faq-glass-wrapper .nexus-smart-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    min-height: 44px;
}

#faq-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#faq-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    box-shadow: 0 4px 14px rgba(8, 145, 178, 0.35);
}

#faq-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(8, 145, 178, 0.45);
}

#faq-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(8, 145, 178, 0.3);
}

[data-theme="dark"] #faq-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(6, 182, 212, 0.4);
}

#faq-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #faq-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
    #faq-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #faq-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
    #faq-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
    #faq-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
}

/* Legacy Page Header - hidden, replaced by welcome hero */
#faq-glass-wrapper .faq-page-header {
    display: none;
}

/* Quick Actions Bar */
#faq-glass-wrapper .faq-quick-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#faq-glass-wrapper .faq-quick-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

[data-theme="light"] #faq-glass-wrapper .faq-quick-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(8, 145, 178, 0.2);
    box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
}

[data-theme="dark"] #faq-glass-wrapper .faq-quick-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(8, 145, 178, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#faq-glass-wrapper .faq-quick-btn:hover {
    transform: translateY(-2px);
    border-color: var(--faq-theme);
}

[data-theme="light"] #faq-glass-wrapper .faq-quick-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.2);
}

[data-theme="dark"] #faq-glass-wrapper .faq-quick-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.3);
}

#faq-glass-wrapper .faq-quick-btn.primary {
    background: var(--faq-theme);
    color: white;
    border-color: var(--faq-theme);
}

#faq-glass-wrapper .faq-quick-btn.primary:hover {
    background: var(--faq-theme-dark);
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.4);
}

/* FAQ Grid */
#faq-glass-wrapper .faq-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

/* FAQ Card */
#faq-glass-wrapper .faq-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #faq-glass-wrapper .faq-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(8, 145, 178, 0.15);
    box-shadow: 0 8px 32px rgba(8, 145, 178, 0.1);
}

[data-theme="dark"] #faq-glass-wrapper .faq-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(8, 145, 178, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#faq-glass-wrapper .faq-card:hover {
    transform: translateY(-4px);
}

[data-theme="light"] #faq-glass-wrapper .faq-card:hover {
    box-shadow: 0 16px 48px rgba(8, 145, 178, 0.15);
}

[data-theme="dark"] #faq-glass-wrapper .faq-card:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

/* Card Icon */
#faq-glass-wrapper .faq-card .card-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

[data-theme="light"] #faq-glass-wrapper .faq-card .card-icon {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.15) 0%, rgba(8, 145, 178, 0.05) 100%);
}

[data-theme="dark"] #faq-glass-wrapper .faq-card .card-icon {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.25) 0%, rgba(8, 145, 178, 0.1) 100%);
}

#faq-glass-wrapper .faq-card h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--faq-theme);
    margin: 0 0 1rem 0;
}

#faq-glass-wrapper .faq-card p {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    line-height: 1.7;
    margin: 0 0 0.75rem 0;
}

#faq-glass-wrapper .faq-card p:last-child {
    margin-bottom: 0;
}

#faq-glass-wrapper .faq-card strong {
    color: var(--htb-text-main);
}

/* Highlight Card */
#faq-glass-wrapper .faq-card.highlight {
    border-left: 4px solid var(--faq-theme);
}

[data-theme="light"] #faq-glass-wrapper .faq-card.highlight {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.08) 0%, rgba(255, 255, 255, 0.7) 100%);
}

[data-theme="dark"] #faq-glass-wrapper .faq-card.highlight {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.15) 0%, rgba(30, 41, 59, 0.6) 100%);
}

/* CTA Card */
#faq-glass-wrapper .faq-cta-card {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #faq-glass-wrapper .faq-cta-card {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.1) 0%, rgba(8, 145, 178, 0.05) 100%);
    border: 1px solid rgba(8, 145, 178, 0.2);
}

[data-theme="dark"] #faq-glass-wrapper .faq-cta-card {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.2) 0%, rgba(8, 145, 178, 0.1) 100%);
    border: 1px solid rgba(8, 145, 178, 0.3);
}

#faq-glass-wrapper .faq-cta-card h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#faq-glass-wrapper .faq-cta-card p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#faq-glass-wrapper .faq-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--faq-theme) 0%, var(--faq-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.4);
}

#faq-glass-wrapper .faq-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(8, 145, 178, 0.5);
}

/* Still Have Questions Section */
#faq-glass-wrapper .faq-help-section {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

#faq-glass-wrapper .faq-help-card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    text-decoration: none;
}

[data-theme="light"] #faq-glass-wrapper .faq-help-card {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(8, 145, 178, 0.15);
}

[data-theme="dark"] #faq-glass-wrapper .faq-help-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(8, 145, 178, 0.2);
}

#faq-glass-wrapper .faq-help-card:hover {
    transform: translateY(-3px);
    border-color: var(--faq-theme);
}

[data-theme="light"] #faq-glass-wrapper .faq-help-card:hover {
    background: rgba(255, 255, 255, 0.8);
    box-shadow: 0 8px 24px rgba(8, 145, 178, 0.15);
}

[data-theme="dark"] #faq-glass-wrapper .faq-help-card:hover {
    background: rgba(30, 41, 59, 0.7);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

#faq-glass-wrapper .faq-help-card .help-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
}

#faq-glass-wrapper .faq-help-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#faq-glass-wrapper .faq-help-card p {
    font-size: 0.85rem;
    color: var(--htb-text-muted);
    margin: 0;
    line-height: 1.5;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #faq-glass-wrapper {
        padding: 1.5rem 1rem 3rem;
    }

    #faq-glass-wrapper .faq-page-header h1 {
        font-size: 1.85rem;
    }

    #faq-glass-wrapper .faq-page-header p {
        font-size: 1rem;
    }

    #faq-glass-wrapper .faq-quick-actions {
        gap: 0.75rem;
    }

    #faq-glass-wrapper .faq-quick-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    #faq-glass-wrapper .faq-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }

    #faq-glass-wrapper .faq-card {
        padding: 1.5rem;
    }

    #faq-glass-wrapper .faq-card h2 {
        font-size: 1.15rem;
    }

    #faq-glass-wrapper .faq-help-section {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    #faq-glass-wrapper .faq-help-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        text-align: left;
        padding: 1rem 1.25rem;
    }

    #faq-glass-wrapper .faq-help-card .help-icon {
        margin-bottom: 0;
        font-size: 1.5rem;
    }

    #faq-glass-wrapper .faq-help-card .help-content {
        flex: 1;
    }

    #faq-glass-wrapper .faq-cta-card {
        padding: 2rem 1.5rem;
    }

    #faq-glass-wrapper .faq-cta-card h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes faqGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #faq-glass-wrapper .faq-card,
    [data-theme="light"] #faq-glass-wrapper .faq-help-card,
    [data-theme="light"] #faq-glass-wrapper .faq-cta-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #faq-glass-wrapper .faq-card,
    [data-theme="dark"] #faq-glass-wrapper .faq-help-card,
    [data-theme="dark"] #faq-glass-wrapper .faq-cta-card {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="faq-glass-wrapper">
    <div class="faq-inner">

        <!-- Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Frequently Asked Questions</h1>
            <p class="nexus-welcome-subtitle">Everything you need to know about using time as currency</p>
            <div class="nexus-smart-buttons">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-rocket"></i>
                    <span>Join Now</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/timebanking-guide" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-book-open"></i>
                    <span>Timebanking Guide</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>

        <!-- Legacy Page Header (hidden) -->
        <div class="faq-page-header">
            <h1>❓ Frequently Asked Questions</h1>
            <p>Everything you need to know about using time as currency</p>
        </div>

        <!-- FAQ Grid -->
        <div class="faq-grid">

            <!-- What is Timebanking -->
            <div class="faq-card highlight">
                <div class="card-icon">⏰</div>
                <h2>What is Timebanking?</h2>
                <p>Timebanking is a system of mutual service exchange that uses units of time as currency. The underlying principle is that <strong>everyone's time is equally valuable</strong>.</p>
                <p>It helps build stronger communities by fostering cooperation and support, transcending traditional monetary transactions.</p>
            </div>

            <!-- Who can join -->
            <div class="faq-card">
                <div class="card-icon">👥</div>
                <h2>Who can join?</h2>
                <p>Anyone! Whether you possess professional expertise, everyday life skills, or unique hobbies, your talents are valued.</p>
                <p>Timebanks embrace diversity and recognize that <strong>every member has something valuable to offer</strong>.</p>
            </div>

            <!-- What can I offer -->
            <div class="faq-card">
                <div class="card-icon">🎁</div>
                <h2>What can I offer?</h2>
                <p>Possibilities are endless: gardening, home repairs, cooking, companionship, mentoring, music lessons, IT help, and more.</p>
                <p>Offer what you <strong>genuinely enjoy and excel at</strong>.</p>
            </div>

            <!-- How do Credits work -->
            <div class="faq-card">
                <div class="card-icon">💎</div>
                <h2>How do Credits work?</h2>
                <p>When you spend an hour helping another member, you earn one <strong>Time Credit</strong>.</p>
                <p>You can use this credit to "buy" an hour of service you need. It's a reciprocal system promoting fairness.</p>
            </div>

            <!-- How do I join -->
            <div class="faq-card">
                <div class="card-icon">✨</div>
                <h2>How do I join?</h2>
                <p>It's simple! Register on our platform, create your profile, and start listing your offers and requests.</p>
                <p>Our team will guide you through the onboarding process.</p>
            </div>

            <!-- Our Philosophy -->
            <div class="faq-card highlight">
                <div class="card-icon">💚</div>
                <h2>Our Philosophy</h2>
                <p><strong>"Time is the most valuable currency."</strong></p>
                <p>We celebrate inclusivity and the joy of giving and receiving. Every hour given strengthens our community.</p>
            </div>

        </div>

        <!-- Help Section -->
        <div class="faq-help-section">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="faq-help-card">
                <span class="help-icon">💬</span>
                <div class="help-content">
                    <h3>Chat With Us</h3>
                    <p>Get answers from our friendly team</p>
                </div>
            </a>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/timebanking-guide" class="faq-help-card">
                <span class="help-icon">📚</span>
                <div class="help-content">
                    <h3>Read the Guide</h3>
                    <p>Learn more about timebanking</p>
                </div>
            </a>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/about-story" class="faq-help-card">
                <span class="help-icon">📖</span>
                <div class="help-content">
                    <h3>Our Story</h3>
                    <p>Discover our mission and values</p>
                </div>
            </a>
        </div>

        <!-- CTA Card -->
        <div class="faq-cta-card">
            <h2>🌟 Join the Movement Today</h2>
            <p>Embark on a journey of enriching lives, one shared moment at a time. Your time is valuable — let's make it count together.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="faq-cta-btn">
                <span>🚀</span> Become a Member
            </a>
        </div>

    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Button Press States
document.querySelectorAll('.faq-quick-btn, .faq-cta-btn, .faq-help-card').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#0891b2';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#0891b2');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>
