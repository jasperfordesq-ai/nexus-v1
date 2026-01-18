<?php
// Phoenix View: Strategic Plan 2026-2030 - Gold Standard v6.1
$pageTitle = 'Strategic Plan 2026-2030';
$hideHero = true;

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   Theme Color: Navy (#1e3a8a)
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

#strategy-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.nexus-smart-btn:active,
.download-btn:active,
.toc-sidebar a:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.nexus-smart-btn,
.download-btn,
.toc-sidebar a {
    min-height: 44px !important;
    display: inline-flex;
    align-items: center;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.nexus-smart-btn:focus-visible,
.download-btn:focus-visible,
.toc-sidebar a:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(30, 58, 138, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .nexus-smart-btn,
    .download-btn {
        min-height: 48px !important;
    }
}
</style>

<style>
/* ========================================
   STRATEGIC PLAN - GLASSMORPHISM 2025
   Theme: Navy/Slate (#1e3a8a)
   ======================================== */

#strategy-glass-wrapper {
    --strategy-theme: #1e3a8a;
    --strategy-theme-rgb: 30, 58, 138;
    --strategy-theme-light: #3b82f6;
    --strategy-theme-dark: #1e40af;
    --success: #059669;
    --danger: #dc2626;
    --warning: #d97706;
    --cyan: #0891b2;
    --purple: #7c3aed;
    position: relative;
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
    margin-top: 120px;
}

/* ===================================
   NEXUS WELCOME HERO - Gold Standard
   =================================== */
#strategy-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(30, 58, 138, 0.12) 0%,
        rgba(59, 130, 246, 0.12) 50%,
        rgba(96, 165, 250, 0.08) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 28px 24px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(30, 58, 138, 0.1);
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

[data-theme="dark"] #strategy-glass-wrapper .nexus-welcome-hero {
    background: linear-gradient(135deg,
        rgba(30, 58, 138, 0.15) 0%,
        rgba(59, 130, 246, 0.15) 50%,
        rgba(96, 165, 250, 0.1) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

#strategy-glass-wrapper .nexus-welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #1e3a8a, #3b82f6, #60a5fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

#strategy-glass-wrapper .nexus-welcome-subtitle {
    font-size: 0.95rem;
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
    line-height: 1.5;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

#strategy-glass-wrapper .nexus-smart-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

#strategy-glass-wrapper .nexus-smart-btn {
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

#strategy-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

#strategy-glass-wrapper .nexus-smart-btn-primary {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    box-shadow: 0 4px 14px rgba(30, 58, 138, 0.35);
}

#strategy-glass-wrapper .nexus-smart-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(30, 58, 138, 0.45);
}

#strategy-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(255, 255, 255, 0.5);
    color: var(--htb-text-main);
    border-color: rgba(30, 58, 138, 0.3);
}

[data-theme="dark"] #strategy-glass-wrapper .nexus-smart-btn-outline {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(59, 130, 246, 0.4);
}

#strategy-glass-wrapper .nexus-smart-btn-outline:hover {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    #strategy-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
    #strategy-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
    #strategy-glass-wrapper .nexus-smart-buttons { gap: 8px; }
    #strategy-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
}

#strategy-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #strategy-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(30, 58, 138, 0.06) 0%,
        rgba(59, 130, 246, 0.06) 25%,
        rgba(96, 165, 250, 0.06) 50%,
        rgba(147, 197, 253, 0.06) 75%,
        rgba(30, 58, 138, 0.06) 100%);
    background-size: 400% 400%;
    animation: strategyGradientShift 15s ease infinite;
}

[data-theme="dark"] #strategy-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(30, 58, 138, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(96, 165, 250, 0.04) 0%, transparent 70%);
}

@keyframes strategyGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Layout Grid */
#strategy-glass-wrapper .strategy-layout {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2.5rem;
    align-items: start;
}

/* Page Header */
#strategy-glass-wrapper .strategy-page-header {
    grid-column: 1 / -1;
    text-align: center;
    margin-bottom: 1rem;
}

