<?php
// Phoenix View: Help Center
$pageTitle = 'Help Center';
$hideHero = true;

// Pretty names mapping
$moduleNames = [
    'core' => 'Platform Basics',
    'getting_started' => 'Getting Started',
    'wallet' => 'Wallet & Transactions',
    'listings' => 'Marketplace',
    'groups' => 'Community Hubs',
    'events' => 'Events',
    'volunteering' => 'Volunteering',
    'blog' => 'News & Updates'
];

$moduleIcons = [
    'core' => '🔧',
    'getting_started' => '🚀',
    'wallet' => '💳',
    'listings' => '🏪',
    'groups' => '👥',
    'events' => '📅',
    'volunteering' => '💚',
    'blog' => '📰'
];

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
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#help-glass-wrapper .nexus-welcome-hero,
#help-glass-wrapper .help-category-card,
#help-glass-wrapper .quick-link-card,
#help-glass-wrapper .help-support-card {
    animation: fadeInUp 0.4s ease-out;
}

#help-glass-wrapper .help-category-card:nth-child(2) { animation-delay: 0.05s; }
#help-glass-wrapper .help-category-card:nth-child(3) { animation-delay: 0.1s; }
#help-glass-wrapper .help-category-card:nth-child(4) { animation-delay: 0.15s; }

/* Button Press States */
#help-glass-wrapper .nexus-smart-btn:active,
#help-glass-wrapper .help-support-btn:active,
#help-glass-wrapper .article-link:active,
button:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
#help-glass-wrapper .nexus-smart-btn,
#help-glass-wrapper .help-support-btn,
#help-glass-wrapper .article-link,
#help-glass-wrapper .help-search-box input {
    min-height: 44px;
}

/* iOS Zoom Prevention */
#help-glass-wrapper .help-search-box input {
    font-size: 16px !important;
}

/* Focus Visible */
#help-glass-wrapper .nexus-smart-btn:focus-visible,
#help-glass-wrapper .help-support-btn:focus-visible,
#help-glass-wrapper .article-link:focus-visible,
#help-glass-wrapper .help-search-box input:focus-visible {
    outline: 3px solid rgba(20, 184, 166, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    #help-glass-wrapper .nexus-smart-btn,
    #help-glass-wrapper .help-support-btn,
    #help-glass-wrapper .article-link {
        min-height: 48px;
    }
}
</style>

<style>
/* ========================================
   HELP CENTER - GLASSMORPHISM 2025
   Theme: Teal (#14b8a6)
   ======================================== */

#help-glass-wrapper {
    --help-theme: #14b8a6;
    --help-theme-rgb: 20, 184, 166;
    --help-theme-light: #2dd4bf;
    --help-theme-dark: #0d9488;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#help-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(20, 184, 166, 0.12) 0%,
        rgba(45, 212, 191, 0.12) 50%,
        rgba(94, 234, 212, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(20, 184, 166, 0.1);
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #help-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(20, 184, 166, 0.15) 0%,
        rgba(45, 212, 191, 0.15) 50%,
        rgba(94, 234, 212, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#help-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #0d9488, #14b8a6, #2dd4bf);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#help-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#help-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#help-glass-wrapper .nexus-smart-btn {
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
}

#help-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#help-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #14b8a6, #2dd4bf);
    color: white;
    box-shadow: 0 4px 14px rgba(20, 184, 166, 0.35);
}

#help-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(20, 184, 166, 0.45);
}

#help-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(20, 184, 166, 0.3);
}

[data-theme="dark"] #help-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(45, 212, 191, 0.4);
}

#help-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #14b8a6, #2dd4bf);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #help-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
    #help-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #help-glass-wrapper .nexus-smart-buttons { gap: 8px; }
    #help-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
}

#help-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #help-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(20, 184, 166, 0.08) 0%,
        rgba(45, 212, 191, 0.08) 25%,
        rgba(94, 234, 212, 0.08) 50%,
        rgba(153, 246, 228, 0.08) 75%,
        rgba(20, 184, 166, 0.08) 100%);
    background-size: 400% 400%;
    animation: helpGradientShift 15s ease infinite;
}

