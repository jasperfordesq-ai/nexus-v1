<?php
// Phoenix View: Social Prescribing - Gold Standard v6.1
$pageTitle = 'Social Prescribing Partner';
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
   Theme Color: Blue (#2563eb)
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

#sp-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.sp-quick-btn:active,
.sp-cta-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.sp-quick-btn,
.sp-cta-btn {
    min-height: 44px !important;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.sp-quick-btn:focus-visible,
.sp-cta-btn:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(37, 99, 235, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .sp-quick-btn,
    .sp-cta-btn {
        min-height: 48px !important;
    }
}
</style>

<style>
/* ========================================
   SOCIAL PRESCRIBING - GLASSMORPHISM 2025
   Theme: Blue (#2563eb)
   ======================================== */

#sp-glass-wrapper {
    --sp-theme: #2563eb;
    --sp-theme-rgb: 37, 99, 235;
    --sp-theme-light: #3b82f6;
    --sp-theme-dark: #1d4ed8;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

@media (max-width: 900px) {
    #sp-glass-wrapper {
        margin-top: 56px;
        padding-top: 1rem;
    }
}

#sp-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #sp-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(37, 99, 235, 0.08) 0%,
        rgba(59, 130, 246, 0.08) 25%,
        rgba(96, 165, 250, 0.08) 50%,
        rgba(147, 197, 253, 0.08) 75%,
        rgba(37, 99, 235, 0.08) 100%);
    background-size: 400% 400%;
    animation: spGradientShift 15s ease infinite;
}

[data-theme="dark"] #sp-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(96, 165, 250, 0.05) 0%, transparent 70%);
}

@keyframes spGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#sp-glass-wrapper .sp-inner {
    max-width: 1100px;
    margin: 0 auto;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#sp-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(37, 99, 235, 0.12) 0%,
        rgba(59, 130, 246, 0.12) 50%,
        rgba(96, 165, 250, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(37, 99, 235, 0.1);
    max-width: 1000px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #sp-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(37, 99, 235, 0.15) 0%,
        rgba(59, 130, 246, 0.15) 50%,
        rgba(96, 165, 250, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#sp-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #2563eb, #3b82f6, #60a5fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#sp-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#sp-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#sp-glass-wrapper .nexus-smart-btn {
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

#sp-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#sp-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: white;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}

#sp-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
}

#sp-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(37, 99, 235, 0.3);
}

[data-theme="dark"] #sp-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(59, 130, 246, 0.4);
}

#sp-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #sp-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
    #sp-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #sp-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
    #sp-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
    #sp-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
}

/* Legacy Page Header - hidden, replaced by welcome hero */
#sp-glass-wrapper .sp-page-header {
    display: none;
}

/* Quick Actions Bar */
#sp-glass-wrapper .sp-quick-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#sp-glass-wrapper .sp-quick-btn {
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

[data-theme="light"] #sp-glass-wrapper .sp-quick-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #sp-glass-wrapper .sp-quick-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(37, 99, 235, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#sp-glass-wrapper .sp-quick-btn:hover {
    transform: translateY(-2px);
    border-color: var(--sp-theme);
}

[data-theme="light"] #sp-glass-wrapper .sp-quick-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
}

[data-theme="dark"] #sp-glass-wrapper .sp-quick-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
}

#sp-glass-wrapper .sp-quick-btn.primary {
    background: var(--sp-theme);
    color: white;
    border-color: var(--sp-theme);
}

#sp-glass-wrapper .sp-quick-btn.primary:hover {
    background: var(--sp-theme-dark);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
}

/* Glass Card */
#sp-glass-wrapper .sp-glass-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    margin-bottom: 2rem;
    overflow: hidden;
    transition: all 0.3s ease;
}

[data-theme="light"] #sp-glass-wrapper .sp-glass-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(37, 99, 235, 0.15);
    box-shadow: 0 8px 32px rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #sp-glass-wrapper .sp-glass-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#sp-glass-wrapper .sp-glass-card:hover {
    transform: translateY(-4px);
}

[data-theme="light"] #sp-glass-wrapper .sp-glass-card:hover {
    box-shadow: 0 16px 48px rgba(37, 99, 235, 0.15);
}

[data-theme="dark"] #sp-glass-wrapper .sp-glass-card:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

/* Section Header */
#sp-glass-wrapper .sp-section-header {
    padding: 1.25rem 1.5rem;
    border-left: 4px solid var(--sp-theme);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