#strategy-glass-wrapper .strategy-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#strategy-glass-wrapper .strategy-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.1rem;
    margin: 0 0 1.5rem 0;
}

#strategy-glass-wrapper .strategy-page-header .download-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--strategy-theme) 0%, var(--strategy-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(30, 58, 138, 0.3);
}

#strategy-glass-wrapper .strategy-page-header .download-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(30, 58, 138, 0.4);
}

/* Sticky TOC Sidebar */
#strategy-glass-wrapper .toc-sidebar {
    position: sticky;
    top: 100px;
    border-radius: 20px;
    padding: 1.5rem;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    transition: all 0.3s ease;
}

[data-theme="light"] #strategy-glass-wrapper .toc-sidebar {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(30, 58, 138, 0.15);
    box-shadow: 0 8px 32px rgba(30, 58, 138, 0.1);
}

[data-theme="dark"] #strategy-glass-wrapper .toc-sidebar {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(30, 58, 138, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#strategy-glass-wrapper .toc-sidebar h3 {
    color: var(--strategy-theme);
    margin: 0 0 1.25rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

#strategy-glass-wrapper .toc-sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

#strategy-glass-wrapper .toc-sidebar li {
    margin-bottom: 0.5rem;
}

#strategy-glass-wrapper .toc-sidebar a {
    text-decoration: none;
    color: var(--htb-text-muted);
    font-size: 0.9rem;
    display: block;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

[data-theme="light"] #strategy-glass-wrapper .toc-sidebar a:hover {
    background: rgba(30, 58, 138, 0.1);
    color: var(--strategy-theme);
    padding-left: 1rem;
}

[data-theme="dark"] #strategy-glass-wrapper .toc-sidebar a:hover {
    background: rgba(30, 58, 138, 0.2);
    color: var(--strategy-theme-light);
    padding-left: 1rem;
}

/* Glass Panels (Sections) */
#strategy-glass-wrapper .glass-panel {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    scroll-margin-top: 100px;
    transition: all 0.3s ease;
}

[data-theme="light"] #strategy-glass-wrapper .glass-panel {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(30, 58, 138, 0.12);
    box-shadow: 0 8px 32px rgba(30, 58, 138, 0.08);
}

[data-theme="dark"] #strategy-glass-wrapper .glass-panel {
    background: rgba(30, 41, 59, 0.65);
    border: 1px solid rgba(30, 58, 138, 0.18);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
}

#strategy-glass-wrapper .glass-panel:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #strategy-glass-wrapper .glass-panel:hover {
    box-shadow: 0 12px 40px rgba(30, 58, 138, 0.12);
}

[data-theme="dark"] #strategy-glass-wrapper .glass-panel:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
}

/* Section Headers */
#strategy-glass-wrapper .glass-panel h2 {
    color: var(--strategy-theme);
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 1.5rem 0;
    padding-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

[data-theme="light"] #strategy-glass-wrapper .glass-panel h2 {
    border-bottom: 2px solid rgba(30, 58, 138, 0.15);
}

[data-theme="dark"] #strategy-glass-wrapper .glass-panel h2 {
    border-bottom: 2px solid rgba(30, 58, 138, 0.25);
}

#strategy-glass-wrapper .glass-panel h3 {
    color: var(--htb-text-main);
    font-size: 1.25rem;
    font-weight: 700;
    margin: 1.5rem 0 1rem 0;
}

#strategy-glass-wrapper .glass-panel h4 {
    color: var(--strategy-theme);
    font-size: 1rem;
    font-weight: 600;
    margin: 1.25rem 0 0.75rem 0;
}

#strategy-glass-wrapper .glass-panel p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.8;
    margin: 0 0 1rem 0;
}

#strategy-glass-wrapper .glass-panel ul,
#strategy-glass-wrapper .glass-panel ol {
    color: var(--htb-text-muted);
    line-height: 1.8;
    margin: 0 0 1rem 0;
    padding-left: 1.5rem;
}

