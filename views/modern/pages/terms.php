<?php
/**
 * Terms of Service - Modern Glassmorphism Design
 * Theme Color: Blue (#3b82f6)
 */
$pageTitle = 'Terms of Service';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}
?>

<style>
/* ============================================
   TERMS OF SERVICE - GLASSMORPHISM 2025
   Theme: Blue (#3b82f6)
   ============================================ */

#terms-glass-wrapper {
    --terms-theme: #3b82f6;
    --terms-theme-rgb: 59, 130, 246;
    --terms-theme-light: #60a5fa;
    --terms-theme-dark: #2563eb;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem; /* Top padding for fixed header + utility bar on desktop */
}

@media (max-width: 900px) {
    #terms-glass-wrapper {
        padding-top: 120px; /* Smaller top padding on mobile (no utility bar) */
    }
}

#terms-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #terms-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(59, 130, 246, 0.08) 0%,
        rgba(96, 165, 250, 0.08) 25%,
        rgba(37, 99, 235, 0.08) 50%,
        rgba(59, 130, 246, 0.08) 75%,
        rgba(96, 165, 250, 0.08) 100%);
    background-size: 400% 400%;
    animation: termsGradientShift 15s ease infinite;
}

[data-theme="dark"] #terms-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(37, 99, 235, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(96, 165, 250, 0.05) 0%, transparent 70%);
}

@keyframes termsGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Content Reveal Animation */
@keyframes termsFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#terms-glass-wrapper {
    animation: termsFadeInUp 0.4s ease-out;
}

#terms-glass-wrapper .terms-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#terms-glass-wrapper .terms-page-header {
    text-align: center;
    margin-bottom: 2rem;
}

#terms-glass-wrapper .terms-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#terms-glass-wrapper .terms-page-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--terms-theme) 0%, var(--terms-theme-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
}

#terms-glass-wrapper .terms-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0;
    max-width: 600px;
    margin: 0 auto;
}

#terms-glass-wrapper .terms-page-header .last-updated {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #terms-glass-wrapper .terms-page-header .last-updated {
    background: rgba(59, 130, 246, 0.1);
    color: var(--terms-theme-dark);
}

[data-theme="dark"] #terms-glass-wrapper .terms-page-header .last-updated {
    background: rgba(59, 130, 246, 0.2);
    color: var(--terms-theme-light);
}

/* Quick Navigation */
#terms-glass-wrapper .terms-quick-nav {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#terms-glass-wrapper .terms-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.15rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

[data-theme="light"] #terms-glass-wrapper .terms-nav-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(59, 130, 246, 0.2);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
}

[data-theme="dark"] #terms-glass-wrapper .terms-nav-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(59, 130, 246, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#terms-glass-wrapper .terms-nav-btn:hover {
    transform: translateY(-2px);
    border-color: var(--terms-theme);
}

[data-theme="light"] #terms-glass-wrapper .terms-nav-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.2);
}

[data-theme="dark"] #terms-glass-wrapper .terms-nav-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
}

/* Terms Section Card */
#terms-glass-wrapper .terms-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #terms-glass-wrapper .terms-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(59, 130, 246, 0.15);
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
}

[data-theme="dark"] #terms-glass-wrapper .terms-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(59, 130, 246, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#terms-glass-wrapper .terms-section:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #terms-glass-wrapper .terms-section:hover {
    box-shadow: 0 12px 40px rgba(59, 130, 246, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .terms-section:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

#terms-glass-wrapper .terms-section .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

#terms-glass-wrapper .terms-section .section-number {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 800;
    flex-shrink: 0;
}

[data-theme="light"] #terms-glass-wrapper .terms-section .section-number {
    background: linear-gradient(135deg, var(--terms-theme) 0%, var(--terms-theme-dark) 100%);
    color: white;
}

[data-theme="dark"] #terms-glass-wrapper .terms-section .section-number {
    background: linear-gradient(135deg, var(--terms-theme) 0%, var(--terms-theme-dark) 100%);
    color: white;
}

#terms-glass-wrapper .terms-section .section-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

[data-theme="light"] #terms-glass-wrapper .terms-section .section-icon {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%);
    color: var(--terms-theme);
}

[data-theme="dark"] #terms-glass-wrapper .terms-section .section-icon {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25) 0%, rgba(59, 130, 246, 0.1) 100%);
    color: var(--terms-theme-light);
}