[data-theme="light"] #sp-glass-wrapper .sp-section-header {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.02) 100%);
    border-bottom: 1px solid rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #sp-glass-wrapper .sp-section-header {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.05) 100%);
    border-bottom: 1px solid rgba(37, 99, 235, 0.2);
}

#sp-glass-wrapper .sp-section-header .icon {
    font-size: 1.5rem;
}

#sp-glass-wrapper .sp-section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

/* Card Body */
#sp-glass-wrapper .sp-card-body {
    padding: 1.75rem;
}

/* Outcomes Grid */
#sp-glass-wrapper .outcomes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    align-items: start;
}

/* Check List */
#sp-glass-wrapper .sp-check-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

#sp-glass-wrapper .sp-check-list li {
    position: relative;
    padding: 1rem 1rem 1rem 3rem;
    margin-bottom: 0.75rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

[data-theme="light"] #sp-glass-wrapper .sp-check-list li {
    background: rgba(37, 99, 235, 0.05);
}

[data-theme="dark"] #sp-glass-wrapper .sp-check-list li {
    background: rgba(37, 99, 235, 0.1);
}

#sp-glass-wrapper .sp-check-list li:hover {
    transform: translateX(4px);
}

[data-theme="light"] #sp-glass-wrapper .sp-check-list li:hover {
    background: rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #sp-glass-wrapper .sp-check-list li:hover {
    background: rgba(37, 99, 235, 0.15);
}

#sp-glass-wrapper .sp-check-list li::before {
    content: '✓';
    position: absolute;
    left: 1rem;
    top: 1rem;
    width: 24px;
    height: 24px;
    background: var(--sp-theme);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
}

#sp-glass-wrapper .sp-check-list li strong {
    color: var(--htb-text-main);
    display: block;
    margin-bottom: 0.25rem;
}

#sp-glass-wrapper .sp-check-list li span {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Quote Box */
#sp-glass-wrapper .sp-quote-box {
    padding: 1.5rem;
    border-radius: 16px;
    border-left: 4px solid var(--sp-theme);
}

[data-theme="light"] #sp-glass-wrapper .sp-quote-box {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0.03) 100%);
}

[data-theme="dark"] #sp-glass-wrapper .sp-quote-box {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.05) 100%);
}

#sp-glass-wrapper .sp-quote-box .quote-text {
    font-style: italic;
    font-size: 1.05rem;
    line-height: 1.7;
    color: var(--htb-text-muted);
    margin: 0 0 1rem 0;
}

#sp-glass-wrapper .sp-quote-box .quote-author {
    font-weight: 700;
    color: var(--sp-theme);
    text-align: right;
}

#sp-glass-wrapper .sp-quote-box .quote-source {
    font-size: 0.8rem;
    color: var(--htb-text-muted);
    opacity: 0.7;
    text-align: right;
    margin-top: 0.25rem;
}

/* Pathway Section Title */
#sp-glass-wrapper .sp-pathway-title {
    text-align: center;
    margin: 3rem 0 2.5rem;
}

#sp-glass-wrapper .sp-pathway-title h2 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#sp-glass-wrapper .sp-pathway-title p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    margin: 0;
}

/* Steps Grid */
#sp-glass-wrapper .sp-steps-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 3rem;
}

/* Step Card */
#sp-glass-wrapper .sp-step-card {
    position: relative;
    text-align: center;
    padding: 2.5rem 1.25rem 1.5rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    transition: all 0.3s ease;
}

[data-theme="light"] #sp-glass-wrapper .sp-step-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(37, 99, 235, 0.15);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.08);
}

[data-theme="dark"] #sp-glass-wrapper .sp-step-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#sp-glass-wrapper .sp-step-card:hover {
    transform: translateY(-6px);
}

[data-theme="light"] #sp-glass-wrapper .sp-step-card:hover {
    box-shadow: 0 12px 32px rgba(37, 99, 235, 0.15);
}

[data-theme="dark"] #sp-glass-wrapper .sp-step-card:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
}

/* Step Number */
#sp-glass-wrapper .sp-step-number {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--sp-theme) 0%, var(--sp-theme-dark) 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.25rem;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
}

#sp-glass-wrapper .sp-step-card .step-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
}

#sp-glass-wrapper .sp-step-card h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#sp-glass-wrapper .sp-step-card p {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    line-height: 1.5;
    margin: 0;
}

/* CTA Card */
#sp-glass-wrapper .sp-cta-card {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #sp-glass-wrapper .sp-cta-card {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
    border: 1px solid rgba(37, 99, 235, 0.2);
}