#strategy-glass-wrapper .glass-panel li {
    margin-bottom: 0.5rem;
}

#strategy-glass-wrapper .glass-panel strong {
    color: var(--htb-text-main);
}

/* Vision Boxes */
#strategy-glass-wrapper .vision-box {
    padding: 1.5rem;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 1.25rem;
}

[data-theme="light"] #strategy-glass-wrapper .vision-box {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.08) 0%, rgba(59, 130, 246, 0.05) 100%);
    border: 1px solid rgba(30, 58, 138, 0.15);
}

[data-theme="dark"] #strategy-glass-wrapper .vision-box {
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.15) 0%, rgba(59, 130, 246, 0.08) 100%);
    border: 1px solid rgba(30, 58, 138, 0.25);
}

#strategy-glass-wrapper .vision-box h3 {
    font-size: 1rem;
    margin: 0 0 0.75rem 0;
    color: var(--htb-text-main);
}

#strategy-glass-wrapper .vision-box p {
    font-size: 1.05rem;
    font-style: italic;
    color: var(--strategy-theme);
    font-weight: 500;
    margin: 0;
    line-height: 1.6;
}

[data-theme="dark"] #strategy-glass-wrapper .vision-box p {
    color: var(--strategy-theme-light);
}

/* SWOT Grid */
#strategy-glass-wrapper .swot-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

#strategy-glass-wrapper .swot-card {
    padding: 1.5rem;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-top: 4px solid #ccc;
}

[data-theme="light"] #strategy-glass-wrapper .swot-card {
    background: rgba(255, 255, 255, 0.6);
    border-color: rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] #strategy-glass-wrapper .swot-card {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
}

#strategy-glass-wrapper .swot-card h4 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

#strategy-glass-wrapper .swot-card ul {
    margin: 0;
    padding-left: 1.25rem;
}

#strategy-glass-wrapper .swot-strengths {
    border-top-color: var(--success);
}
#strategy-glass-wrapper .swot-strengths h4 {
    color: var(--success);
}

#strategy-glass-wrapper .swot-weaknesses {
    border-top-color: var(--danger);
}
#strategy-glass-wrapper .swot-weaknesses h4 {
    color: var(--danger);
}

#strategy-glass-wrapper .swot-opps {
    border-top-color: var(--strategy-theme-light);
}
#strategy-glass-wrapper .swot-opps h4 {
    color: var(--strategy-theme-light);
}

#strategy-glass-wrapper .swot-threats {
    border-top-color: var(--warning);
}
#strategy-glass-wrapper .swot-threats h4 {
    color: var(--warning);
}

/* Tables */
#strategy-glass-wrapper .table-responsive {
    overflow-x: auto;
    margin: 1rem 0;
    border-radius: 12px;
}

#strategy-glass-wrapper table {
    width: 100%;
    border-collapse: collapse;
}

[data-theme="light"] #strategy-glass-wrapper table {
    background: rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] #strategy-glass-wrapper table {
    background: rgba(30, 41, 59, 0.6);
}

#strategy-glass-wrapper th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--htb-text-main);
}

[data-theme="light"] #strategy-glass-wrapper th {
    background: rgba(30, 58, 138, 0.08);
}

[data-theme="dark"] #strategy-glass-wrapper th {
    background: rgba(30, 58, 138, 0.15);
}

#strategy-glass-wrapper td {
    padding: 1rem;
    color: var(--htb-text-muted);
    font-size: 0.95rem;
}

[data-theme="light"] #strategy-glass-wrapper td {
    border-top: 1px solid rgba(30, 58, 138, 0.1);
}

[data-theme="dark"] #strategy-glass-wrapper td {
    border-top: 1px solid rgba(30, 58, 138, 0.15);
}

/* Timeline Pills */
#strategy-glass-wrapper .pill {
    display: inline-block;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#strategy-glass-wrapper .pill-submit {
    background: var(--cyan);
}

