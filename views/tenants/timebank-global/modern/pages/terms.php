<?php
/**
 * Platform Provider Terms of Service - timebank.global
 *
 * This document governs the relationship between:
 * - Timebank Global (Platform Provider)
 * - Tenant Operators (Independent timebanks using the platform)
 * - End Users (Members of tenant timebanks)
 *
 * Theme Color: Indigo (#6366f1)
 */
$pageTitle = 'Platform Terms of Service';
$hideHero = true;

require __DIR__ . '/../../../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
$lastUpdated = '16 January 2026';
$effectiveDate = '16 January 2026';
$version = '1.0';
?>

<style>
/* ============================================
   PLATFORM TERMS OF SERVICE - GLASSMORPHISM
   Theme: Indigo (#6366f1)
   ============================================ */

#platform-terms-wrapper {
    --pt-theme: #6366f1;
    --pt-theme-rgb: 99, 102, 241;
    --pt-theme-light: #818cf8;
    --pt-theme-dark: #4f46e5;
    --pt-success: #10b981;
    --pt-warning: #f59e0b;
    --pt-danger: #ef4444;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #platform-terms-wrapper {
        padding-top: 120px;
    }
}

#platform-terms-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #platform-terms-wrapper::before {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(129, 140, 248, 0.08) 25%,
        rgba(79, 70, 229, 0.08) 50%,
        rgba(99, 102, 241, 0.08) 75%,
        rgba(129, 140, 248, 0.08) 100%);
    background-size: 400% 400%;
    animation: ptGradientShift 15s ease infinite;
}

[data-theme="dark"] #platform-terms-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(79, 70, 229, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(129, 140, 248, 0.05) 0%, transparent 70%);
}

@keyframes ptGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

@keyframes ptFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#platform-terms-wrapper {
    animation: ptFadeInUp 0.4s ease-out;
}

#platform-terms-wrapper .pt-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#platform-terms-wrapper .pt-page-header {
    text-align: center;
    margin-bottom: 2rem;
}

#platform-terms-wrapper .pt-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#platform-terms-wrapper .pt-page-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--pt-theme) 0%, var(--pt-theme-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

#platform-terms-wrapper .pt-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0;
    max-width: 700px;
    margin: 0 auto;
}

#platform-terms-wrapper .pt-meta-badges {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

#platform-terms-wrapper .pt-meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #platform-terms-wrapper .pt-meta-badge {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pt-theme-dark);
}

[data-theme="dark"] #platform-terms-wrapper .pt-meta-badge {
    background: rgba(99, 102, 241, 0.2);
    color: var(--pt-theme-light);
}

/* Table of Contents */
#platform-terms-wrapper .pt-toc {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
}

[data-theme="light"] #platform-terms-wrapper .pt-toc {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #platform-terms-wrapper .pt-toc {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

#platform-terms-wrapper .pt-toc h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

#platform-terms-wrapper .pt-toc-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

#platform-terms-wrapper .pt-toc a {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    text-decoration: none;
    transition: all 0.2s ease;
}

#platform-terms-wrapper .pt-toc a:hover {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pt-theme);
}

#platform-terms-wrapper .pt-toc a .toc-num {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: var(--pt-theme);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Section Card */
#platform-terms-wrapper .pt-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #platform-terms-wrapper .pt-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-terms-wrapper .pt-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#platform-terms-wrapper .pt-section:hover {
    transform: translateY(-2px);
}

#platform-terms-wrapper .pt-section .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

#platform-terms-wrapper .pt-section .section-number {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 800;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--pt-theme) 0%, var(--pt-theme-dark) 100%);
    color: white;
}

#platform-terms-wrapper .pt-section .section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

#platform-terms-wrapper .pt-section p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.75;
    margin: 0 0 1rem 0;
}

#platform-terms-wrapper .pt-section p:last-child {
    margin-bottom: 0;
}

#platform-terms-wrapper .pt-section strong {
    color: var(--htb-text-main);
}

#platform-terms-wrapper .pt-section ul,
#platform-terms-wrapper .pt-section ol {
    margin: 1rem 0;
    padding-left: 0;
    list-style: none;
}

#platform-terms-wrapper .pt-section ul li,
#platform-terms-wrapper .pt-section ol li {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.75rem;
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#platform-terms-wrapper .pt-section ul li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--pt-theme);
}

#platform-terms-wrapper .pt-section ol {
    counter-reset: pt-counter;
}

#platform-terms-wrapper .pt-section ol li {
    counter-increment: pt-counter;
}

#platform-terms-wrapper .pt-section ol li::before {
    content: counter(pt-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: var(--pt-theme);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Highlight Section */
#platform-terms-wrapper .pt-section.highlight {
    border-left: 4px solid var(--pt-theme);
}

[data-theme="light"] #platform-terms-wrapper .pt-section.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(255, 255, 255, 0.7) 100%);
}

[data-theme="dark"] #platform-terms-wrapper .pt-section.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(30, 41, 59, 0.6) 100%);
}

/* Important Box */
#platform-terms-wrapper .pt-important {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 14px;
    margin: 1.5rem 0;
}

#platform-terms-wrapper .pt-important.info {
    border-left: 4px solid var(--pt-theme);
}

#platform-terms-wrapper .pt-important.warning {
    border-left: 4px solid var(--pt-warning);
}

#platform-terms-wrapper .pt-important.danger {
    border-left: 4px solid var(--pt-danger);
}

[data-theme="light"] #platform-terms-wrapper .pt-important {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

[data-theme="light"] #platform-terms-wrapper .pt-important.warning {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.15);
}

[data-theme="light"] #platform-terms-wrapper .pt-important.danger {
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.15);
}

[data-theme="dark"] #platform-terms-wrapper .pt-important {
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #platform-terms-wrapper .pt-important.warning {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.2);
}

[data-theme="dark"] #platform-terms-wrapper .pt-important.danger {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.2);
}

#platform-terms-wrapper .pt-important .important-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

#platform-terms-wrapper .pt-important.info .important-icon { color: var(--pt-theme); }
#platform-terms-wrapper .pt-important.warning .important-icon { color: var(--pt-warning); }
#platform-terms-wrapper .pt-important.danger .important-icon { color: var(--pt-danger); }

#platform-terms-wrapper .pt-important .important-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#platform-terms-wrapper .pt-important .important-content p {
    margin: 0;
    font-size: 0.95rem;
}

/* Definition Box */
#platform-terms-wrapper .pt-definition {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 0.5rem 1.5rem;
    padding: 1.25rem;
    border-radius: 14px;
    margin: 1rem 0;
}

[data-theme="light"] #platform-terms-wrapper .pt-definition {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-terms-wrapper .pt-definition {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

#platform-terms-wrapper .pt-definition dt {
    font-weight: 700;
    color: var(--htb-text-main);
    font-size: 0.95rem;
}

#platform-terms-wrapper .pt-definition dd {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    margin: 0;
    line-height: 1.5;
}

/* Hierarchy Visual */
#platform-terms-wrapper .pt-hierarchy {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.5rem;
    border-radius: 16px;
    margin: 1.5rem 0;
}

[data-theme="light"] #platform-terms-wrapper .pt-hierarchy {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #platform-terms-wrapper .pt-hierarchy {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.08) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

#platform-terms-wrapper .pt-hierarchy-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

#platform-terms-wrapper .pt-hierarchy-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

#platform-terms-wrapper .pt-hierarchy-item.level-1 .pt-hierarchy-icon {
    background: linear-gradient(135deg, var(--pt-theme) 0%, var(--pt-theme-dark) 100%);
    color: white;
}

#platform-terms-wrapper .pt-hierarchy-item.level-2 .pt-hierarchy-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    margin-left: 2rem;
}

#platform-terms-wrapper .pt-hierarchy-item.level-3 .pt-hierarchy-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    margin-left: 4rem;
}

#platform-terms-wrapper .pt-hierarchy-text h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.25rem 0;
}

#platform-terms-wrapper .pt-hierarchy-text p {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    margin: 0;
}

/* Subsection */
#platform-terms-wrapper .pt-subsection {
    margin: 1.5rem 0;
    padding-left: 1rem;
    border-left: 3px solid rgba(99, 102, 241, 0.3);
}