[data-theme="dark"] #help-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(20, 184, 166, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(45, 212, 191, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(94, 234, 212, 0.05) 0%, transparent 70%);
}

@keyframes helpGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#help-glass-wrapper .help-inner {
    max-width: 1100px;
    margin: 0 auto;
}

/* Search Header */
#help-glass-wrapper .help-search-header {
    text-align: center;
    padding: 3rem 2rem;
    margin-bottom: 2.5rem;
    border-radius: 24px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #help-glass-wrapper .help-search-header {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(20, 184, 166, 0.08) 100%);
    border: 1px solid rgba(20, 184, 166, 0.2);
    box-shadow: 0 8px 32px rgba(20, 184, 166, 0.15);
}

[data-theme="dark"] #help-glass-wrapper .help-search-header {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.2) 0%, rgba(20, 184, 166, 0.1) 100%);
    border: 1px solid rgba(20, 184, 166, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#help-glass-wrapper .help-search-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#help-glass-wrapper .help-search-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0 0 1.5rem 0;
}

#help-glass-wrapper .help-search-box {
    max-width: 500px;
    margin: 0 auto;
    position: relative;
}

#help-glass-wrapper .help-search-box input {
    width: 100%;
    padding: 1rem 1.25rem 1rem 3rem;
    border-radius: 50px;
    font-size: 1rem;
    outline: none;
    transition: all 0.3s ease;
}

[data-theme="light"] #help-glass-wrapper .help-search-box input {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(20, 184, 166, 0.2);
    color: var(--htb-text-main);
    box-shadow: 0 4px 16px rgba(20, 184, 166, 0.1);
}

[data-theme="dark"] #help-glass-wrapper .help-search-box input {
    background: rgba(30, 41, 59, 0.8);
    border: 2px solid rgba(20, 184, 166, 0.3);
    color: var(--htb-text-main);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#help-glass-wrapper .help-search-box input::placeholder {
    color: var(--htb-text-muted);
}

#help-glass-wrapper .help-search-box input:focus {
    border-color: var(--help-theme);
}

[data-theme="light"] #help-glass-wrapper .help-search-box input:focus {
    box-shadow: 0 4px 24px rgba(20, 184, 166, 0.2);
}

[data-theme="dark"] #help-glass-wrapper .help-search-box input:focus {
    box-shadow: 0 4px 24px rgba(20, 184, 166, 0.3);
}

#help-glass-wrapper .help-search-box .search-icon {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--help-theme);
    font-size: 1.1rem;
}

/* Category Grid */
#help-glass-wrapper .help-categories-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

/* Category Card */
#help-glass-wrapper .help-category-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

[data-theme="light"] #help-glass-wrapper .help-category-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(20, 184, 166, 0.15);
    box-shadow: 0 8px 32px rgba(20, 184, 166, 0.1);
}

[data-theme="dark"] #help-glass-wrapper .help-category-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(20, 184, 166, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#help-glass-wrapper .help-category-card:hover {
    transform: translateY(-4px);
}

[data-theme="light"] #help-glass-wrapper .help-category-card:hover {
    box-shadow: 0 16px 48px rgba(20, 184, 166, 0.15);
}

[data-theme="dark"] #help-glass-wrapper .help-category-card:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
}

/* Category Header */
#help-glass-wrapper .category-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(20, 184, 166, 0.1);
}

#help-glass-wrapper .category-header .cat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

[data-theme="light"] #help-glass-wrapper .category-header .cat-icon {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(20, 184, 166, 0.05) 100%);
}

[data-theme="dark"] #help-glass-wrapper .category-header .cat-icon {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.25) 0%, rgba(20, 184, 166, 0.1) 100%);
}

#help-glass-wrapper .category-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--help-theme);
    margin: 0;
}

/* Article List */
#help-glass-wrapper .article-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

#help-glass-wrapper .article-list li {
    border-bottom: 1px solid rgba(20, 184, 166, 0.08);
}

#help-glass-wrapper .article-list li:last-child {
    border-bottom: none;
}

