<?php
// Phoenix View: Impact Summary - Gold Standard v6.1
$pageTitle = 'Impact Summary';
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
   Theme Color: Emerald (#059669)
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

#impact-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.impact-quick-btn:active,
.impact-cta-btn:active,
.impact-doc-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.impact-quick-btn,
.impact-cta-btn,
.impact-doc-btn {
    min-height: 44px !important;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.impact-quick-btn:focus-visible,
.impact-cta-btn:focus-visible,
.impact-doc-btn:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(5, 150, 105, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .impact-quick-btn,
    .impact-cta-btn,
    .impact-doc-btn {
        min-height: 48px !important;
    }
}
</style>

<style>
/* ========================================
   IMPACT SUMMARY - GLASSMORPHISM 2025
   Theme: Emerald (#059669)
   ======================================== */

#impact-glass-wrapper {
    --impact-theme: #059669;
    --impact-theme-rgb: 5, 150, 105;
    --impact-theme-light: #10b981;
    --impact-theme-dark: #047857;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

@media (max-width: 900px) {
    #impact-glass-wrapper {
        margin-top: 56px;
        padding-top: 1rem;
    }
}

#impact-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #impact-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(5, 150, 105, 0.08) 0%,
        rgba(16, 185, 129, 0.08) 25%,
        rgba(52, 211, 153, 0.08) 50%,
        rgba(110, 231, 183, 0.08) 75%,
        rgba(5, 150, 105, 0.08) 100%);
    background-size: 400% 400%;
    animation: impactGradientShift 15s ease infinite;
}

[data-theme="dark"] #impact-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(5, 150, 105, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(52, 211, 153, 0.05) 0%, transparent 70%);
}

@keyframes impactGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#impact-glass-wrapper .impact-inner {
    max-width: 1100px;
    margin: 0 auto;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#impact-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(5, 150, 105, 0.12) 0%,
        rgba(16, 185, 129, 0.12) 50%,
        rgba(52, 211, 153, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(5, 150, 105, 0.1);
    max-width: 1000px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #impact-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(5, 150, 105, 0.15) 0%,
        rgba(16, 185, 129, 0.15) 50%,
        rgba(52, 211, 153, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#impact-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #059669, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#impact-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#impact-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#impact-glass-wrapper .nexus-smart-btn {
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

#impact-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#impact-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}

#impact-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
}

#impact-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(5, 150, 105, 0.3);
}

[data-theme="dark"] #impact-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(16, 185, 129, 0.4);
}

#impact-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #impact-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
    #impact-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #impact-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
    #impact-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
    #impact-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
}

/* Legacy Page Header - hidden, replaced by welcome hero */
#impact-glass-wrapper .impact-page-header {
    display: none;
}

/* Quick Actions Bar */
#impact-glass-wrapper .impact-quick-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#impact-glass-wrapper .impact-quick-btn {
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

[data-theme="light"] #impact-glass-wrapper .impact-quick-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(5, 150, 105, 0.2);
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.1);
}

[data-theme="dark"] #impact-glass-wrapper .impact-quick-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(5, 150, 105, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#impact-glass-wrapper .impact-quick-btn:hover {
    transform: translateY(-2px);
    border-color: var(--impact-theme);
}

[data-theme="light"] #impact-glass-wrapper .impact-quick-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.2);
}

[data-theme="dark"] #impact-glass-wrapper .impact-quick-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
}

#impact-glass-wrapper .impact-quick-btn.primary {
    background: var(--impact-theme);
    color: white;
    border-color: var(--impact-theme);
}

#impact-glass-wrapper .impact-quick-btn.primary:hover {
    background: var(--impact-theme-dark);
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.4);
}

/* Hero Stat */
#impact-glass-wrapper .impact-hero-stat {
    text-align: center;
    padding: 2.5rem 2rem;
    border-radius: 24px;
    margin-bottom: 2.5rem;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #impact-glass-wrapper .impact-hero-stat {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(5, 150, 105, 0.05) 100%);
    border: 1px solid rgba(5, 150, 105, 0.2);
}