#platform-terms-wrapper .pt-subsection h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
}

/* Contact CTA */
#platform-terms-wrapper .pt-cta {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #platform-terms-wrapper .pt-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #platform-terms-wrapper .pt-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

#platform-terms-wrapper .pt-cta h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
}

#platform-terms-wrapper .pt-cta p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#platform-terms-wrapper .pt-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--pt-theme) 0%, var(--pt-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
}

#platform-terms-wrapper .pt-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
}

/* Back Link */
#platform-terms-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="light"] #platform-terms-wrapper .back-link {
    color: var(--pt-theme-dark);
}

[data-theme="dark"] #platform-terms-wrapper .back-link {
    color: var(--pt-theme-light);
}

#platform-terms-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    #platform-terms-wrapper {
        padding: 120px 1rem 3rem;
    }

    #platform-terms-wrapper .pt-page-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #platform-terms-wrapper .pt-toc-grid {
        grid-template-columns: 1fr;
    }

    #platform-terms-wrapper .pt-section {
        padding: 1.5rem;
    }

    #platform-terms-wrapper .pt-section .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    #platform-terms-wrapper .pt-definition {
        grid-template-columns: 1fr;
        gap: 0.25rem 0;
    }

    #platform-terms-wrapper .pt-definition dt {
        margin-top: 0.5rem;
    }

    #platform-terms-wrapper .pt-hierarchy-item.level-2 .pt-hierarchy-icon {
        margin-left: 1rem;
    }

    #platform-terms-wrapper .pt-hierarchy-item.level-3 .pt-hierarchy-icon {
        margin-left: 2rem;
    }

    #platform-terms-wrapper .pt-important {
        flex-direction: column;
    }
}

/* Focus Visible */
#platform-terms-wrapper .pt-cta-btn:focus-visible,
#platform-terms-wrapper .back-link:focus-visible,
#platform-terms-wrapper .pt-toc a:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.5);
    outline-offset: 2px;
}
</style>