#help-glass-wrapper .article-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: var(--htb-text-main);
    transition: all 0.2s ease;
}

[data-theme="light"] #help-glass-wrapper .article-link:hover {
    background: rgba(20, 184, 166, 0.05);
}

[data-theme="dark"] #help-glass-wrapper .article-link:hover {
    background: rgba(20, 184, 166, 0.1);
}

#help-glass-wrapper .article-link:hover {
    color: var(--help-theme);
}

#help-glass-wrapper .article-link .arrow {
    color: var(--htb-text-muted);
    transition: all 0.2s ease;
    font-size: 0.85rem;
}

#help-glass-wrapper .article-link:hover .arrow {
    color: var(--help-theme);
    transform: translateX(3px);
}

/* Empty State */
#help-glass-wrapper .help-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-bottom: 2.5rem;
}

[data-theme="light"] #help-glass-wrapper .help-empty-state {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(20, 184, 166, 0.15);
}

[data-theme="dark"] #help-glass-wrapper .help-empty-state {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(20, 184, 166, 0.2);
}

#help-glass-wrapper .help-empty-state .empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

#help-glass-wrapper .help-empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#help-glass-wrapper .help-empty-state p {
    color: var(--htb-text-muted);
    margin: 0;
}

/* Quick Links Section */
#help-glass-wrapper .help-quick-links {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2.5rem;
}

#help-glass-wrapper .quick-link-card {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    text-decoration: none;
}

[data-theme="light"] #help-glass-wrapper .quick-link-card {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(20, 184, 166, 0.15);
}

[data-theme="dark"] #help-glass-wrapper .quick-link-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(20, 184, 166, 0.2);
}

#help-glass-wrapper .quick-link-card:hover {
    transform: translateY(-3px);
    border-color: var(--help-theme);
}

[data-theme="light"] #help-glass-wrapper .quick-link-card:hover {
    background: rgba(255, 255, 255, 0.8);
    box-shadow: 0 8px 24px rgba(20, 184, 166, 0.15);
}

[data-theme="dark"] #help-glass-wrapper .quick-link-card:hover {
    background: rgba(30, 41, 59, 0.7);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

#help-glass-wrapper .quick-link-card .link-icon {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: block;
}

#help-glass-wrapper .quick-link-card h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.25rem 0;
}

#help-glass-wrapper .quick-link-card p {
    font-size: 0.8rem;
    color: var(--htb-text-muted);
    margin: 0;
    line-height: 1.4;
}

/* Support CTA Card */
#help-glass-wrapper .help-support-card {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
}

[data-theme="light"] #help-glass-wrapper .help-support-card {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.1) 0%, rgba(20, 184, 166, 0.05) 100%);
    border: 1px solid rgba(20, 184, 166, 0.2);
}

[data-theme="dark"] #help-glass-wrapper .help-support-card {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.2) 0%, rgba(20, 184, 166, 0.1) 100%);
    border: 1px solid rgba(20, 184, 166, 0.3);
}

#help-glass-wrapper .help-support-card h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#help-glass-wrapper .help-support-card p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#help-glass-wrapper .help-support-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--help-theme) 0%, var(--help-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(20, 184, 166, 0.4);
}