[data-theme="dark"] #impact-glass-wrapper .impact-hero-stat {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.25) 0%, rgba(5, 150, 105, 0.1) 100%);
    border: 1px solid rgba(5, 150, 105, 0.3);
}

#impact-glass-wrapper .impact-hero-stat .big-number {
    font-size: 5rem;
    font-weight: 900;
    color: var(--impact-theme);
    line-height: 1;
    margin-bottom: 0.5rem;
    text-shadow: 0 4px 24px rgba(5, 150, 105, 0.3);
}

#impact-glass-wrapper .impact-hero-stat .stat-label {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin-bottom: 0.5rem;
}

#impact-glass-wrapper .impact-hero-stat .stat-desc {
    font-size: 1rem;
    color: var(--htb-text-muted);
}

/* Glass Card */
#impact-glass-wrapper .impact-glass-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    margin-bottom: 2rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

[data-theme="light"] #impact-glass-wrapper .impact-glass-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(5, 150, 105, 0.15);
    box-shadow: 0 8px 32px rgba(5, 150, 105, 0.1);
}

[data-theme="dark"] #impact-glass-wrapper .impact-glass-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(5, 150, 105, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#impact-glass-wrapper .impact-glass-card:hover {
    transform: translateY(-4px);
}

[data-theme="light"] #impact-glass-wrapper .impact-glass-card:hover {
    box-shadow: 0 16px 48px rgba(5, 150, 105, 0.15);
}

[data-theme="dark"] #impact-glass-wrapper .impact-glass-card:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

/* Section Header */
#impact-glass-wrapper .impact-section-header {
    padding: 1.25rem 1.5rem;
    border-left: 4px solid var(--impact-theme);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

[data-theme="light"] #impact-glass-wrapper .impact-section-header {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.08) 0%, rgba(5, 150, 105, 0.02) 100%);
    border-bottom: 1px solid rgba(5, 150, 105, 0.1);
}

[data-theme="dark"] #impact-glass-wrapper .impact-section-header {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(5, 150, 105, 0.05) 100%);
    border-bottom: 1px solid rgba(5, 150, 105, 0.2);
}

#impact-glass-wrapper .impact-section-header .icon {
    font-size: 1.5rem;
}

#impact-glass-wrapper .impact-section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

/* Card Body */
#impact-glass-wrapper .impact-card-body {
    padding: 1.75rem;
}

/* Two Column Grid */
#impact-glass-wrapper .impact-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

/* Check List */
#impact-glass-wrapper .impact-check-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

#impact-glass-wrapper .impact-check-list li {
    position: relative;
    padding: 1rem 1rem 1rem 3.5rem;
    margin-bottom: 0.75rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

[data-theme="light"] #impact-glass-wrapper .impact-check-list li {
    background: rgba(5, 150, 105, 0.05);
}

[data-theme="dark"] #impact-glass-wrapper .impact-check-list li {
    background: rgba(5, 150, 105, 0.1);
}

#impact-glass-wrapper .impact-check-list li:hover {
    transform: translateX(4px);
}

[data-theme="light"] #impact-glass-wrapper .impact-check-list li:hover {
    background: rgba(5, 150, 105, 0.1);
}

[data-theme="dark"] #impact-glass-wrapper .impact-check-list li:hover {
    background: rgba(5, 150, 105, 0.15);
}

#impact-glass-wrapper .impact-check-list li::before {
    content: '✓';
    position: absolute;
    left: 1rem;
    top: 1rem;
    width: 28px;
    height: 28px;
    background: var(--impact-theme);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: bold;
}

#impact-glass-wrapper .impact-check-list li .stat-highlight {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--impact-theme);
    display: block;
    margin-bottom: 0.25rem;
}

#impact-glass-wrapper .impact-check-list li span {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Info Box */
#impact-glass-wrapper .impact-info-box {
    padding: 1.5rem;
    border-radius: 16px;
    border-left: 4px solid var(--impact-theme);
}

[data-theme="light"] #impact-glass-wrapper .impact-info-box {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.08) 0%, rgba(5, 150, 105, 0.03) 100%);
}

[data-theme="dark"] #impact-glass-wrapper .impact-info-box {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(5, 150, 105, 0.05) 100%);
}

#impact-glass-wrapper .impact-info-box h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--impact-theme);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