[data-theme="dark"] #sp-glass-wrapper .sp-cta-card {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.2) 0%, rgba(37, 99, 235, 0.1) 100%);
    border: 1px solid rgba(37, 99, 235, 0.3);
}

#sp-glass-wrapper .sp-cta-card h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#sp-glass-wrapper .sp-cta-card p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 700px;
    margin: 0 auto 1.5rem auto;
}

#sp-glass-wrapper .sp-cta-card .cta-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

#sp-glass-wrapper .sp-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

#sp-glass-wrapper .sp-cta-btn.primary {
    background: linear-gradient(135deg, var(--sp-theme) 0%, var(--sp-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
}

#sp-glass-wrapper .sp-cta-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.5);
}

#sp-glass-wrapper .sp-cta-btn.secondary {
    color: var(--sp-theme);
    border: 2px solid var(--sp-theme);
}

[data-theme="light"] #sp-glass-wrapper .sp-cta-btn.secondary {
    background: rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] #sp-glass-wrapper .sp-cta-btn.secondary {
    background: rgba(30, 41, 59, 0.8);
}

#sp-glass-wrapper .sp-cta-btn.secondary:hover {
    background: var(--sp-theme);
    color: white;
    transform: translateY(-2px);
}

/* Stats Highlight */
#sp-glass-wrapper .sp-stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

#sp-glass-wrapper .sp-stat-chip {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

[data-theme="light"] #sp-glass-wrapper .sp-stat-chip {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(37, 99, 235, 0.15);
}

[data-theme="dark"] #sp-glass-wrapper .sp-stat-chip {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(37, 99, 235, 0.2);
}

#sp-glass-wrapper .sp-stat-chip:hover {
    transform: translateY(-3px);
}

#sp-glass-wrapper .sp-stat-chip .stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

#sp-glass-wrapper .sp-stat-chip .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--sp-theme);
    display: block;
}

#sp-glass-wrapper .sp-stat-chip .stat-label {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    font-weight: 500;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1024px) {
    #sp-glass-wrapper .sp-steps-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem 1.5rem;
    }

    #sp-glass-wrapper .sp-step-card {
        margin-top: 20px;
    }
}