<div id="platform-terms-wrapper">
    <div class="pt-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="pt-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-globe"></i></span>
                Platform Terms of Service
            </h1>
            <p>Terms governing the use of the Timebank Global platform by Tenant Operators and their Members</p>
            <div class="pt-meta-badges">
                <span class="pt-meta-badge">
                    <i class="fa-solid fa-calendar"></i>
                    Last Updated: <?= $lastUpdated ?>
                </span>
                <span class="pt-meta-badge">
                    <i class="fa-solid fa-calendar-check"></i>
                    Effective: <?= $effectiveDate ?>
                </span>
                <span class="pt-meta-badge">
                    <i class="fa-solid fa-code-branch"></i>
                    Version <?= $version ?>
                </span>
            </div>
        </div>

        <!-- Table of Contents -->
        <div class="pt-toc">
            <h3><i class="fa-solid fa-list"></i> Table of Contents</h3>
            <div class="pt-toc-grid">
                <a href="#definitions"><span class="toc-num">1</span> Definitions</a>
                <a href="#platform-role"><span class="toc-num">2</span> Platform Provider Role</a>
                <a href="#acceptance"><span class="toc-num">3</span> Acceptance of Terms</a>
                <a href="#tenant-responsibilities"><span class="toc-num">4</span> Tenant Responsibilities</a>
                <a href="#member-terms"><span class="toc-num">5</span> Member Terms</a>
                <a href="#federation"><span class="toc-num">6</span> Federation Services</a>
                <a href="#time-credits"><span class="toc-num">7</span> Time Credits</a>
                <a href="#prohibited"><span class="toc-num">8</span> Prohibited Uses</a>
                <a href="#ip"><span class="toc-num">9</span> Intellectual Property</a>
                <a href="#data-protection"><span class="toc-num">10</span> Data Protection</a>
                <a href="#liability"><span class="toc-num">11</span> Liability</a>
                <a href="#indemnification"><span class="toc-num">12</span> Indemnification</a>
                <a href="#termination"><span class="toc-num">13</span> Termination</a>
                <a href="#modifications"><span class="toc-num">14</span> Modifications</a>
                <a href="#governing-law"><span class="toc-num">15</span> Governing Law & Jurisdiction</a>
                <a href="#schedule-1"><span class="toc-num">S1</span> Schedule: Contracting Entities</a>
                <a href="#contact"><span class="toc-num">16</span> Contact</a>
            </div>
        </div>

        <!-- Introduction -->
        <div class="pt-section highlight">
            <div class="section-header">
                <div class="section-number"><i class="fa-solid fa-handshake"></i></div>
                <h2>Introduction</h2>
            </div>
            <p>Welcome to <strong>Timebank Global</strong> (accessible at timebank.global), a platform that enables independent timebank communities worldwide to operate, connect, and optionally collaborate through federation.</p>
            <p>These Platform Terms of Service ("Platform Terms") govern the relationship between:</p>

            <div class="pt-hierarchy">
                <div class="pt-hierarchy-item level-1">
                    <div class="pt-hierarchy-icon"><i class="fa-solid fa-globe"></i></div>
                    <div class="pt-hierarchy-text">
                        <h4>Timebank Global (Platform Provider)</h4>
                        <p>Operated by hOUR Timebank CLG (RBN: Timebank Ireland) — RCN 20162023</p>
                    </div>
                </div>
                <div class="pt-hierarchy-item level-2">
                    <div class="pt-hierarchy-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="pt-hierarchy-text">
                        <h4>Tenant Operators</h4>
                        <p>Independent organisations operating timebanks on our platform</p>
                    </div>
                </div>
                <div class="pt-hierarchy-item level-3">
                    <div class="pt-hierarchy-icon"><i class="fa-solid fa-user"></i></div>
                    <div class="pt-hierarchy-text">
                        <h4>Members</h4>
                        <p>Individual users of each tenant timebank</p>
                    </div>
                </div>
            </div>

            <div class="pt-important info">
                <span class="important-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="important-content">
                    <h4>Understanding This Document</h4>
                    <p>This document establishes Timebank Global as a <strong>technology platform provider</strong>. Each Tenant Operator is an independent organisation responsible for their own timebank community, compliance, and member relationships.</p>
                </div>
            </div>
        </div>

        <!-- Section 1: Definitions -->
        <div class="pt-section" id="definitions">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Definitions</h2>
            </div>
            <p>Throughout these Platform Terms, the following terms have specific meanings:</p>

            <dl class="pt-definition">
                <dt>"Platform"</dt>
                <dd>The Timebank Global software, infrastructure, and services accessible at timebank.global and associated domains.</dd>

                <dt>"Platform Provider"</dt>
                <dd>hOUR Timebank CLG (Registered Business Name: Timebank Ireland), a Company Limited by Guarantee registered in Ireland, and a Registered Charity (RCN 20162023), operating the Timebank Global platform.</dd>

                <dt>"Tenant" or "Tenant Operator"</dt>
                <dd>An independent organisation, community group, charity, or other entity that has been granted access to operate a timebank instance on the Platform.</dd>

                <dt>"Member" or "End User"</dt>
                <dd>An individual who registers for and uses a timebank operated by a Tenant Operator.</dd>

                <dt>"Time Credits"</dt>
                <dd>Non-monetary units used within the Platform to track and facilitate the exchange of services between Members.</dd>

                <dt>"Federation"</dt>
                <dd>Optional features that allow approved Tenants and their Members to interact across timebank boundaries.</dd>

                <dt>"Services"</dt>
                <dd>The activities, skills, or assistance that Members offer to and receive from each other through the Platform.</dd>

                <dt>"Tenant Agreement"</dt>
                <dd>A separate agreement between the Platform Provider and each Tenant Operator governing specific operational terms.</dd>
            </dl>
        </div>

        <!-- Section 2: Platform Provider Role -->
        <div class="pt-section" id="platform-role">
            <div class="section-header">
                <div class="section-number">2</div>
                <h2>Platform Provider Role & Disclaimer</h2>
            </div>

            <div class="pt-important danger">
                <span class="important-icon"><i class="fa-solid fa-gavel"></i></span>
                <div class="important-content">
                    <h4>DIRECTORY SERVICE ONLY</h4>
                    <p><strong>Timebank Global is a DIRECTORY and SOFTWARE PLATFORM.</strong> We provide technology that enables independent timebanks to list themselves and operate. We do NOT operate any timebank (except Timebank Ireland), we do NOT participate in any service exchange, and we accept NO liability for the actions of Tenant Operators or their Members. Each timebank listed on our platform is 100% independent and solely responsible for themselves.</p>
                </div>
            </div>

            <div class="pt-subsection">
                <h3>2.1 What We Are</h3>
                <p>Timebank Global is a <strong>technology platform provider and directory service</strong>, operated by <strong>hOUR Timebank CLG (RBN: Timebank Ireland)</strong>, a Company Limited by Guarantee and Registered Charity (RCN 20162023) in Ireland. We provide:</p>
                <ul>
                    <li>Software infrastructure for timebanks to operate</li>
                    <li>A directory listing independent timebanks worldwide</li>
                    <li>Technical tools and hosting services</li>
                </ul>
                <p>Our role is analogous to:</p>
                <ul>
                    <li><strong>Airbnb</strong> — which does not own properties or employ hosts</li>
                    <li><strong>Uber</strong> — which does not own vehicles or employ drivers</li>
                    <li><strong>Amazon Marketplace</strong> — which does not sell products from third-party sellers</li>
                    <li><strong>Facebook Groups</strong> — which does not operate or moderate community groups</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>2.2 What We Are Not</h3>
                <p>The Platform Provider is <strong>expressly NOT</strong>:</p>
                <ul>
                    <li>An operator, manager, or controller of any Tenant timebank</li>
                    <li>A party to any exchange, transaction, or agreement between Members</li>
                    <li>An employer, agent, principal, or joint venturer with any Tenant Operator or Member</li>
                    <li>A supervisor, verifier, or validator of Members' identities, skills, qualifications, or backgrounds</li>
                    <li>An endorser, guarantor, or insurer of any Services exchanged</li>
                    <li>Responsible or liable for the conduct, acts, or omissions of Tenant Operators or their Members</li>
                    <li>A financial services provider, payment processor, money transmitter, or currency issuer</li>
                    <li>A provider of any services exchanged between Members (we only provide the platform software)</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>2.3 No Agency or Employment Relationship</h3>
                <p><strong>No agency, partnership, joint venture, employer-employee, or franchisor-franchisee relationship</strong> is intended or created by these Platform Terms between the Platform Provider and any Tenant Operator, Member, or third party.</p>
                <p>Tenant Operators are <strong>independent entities</strong> that use our software under licence. They are not our agents, employees, franchisees, or representatives. We do not control their operations, policies, or conduct.</p>
            </div>

            <div class="pt-important warning">
                <span class="important-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div class="important-content">
                    <h4>Critical Disclaimer — Please Read Carefully</h4>
                    <p><strong>WE DO NOT VERIFY, VET, ENDORSE, OR GUARANTEE:</strong></p>
                    <ul style="margin: 0.5rem 0 0 0; padding-left: 1.25rem;">
                        <li>The identity, background, or qualifications of any Member</li>
                        <li>The quality, safety, legality, or suitability of any Services offered or exchanged</li>
                        <li>The accuracy of any information provided by Tenant Operators or Members</li>
                        <li>The compliance of any Tenant Operator with applicable laws</li>
                        <li>The outcome of any service exchange or interaction between Members</li>
                    </ul>
                    <p style="margin-top: 0.75rem;"><strong>ALL EXCHANGES ARE CONDUCTED ENTIRELY AT THE RISK OF THE PARTICIPATING MEMBERS AND UNDER THE SOLE RESPONSIBILITY OF THE RELEVANT TENANT OPERATOR.</strong></p>
                </div>
            </div>

            <div class="pt-subsection">
                <h3>2.4 Platform Services We Provide</h3>
                <p>The Platform Provider offers <strong>technology services only</strong>:</p>
                <ul>
                    <li>Secure cloud hosting and technical infrastructure</li>
                    <li>User registration and authentication systems</li>
                    <li>Time credit tracking and ledger management tools</li>
                    <li>Communication features (messaging, notifications)</li>
                    <li>Member directory and skill-matching functionality</li>
                    <li>Optional federation services for cross-tenant collaboration</li>
                    <li>Administrative dashboards for Tenant Operators</li>
                    <li>Technical support and platform maintenance</li>
                </ul>
                <p>We <strong>do not provide</strong> any of the actual services exchanged between Members. Those services are provided solely by Members to other Members, facilitated by Tenant Operators.</p>
            </div>

            <div class="pt-subsection">
                <h3>2.5 Tenant Operators Are 100% Responsible</h3>
                <p>Each Tenant Operator that uses our platform is an <strong>independent legal entity</strong> that is <strong>solely and entirely responsible</strong> for:</p>
                <ul>
                    <li>Their own timebank's operation, policies, and procedures</li>
                    <li>Their Members' conduct and the services they exchange</li>
                    <li>Compliance with all applicable laws in their jurisdiction</li>
                    <li>Member verification, safeguarding, and dispute resolution</li>
                    <li>Insurance, liability coverage, and risk management</li>
                    <li>Their own Terms of Service and Privacy Policy</li>
                </ul>
                <p><strong>The Platform Provider accepts no responsibility whatsoever for any Tenant Operator's timebank or its Members.</strong></p>
            </div>
        </div>

        <!-- Section 3: Acceptance of Terms -->
        <div class="pt-section" id="acceptance">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Acceptance of Terms</h2>
            </div>

            <div class="pt-subsection">
                <h3>3.1 For Tenant Operators</h3>
                <p>By applying for, receiving, or using a tenant instance on the Platform, you (the Tenant Operator) agree to:</p>
                <ul>
                    <li>These Platform Terms of Service</li>
                    <li>The separate Tenant Agreement (if applicable)</li>
                    <li>Our Data Processing Agreement</li>
                    <li>All applicable laws and regulations in your jurisdiction</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>3.2 For Members</h3>
                <p>By creating an account on any timebank operated on this Platform, you (the Member) agree to:</p>
                <ul>
                    <li>These Platform Terms of Service</li>
                    <li>The specific Terms of Service of your Tenant timebank</li>
                    <li>The Privacy Policy of both the Platform and your Tenant timebank</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>3.3 Capacity to Accept</h3>
                <p>You represent that you:</p>
                <ul>
                    <li>Are at least 16 years of age (or the age of digital consent in your jurisdiction)</li>
                    <li>Have the legal capacity to enter into binding agreements</li>
                    <li>Are not prohibited from using the Platform under any applicable law</li>
                    <li>If accepting on behalf of an organisation, have authority to bind that organisation</li>
                </ul>
            </div>
        </div>

        <!-- Section 4: Tenant Operator Responsibilities -->
        <div class="pt-section" id="tenant-responsibilities">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>Tenant Operator Responsibilities</h2>
            </div>
            <p>Each Tenant Operator is an independent entity and is solely responsible for:</p>

            <div class="pt-subsection">
                <h3>4.1 Legal & Regulatory Compliance</h3>
                <ul>
                    <li>Compliance with all laws and regulations in their operating jurisdiction(s)</li>
                    <li>Maintaining any required registrations, permits, or licences</li>
                    <li>Data protection compliance (GDPR, CCPA, or equivalent local laws)</li>
                    <li>Tax obligations and reporting requirements</li>
                    <li>Employment law considerations where applicable</li>
                    <li>Charity or non-profit regulatory requirements where applicable</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>4.2 Community Management</h3>
                <ul>
                    <li>Establishing and enforcing their own Terms of Service for Members</li>
                    <li>Creating and maintaining their own Privacy Policy</li>
                    <li>Member verification and vetting procedures</li>
                    <li>Safeguarding policies and procedures (especially for vulnerable persons)</li>
                    <li>Dispute resolution between their Members</li>
                    <li>Content moderation within their timebank</li>
                    <li>Training and supporting their Members</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>4.3 Operational Responsibilities</h3>
                <ul>
                    <li>Accurate representation of their organisation and its purpose</li>
                    <li>Maintaining adequate insurance coverage appropriate to their activities</li>
                    <li>Designating authorised administrators for their tenant instance</li>
                    <li>Responding to Member inquiries and complaints</li>
                    <li>Reporting serious incidents to appropriate authorities</li>
                    <li>Cooperating with Platform Provider requests for information</li>
                </ul>
            </div>

            <div class="pt-important danger">
                <span class="important-icon"><i class="fa-solid fa-gavel"></i></span>
                <div class="important-content">
                    <h4>Independent Legal Entities</h4>
                    <p>Each Tenant Operator is an independent legal entity. The Platform Provider has no control over Tenant operations and accepts no liability for Tenant Operator actions, omissions, or failures to comply with applicable laws.</p>
                </div>
            </div>
        </div>

        <!-- Section 5: Member Terms -->
        <div class="pt-section" id="member-terms">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Member Terms</h2>
            </div>

            <div class="pt-subsection">
                <h3>5.1 Relationship Structure</h3>
                <p>As a Member, your primary relationship is with your <strong>Tenant Operator</strong> (your local timebank). The Platform Provider supplies the technology; your Tenant Operator runs your timebank community.</p>
                <ul>
                    <li><strong>For membership questions:</strong> Contact your Tenant Operator</li>
                    <li><strong>For disputes with other Members:</strong> Contact your Tenant Operator</li>
                    <li><strong>For platform technical issues:</strong> Contact the Platform Provider</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>5.2 Account Responsibilities</h3>
                <p>All Members must:</p>
                <ul>
                    <li>Provide accurate and truthful information during registration</li>
                    <li>Maintain the security of their account credentials</li>
                    <li>Not share accounts or transfer accounts to others</li>
                    <li>Promptly update information if it changes</li>
                    <li>Comply with their Tenant Operator's Terms of Service</li>
                    <li>Comply with these Platform Terms</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>5.3 Service Exchange Responsibilities</h3>
                <p>When participating in service exchanges, Members must:</p>
                <ul>
                    <li>Accurately represent their skills and abilities</li>
                    <li>Honour commitments made to other Members</li>
                    <li>Communicate promptly and respectfully</li>
                    <li>Not offer Services that are illegal in their jurisdiction</li>
                    <li>Not offer Services requiring professional licences they do not hold</li>
                    <li>Take reasonable precautions for personal safety</li>
                    <li>Report concerns to their Tenant Operator</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>5.4 Safety Guidance</h3>
                <p>The Platform Provider recommends that Members:</p>
                <ul>
                    <li>Meet in public places for initial exchanges</li>
                    <li>Inform someone of their whereabouts during exchanges</li>
                    <li>Trust their instincts and decline exchanges that feel unsafe</li>
                    <li>Use the Platform's messaging for initial communications</li>
                    <li>Report any concerning behaviour to their Tenant Operator</li>
                </ul>
            </div>
        </div>

        <!-- Section 6: Federation Services -->
        <div class="pt-section" id="federation">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>Federation Services</h2>
            </div>
            <p>The Platform offers optional federation features that enable approved Tenant Operators and their Members to interact across timebank boundaries.</p>

            <div class="pt-subsection">
                <h3>6.1 Federation is Optional</h3>
                <ul>
                    <li>Federation features are <strong>disabled by default</strong></li>
                    <li>Tenant Operators must explicitly enable federation for their timebank</li>
                    <li>Members must explicitly opt-in to federation visibility</li>
                    <li>Either party may disable federation at any time</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>6.2 Federation Governance</h3>
                <p>When federation features are enabled:</p>
                <ul>
                    <li>Cross-tenant interactions are governed by the policies of <strong>both</strong> participating Tenant Operators</li>
                    <li>Members must comply with the rules of both their home timebank and any federated timebank they interact with</li>
                    <li>Disputes involving cross-tenant exchanges should first be addressed with the relevant Tenant Operators</li>
                    <li>The Platform Provider may mediate federation disputes but is not obligated to do so</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>6.3 Federation Controls</h3>
                <p>The Platform Provider maintains controls to ensure federation safety:</p>
                <ul>
                    <li><strong>Global Kill Switch:</strong> Platform-wide federation can be disabled instantly if required</li>
                    <li><strong>Tenant Whitelist:</strong> Only approved Tenants may participate in federation</li>
                    <li><strong>Partnership Approval:</strong> Tenant-to-tenant federation requires mutual agreement</li>
                    <li><strong>Rate Limits:</strong> Cross-tenant transactions are subject to rate limits</li>
                    <li><strong>Audit Logging:</strong> All federation activity is logged for security purposes</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>6.4 Cross-Tenant Transactions</h3>
                <p>When Time Credits are exchanged across tenant boundaries:</p>
                <ul>
                    <li>Each Tenant maintains its own Time Credit ledger</li>
                    <li>Cross-tenant transfers are recorded in both ledgers</li>
                    <li>Time Credit values are equal across all Tenants (1 hour = 1 credit)</li>
                    <li>The Platform Provider does not guarantee cross-tenant credit redemption</li>
                </ul>
            </div>

            <div class="pt-important info">
                <span class="important-icon"><i class="fa-solid fa-link"></i></span>
                <div class="important-content">
                    <h4>Federation Data Sharing</h4>
                    <p>When you opt into federation, limited profile information (name, skills, general location) may be visible to Members of partner timebanks. Your Tenant Operator's Privacy Policy and the Platform Privacy Policy provide more details.</p>
                </div>
            </div>
        </div>

        <!-- Section 7: Time Credits -->
        <div class="pt-section" id="time-credits">
            <div class="section-header">
                <div class="section-number">7</div>
                <h2>Time Credits</h2>
            </div>

            <div class="pt-subsection">
                <h3>7.1 Nature of Time Credits</h3>
                <p>Time Credits are <strong>not</strong>:</p>
                <ul>
                    <li>Money, currency, or legal tender</li>
                    <li>Cryptocurrency or digital assets</li>
                    <li>Vouchers, coupons, or gift cards</li>
                    <li>Transferable for cash or monetary value</li>
                    <li>Property that can be bought, sold, or traded for money</li>
                </ul>
                <p>Time Credits <strong>are</strong>:</p>
                <ul>
                    <li>A record-keeping mechanism to track service exchanges</li>
                    <li>Equal in value (1 hour of any service = 1 Time Credit)</li>
                    <li>Administered by Tenant Operators</li>
                    <li>Subject to the policies of each Tenant timebank</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>7.2 Time Credit Policies</h3>
                <ul>
                    <li>Each Tenant Operator may set policies for initial credit allocation</li>
                    <li>Time Credits may expire according to Tenant policies</li>
                    <li>Time Credits cannot be inherited or transferred upon death</li>
                    <li>Time Credits are forfeited upon account closure or termination</li>
                    <li>The Platform Provider is not responsible for lost or disputed Time Credits</li>
                </ul>
            </div>

            <div class="pt-important warning">
                <span class="important-icon"><i class="fa-solid fa-coins"></i></span>
                <div class="important-content">
                    <h4>Tax Considerations</h4>
                    <p>Time Credits may have tax implications in some jurisdictions. Members and Tenant Operators are responsible for understanding and complying with their local tax obligations. The Platform Provider does not provide tax advice.</p>
                </div>
            </div>
        </div>

        <!-- Section 8: Prohibited Uses -->
        <div class="pt-section" id="prohibited">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Prohibited Uses</h2>
            </div>
            <p>The following activities are strictly prohibited on the Platform:</p>

            <div class="pt-subsection">
                <h3>8.1 Illegal Activities</h3>
                <ul>
                    <li>Any activity that violates applicable laws or regulations</li>
                    <li>Money laundering or tax evasion schemes</li>
                    <li>Offering or requesting illegal services</li>
                    <li>Fraud, theft, or deception</li>
                    <li>Trafficking in illegal goods or services</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>8.2 Harmful Conduct</h3>
                <ul>
                    <li>Harassment, bullying, or intimidation</li>
                    <li>Discrimination based on protected characteristics</li>
                    <li>Threatening or violent behaviour</li>
                    <li>Sexual harassment or exploitation</li>
                    <li>Endangering minors or vulnerable persons</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>8.3 Platform Abuse</h3>
                <ul>
                    <li>Creating multiple accounts for fraudulent purposes</li>
                    <li>Impersonating others or misrepresenting identity</li>
                    <li>Spam, unsolicited commercial messages, or advertising</li>
                    <li>Attempting to circumvent security measures</li>
                    <li>Reverse-engineering or copying Platform software</li>
                    <li>Scraping or harvesting user data</li>
                    <li>Introducing malware, viruses, or malicious code</li>
                    <li>Interfering with Platform operations or other users' access</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>8.4 Misrepresentation</h3>
                <ul>
                    <li>Falsely claiming professional qualifications or licences</li>
                    <li>Misrepresenting skills, experience, or capabilities</li>
                    <li>Creating false or misleading reviews</li>
                    <li>Misrepresenting affiliation with the Platform Provider</li>
                    <li>Using Platform branding without authorisation</li>
                </ul>
            </div>
        </div>

        <!-- Section 9: Intellectual Property -->
        <div class="pt-section" id="ip">
            <div class="section-header">
                <div class="section-number">9</div>
                <h2>Intellectual Property</h2>
            </div>

            <div class="pt-subsection">
                <h3>9.1 Platform Provider IP</h3>
                <p>The Platform Provider owns or licences all intellectual property in:</p>
                <ul>
                    <li>The Platform software, code, and architecture</li>
                    <li>Platform branding, logos, and trademarks (including "Timebank Global")</li>
                    <li>Proprietary algorithms (including EdgeRank, MatchRank, CommunityRank)</li>
                    <li>Platform documentation and materials</li>
                    <li>The structure and organisation of the Platform</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>9.2 Tenant Operator IP</h3>
                <p>Tenant Operators retain ownership of:</p>
                <ul>
                    <li>Their own branding, logos, and trademarks</li>
                    <li>Custom content they create for their timebank</li>
                    <li>Their own policies and documentation</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>9.3 Member Content</h3>
                <p>Members retain ownership of content they create but grant:</p>
                <ul>
                    <li>Their Tenant Operator a licence to display and use content within the timebank</li>
                    <li>The Platform Provider a licence to host, display, and process content as necessary to provide Platform services</li>
                    <li>If federation is enabled, partner Tenant Operators a licence to display federated content</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>9.4 Restrictions</h3>
                <ul>
                    <li>No right to copy, modify, or create derivative works from Platform software</li>
                    <li>No right to sublicence Platform access to third parties</li>
                    <li>No right to use Platform Provider trademarks without written permission</li>
                    <li>No right to remove or alter copyright notices or attributions</li>
                </ul>
            </div>
        </div>

        <!-- Section 10: Data Protection -->
        <div class="pt-section" id="data-protection">
            <div class="section-header">
                <div class="section-number">10</div>
                <h2>Data Protection</h2>
            </div>

            <div class="pt-subsection">
                <h3>10.1 Data Controller Roles</h3>
                <ul>
                    <li><strong>Platform Provider:</strong> Data Controller for Platform operations and Tenant Operator data</li>
                    <li><strong>Tenant Operators:</strong> Data Controllers for their Member data</li>
                    <li><strong>Joint Controllers:</strong> Platform Provider and Tenant Operators are joint controllers for certain shared processing activities</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>10.2 Data Processing Agreement</h3>
                <p>The Platform Provider acts as a Data Processor on behalf of Tenant Operators for certain processing activities. A separate Data Processing Agreement governs this relationship and addresses:</p>
                <ul>
                    <li>Categories of personal data processed</li>
                    <li>Processing purposes and instructions</li>
                    <li>Security measures and safeguards</li>
                    <li>Sub-processor engagement</li>
                    <li>Data subject rights assistance</li>
                    <li>Data breach notification procedures</li>
                    <li>Data return and deletion upon termination</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>10.3 International Data Transfers</h3>
                <p>The Platform Provider:</p>
                <ul>
                    <li>Primarily stores data within the European Economic Area (EEA)</li>
                    <li>Uses EU-approved transfer mechanisms for any transfers outside the EEA</li>
                    <li>Maintains records of all sub-processors and their locations</li>
                    <li>Will notify Tenant Operators of material changes to sub-processors</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>10.4 Privacy Policies</h3>
                <p>Data collection and use is further described in:</p>
                <ul>
                    <li>The Platform Privacy Policy (governing Platform Provider processing)</li>
                    <li>Each Tenant Operator's Privacy Policy (governing their Member data processing)</li>
                </ul>
            </div>
        </div>

        <!-- Section 11: Limitation of Liability -->
        <div class="pt-section" id="liability">
            <div class="section-header">
                <div class="section-number">11</div>
                <h2>Limitation of Liability</h2>
            </div>

            <div class="pt-subsection">
                <h3>11.1 Platform Provider Liability Exclusions</h3>
                <p>To the maximum extent permitted by applicable law, the Platform Provider shall not be liable for:</p>
                <ul>
                    <li>Acts or omissions of Tenant Operators</li>
                    <li>Acts or omissions of Members</li>
                    <li>The quality, safety, legality, or suitability of any Services exchanged</li>
                    <li>Disputes between Members or between Members and Tenant Operators</li>
                    <li>Loss or theft of Time Credits</li>
                    <li>Personal injury, property damage, or other harm arising from service exchanges</li>
                    <li>Tenant Operator compliance failures</li>
                    <li>Third-party services or content</li>
                    <li>Service interruptions, data loss, or security breaches beyond our reasonable control</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>11.2 Disclaimer of Warranties</h3>
                <p>The Platform is provided <strong>"as is"</strong> and <strong>"as available"</strong> without warranties of any kind, whether express or implied, including but not limited to:</p>
                <ul>
                    <li>Implied warranties of merchantability</li>
                    <li>Fitness for a particular purpose</li>
                    <li>Non-infringement</li>
                    <li>Accuracy, reliability, or completeness of Platform content</li>
                    <li>Uninterrupted or error-free operation</li>
                    <li>Security from unauthorised access</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>11.3 Liability Cap</h3>
                <p>Where liability cannot be excluded, the Platform Provider's total aggregate liability to any Tenant Operator or Member shall not exceed:</p>
                <ul>
                    <li><strong>For Tenant Operators:</strong> The fees paid by that Tenant Operator in the 12 months preceding the claim (or €100 if no fees were paid)</li>
                    <li><strong>For Members:</strong> €100</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>11.4 Exclusion of Consequential Damages</h3>
                <p>The Platform Provider shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to:</p>
                <ul>
                    <li>Loss of profits, revenue, or business</li>
                    <li>Loss of data or goodwill</li>
                    <li>Cost of substitute services</li>
                    <li>Any damages arising from service exchanges</li>
                </ul>
            </div>

            <div class="pt-important info">
                <span class="important-icon"><i class="fa-solid fa-balance-scale"></i></span>
                <div class="important-content">
                    <h4>Consumer Rights</h4>
                    <p>Nothing in these Platform Terms excludes or limits liability that cannot be excluded or limited under applicable law, including liability for death or personal injury caused by negligence, or for fraud or fraudulent misrepresentation.</p>
                </div>
            </div>
        </div>

        <!-- Section 12: Indemnification -->
        <div class="pt-section" id="indemnification">
            <div class="section-header">
                <div class="section-number">12</div>
                <h2>Indemnification</h2>
            </div>

            <div class="pt-important danger">
                <span class="important-icon"><i class="fa-solid fa-shield"></i></span>
                <div class="important-content">
                    <h4>100% INDEMNIFICATION REQUIRED</h4>
                    <p>Tenant Operators and Members must fully indemnify the Platform Provider against ALL claims arising from their use of the Platform or the operation of their timebanks. The Platform Provider accepts NO liability for any timebank's operations, members, or service exchanges.</p>
                </div>
            </div>

            <div class="pt-subsection">
                <h3>12.1 Tenant Operator Full Indemnification</h3>
                <p><strong>To the fullest extent permitted by applicable law</strong>, each Tenant Operator agrees to <strong>indemnify, defend, and hold completely harmless</strong> the Platform Provider, hOUR Timebank CLG, and its officers, directors, employees, agents, successors, and assigns (collectively, the "Indemnified Parties") from and against <strong>any and all claims, demands, actions, suits, proceedings, losses, damages, liabilities, settlements, judgments, fines, penalties, costs, and expenses</strong> (including reasonable attorneys' fees, court costs, and expert witness fees) arising out of or relating to:</p>
                <ul>
                    <li>The Tenant Operator's use of the Platform or operation of their timebank</li>
                    <li>Any Services provided, offered, or exchanged by the Tenant Operator's Members</li>
                    <li>Any act or omission of the Tenant Operator or any of their Members, employees, volunteers, or agents</li>
                    <li>Any injury, death, property damage, or other harm arising from any service exchange facilitated by the Tenant Operator's timebank</li>
                    <li>The Tenant Operator's breach of these Platform Terms or any applicable law</li>
                    <li>Any claim by a Member, former Member, or third party relating to the Tenant Operator's timebank</li>
                    <li>Disputes between Members of the Tenant Operator's timebank or with Members of other timebanks</li>
                    <li>The Tenant Operator's failure to fulfil their responsibilities under Section 4</li>
                    <li>Any allegation that the Tenant Operator violated any third party's rights, including intellectual property rights</li>
                    <li>Any regulatory investigation, inquiry, or enforcement action relating to the Tenant Operator's timebank</li>
                    <li>Tax liabilities or claims arising from the Tenant Operator's activities</li>
                    <li>Any claim that Time Credits constitute money, currency, or taxable income</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>12.2 Member Full Indemnification</h3>
                <p><strong>To the fullest extent permitted by applicable law</strong>, each Member agrees to <strong>indemnify, defend, and hold completely harmless</strong> the Indemnified Parties and their Tenant Operator from and against <strong>any and all claims, demands, actions, suits, proceedings, losses, damages, liabilities, settlements, judgments, fines, penalties, costs, and expenses</strong> (including reasonable attorneys' fees) arising out of or relating to:</p>
                <ul>
                    <li>The Member's use of the Platform or participation in their timebank</li>
                    <li>Any Services provided or received by the Member</li>
                    <li>Any injury, death, property damage, or other harm arising from services the Member provided or received</li>
                    <li>The Member's breach of these Platform Terms, their Tenant's Terms, or any applicable law</li>
                    <li>The Member's content, communications, listings, or profile information</li>
                    <li>Any claim by another Member, Tenant Operator, or third party relating to the Member's conduct</li>
                    <li>Any allegation that the Member misrepresented their skills, qualifications, or identity</li>
                    <li>Any claim arising from the Member's failure to obtain required licences, permits, or insurance</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>12.3 Indemnification Procedure</h3>
                <p>The indemnification obligations above are subject to:</p>
                <ul>
                    <li><strong>Notice:</strong> The Indemnified Party shall promptly notify the indemnifying party of any claim, but failure to provide notice shall not relieve indemnification obligations except to the extent the indemnifying party is materially prejudiced.</li>
                    <li><strong>Control:</strong> The indemnifying party shall have the right to control the defence of any claim at their own expense, with counsel reasonably acceptable to the Indemnified Party.</li>
                    <li><strong>Cooperation:</strong> The Indemnified Party shall cooperate with the defence and may participate at their own expense.</li>
                    <li><strong>Settlement:</strong> No settlement that admits liability or imposes obligations on the Indemnified Party shall be made without written consent.</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>12.4 Release of Claims</h3>
                <p><strong>Tenant Operators and Members hereby release</strong> the Platform Provider and all Indemnified Parties from any and all claims, demands, damages (actual and consequential), losses, and causes of action of every kind and nature, known and unknown, suspected and unsuspected, disclosed and undisclosed, arising out of or in any way connected with:</p>
                <ul>
                    <li>Any dispute with other Members, Tenant Operators, or third parties</li>
                    <li>Any service exchange or transaction facilitated through the Platform</li>
                    <li>Any act or omission of any other user of the Platform</li>
                    <li>Any content posted by other users on the Platform</li>
                </ul>
                <p><strong>If you are a California resident</strong>, you expressly waive California Civil Code Section 1542, which provides: <em>"A general release does not extend to claims that the creditor or releasing party does not know or suspect to exist in his or her favor at the time of executing the release, and that if known by him or her, would have materially affected his or her settlement with the debtor or released party."</em></p>
            </div>

            <div class="pt-subsection">
                <h3>12.5 Survival</h3>
                <p>The indemnification and release obligations in this Section 12 shall <strong>survive termination</strong> of these Platform Terms, closure of any account, or discontinuation of the Platform, and shall remain in full force and effect indefinitely.</p>
            </div>
        </div>

        <!-- Section 13: Termination -->
        <div class="pt-section" id="termination">
            <div class="section-header">
                <div class="section-number">13</div>
                <h2>Termination</h2>
            </div>

            <div class="pt-subsection">
                <h3>13.1 Termination by Platform Provider</h3>
                <p>The Platform Provider may suspend or terminate access:</p>
                <ul>
                    <li><strong>Immediately</strong> for serious violations, security threats, or illegal activity</li>
                    <li><strong>With 30 days' notice</strong> for other breaches, upon failure to cure</li>
                    <li><strong>With 90 days' notice</strong> for convenience (discontinuation of service)</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>13.2 Termination by Tenant Operators</h3>
                <p>Tenant Operators may terminate their tenancy:</p>
                <ul>
                    <li>With 30 days' written notice at any time</li>
                    <li>Subject to data export and transition provisions in the Tenant Agreement</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>13.3 Effect of Termination</h3>
                <p>Upon termination:</p>
                <ul>
                    <li>Access to the Platform will be disabled</li>
                    <li>Time Credits will be forfeited (cannot be converted to cash)</li>
                    <li>Tenant Operators may request data export within 30 days</li>
                    <li>Personal data will be retained per the Privacy Policy and retention schedule</li>
                    <li>Provisions that by their nature should survive will survive (including Sections 11, 12, and 15)</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>13.4 Member Account Closure</h3>
                <p>Members may close their accounts by:</p>
                <ul>
                    <li>Using the account deletion feature in settings</li>
                    <li>Contacting their Tenant Operator</li>
                </ul>
                <p>Account closure is subject to the Tenant Operator's policies and applicable data retention requirements.</p>
            </div>
        </div>

        <!-- Section 14: Modifications -->
        <div class="pt-section" id="modifications">
            <div class="section-header">
                <div class="section-number">14</div>
                <h2>Modifications to Terms</h2>
            </div>

            <div class="pt-subsection">
                <h3>14.1 Right to Modify</h3>
                <p>The Platform Provider reserves the right to modify these Platform Terms at any time. Modifications may be made to:</p>
                <ul>
                    <li>Reflect changes in law or regulatory requirements</li>
                    <li>Address security or operational needs</li>
                    <li>Improve clarity or correct errors</li>
                    <li>Add new features or services</li>
                    <li>Remove discontinued features or services</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>14.2 Notice of Changes</h3>
                <ul>
                    <li><strong>Material changes:</strong> 30 days' advance notice via email to Tenant Operators</li>
                    <li><strong>Minor changes:</strong> Posted on the Platform with updated effective date</li>
                    <li><strong>Emergency changes:</strong> May be effective immediately if required for security or legal compliance</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>14.3 Acceptance of Changes</h3>
                <p>Continued use of the Platform after the effective date of changes constitutes acceptance. Tenant Operators who do not accept material changes may terminate their tenancy within the notice period.</p>
            </div>
        </div>

        <!-- Section 15: Governing Law & Global Jurisdiction -->
        <div class="pt-section" id="governing-law">
            <div class="section-header">
                <div class="section-number">15</div>
                <h2>Governing Law, Jurisdiction & Contracting Entities</h2>
            </div>

            <div class="pt-important info">
                <span class="important-icon"><i class="fa-solid fa-globe"></i></span>
                <div class="important-content">
                    <h4>Global Platform Structure</h4>
                    <p>Timebank Global operates worldwide. The entity you contract with, the governing law, and where disputes are resolved depend on your country of residence. Please see Schedule 1 below for details specific to your region.</p>
                </div>
            </div>

            <div class="pt-subsection">
                <h3>15.1 Contracting Entities by Region</h3>
                <p>When used in these Platform Terms, "Timebank Global," "Platform Provider," "we," "us," or "our" refers to the entity set out in <strong>Schedule 1</strong> based on your country of residence or place of establishment.</p>
                <p>Currently, all services worldwide are provided by <strong>hOUR Timebank CLG T/A Timebank Ireland</strong>, registered in Ireland. As the Platform grows, regional entities may be established, and this section will be updated accordingly.</p>
            </div>

            <div class="pt-subsection">
                <h3>15.2 Governing Law by Region</h3>
                <p>The laws that govern these Platform Terms depend on where you reside:</p>
                <ul>
                    <li><strong>European Economic Area (EEA), United Kingdom, or Switzerland:</strong> These Terms are governed by the laws of <strong>Ireland</strong>.</li>
                    <li><strong>United States or Canada:</strong> These Terms are governed by the laws of the <strong>State of Delaware, USA</strong>, without regard to conflict of law principles. See Section 15.5 for US-specific provisions.</li>
                    <li><strong>Australia or New Zealand:</strong> These Terms are governed by the laws of <strong>Ireland</strong>, subject to mandatory consumer protection laws of your country that cannot be excluded.</li>
                    <li><strong>Brazil:</strong> These Terms are governed by the laws of <strong>Brazil</strong>. See Section 15.7 for Brazil-specific provisions.</li>
                    <li><strong>All other countries:</strong> These Terms are governed by the laws of <strong>Ireland</strong>.</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>15.3 Dispute Resolution (General)</h3>
                <p>Unless otherwise specified for your region, disputes shall be resolved as follows:</p>
                <ol>
                    <li><strong>Informal Resolution:</strong> The parties shall first attempt to resolve the dispute informally through good-faith negotiation for a period of 30 days.</li>
                    <li><strong>Mediation:</strong> If informal resolution fails, the parties agree to attempt mediation before initiating legal proceedings.</li>
                    <li><strong>Jurisdiction:</strong> Subject to regional provisions below, legal proceedings shall be brought in the courts of Ireland, and the parties consent to the jurisdiction of such courts.</li>
                </ol>
            </div>

            <div class="pt-subsection">
                <h3>15.4 European Economic Area, UK & Switzerland</h3>
                <p>If you reside in the EEA, United Kingdom, or Switzerland:</p>
                <ul>
                    <li><strong>Consumer Rights Preserved:</strong> If you are acting as a consumer, nothing in these Terms affects your statutory consumer rights under mandatory laws of your country of residence, including the right to bring proceedings in the courts of your country.</li>
                    <li><strong>Online Dispute Resolution:</strong> The European Commission provides an online dispute resolution platform at <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr</a>. We are not obliged and do not commit to using this platform.</li>
                    <li><strong>UK Post-Brexit:</strong> For UK residents, these Terms are governed by the laws of Ireland. However, UK consumer protection laws that cannot be excluded by contract will continue to apply.</li>
                    <li><strong>No Mandatory Arbitration:</strong> The arbitration provisions in Section 15.5 do not apply to you.</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>15.5 United States — Additional Terms</h3>
                <p><strong>PLEASE READ THIS SECTION CAREFULLY. IT AFFECTS YOUR LEGAL RIGHTS, INCLUDING YOUR RIGHT TO FILE A LAWSUIT IN COURT.</strong></p>

                <p>If you reside in the United States, the following additional terms apply:</p>

                <h4>15.5.1 Governing Law and Jurisdiction</h4>
                <p>These Terms are governed by the laws of the State of Delaware, USA, without regard to conflict of law principles. For any claim not subject to arbitration, you agree to submit to the personal jurisdiction of the state and federal courts located in Wilmington, Delaware.</p>

                <h4>15.5.2 Agreement to Arbitrate</h4>
                <p>You and the Platform Provider agree that any dispute, claim, or controversy arising out of or relating to these Terms or the Platform (collectively, "Disputes") will be resolved by <strong>binding individual arbitration</strong> rather than in court, except that:</p>
                <ul>
                    <li>Either party may bring an individual action in small claims court if the claim qualifies;</li>
                    <li>Either party may seek injunctive relief in court for intellectual property infringement or misuse.</li>
                </ul>
                <p><strong>There is no judge or jury in arbitration.</strong> Arbitration procedures are simpler and more limited than court proceedings. The arbitrator's decision is binding and may be entered as a judgment in any court of competent jurisdiction.</p>

                <h4>15.5.3 Arbitration Rules and Procedures</h4>
                <p>The arbitration will be administered by the American Arbitration Association ("AAA") under its Consumer Arbitration Rules, as modified by this agreement. The AAA Rules are available at <a href="https://www.adr.org" target="_blank" rel="noopener">www.adr.org</a>.</p>
                <ul>
                    <li>The arbitration will be conducted in English.</li>
                    <li>The arbitration will take place in your county of residence or, at your election, by telephone or video conference.</li>
                    <li>For claims under $10,000, you may choose whether arbitration proceeds in person, by telephone, or based solely on written submissions.</li>
                    <li>The arbitrator may award the same damages and relief as a court (including injunctive and declaratory relief or statutory damages).</li>
                </ul>

                <h4>15.5.4 Class Action and Jury Trial Waiver</h4>
                <p><strong>YOU AND THE PLATFORM PROVIDER AGREE THAT EACH MAY BRING CLAIMS AGAINST THE OTHER ONLY IN YOUR OR ITS INDIVIDUAL CAPACITY, AND NOT AS A PLAINTIFF OR CLASS MEMBER IN ANY PURPORTED CLASS, COLLECTIVE, OR REPRESENTATIVE PROCEEDING.</strong></p>
                <p>Unless both you and we agree otherwise, the arbitrator may not consolidate more than one person's claims, and may not otherwise preside over any form of representative or class proceeding.</p>
                <p><strong>YOU ACKNOWLEDGE AND AGREE THAT YOU AND THE PLATFORM PROVIDER ARE EACH WAIVING THE RIGHT TO A TRIAL BY JURY.</strong></p>

                <h4>15.5.5 30-Day Right to Opt Out of Arbitration</h4>
                <p>You have the right to opt out of the arbitration and class action waiver provisions above by sending written notice of your decision to opt out to: <strong>jasper@hour-timebank.ie</strong> with the subject line "Arbitration Opt-Out" within 30 days of first accepting these Terms. Your notice must include your name, address, email address, and username. If you opt out, you and we will not be bound by the arbitration provisions, and disputes will be resolved in court.</p>

                <h4>15.5.6 DMCA Notice</h4>
                <p>If you believe that content on the Platform infringes your copyright, please send a notice complying with the Digital Millennium Copyright Act (17 U.S.C. § 512) to our designated agent at: <strong>jasper@hour-timebank.ie</strong>.</p>

                <h4>15.5.7 California Residents</h4>
                <p>If you are a California resident, you waive California Civil Code Section 1542, which says: "A general release does not extend to claims that the creditor or releasing party does not know or suspect to exist in his or her favor at the time of executing the release, and that if known by him or her, would have materially affected his or her settlement with the debtor or released party."</p>
                <p>Under California Civil Code Section 1789.3, California users are entitled to the following specific consumer rights notice: The Complaint Assistance Unit of the Division of Consumer Services of the California Department of Consumer Affairs may be contacted in writing at 1625 North Market Blvd., Suite N 112, Sacramento, CA 95834, or by telephone at (916) 445-1254 or (800) 952-5210.</p>
            </div>

            <div class="pt-subsection">
                <h3>15.6 Australia & New Zealand</h3>
                <p>If you reside in Australia or New Zealand:</p>
                <ul>
                    <li><strong>Consumer Guarantees:</strong> Nothing in these Terms excludes, restricts, or modifies any consumer guarantee, right, or remedy conferred on you by the Australian Consumer Law (Schedule 2 of the Competition and Consumer Act 2010) or the New Zealand Consumer Guarantees Act 1993 that cannot be excluded.</li>
                    <li><strong>Limitation of Liability:</strong> To the extent our liability cannot be excluded, our total liability to you is limited to AUD/NZD $100 or the resupply of the services, at our election.</li>
                    <li><strong>Jurisdiction:</strong> Subject to your statutory rights, disputes shall be resolved in the courts of Ireland, or at your election, the courts of your state or territory of residence.</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>15.7 Brazil</h3>
                <p>If you reside in Brazil:</p>
                <ul>
                    <li><strong>Governing Law:</strong> These Terms are governed by the laws of Brazil, including the Marco Civil da Internet (Law No. 12.965/2014) and the Lei Geral de Proteção de Dados (LGPD, Law No. 13.709/2018).</li>
                    <li><strong>Jurisdiction:</strong> Legal proceedings may only be brought in the courts of Brazil, in the jurisdiction of your domicile.</li>
                    <li><strong>Consumer Rights:</strong> Your consumer rights under the Brazilian Consumer Defense Code (Law No. 8.078/1990) are preserved and cannot be waived.</li>
                    <li><strong>No Arbitration:</strong> The arbitration provisions in Section 15.5 do not apply to you.</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>15.8 Change of Residence</h3>
                <p>If you change your country of residence or place of establishment:</p>
                <ul>
                    <li>The contracting entity, governing law, and jurisdiction provisions that apply to your new country of residence will take effect from the date of your move;</li>
                    <li>You agree to update your account information to reflect your new country of residence;</li>
                    <li>Disputes arising before your change of residence will be governed by the provisions that applied at the time the dispute arose.</li>
                </ul>
            </div>
        </div>

        <!-- Schedule 1: Contracting Entities -->
        <div class="pt-section" id="schedule-1">
            <div class="section-header">
                <div class="section-number">S1</div>
                <h2>Schedule 1: Contracting Entities & Governing Law</h2>
            </div>
            <p>The following table shows which entity you contract with based on your country of residence or place of establishment:</p>

            <div style="overflow-x: auto; margin: 1.5rem 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--pt-theme); color: white;">
                            <th style="padding: 1rem; text-align: left; border: 1px solid rgba(255,255,255,0.2);">Your Region</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid rgba(255,255,255,0.2);">Contracting Entity</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid rgba(255,255,255,0.2);">Governing Law</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid rgba(255,255,255,0.2);">Dispute Forum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background: var(--htb-bg-secondary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>European Economic Area (EEA)</strong><br><small style="color: var(--htb-text-muted);">Austria, Belgium, Bulgaria, Croatia, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hungary, Iceland, Ireland, Italy, Latvia, Liechtenstein, Lithuania, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Slovakia, Slovenia, Spain, Sweden</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland (or consumer's home country)</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>United Kingdom</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland (or UK courts for consumers)</td>
                        </tr>
                        <tr style="background: var(--htb-bg-secondary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>Switzerland</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland (or Swiss courts for consumers)</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>United States</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Delaware, USA</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Arbitration (AAA) or courts in Delaware</td>
                        </tr>
                        <tr style="background: var(--htb-bg-secondary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>Canada</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Delaware, USA</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Arbitration (AAA) or courts in Delaware</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>Australia</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland + Australian Consumer Law</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland or Australia</td>
                        </tr>
                        <tr style="background: var(--htb-bg-secondary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>New Zealand</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland + NZ Consumer Guarantees</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland or New Zealand</td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>Brazil</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Brazil</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Brazil (consumer's domicile)</td>
                        </tr>
                        <tr style="background: var(--htb-bg-secondary);">
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-main);"><strong>All Other Countries</strong></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">hOUR Timebank CLG<br>(RBN: Timebank Ireland)<br><small>RCN 20162023 — Ireland</small></td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Ireland</td>
                            <td style="padding: 0.75rem; border: 1px solid var(--htb-border); color: var(--htb-text-muted);">Courts of Ireland</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pt-important info">
                <span class="important-icon"><i class="fa-solid fa-building"></i></span>
                <div class="important-content">
                    <h4>Future Regional Entities</h4>
                    <p>As Timebank Global expands, we may establish regional entities (e.g., Timebank Global Inc. in the USA, Timebank Global Pty Ltd in Australia). This Schedule will be updated to reflect any new contracting entities. Users will be notified of changes per Section 14.</p>
                </div>
            </div>

            <div class="pt-subsection">
                <h3>Contact Addresses by Entity</h3>
                <p><strong>hOUR Timebank CLG</strong></p>
                <ul>
                    <li><strong>Registered Business Name:</strong> Timebank Ireland</li>
                    <li><strong>Company Type:</strong> Company Limited by Guarantee (CLG)</li>
                    <li><strong>Registered Charity Number (RCN):</strong> 20162023</li>
                    <li><strong>Registered Address:</strong> 21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
                    <li><strong>Email:</strong> jasper@hour-timebank.ie</li>
                </ul>
            </div>
        </div>

        <!-- Section 16: Contact -->
        <div class="pt-section" id="contact">
            <div class="section-header">
                <div class="section-number">16</div>
                <h2>Contact Information</h2>
            </div>

            <div class="pt-subsection">
                <h3>16.1 Platform Provider Contact</h3>
                <p><strong>Timebank Global</strong><br>
                Operated by hOUR Timebank CLG (RBN: Timebank Ireland)<br>
                Registered Charity Number: 20162023</p>
                <ul>
                    <li><strong>Registered Address:</strong> 21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</li>
                    <li><strong>Email:</strong> jasper@hour-timebank.ie</li>
                </ul>
            </div>

            <div class="pt-subsection">
                <h3>16.2 Your Tenant Operator</h3>
                <p>For questions about your specific timebank community, membership, or disputes with other Members, please contact your Tenant Operator directly. Their contact information should be available in your timebank's settings or "About" page.</p>
            </div>
        </div>

        <!-- Miscellaneous -->
        <div class="pt-section">
            <div class="section-header">
                <div class="section-number">17</div>
                <h2>General Provisions</h2>
            </div>

            <div class="pt-subsection">
                <h3>17.1 Entire Agreement</h3>
                <p>These Platform Terms, together with the Privacy Policy, any applicable Tenant Agreement, and Data Processing Agreement, constitute the entire agreement between you and the Platform Provider regarding the Platform.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.2 Severability</h3>
                <p>If any provision of these Platform Terms is found to be invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.3 Waiver</h3>
                <p>No failure or delay by the Platform Provider in exercising any right shall constitute a waiver of that right.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.4 Assignment</h3>
                <p>You may not assign or transfer your rights under these Platform Terms. The Platform Provider may assign its rights and obligations to a successor in connection with a merger, acquisition, or sale of assets.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.5 No Third-Party Beneficiaries</h3>
                <p>These Platform Terms do not create any third-party beneficiary rights, except that Tenant Operators are intended third-party beneficiaries of Member obligations.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.6 Language</h3>
                <p>These Platform Terms are drafted in English. If translated into other languages, the English version shall prevail in case of any conflict.</p>
            </div>

            <div class="pt-subsection">
                <h3>17.7 Headings</h3>
                <p>Section headings are for convenience only and do not affect the interpretation of these Platform Terms.</p>
            </div>
        </div>

        <!-- Final Acknowledgment -->
        <div class="pt-section highlight">
            <div class="section-header">
                <div class="section-number"><i class="fa-solid fa-check"></i></div>
                <h2>Acknowledgment</h2>
            </div>
            <p>By using the Timebank Global Platform, you acknowledge that you have read, understood, and agree to be bound by these Platform Terms of Service.</p>
            <p>If you are a Tenant Operator, you further acknowledge that you are an independent entity responsible for your own timebank community and compliance with applicable laws.</p>
            <p>If you are a Member, you acknowledge that your primary relationship is with your Tenant Operator, and that the Platform Provider is a technology provider, not a party to your service exchanges.</p>
        </div>

        <!-- Contact CTA -->
        <div class="pt-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Questions?</h2>
            <p>If you have questions about these Platform Terms or need clarification, our team is here to help.</p>
            <a href="<?= $basePath ?>/contact" class="pt-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </div>

        <p style="text-align: center; color: var(--htb-text-muted); font-size: 0.9rem; margin-top: 2rem;">
            &copy; <?= date('Y') ?> hOUR Timebank CLG (RBN: Timebank Ireland) — Registered Charity RCN 20162023. All rights reserved.<br>
            Platform Terms Version <?= $version ?> | Last Updated: <?= $lastUpdated ?>
        </p>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#platform-terms-wrapper .pt-toc a').forEach(link => {
    link.addEventListener('click', function(e) {
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
</script>

<?php require __DIR__ . '/../../../../layouts/modern/footer.php'; ?>