#terms-glass-wrapper .terms-section .section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

#terms-glass-wrapper .terms-section p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.75;
    margin: 0 0 1rem 0;
}

#terms-glass-wrapper .terms-section p:last-child {
    margin-bottom: 0;
}

#terms-glass-wrapper .terms-section strong {
    color: var(--htb-text-main);
}

#terms-glass-wrapper .terms-section ul,
#terms-glass-wrapper .terms-section ol {
    margin: 1rem 0;
    padding-left: 0;
    list-style: none;
}

#terms-glass-wrapper .terms-section ul li,
#terms-glass-wrapper .terms-section ol li {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.75rem;
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#terms-glass-wrapper .terms-section ul li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--terms-theme);
}

#terms-glass-wrapper .terms-section ol {
    counter-reset: terms-counter;
}

#terms-glass-wrapper .terms-section ol li {
    counter-increment: terms-counter;
}

#terms-glass-wrapper .terms-section ol li::before {
    content: counter(terms-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: var(--terms-theme);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Highlight Section */
#terms-glass-wrapper .terms-section.highlight {
    border-left: 4px solid var(--terms-theme);
}

[data-theme="light"] #terms-glass-wrapper .terms-section.highlight {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(255, 255, 255, 0.7) 100%);
}

[data-theme="dark"] #terms-glass-wrapper .terms-section.highlight {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(30, 41, 59, 0.6) 100%);
}

/* Important Notice */
#terms-glass-wrapper .terms-notice {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 14px;
    margin: 1.5rem 0;
}

[data-theme="light"] #terms-glass-wrapper .terms-notice {
    background: rgba(59, 130, 246, 0.08);
    border: 1px solid rgba(59, 130, 246, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .terms-notice {
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

#terms-glass-wrapper .terms-notice .notice-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

#terms-glass-wrapper .terms-notice .notice-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#terms-glass-wrapper .terms-notice .notice-content p {
    margin: 0;
    font-size: 0.95rem;
}

/* Time Credit Visual */
#terms-glass-wrapper .time-credit-visual {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 2rem;
    margin: 1.5rem 0;
    border-radius: 16px;
}

[data-theme="light"] #terms-glass-wrapper .time-credit-visual {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .time-credit-visual {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.08) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

#terms-glass-wrapper .time-credit-visual .credit-box {
    text-align: center;
}

#terms-glass-wrapper .time-credit-visual .credit-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    margin: 0 auto 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

[data-theme="light"] #terms-glass-wrapper .time-credit-visual .credit-icon {
    background: rgba(255, 255, 255, 0.8);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

[data-theme="dark"] #terms-glass-wrapper .time-credit-visual .credit-icon {
    background: rgba(30, 41, 59, 0.8);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

#terms-glass-wrapper .time-credit-visual .credit-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-main);
}

#terms-glass-wrapper .time-credit-visual .equals-sign {
    font-size: 2rem;
    font-weight: 800;
    color: var(--terms-theme);
}

/* Prohibited Items Grid */
#terms-glass-wrapper .prohibited-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

#terms-glass-wrapper .prohibited-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 12px;
}

[data-theme="light"] #terms-glass-wrapper .prohibited-item {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .prohibited-item {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

#terms-glass-wrapper .prohibited-item .prohibited-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(239, 68, 68, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ef4444;
    font-size: 1rem;
    flex-shrink: 0;
}

#terms-glass-wrapper .prohibited-item span {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    font-weight: 500;
}

/* Contact CTA */
#terms-glass-wrapper .terms-cta {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #terms-glass-wrapper .terms-cta {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

[data-theme="dark"] #terms-glass-wrapper .terms-cta {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.1) 100%);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

#terms-glass-wrapper .terms-cta h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#terms-glass-wrapper .terms-cta p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#terms-glass-wrapper .terms-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--terms-theme) 0%, var(--terms-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
}

#terms-glass-wrapper .terms-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.5);
}

/* Back to Legal Link */
#terms-glass-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="light"] #terms-glass-wrapper .back-link {
    color: var(--terms-theme-dark);
}

[data-theme="dark"] #terms-glass-wrapper .back-link {
    color: var(--terms-theme-light);
}