@media (max-width: 768px) {
    #sp-glass-wrapper {
        padding: 1.5rem 1rem 3rem;
    }

    #sp-glass-wrapper .sp-page-header h1 {
        font-size: 1.85rem;
    }

    #sp-glass-wrapper .sp-page-header p {
        font-size: 1rem;
    }

    #sp-glass-wrapper .sp-quick-actions {
        gap: 0.75rem;
    }

    #sp-glass-wrapper .sp-quick-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    #sp-glass-wrapper .outcomes-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    #sp-glass-wrapper .sp-stats-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    #sp-glass-wrapper .sp-stat-chip {
        display: flex;
        align-items: center;
        gap: 1rem;
        text-align: left;
        padding: 1rem 1.25rem;
    }

    #sp-glass-wrapper .sp-stat-chip .stat-icon {
        margin-bottom: 0;
        font-size: 1.75rem;
    }

    #sp-glass-wrapper .sp-stat-chip .stat-value {
        font-size: 1.5rem;
    }

    #sp-glass-wrapper .sp-steps-grid {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }

    #sp-glass-wrapper .sp-pathway-title h2 {
        font-size: 1.5rem;
    }

    #sp-glass-wrapper .sp-cta-card {
        padding: 2rem 1.5rem;
    }

    #sp-glass-wrapper .sp-cta-card h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    #sp-glass-wrapper .sp-cta-card .cta-buttons {
        flex-direction: column;
    }

    #sp-glass-wrapper .sp-cta-btn {
        width: 100%;
        justify-content: center;
    }

    @keyframes spGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #sp-glass-wrapper .sp-glass-card,
    [data-theme="light"] #sp-glass-wrapper .sp-step-card,
    [data-theme="light"] #sp-glass-wrapper .sp-stat-chip,
    [data-theme="light"] #sp-glass-wrapper .sp-cta-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #sp-glass-wrapper .sp-glass-card,
    [data-theme="dark"] #sp-glass-wrapper .sp-step-card,
    [data-theme="dark"] #sp-glass-wrapper .sp-stat-chip,
    [data-theme="dark"] #sp-glass-wrapper .sp-cta-card {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="sp-glass-wrapper">
    <div class="sp-inner">

        <!-- Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Social Prescribing Partner</h1>
            <p class="nexus-welcome-subtitle">Evidence-based, community-led, and 100% effective for wellbeing</p>
            <div class="nexus-smart-buttons">
                <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Download Proposal</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Impact Report</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>

        <!-- Legacy Page Header (hidden) -->
        <div class="sp-page-header">
            <h1>💊 Social Prescribing Partner</h1>
            <p>Evidence-based, community-led, and 100% effective for wellbeing</p>
        </div>

        <!-- Stats Row -->
        <div class="sp-stats-row">
            <div class="sp-stat-chip">
                <span class="stat-icon">💯</span>
                <div>
                    <span class="stat-value">100%</span>
                    <span class="stat-label">Improved Wellbeing</span>
                </div>
            </div>
            <div class="sp-stat-chip">
                <span class="stat-icon">🤝</span>
                <div>
                    <span class="stat-value">95%</span>
                    <span class="stat-label">Increased Connection</span>
                </div>
            </div>
            <div class="sp-stat-chip">
                <span class="stat-icon">📈</span>
                <div>
                    <span class="stat-value">16:1</span>
                    <span class="stat-label">SROI Ratio</span>
                </div>
            </div>
        </div>

        <!-- Validated Outcomes Section -->
        <div class="sp-glass-card">
            <div class="sp-section-header">
                <span class="icon">✅</span>
                <h2>Validated Outcomes</h2>
            </div>
            <div class="sp-card-body">
                <div class="outcomes-grid">
                    <div>
                        <ul class="sp-check-list">
                            <li>
                                <strong>100% Improved Wellbeing</strong>
                                <span>Every member surveyed reported an improvement in emotional, physical, or mental wellbeing.</span>
                            </li>
                            <li>
                                <strong>95% Increased Connection</strong>
                                <span>We are successfully tackling loneliness and social isolation in our communities.</span>
                            </li>
                            <li>
                                <strong>Strategic Fit</strong>
                                <span>"Could become part of a social prescribing offering for early intervention".</span>
                            </li>
                        </ul>
                    </div>
                    <div class="sp-quote-box">
                        <p class="quote-text">"Monica found out about TBI through the outreach mental health team... Since joining TBI, Monica 'feels much more connected to the community which has had a positive mental health impact'."</p>
                        <div class="quote-author">— Monica (Member)</div>
                        <div class="quote-source">Source: 2023 Social Impact Study</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pathway Section Title -->
        <div class="sp-pathway-title">
            <h2>🔄 The Managed Referral Pathway</h2>
            <p>A structured approach to connect clients with community support</p>
        </div>

        <!-- Steps Grid -->
        <div class="sp-steps-grid">
            <div class="sp-step-card">
                <div class="sp-step-number">1</div>
                <span class="step-icon">📋</span>
                <h3>Formal Referral</h3>
                <p>Warm handover from Link Worker to our TBI Hub Coordinator.</p>
            </div>
            <div class="sp-step-card">
                <div class="sp-step-number">2</div>
                <span class="step-icon">👋</span>
                <h3>Onboarding</h3>
                <p>1-to-1 welcome to explain the model and identify skills.</p>
            </div>
            <div class="sp-step-card">
                <div class="sp-step-number">3</div>
                <span class="step-icon">🔗</span>
                <h3>Connection</h3>
                <p>Active facilitation of first exchanges and group activities.</p>
            </div>
            <div class="sp-step-card">
                <div class="sp-step-number">4</div>
                <span class="step-icon">📊</span>
                <h3>Follow-up</h3>
                <p>Feedback to Link Worker on engagement and outcomes.</p>
            </div>
        </div>

        <!-- CTA Card -->
        <div class="sp-cta-card">
            <h2>🤝 Partner With Us</h2>
            <p>We are seeking a public sector contract to secure the essential Hub Coordinator role, which is the <strong>lynchpin of the entire service</strong>. Join us in launching a formal pilot to demonstrate the power of community-led wellbeing.</p>
            <div class="cta-buttons">
                <a href="/uploads/tenants/hour-timebank/THE-RECIPROCITY-PATHWAY_A-Pilot-Proposal.pdf" target="_blank" class="sp-cta-btn primary">
                    <span>📄</span> Download Pilot Proposal
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="sp-cta-btn secondary">
                    <span>📧</span> Get In Touch
                </a>
            </div>
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
document.querySelectorAll('.sp-quick-btn, .sp-cta-btn, .sp-help-card').forEach(btn => {
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
        meta.content = '#2563eb';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#2563eb');
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