#strategy-glass-wrapper .pill-secure {
    background: var(--success);
}

#strategy-glass-wrapper .pill-launch {
    background: var(--purple);
}

#strategy-glass-wrapper .pill-ongoing {
    background: var(--warning);
}

/* Pillar Headers */
#strategy-glass-wrapper .pillar-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 2rem 0 1rem 0;
    padding: 1rem;
    border-radius: 12px;
    border-left: 4px solid var(--strategy-theme-light);
}

[data-theme="light"] #strategy-glass-wrapper .pillar-header {
    background: rgba(30, 58, 138, 0.05);
}

[data-theme="dark"] #strategy-glass-wrapper .pillar-header {
    background: rgba(30, 58, 138, 0.1);
}

#strategy-glass-wrapper .pillar-header h3 {
    margin: 0;
    color: var(--strategy-theme);
    font-size: 1.15rem;
}

[data-theme="dark"] #strategy-glass-wrapper .pillar-header h3 {
    color: var(--strategy-theme-light);
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1024px) {
    #strategy-glass-wrapper .strategy-layout {
        grid-template-columns: 1fr;
    }

    #strategy-glass-wrapper .toc-sidebar {
        display: none;
    }

    #strategy-glass-wrapper .strategy-page-header {
        margin-bottom: 0;
    }

    #strategy-glass-wrapper .swot-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    /* CRITICAL: Prevent horizontal overflow */
    #strategy-glass-wrapper {
        padding: 1rem 12px 3rem;
        margin-top: 56px !important;
        overflow-x: hidden !important;
        max-width: 100vw !important;
        box-sizing: border-box;
    }

    #strategy-glass-wrapper .strategy-layout {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }

    #strategy-glass-wrapper main {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }

    #strategy-glass-wrapper .strategy-page-header h1 {
        font-size: 1.5rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    #strategy-glass-wrapper .strategy-page-header p {
        font-size: 0.95rem;
    }

    /* Glass panels - prevent overflow */
    #strategy-glass-wrapper .glass-panel {
        padding: 1rem 12px;
        border-radius: 16px;
        margin-bottom: 1.25rem;
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    #strategy-glass-wrapper .glass-panel h2 {
        font-size: 1.15rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        word-break: break-word;
    }

    #strategy-glass-wrapper .glass-panel h3 {
        font-size: 1rem;
        word-break: break-word;
    }

    #strategy-glass-wrapper .glass-panel h4 {
        font-size: 0.9rem;
        word-break: break-word;
    }

    #strategy-glass-wrapper .glass-panel p {
        font-size: 0.9rem;
        line-height: 1.6;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    #strategy-glass-wrapper .glass-panel ul,
    #strategy-glass-wrapper .glass-panel ol {
        font-size: 0.9rem;
        padding-left: 1.25rem;
        line-height: 1.6;
        max-width: 100%;
    }

    #strategy-glass-wrapper .glass-panel li {
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    /* Welcome Hero - compact on mobile */
    #strategy-glass-wrapper .nexus-welcome-hero {
        padding: 16px 12px;
        border-radius: 16px;
        margin-bottom: 16px;
        max-width: 100% !important;
        box-sizing: border-box;
    }

    #strategy-glass-wrapper .nexus-welcome-title {
        font-size: 1.25rem;
        word-break: break-word;
    }

    #strategy-glass-wrapper .nexus-welcome-subtitle {
        font-size: 0.85rem;
        margin-bottom: 14px;
        word-wrap: break-word;
    }

    #strategy-glass-wrapper .nexus-smart-buttons {
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }

    #strategy-glass-wrapper .nexus-smart-btn {
        width: 100%;
        justify-content: center;
        padding: 14px 16px;
        box-sizing: border-box;
    }

    /* Tables - horizontal scroll container */
    #strategy-glass-wrapper .table-responsive {
        margin: 0.75rem -12px;
        padding: 0;
        border-radius: 0;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        max-width: calc(100% + 24px);
    }

    #strategy-glass-wrapper table {
        font-size: 0.75rem;
        min-width: 450px;
        margin: 0 12px;
    }

    #strategy-glass-wrapper th,
    #strategy-glass-wrapper td {
        padding: 0.5rem 0.4rem;
        white-space: nowrap;
    }

    #strategy-glass-wrapper th:first-child,
    #strategy-glass-wrapper td:first-child {
        white-space: normal;
        min-width: 100px;
        max-width: 140px;
        word-wrap: break-word;
    }

    /* SWOT Grid - stack on mobile */
    #strategy-glass-wrapper .swot-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        max-width: 100%;
    }

    #strategy-glass-wrapper .swot-card {
        padding: 1rem;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
    }

    #strategy-glass-wrapper .swot-card h4 {
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
    }

    #strategy-glass-wrapper .swot-card ul {
        font-size: 0.85rem;
        padding-left: 1rem;
        margin: 0;
    }

    #strategy-glass-wrapper .swot-card li {
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    /* Vision boxes - tighter on mobile */
    #strategy-glass-wrapper .vision-box {
        padding: 1rem;
        margin-bottom: 1rem;
        max-width: 100%;
        box-sizing: border-box;
    }

    #strategy-glass-wrapper .vision-box h3 {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    #strategy-glass-wrapper .vision-box p {
        font-size: 0.9rem;
        word-wrap: break-word;
    }

    /* Pillar headers - compact */
    #strategy-glass-wrapper .pillar-header {
        padding: 0.75rem;
        margin: 1.5rem 0 0.75rem 0;
        max-width: 100%;
        box-sizing: border-box;
    }

    #strategy-glass-wrapper .pillar-header h3 {
        font-size: 0.95rem;
    }

    /* Pills - smaller on mobile */
    #strategy-glass-wrapper .pill {
        font-size: 0.6rem;
        padding: 0.2rem 0.5rem;
    }

    @keyframes strategyGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Small phone breakpoint */