#terms-glass-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #terms-glass-wrapper {
        padding: 120px 1rem 3rem;
    }

    #terms-glass-wrapper .terms-page-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #terms-glass-wrapper .terms-page-header .header-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }

    #terms-glass-wrapper .terms-page-header p {
        font-size: 1rem;
    }

    #terms-glass-wrapper .terms-quick-nav {
        gap: 0.5rem;
    }

    #terms-glass-wrapper .terms-nav-btn {
        padding: 0.5rem 0.9rem;
        font-size: 0.8rem;
    }

    #terms-glass-wrapper .terms-section {
        padding: 1.5rem;
    }

    #terms-glass-wrapper .terms-section .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    #terms-glass-wrapper .terms-section .section-icon {
        width: 42px;
        height: 42px;
        font-size: 1.15rem;
    }

    #terms-glass-wrapper .terms-section .section-header h2 {
        font-size: 1.2rem;
    }

    #terms-glass-wrapper .prohibited-grid {
        grid-template-columns: 1fr;
    }

    #terms-glass-wrapper .time-credit-visual {
        flex-direction: column;
        gap: 1rem;
        padding: 1.5rem;
    }

    #terms-glass-wrapper .time-credit-visual .equals-sign {
        transform: rotate(90deg);
    }

    #terms-glass-wrapper .terms-notice {
        flex-direction: column;
        text-align: center;
    }

    #terms-glass-wrapper .terms-cta {
        padding: 2rem 1.5rem;
    }

    #terms-glass-wrapper .terms-cta h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes termsGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Touch Targets */
#terms-glass-wrapper .terms-nav-btn,
#terms-glass-wrapper .terms-cta-btn {
    min-height: 44px;
}

@media (max-width: 768px) {
    #terms-glass-wrapper .terms-nav-btn,
    #terms-glass-wrapper .terms-cta-btn {
        min-height: 48px;
    }
}