#impact-glass-wrapper .impact-info-box p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.7;
    margin: 0 0 0.75rem 0;
}

#impact-glass-wrapper .impact-info-box p:last-child {
    margin-bottom: 0;
}

#impact-glass-wrapper .impact-info-box .highlight-text {
    font-weight: 700;
    color: var(--htb-text-main);
}

/* Documents Section Title */
#impact-glass-wrapper .impact-docs-title {
    text-align: center;
    margin: 3rem 0 2rem;
}

#impact-glass-wrapper .impact-docs-title h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#impact-glass-wrapper .impact-docs-title p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    margin: 0;
}

/* Documents Grid */
#impact-glass-wrapper .impact-docs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

#impact-glass-wrapper .impact-doc-card {
    text-align: center;
    padding: 2rem 1.5rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    transition: all 0.3s ease;
}

[data-theme="light"] #impact-glass-wrapper .impact-doc-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(5, 150, 105, 0.15);
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.08);
}

[data-theme="dark"] #impact-glass-wrapper .impact-doc-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(5, 150, 105, 0.2);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#impact-glass-wrapper .impact-doc-card:hover {
    transform: translateY(-4px);
}

[data-theme="light"] #impact-glass-wrapper .impact-doc-card:hover {
    box-shadow: 0 12px 32px rgba(5, 150, 105, 0.15);
}

[data-theme="dark"] #impact-glass-wrapper .impact-doc-card:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
}

#impact-glass-wrapper .impact-doc-card .doc-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: block;
}

#impact-glass-wrapper .impact-doc-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
}

#impact-glass-wrapper .impact-doc-card p {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    line-height: 1.5;
    margin: 0 0 1.25rem 0;
}

#impact-glass-wrapper .impact-doc-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

#impact-glass-wrapper .impact-doc-btn.primary {
    background: linear-gradient(135deg, var(--impact-theme) 0%, var(--impact-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

#impact-glass-wrapper .impact-doc-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
}

#impact-glass-wrapper .impact-doc-btn.secondary {
    color: var(--impact-theme);
    border: 2px solid var(--impact-theme);
}

[data-theme="light"] #impact-glass-wrapper .impact-doc-btn.secondary {
    background: rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] #impact-glass-wrapper .impact-doc-btn.secondary {
    background: rgba(30, 41, 59, 0.8);
}

#impact-glass-wrapper .impact-doc-btn.secondary:hover {
    background: var(--impact-theme);
    color: white;
    transform: translateY(-2px);
}

/* CTA Card */
#impact-glass-wrapper .impact-cta-card {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #impact-glass-wrapper .impact-cta-card {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
    border: 1px solid rgba(5, 150, 105, 0.2);
}

[data-theme="dark"] #impact-glass-wrapper .impact-cta-card {
    background: linear-gradient(135deg, rgba(5, 150, 105, 0.2) 0%, rgba(5, 150, 105, 0.1) 100%);
    border: 1px solid rgba(5, 150, 105, 0.3);
}

#impact-glass-wrapper .impact-cta-card h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#impact-glass-wrapper .impact-cta-card p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#impact-glass-wrapper .impact-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--impact-theme) 0%, var(--impact-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.4);
}