@media (max-width: 480px) {
    #strategy-glass-wrapper {
        padding: 0.75rem 8px 3rem;
    }

    #strategy-glass-wrapper .glass-panel {
        padding: 0.875rem 10px;
        border-radius: 14px;
    }

    #strategy-glass-wrapper .glass-panel h2 {
        font-size: 1.05rem;
    }

    #strategy-glass-wrapper .glass-panel h3 {
        font-size: 0.95rem;
    }

    #strategy-glass-wrapper .glass-panel p,
    #strategy-glass-wrapper .glass-panel li {
        font-size: 0.85rem;
    }

    #strategy-glass-wrapper .nexus-welcome-hero {
        padding: 14px 10px;
        border-radius: 14px;
    }

    #strategy-glass-wrapper .nexus-welcome-title {
        font-size: 1.15rem;
    }

    #strategy-glass-wrapper .nexus-welcome-subtitle {
        font-size: 0.8rem;
    }

    #strategy-glass-wrapper .nexus-smart-btn {
        padding: 12px 14px;
        font-size: 0.85rem;
    }

    #strategy-glass-wrapper .table-responsive {
        margin: 0.5rem -8px;
    }

    #strategy-glass-wrapper table {
        font-size: 0.7rem;
        min-width: 400px;
        margin: 0 8px;
    }

    #strategy-glass-wrapper th,
    #strategy-glass-wrapper td {
        padding: 0.4rem 0.3rem;
    }

    #strategy-glass-wrapper .swot-card {
        padding: 0.875rem;
    }

    #strategy-glass-wrapper .swot-card h4 {
        font-size: 0.9rem;
    }

    #strategy-glass-wrapper .swot-card ul {
        font-size: 0.8rem;
    }

    #strategy-glass-wrapper .vision-box {
        padding: 0.875rem;
    }

    #strategy-glass-wrapper .vision-box p {
        font-size: 0.85rem;
    }

    #strategy-glass-wrapper .pillar-header {
        padding: 0.6rem;
    }

    #strategy-glass-wrapper .pillar-header h3 {
        font-size: 0.9rem;
    }

    #strategy-glass-wrapper .pill {
        font-size: 0.55rem;
        padding: 0.15rem 0.4rem;
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #strategy-glass-wrapper .toc-sidebar,
    [data-theme="light"] #strategy-glass-wrapper .glass-panel,
    [data-theme="light"] #strategy-glass-wrapper .swot-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #strategy-glass-wrapper .toc-sidebar,
    [data-theme="dark"] #strategy-glass-wrapper .glass-panel,
    [data-theme="dark"] #strategy-glass-wrapper .swot-card {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="strategy-glass-wrapper">

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Strategic Plan 2026-2030</h1>
        <p class="nexus-welcome-subtitle">The Power of an Hour: Building a Resilient, Connected Ireland through community timebanking.</p>

        <div class="nexus-smart-buttons">
            <a href="/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf" target="_blank" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-file-pdf"></i>
                <span>Download PDF</span>
            </a>
            <a href="<?= $basePath ?>/about-story" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-book-open"></i>
                <span>Our Story</span>
            </a>
            <a href="<?= $basePath ?>/contact" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-envelope"></i>
                <span>Contact Us</span>
            </a>
        </div>
    </div>

    <div class="strategy-layout">

        <!-- Page Header -->
        <div class="strategy-page-header" style="display: none;">
            <h1>📋 Strategic Plan 2026-2030</h1>
            <p>The Power of an Hour: Building a Resilient, Connected Ireland</p>
            <a href="/uploads/tenants/hour-timebank/Timebank-Ireland-Strategic-Plan-2026-2030.pdf" target="_blank" class="download-btn">
                <span>📄</span> Download Official PDF
            </a>
        </div>

        <!-- Sticky TOC Sidebar -->
        <aside class="toc-sidebar">
            <h3>📑 Contents</h3>
            <ul>
                <li><a href="#executive-summary">1. Executive Summary</a></li>
                <li><a href="#vision">2. Vision & Mission</a></li>
                <li><a href="#analysis">3. SWOT Analysis</a></li>
                <li><a href="#pillars">4. Strategic Pillars</a></li>
                <li><a href="#roadmap">5. Roadmap (Year 1)</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main>

            <!-- 1. Executive Summary -->
            <section id="executive-summary" class="glass-panel">
                <h2>📊 1. Executive Summary</h2>
                <p>This five-year strategic plan (2026-2030) outlines a clear and ambitious path for hOUR Timebank (TBI) to transition from a single, high-impact regional organisation into a resilient and scalable national network.</p>
                <p>Our strategy is built on a foundation of proven, exceptional social value. A 2023 Social Impact Study quantified our SROI at <strong>1:16</strong>—for every €1 invested, €16 in tangible social value is returned.</p>

                <h4>Two Primary Goals:</h4>
                <ol>
                    <li><strong>Sustainable Growth:</strong> Scale from 245 to 2,500+ members by 2030, establishing a "Centre of Excellence" in West Cork.</li>
                    <li><strong>Maximising Social Impact:</strong> Deepen community value by investing in technology and diversifying our financial model.</li>
                </ol>
            </section>

            <!-- 2. Vision & Mission -->
            <section id="vision" class="glass-panel">
                <h2>🎯 2. Our 2026 Vision</h2>

                <div class="vision-box">
                    <h3>🚩 Our Mission</h3>
                    <p>To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received.</p>
                </div>

                <div class="vision-box">
                    <h3>👁️ Our Vision</h3>
                    <p>An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient communities.</p>
                </div>
            </section>

            <!-- 3. SWOT Analysis -->
            <section id="analysis" class="glass-panel">
                <h2>📈 3. SWOT Analysis</h2>
                <div class="swot-grid">
                    <div class="swot-card swot-strengths">
                        <h4>✅ Strengths</h4>
                        <ul>
                            <li><strong>Proven Impact:</strong> Independently validated 1:16 SROI.</li>
                            <li><strong>Partnerships:</strong> WCDP and Rethink Ireland.</li>
                            <li><strong>Lean Operations:</strong> Effective low-cost model.</li>
                        </ul>
                    </div>
                    <div class="swot-card swot-weaknesses">
                        <h4>⚠️ Weaknesses</h4>
                        <ul>
                            <li><strong>Financial Instability:</strong> Funding gap after shop closure.</li>
                            <li><strong>Human Resources:</strong> Reliance on key individuals.</li>
                            <li><strong>No Physical Hub:</strong> Lack of central premises.</li>
                        </ul>
                    </div>
                    <div class="swot-card swot-opps">
                        <h4>🚀 Opportunities</h4>
                        <ul>
                            <li><strong>Public Sector Contracts:</strong> HSE Social Prescribing.</li>
                            <li><strong>Hybrid Models:</strong> B2B Timebanking.</li>
                            <li><strong>"Loneliness Epidemic":</strong> Powerful narrative.</li>
                        </ul>
                    </div>
                    <div class="swot-card swot-threats">
                        <h4>🔥 Threats</h4>
                        <ul>
                            <li><strong>Funding Cliff:</strong> Securing long-term coordinator funding.</li>
                            <li><strong>Volunteer Burnout:</strong> Unsustainable burden.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- 4. Strategic Pillars -->
            <section id="pillars" class="glass-panel">
                <h2>🏛️ 4. Strategic Pillars</h2>

                <div class="pillar-header">
                    <span>🌱</span>
                    <h3>Pillar 1: Roots & Reach (Growth)</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Key Initiatives</th>
                                <th>Year 1 Priorities</th>
                                <th>KPIs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1.1: West Cork Centre</strong></td>
                                <td>Secure funding for Hub Coordinator.</td>
                                <td>Monthly Hours > 200</td>
                            </tr>
                            <tr>
                                <td><strong>1.2: National Plan</strong></td>
                                <td>"Hub-in-a-Box" toolkit.</td>
                                <td>Toolkit completed.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pillar-header">
                    <span>💰</span>
                    <h3>Pillar 2: Financial Resilience</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Key Initiatives</th>
                                <th>Year 1 Priorities</th>
                                <th>KPIs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>2.1: Core Funding</strong></td>
                                <td>Develop "Case for Support" (SROI).</td>
                                <td>Core costs funded 2026-28.</td>
                            </tr>
                            <tr>
                                <td><strong>2.2: Public Contracts</strong></td>
                                <td>Pilot in West Cork for Social Prescribing.</td>
                                <td>1x Pilot Contract.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 5. Roadmap -->
            <section id="roadmap" class="glass-panel">
                <h2>🗓️ 5. Roadmap: Year 1</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Initiative</th>
                                <th>Q1</th>
                                <th>Q2</th>
                                <th>Q3</th>
                                <th>Q4</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Fund Coordinator</strong></td>
                                <td><span class="pill pill-submit">SUBMIT</span></td>
                                <td><span class="pill pill-secure">SECURE</span></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><strong>Re-Engagement</strong></td>
                                <td></td>
                                <td></td>
                                <td><span class="pill pill-launch">LAUNCH</span></td>
                                <td><span class="pill pill-ongoing">ONGOING</span></td>
                            </tr>
                            <tr>
                                <td><strong>Multi-Year Grants</strong></td>
                                <td><span class="pill pill-submit">SUBMIT</span></td>
                                <td><span class="pill pill-submit">PITCH</span></td>
                                <td><span class="pill pill-secure">SECURE</span></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
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
document.querySelectorAll('.nexus-smart-btn, .download-btn, .toc-sidebar a').forEach(btn => {
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
        meta.content = '#1e3a8a';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#1e3a8a');
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