/* Button Press States */
#terms-glass-wrapper .terms-nav-btn:active,
#terms-glass-wrapper .terms-cta-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Focus Visible */
#terms-glass-wrapper .terms-nav-btn:focus-visible,
#terms-glass-wrapper .terms-cta-btn:focus-visible,
#terms-glass-wrapper .back-link:focus-visible {
    outline: 3px solid rgba(59, 130, 246, 0.5);
    outline-offset: 2px;
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #terms-glass-wrapper .terms-section,
    [data-theme="light"] #terms-glass-wrapper .terms-cta {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #terms-glass-wrapper .terms-section,
    [data-theme="dark"] #terms-glass-wrapper .terms-cta {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="terms-glass-wrapper">
    <div class="terms-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="terms-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-file-contract"></i></span>
                Terms of Service
            </h1>
            <p>The rules and guidelines for using our platform</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="terms-quick-nav">
            <a href="#time-credits" class="terms-nav-btn">
                <i class="fa-solid fa-clock"></i> Time Credits
            </a>
            <a href="#community" class="terms-nav-btn">
                <i class="fa-solid fa-users"></i> Community Rules
            </a>
            <a href="#prohibited" class="terms-nav-btn">
                <i class="fa-solid fa-ban"></i> Prohibited
            </a>
            <a href="#liability" class="terms-nav-btn">
                <i class="fa-solid fa-shield"></i> Liability
            </a>
        </div>

        <!-- Introduction -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-handshake"></i></div>
                <h2>Welcome to <?= htmlspecialchars($tenantName) ?></h2>
            </div>
            <p>By accessing or using our platform, you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
            <p>These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
        </div>

        <!-- Time Credit System -->
        <div class="terms-section" id="time-credits">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Time Credit System</h2>
            </div>
            <p>Our platform operates on a simple but powerful principle: <strong>everyone's time is equal</strong>.</p>

            <div class="time-credit-visual">
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-clock"></i></div>
                    <span class="credit-label">1 Hour of Service</span>
                </div>
                <span class="equals-sign">=</span>
                <div class="credit-box">
                    <div class="credit-icon"><i class="fa-solid fa-gem"></i></div>
                    <span class="credit-label">1 Time Credit</span>
                </div>
            </div>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-info-circle"></i></span>
                <div class="notice-content">
                    <h4>Important</h4>
                    <p>Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges.</p>
                </div>
            </div>

            <ul>
                <li>One hour of service provided equals one Time Credit earned</li>
                <li>Credits can be used to receive services from other members</li>
                <li>The type of service does not affect the credit value</li>
                <li>Credits are tracked automatically through the platform</li>
            </ul>
        </div>

        <!-- Account Responsibilities -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">2</div>
                <h2>Account Responsibilities</h2>
            </div>
            <p>When you create an account, you agree to:</p>
            <ul>
                <li><strong>Provide accurate information:</strong> Your profile must reflect your true identity and skills</li>
                <li><strong>Maintain security:</strong> Keep your login credentials confidential and secure</li>
                <li><strong>Use one account:</strong> Each person may only maintain one active account</li>
                <li><strong>Stay current:</strong> Update your profile when your skills or availability change</li>
                <li><strong>Be reachable:</strong> Respond to messages and requests in a timely manner</li>
            </ul>
        </div>

        <!-- Community Guidelines -->
        <div class="terms-section highlight" id="community">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Community Guidelines</h2>
            </div>
            <p>Our community is built on <strong>trust, respect, and mutual support</strong>. All members must:</p>
            <ol>
                <li><strong>Treat everyone with respect</strong> — Be kind and courteous in all interactions</li>
                <li><strong>Honor your commitments</strong> — If you agree to an exchange, follow through</li>
                <li><strong>Communicate clearly</strong> — Keep other members informed about your availability</li>
                <li><strong>Be inclusive</strong> — Welcome members of all backgrounds and abilities</li>
                <li><strong>Give honest feedback</strong> — Help the community by providing fair reviews</li>
            </ol>
        </div>

        <!-- Prohibited Activities -->
        <div class="terms-section" id="prohibited">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>Prohibited Activities</h2>
            </div>
            <p>The following activities are strictly prohibited and may result in account termination:</p>

            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Harassment or discrimination</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Fraudulent exchanges</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Illegal services or activities</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Spam or solicitation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Impersonation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Sharing others' private info</span>
                </div>
            </div>
        </div>

        <!-- Safety -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Safety & Meetings</h2>
            </div>
            <p>Your safety is important. We recommend following these guidelines:</p>
            <ul>
                <li><strong>First meetings:</strong> Meet in public places for initial exchanges</li>
                <li><strong>Verify identity:</strong> Confirm the member's profile before meeting</li>
                <li><strong>Trust your instincts:</strong> If something feels wrong, don't proceed</li>
                <li><strong>Report concerns:</strong> Let us know about any suspicious behavior</li>
                <li><strong>Keep records:</strong> Document exchanges through the platform</li>
            </ul>
        </div>

        <!-- Liability -->
        <div class="terms-section" id="liability">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>Limitation of Liability</h2>
            </div>
            <p><?= htmlspecialchars($tenantName) ?> provides a platform for community members to connect and exchange services. However:</p>
            <ul>
                <li>We do not guarantee the quality or safety of any services exchanged</li>
                <li>We are not responsible for disputes between members</li>
                <li>Members exchange services at their own risk</li>
                <li>We recommend obtaining appropriate insurance for professional services</li>
            </ul>
            <p>By using the platform, you agree to hold <?= htmlspecialchars($tenantName) ?> harmless from any claims arising from your participation.</p>
        </div>

        <!-- Termination -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">7</div>
                <h2>Account Termination</h2>
            </div>
            <p>We reserve the right to suspend or terminate accounts that violate these terms. Reasons for termination include:</p>
            <ul>
                <li>Repeated violation of community guidelines</li>
                <li>Fraudulent or deceptive behavior</li>
                <li>Harassment of other members</li>
                <li>Extended inactivity (over 12 months)</li>
                <li>Providing false information</li>
            </ul>
            <p>You may also close your account at any time through your account settings.</p>
        </div>

        <!-- Changes to Terms -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Changes to These Terms</h2>
            </div>
            <p>We may update these terms from time to time to reflect changes in our practices or for legal reasons. When we make significant changes:</p>
            <ul>
                <li>We will notify you via email or platform notification</li>
                <li>The updated date will be shown at the top of this page</li>
                <li>Continued use of the platform constitutes acceptance of the new terms</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="terms-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Have Questions?</h2>
            <p>If you have any questions about these Terms of Service or need clarification on any points, our team is here to help.</p>
            <a href="<?= $basePath ?>/contact" class="terms-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </div>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Button press states
document.querySelectorAll('#terms-glass-wrapper .terms-nav-btn, #terms-glass-wrapper .terms-cta-btn').forEach(btn => {
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
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