#impact-glass-wrapper .impact-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.5);
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #impact-glass-wrapper {
        padding: 1.5rem 1rem 3rem;
    }

    #impact-glass-wrapper .impact-page-header h1 {
        font-size: 1.85rem;
    }

    #impact-glass-wrapper .impact-page-header p {
        font-size: 1rem;
    }

    #impact-glass-wrapper .impact-quick-actions {
        gap: 0.75rem;
    }

    #impact-glass-wrapper .impact-quick-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    #impact-glass-wrapper .impact-hero-stat {
        padding: 2rem 1.5rem;
    }

    #impact-glass-wrapper .impact-hero-stat .big-number {
        font-size: 3.5rem;
    }

    #impact-glass-wrapper .impact-hero-stat .stat-label {
        font-size: 1.25rem;
    }

    #impact-glass-wrapper .impact-two-col {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    #impact-glass-wrapper .impact-docs-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }

    #impact-glass-wrapper .impact-doc-card {
        padding: 1.5rem;
    }

    #impact-glass-wrapper .impact-docs-title h2 {
        font-size: 1.35rem;
    }

    #impact-glass-wrapper .impact-cta-card {
        padding: 2rem 1.5rem;
    }

    #impact-glass-wrapper .impact-cta-card h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes impactGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #impact-glass-wrapper .impact-glass-card,
    [data-theme="light"] #impact-glass-wrapper .impact-doc-card,
    [data-theme="light"] #impact-glass-wrapper .impact-hero-stat,
    [data-theme="light"] #impact-glass-wrapper .impact-cta-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #impact-glass-wrapper .impact-glass-card,
    [data-theme="dark"] #impact-glass-wrapper .impact-doc-card,
    [data-theme="dark"] #impact-glass-wrapper .impact-hero-stat,
    [data-theme="dark"] #impact-glass-wrapper .impact-cta-card {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="impact-glass-wrapper">
    <div class="impact-inner">

        <!-- Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Impact Summary</h1>
            <p class="nexus-welcome-subtitle">Independently validated by our 2023 Social Impact Study</p>
            <div class="nexus-smart-buttons">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>Full Report</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-bullseye"></i>
                    <span>Strategic Plan</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>

        <!-- Legacy Page Header (hidden) -->
        <div class="impact-page-header">
            <h1>📊 Impact Summary</h1>
            <p>Independently validated by our 2023 Social Impact Study</p>
        </div>

        <!-- Hero Stat -->
        <div class="impact-hero-stat">
            <div class="big-number">€16:1</div>
            <div class="stat-label">Social Return on Investment</div>
            <div class="stat-desc">For every €1 invested, we generate €16 in social value</div>
        </div>

        <!-- Wellbeing Section -->
        <div class="impact-glass-card">
            <div class="impact-section-header">
                <span class="icon">💚</span>
                <h2>Profound Impact on Wellbeing</h2>
            </div>
            <div class="impact-card-body">
                <div class="impact-two-col">
                    <div>
                        <ul class="impact-check-list">
                            <li>
                                <span class="stat-highlight">100%</span>
                                <span>of members reported improved mental and emotional wellbeing.</span>
                            </li>
                            <li>
                                <span class="stat-highlight">95%</span>
                                <span>feel more socially connected, actively tackling loneliness.</span>
                            </li>
                            <li>
                                <span class="stat-highlight">Transformational</span>
                                <span>Members describe TBI as "transformational and lifesaving".</span>
                            </li>
                        </ul>
                    </div>
                    <div class="impact-info-box">
                        <h3>🏥 A Public Health Solution</h3>
                        <p>The study found our model is a highly efficient, effective, and scalable intervention for tackling social isolation.</p>
                        <p class="highlight-text">It explicitly concluded that Timebank Ireland "could become part of a social prescribing offering".</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="impact-docs-title">
            <h2>📚 Our Strategic Documents</h2>
            <p>Explore our validated research and future roadmap</p>
        </div>

        <div class="impact-docs-grid">
            <div class="impact-doc-card">
                <span class="doc-icon">📊</span>
                <h3>2023 Impact Study</h3>
                <p>Full independent validation of our SROI model and outcomes.</p>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="impact-doc-btn primary">
                    <span>📄</span> Read Full Report
                </a>
            </div>
            <div class="impact-doc-card">
                <span class="doc-icon">🎯</span>
                <h3>Strategic Plan 2030</h3>
                <p>Our roadmap for national scaling and sustainable growth.</p>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="impact-doc-btn secondary">
                    <span>📋</span> Read Strategic Plan
                </a>
            </div>
        </div>

        <!-- CTA Card -->
        <div class="impact-cta-card">
            <h2>🚀 Ready to Scale Our Impact?</h2>
            <p>Partner with us to expand this proven model and bring the benefits of timebanking to more communities across Ireland.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="impact-cta-btn">
                <span>📧</span> Contact Strategy Team
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
document.querySelectorAll('.impact-quick-btn, .impact-cta-btn, .impact-doc-btn, .impact-doc-card').forEach(btn => {
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
        meta.content = '#059669';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#059669');
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