#help-glass-wrapper .help-support-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(20, 184, 166, 0.5);
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1024px) {
    #help-glass-wrapper .help-quick-links {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    #help-glass-wrapper {
        padding: 1.5rem 1rem 3rem;
    }

    #help-glass-wrapper .help-search-header {
        padding: 2rem 1.5rem;
        margin-bottom: 2rem;
    }

    #help-glass-wrapper .help-search-header h1 {
        font-size: 1.85rem;
    }

    #help-glass-wrapper .help-search-header p {
        font-size: 1rem;
    }

    #help-glass-wrapper .help-categories-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }

    #help-glass-wrapper .help-quick-links {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    #help-glass-wrapper .quick-link-card {
        padding: 1.25rem 0.75rem;
    }

    #help-glass-wrapper .quick-link-card .link-icon {
        font-size: 1.5rem;
    }

    #help-glass-wrapper .quick-link-card h4 {
        font-size: 0.85rem;
    }

    #help-glass-wrapper .quick-link-card p {
        font-size: 0.75rem;
    }

    #help-glass-wrapper .help-support-card {
        padding: 2rem 1.5rem;
    }

    #help-glass-wrapper .help-support-card h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes helpGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #help-glass-wrapper .help-search-header,
    [data-theme="light"] #help-glass-wrapper .help-category-card,
    [data-theme="light"] #help-glass-wrapper .quick-link-card,
    [data-theme="light"] #help-glass-wrapper .help-support-card,
    [data-theme="light"] #help-glass-wrapper .help-empty-state {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #help-glass-wrapper .help-search-header,
    [data-theme="dark"] #help-glass-wrapper .help-category-card,
    [data-theme="dark"] #help-glass-wrapper .quick-link-card,
    [data-theme="dark"] #help-glass-wrapper .help-support-card,
    [data-theme="dark"] #help-glass-wrapper .help-empty-state {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="help-glass-wrapper">
    <div class="help-inner">

        <!-- Smart Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Help Center</h1>
            <p class="nexus-welcome-subtitle">Search our knowledge base or browse topics below to find answers to your questions.</p>

            <div class="nexus-smart-buttons">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/faq" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>FAQ</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/timebanking-guide" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-book"></i>
                    <span>Guide</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/about-story" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-scroll"></i>
                    <span>Our Story</span>
                </a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contact</span>
                </a>
            </div>
        </div>

        <!-- Search Box -->
        <div class="help-search-box" style="max-width: 600px; margin: 0 auto 2rem auto;">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Search for help articles..." id="helpSearchInput">
        </div>

        <!-- Category Cards Grid -->
        <?php if (empty($data['groupedArticles'])): ?>
            <div class="help-empty-state">
                <span class="empty-icon">🤷</span>
                <h3>No articles found</h3>
                <p>We couldn't find any help topics for your enabled features.</p>
            </div>
        <?php else: ?>
            <div class="help-categories-grid">
                <?php foreach ($data['groupedArticles'] as $tag => $articles): ?>
                    <div class="help-category-card">
                        <div class="category-header">
                            <div class="cat-icon"><?= $moduleIcons[$tag] ?? '📄' ?></div>
                            <h3><?= $moduleNames[$tag] ?? ucfirst(str_replace('_', ' ', $tag)) ?></h3>
                        </div>
                        <ul class="article-list">
                            <?php foreach ($articles as $art): ?>
                                <li>
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help/<?= $art['slug'] ?>" class="article-link">
                                        <span><?= htmlspecialchars($art['title']) ?></span>
                                        <span class="arrow">→</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Support CTA Card -->
        <div class="help-support-card">
            <h2>💡 Still need help?</h2>
            <p>Our team is here to support you. Reach out and we'll get back to you as soon as possible.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact" class="help-support-btn">
                <span>📧</span> Contact Support
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
document.querySelectorAll('#help-glass-wrapper .nexus-smart-btn, #help-glass-wrapper .help-support-btn, #help-glass-wrapper .article-link').forEach(btn => {
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
        meta.content = '#14b8a6';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#14b8a6');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Simple search filter for articles
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('helpSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const articleLinks = document.querySelectorAll('#help-glass-wrapper .article-link');
            const categoryCards = document.querySelectorAll('#help-glass-wrapper .help-category-card');

            articleLinks.forEach(function(link) {
                const text = link.textContent.toLowerCase();
                const listItem = link.parentElement;
                if (query === '' || text.includes(query)) {
                    listItem.style.display = '';
                } else {
                    listItem.style.display = 'none';
                }
            });

            // Hide empty categories
            categoryCards.forEach(function(card) {
                const visibleItems = card.querySelectorAll('.article-list li[style=""], .article-list li:not([style])');
                let hasVisible = false;
                visibleItems.forEach(function(item) {
                    if (item.style.display !== 'none') hasVisible = true;
                });
                card.style.display = hasVisible ? '' : 'none';
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>
