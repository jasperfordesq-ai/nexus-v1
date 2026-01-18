<?php
/**
 * Platform Privacy Policy - timebank.global
 *
 * This document governs data protection across the platform:
 * - Timebank Global (Platform Provider) - Data Processor
 * - Tenant Operators (Independent timebanks) - Data Controllers
 * - End Users (Members of tenant timebanks) - Data Subjects
 *
 * Theme Color: Indigo (#6366f1)
 */
$pageTitle = 'Platform Privacy Policy';
$hideHero = true;

require __DIR__ . '/../../../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
$lastUpdated = '16 January 2026';
$effectiveDate = '16 January 2026';
$version = '1.0';
?>

<style>
/* ============================================
   PLATFORM PRIVACY POLICY - GLASSMORPHISM
   Theme: Indigo (#6366f1)
   ============================================ */

#platform-privacy-wrapper {
    --pp-theme: #6366f1;
    --pp-theme-rgb: 99, 102, 241;
    --pp-theme-light: #818cf8;
    --pp-theme-dark: #4f46e5;
    --pp-success: #10b981;
    --pp-warning: #f59e0b;
    --pp-danger: #ef4444;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #platform-privacy-wrapper {
        padding-top: 120px;
    }
}

#platform-privacy-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #platform-privacy-wrapper::before {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(129, 140, 248, 0.08) 25%,
        rgba(79, 70, 229, 0.08) 50%,
        rgba(99, 102, 241, 0.08) 75%,
        rgba(129, 140, 248, 0.08) 100%);
    background-size: 400% 400%;
    animation: ppGradientShift 15s ease infinite;
}

[data-theme="dark"] #platform-privacy-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(79, 70, 229, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(129, 140, 248, 0.05) 0%, transparent 70%);
}

@keyframes ppGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

@keyframes ppFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#platform-privacy-wrapper {
    animation: ppFadeInUp 0.4s ease-out;
}

#platform-privacy-wrapper .pp-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#platform-privacy-wrapper .pp-page-header {
    text-align: center;
    margin-bottom: 2rem;
}

#platform-privacy-wrapper .pp-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#platform-privacy-wrapper .pp-page-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--pp-theme) 0%, var(--pp-theme-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

#platform-privacy-wrapper .pp-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0;
    max-width: 700px;
    margin: 0 auto;
}

#platform-privacy-wrapper .pp-page-header .version-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #platform-privacy-wrapper .pp-page-header .version-badge {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pp-theme-dark);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-page-header .version-badge {
    background: rgba(99, 102, 241, 0.2);
    color: var(--pp-theme-light);
}

/* Platform Hierarchy Visual */
#platform-privacy-wrapper .pp-hierarchy {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 2rem 0;
    flex-wrap: wrap;
}

#platform-privacy-wrapper .pp-hierarchy-item {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    text-align: center;
    min-width: 200px;
    position: relative;
}

[data-theme="light"] #platform-privacy-wrapper .pp-hierarchy-item {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-hierarchy-item {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.3);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#platform-privacy-wrapper .pp-hierarchy-item.platform {
    border-color: var(--pp-theme);
    border-width: 2px;
}

#platform-privacy-wrapper .pp-hierarchy-item .role-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--pp-theme);
}

#platform-privacy-wrapper .pp-hierarchy-item h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.25rem 0;
}

#platform-privacy-wrapper .pp-hierarchy-item .role-tag {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    display: inline-block;
}

[data-theme="light"] #platform-privacy-wrapper .pp-hierarchy-item .role-tag {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pp-theme-dark);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-hierarchy-item .role-tag {
    background: rgba(99, 102, 241, 0.2);
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .pp-hierarchy-arrow {
    display: flex;
    align-items: center;
    color: var(--pp-theme);
    font-size: 1.5rem;
}

@media (max-width: 768px) {
    #platform-privacy-wrapper .pp-hierarchy {
        flex-direction: column;
        align-items: center;
    }
    #platform-privacy-wrapper .pp-hierarchy-arrow {
        transform: rotate(90deg);
    }
}

/* Quick Navigation */
#platform-privacy-wrapper .pp-quick-nav {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#platform-privacy-wrapper .pp-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

[data-theme="light"] #platform-privacy-wrapper .pp-nav-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-nav-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#platform-privacy-wrapper .pp-nav-btn:hover {
    transform: translateY(-2px);
    border-color: var(--pp-theme);
}

[data-theme="light"] #platform-privacy-wrapper .pp-nav-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-nav-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
}

/* Section Card */
#platform-privacy-wrapper .pp-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #platform-privacy-wrapper .pp-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#platform-privacy-wrapper .pp-section:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #platform-privacy-wrapper .pp-section:hover {
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-section:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

#platform-privacy-wrapper .pp-section .section-number {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
}

[data-theme="light"] #platform-privacy-wrapper .pp-section .section-number {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pp-theme);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-section .section-number {
    background: rgba(99, 102, 241, 0.2);
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .pp-section .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-right: 50px;
}

#platform-privacy-wrapper .pp-section .section-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

[data-theme="light"] #platform-privacy-wrapper .pp-section .section-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.05) 100%);
    color: var(--pp-theme);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-section .section-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0.1) 100%);
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .pp-section .section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

#platform-privacy-wrapper .pp-section p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.75;
    margin: 0 0 1rem 0;
}

#platform-privacy-wrapper .pp-section p:last-child {
    margin-bottom: 0;
}

#platform-privacy-wrapper .pp-section strong {
    color: var(--htb-text-main);
}

#platform-privacy-wrapper .pp-section ul,
#platform-privacy-wrapper .pp-section ol {
    margin: 1rem 0;
    padding-left: 0;
    list-style: none;
}

#platform-privacy-wrapper .pp-section ul li,
#platform-privacy-wrapper .pp-section ol li {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.75rem;
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#platform-privacy-wrapper .pp-section ul li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--pp-theme);
}

#platform-privacy-wrapper .pp-section ol {
    counter-reset: list-counter;
}

#platform-privacy-wrapper .pp-section ol li::before {
    counter-increment: list-counter;
    content: counter(list-counter) ".";
    position: absolute;
    left: 0;
    font-weight: 700;
    color: var(--pp-theme);
}

/* Important Box */
#platform-privacy-wrapper .pp-important {
    padding: 1.25rem;
    border-radius: 14px;
    margin: 1.5rem 0;
    display: flex;
    gap: 1rem;
}

#platform-privacy-wrapper .pp-important.info {
    border-left: 4px solid var(--pp-theme);
}

[data-theme="light"] #platform-privacy-wrapper .pp-important.info {
    background: rgba(99, 102, 241, 0.08);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-important.info {
    background: rgba(99, 102, 241, 0.15);
}

#platform-privacy-wrapper .pp-important.warning {
    border-left: 4px solid var(--pp-warning);
}

[data-theme="light"] #platform-privacy-wrapper .pp-important.warning {
    background: rgba(245, 158, 11, 0.08);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-important.warning {
    background: rgba(245, 158, 11, 0.15);
}

#platform-privacy-wrapper .pp-important.danger {
    border-left: 4px solid var(--pp-danger);
}

[data-theme="light"] #platform-privacy-wrapper .pp-important.danger {
    background: rgba(239, 68, 68, 0.08);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-important.danger {
    background: rgba(239, 68, 68, 0.15);
}

#platform-privacy-wrapper .pp-important.success {
    border-left: 4px solid var(--pp-success);
}

[data-theme="light"] #platform-privacy-wrapper .pp-important.success {
    background: rgba(16, 185, 129, 0.08);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-important.success {
    background: rgba(16, 185, 129, 0.15);
}

#platform-privacy-wrapper .pp-important .important-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

#platform-privacy-wrapper .pp-important.info .important-icon { color: var(--pp-theme); }
#platform-privacy-wrapper .pp-important.warning .important-icon { color: var(--pp-warning); }
#platform-privacy-wrapper .pp-important.danger .important-icon { color: var(--pp-danger); }
#platform-privacy-wrapper .pp-important.success .important-icon { color: var(--pp-success); }

#platform-privacy-wrapper .pp-important .important-content h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#platform-privacy-wrapper .pp-important .important-content p {
    font-size: 0.95rem;
    margin: 0;
}

/* Data Table */
#platform-privacy-wrapper .pp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 1.5rem 0;
    border-radius: 12px;
    overflow: hidden;
    font-size: 0.95rem;
}

[data-theme="light"] #platform-privacy-wrapper .pp-table {
    border: 1px solid rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-table {
    border: 1px solid rgba(99, 102, 241, 0.2);
}

#platform-privacy-wrapper .pp-table th,
#platform-privacy-wrapper .pp-table td {
    padding: 1rem;
    text-align: left;
    vertical-align: top;
}

#platform-privacy-wrapper .pp-table th {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

[data-theme="light"] #platform-privacy-wrapper .pp-table th {
    background: rgba(99, 102, 241, 0.1);
    color: var(--pp-theme-dark);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-table th {
    background: rgba(99, 102, 241, 0.2);
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .pp-table td {
    color: var(--htb-text-muted);
}

[data-theme="light"] #platform-privacy-wrapper .pp-table tr:nth-child(even) td {
    background: rgba(99, 102, 241, 0.03);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-table tr:nth-child(even) td {
    background: rgba(99, 102, 241, 0.05);
}

#platform-privacy-wrapper .pp-table td strong {
    color: var(--htb-text-main);
}

/* Definition List */
#platform-privacy-wrapper .pp-section dl {
    margin: 1rem 0;
}

#platform-privacy-wrapper .pp-section dt {
    font-weight: 700;
    color: var(--htb-text-main);
    margin-top: 1rem;
    margin-bottom: 0.25rem;
}

#platform-privacy-wrapper .pp-section dt:first-child {
    margin-top: 0;
}

#platform-privacy-wrapper .pp-section dd {
    color: var(--htb-text-muted);
    margin-left: 0;
    padding-left: 1rem;
    border-left: 2px solid rgba(99, 102, 241, 0.3);
    line-height: 1.6;
}

/* Rights Grid */
#platform-privacy-wrapper .pp-rights-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

@media (max-width: 768px) {
    #platform-privacy-wrapper .pp-rights-grid {
        grid-template-columns: 1fr;
    }
}

#platform-privacy-wrapper .pp-right-card {
    padding: 1.25rem;
    border-radius: 14px;
    transition: all 0.3s ease;
}

[data-theme="light"] #platform-privacy-wrapper .pp-right-card {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-right-card {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

#platform-privacy-wrapper .pp-right-card:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #platform-privacy-wrapper .pp-right-card:hover {
    background: rgba(99, 102, 241, 0.08);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-right-card:hover {
    background: rgba(99, 102, 241, 0.15);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#platform-privacy-wrapper .pp-right-card .right-icon {
    font-size: 1.5rem;
    margin-bottom: 0.75rem;
    display: block;
    color: var(--pp-theme);
}

#platform-privacy-wrapper .pp-right-card h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#platform-privacy-wrapper .pp-right-card p {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    margin: 0;
    line-height: 1.5;
}

/* Back Link */
#platform-privacy-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="light"] #platform-privacy-wrapper .back-link {
    color: var(--pp-theme-dark);
}

[data-theme="dark"] #platform-privacy-wrapper .back-link {
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Contact CTA */
#platform-privacy-wrapper .pp-cta {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #platform-privacy-wrapper .pp-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

#platform-privacy-wrapper .pp-cta h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#platform-privacy-wrapper .pp-cta p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#platform-privacy-wrapper .pp-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--pp-theme) 0%, var(--pp-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
    margin: 0.5rem;
}

#platform-privacy-wrapper .pp-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
}

#platform-privacy-wrapper .pp-cta-btn.secondary {
    background: transparent;
    border: 2px solid var(--pp-theme);
    box-shadow: none;
}

[data-theme="light"] #platform-privacy-wrapper .pp-cta-btn.secondary {
    color: var(--pp-theme-dark);
}

[data-theme="dark"] #platform-privacy-wrapper .pp-cta-btn.secondary {
    color: var(--pp-theme-light);
}

#platform-privacy-wrapper .pp-cta-btn.secondary:hover {
    background: rgba(99, 102, 241, 0.1);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
}

/* Responsive */
@media (max-width: 768px) {
    #platform-privacy-wrapper {
        padding: 120px 1rem 3rem;
    }

    #platform-privacy-wrapper .pp-page-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #platform-privacy-wrapper .pp-page-header .header-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }

    #platform-privacy-wrapper .pp-page-header p {
        font-size: 1rem;
    }

    #platform-privacy-wrapper .pp-quick-nav {
        gap: 0.4rem;
    }

    #platform-privacy-wrapper .pp-nav-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }

    #platform-privacy-wrapper .pp-section {
        padding: 1.5rem;
    }

    #platform-privacy-wrapper .pp-section .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        padding-right: 0;
    }

    #platform-privacy-wrapper .pp-section .section-number {
        position: static;
        margin-bottom: 0.5rem;
    }

    #platform-privacy-wrapper .pp-section .section-icon {
        width: 42px;
        height: 42px;
        font-size: 1.15rem;
    }

    #platform-privacy-wrapper .pp-section .section-header h2 {
        font-size: 1.2rem;
    }

    #platform-privacy-wrapper .pp-table th,
    #platform-privacy-wrapper .pp-table td {
        padding: 0.75rem;
        font-size: 0.85rem;
    }

    #platform-privacy-wrapper .pp-cta {
        padding: 2rem 1.5rem;
    }

    #platform-privacy-wrapper .pp-cta h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Touch Targets */
#platform-privacy-wrapper .pp-nav-btn,
#platform-privacy-wrapper .pp-cta-btn {
    min-height: 44px;
}

@media (max-width: 768px) {
    #platform-privacy-wrapper .pp-nav-btn,
    #platform-privacy-wrapper .pp-cta-btn {
        min-height: 48px;
    }
}

/* Focus Visible */
#platform-privacy-wrapper .pp-nav-btn:focus-visible,
#platform-privacy-wrapper .pp-cta-btn:focus-visible,
#platform-privacy-wrapper .back-link:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.5);
    outline-offset: 2px;
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #platform-privacy-wrapper .pp-section,
    [data-theme="light"] #platform-privacy-wrapper .pp-cta {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #platform-privacy-wrapper .pp-section,
    [data-theme="dark"] #platform-privacy-wrapper .pp-cta {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="platform-privacy-wrapper">
    <div class="pp-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="pp-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-shield-halved"></i></span>
                Platform Privacy Policy
            </h1>
            <p>How personal data is collected, processed, and protected across the Timebank Global federation</p>
            <span class="version-badge">
                <i class="fa-solid fa-calendar"></i>
                Version <?= $version ?> &bull; Effective <?= $effectiveDate ?>
            </span>
        </div>

        <!-- Platform Hierarchy -->
        <div class="pp-hierarchy">
            <div class="pp-hierarchy-item platform">
                <div class="role-icon"><i class="fa-solid fa-globe"></i></div>
                <h4>Timebank Global</h4>
                <span class="role-tag">Data Processor</span>
            </div>
            <div class="pp-hierarchy-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="pp-hierarchy-item">
                <div class="role-icon"><i class="fa-solid fa-building"></i></div>
                <h4>Tenant Operators</h4>
                <span class="role-tag">Data Controllers</span>
            </div>
            <div class="pp-hierarchy-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="pp-hierarchy-item">
                <div class="role-icon"><i class="fa-solid fa-users"></i></div>
                <h4>Members</h4>
                <span class="role-tag">Data Subjects</span>
            </div>
        </div>

        <!-- Quick Navigation -->
        <div class="pp-quick-nav">
            <a href="#section-1" class="pp-nav-btn"><i class="fa-solid fa-building-columns"></i> Platform Role</a>
            <a href="#section-2" class="pp-nav-btn"><i class="fa-solid fa-database"></i> Data Collection</a>
            <a href="#section-3" class="pp-nav-btn"><i class="fa-solid fa-chart-pie"></i> Data Use</a>
            <a href="#section-4" class="pp-nav-btn"><i class="fa-solid fa-share-nodes"></i> Data Sharing</a>
            <a href="#section-5" class="pp-nav-btn"><i class="fa-solid fa-user-shield"></i> Your Rights</a>
            <a href="#section-6" class="pp-nav-btn"><i class="fa-solid fa-earth-europe"></i> International</a>
            <a href="#section-7" class="pp-nav-btn"><i class="fa-solid fa-cookie-bite"></i> Cookies</a>
        </div>

        <!-- Introduction -->
        <div class="pp-section" id="introduction">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-handshake"></i></div>
                <h2>Introduction</h2>
            </div>

            <p>This Platform Privacy Policy ("Policy") explains how personal data is collected, used, shared, and protected across the Timebank Global platform operated by <strong>hOUR Timebank CLG</strong> (Registered Business Name: Timebank Ireland), a Company Limited by Guarantee registered in Ireland and a Registered Charity (RCN 20162023).</p>

            <div class="pp-important info">
                <span class="important-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="important-content">
                    <h4>Three-Tier Privacy Model</h4>
                    <p>The Timebank Global platform operates a federated model with distinct data protection roles: the <strong>Platform Provider</strong> (Data Processor), <strong>Tenant Operators</strong> (Data Controllers), and <strong>Members</strong> (Data Subjects). Each party has specific responsibilities under applicable data protection laws.</p>
                </div>
            </div>

            <p>This Policy applies to:</p>
            <ul>
                <li>The Timebank Global platform at timebank.global and all related domains</li>
                <li>All Tenant Operators who use the platform to operate their timebanks</li>
                <li>All Members who register with any timebank on the platform</li>
                <li>Visitors to the platform who do not have accounts</li>
            </ul>
        </div>

        <!-- Section 1: Platform Provider Role -->
        <div class="pp-section" id="section-1">
            <span class="section-number">1</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-building-columns"></i></div>
                <h2>Platform Provider Role & Data Processing</h2>
            </div>

            <div class="pp-important danger">
                <span class="important-icon"><i class="fa-solid fa-gavel"></i></span>
                <div class="important-content">
                    <h4>DATA PROCESSOR ONLY</h4>
                    <p><strong>Timebank Global is a DATA PROCESSOR, not a Data Controller.</strong> We provide technology infrastructure that enables independent timebanks to operate. Each Tenant Operator is the Data Controller for their members' personal data and determines the purposes and means of processing.</p>
                </div>
            </div>

            <p>As the Platform Provider, hOUR Timebank CLG:</p>
            <ul>
                <li><strong>Processes data on behalf of Tenant Operators</strong> according to their instructions and applicable Data Processing Agreements</li>
                <li><strong>Does NOT determine</strong> the purposes for which member data is collected or used (that is the Tenant Operator's responsibility)</li>
                <li><strong>Does NOT have direct relationships</strong> with members of tenant timebanks (except Timebank Ireland members)</li>
                <li><strong>Implements technical measures</strong> to secure data across the platform</li>
                <li><strong>Maintains the platform infrastructure</strong> including servers, databases, and software</li>
            </ul>

            <p><strong>Exception:</strong> For <strong>Timebank Ireland</strong> (our own timebank), hOUR Timebank CLG acts as both Platform Provider AND Data Controller. Timebank Ireland members should refer to the Timebank Ireland Privacy Policy for controller-specific information.</p>

            <h3>Data Processing Agreement</h3>
            <p>All Tenant Operators are required to enter into a Data Processing Agreement (DPA) with the Platform Provider that specifies:</p>
            <ul>
                <li>The nature and purpose of processing</li>
                <li>The types of personal data processed</li>
                <li>Categories of data subjects</li>
                <li>Obligations and rights of each party</li>
                <li>Sub-processor authorizations</li>
                <li>Data breach notification procedures</li>
            </ul>
        </div>

        <!-- Section 2: Data We Collect -->
        <div class="pp-section" id="section-2">
            <span class="section-number">2</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-database"></i></div>
                <h2>Data We Collect</h2>
            </div>

            <p>The platform collects and processes the following categories of personal data on behalf of Tenant Operators:</p>

            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Data Category</th>
                        <th>Examples</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Account Data</strong></td>
                        <td>Name, email address, password (hashed), account creation date</td>
                        <td>Account creation and authentication</td>
                    </tr>
                    <tr>
                        <td><strong>Profile Data</strong></td>
                        <td>Bio, profile photo, skills offered, location, phone number (optional)</td>
                        <td>Community participation and member discovery</td>
                    </tr>
                    <tr>
                        <td><strong>Transaction Data</strong></td>
                        <td>Time credit exchanges, service descriptions, dates, counterparties</td>
                        <td>Recording exchanges and maintaining balances</td>
                    </tr>
                    <tr>
                        <td><strong>Communication Data</strong></td>
                        <td>Messages between members, notifications, system emails</td>
                        <td>Facilitating member communication</td>
                    </tr>
                    <tr>
                        <td><strong>Activity Data</strong></td>
                        <td>Login history, page views, features used, preferences</td>
                        <td>Security, fraud prevention, and platform improvement</td>
                    </tr>
                    <tr>
                        <td><strong>Technical Data</strong></td>
                        <td>IP address, browser type, device information, operating system</td>
                        <td>Security, troubleshooting, and analytics</td>
                    </tr>
                    <tr>
                        <td><strong>Tenant Operator Data</strong></td>
                        <td>Organization name, administrator contacts, configuration settings</td>
                        <td>Platform administration and support</td>
                    </tr>
                </tbody>
            </table>

            <div class="pp-important warning">
                <span class="important-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div class="important-content">
                    <h4>Special Categories of Data</h4>
                    <p>The platform does NOT intentionally collect special categories of personal data (racial or ethnic origin, political opinions, religious beliefs, health data, etc.). Tenant Operators must not configure their timebanks to collect such data without explicit consent and appropriate safeguards.</p>
                </div>
            </div>

            <h3>Data Collection Methods</h3>
            <ul>
                <li><strong>Direct Collection:</strong> Information you provide when registering, updating your profile, or using platform features</li>
                <li><strong>Automatic Collection:</strong> Technical data collected through cookies, server logs, and similar technologies</li>
                <li><strong>Third-Party Sources:</strong> Limited data from authentication providers (e.g., Google, Facebook) if you choose social login</li>
            </ul>
        </div>

        <!-- Section 3: How Data Is Used -->
        <div class="pp-section" id="section-3">
            <span class="section-number">3</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h2>How Data Is Used</h2>
            </div>

            <p>Personal data processed through the platform is used for the following purposes:</p>

            <h3>By Tenant Operators (Data Controllers)</h3>
            <p>Each Tenant Operator determines how member data is used within their timebank. Common purposes include:</p>
            <ul>
                <li>Managing membership and verifying member identity</li>
                <li>Facilitating time credit exchanges between members</li>
                <li>Communicating with members about timebank activities</li>
                <li>Maintaining accurate transaction records</li>
                <li>Reporting to funders or regulatory bodies (where required)</li>
                <li>Improving their timebank's services</li>
            </ul>

            <h3>By the Platform Provider (Data Processor)</h3>
            <p>The Platform Provider processes data solely to:</p>
            <ul>
                <li>Operate and maintain the platform infrastructure</li>
                <li>Provide technical support to Tenant Operators</li>
                <li>Ensure platform security and prevent fraud</li>
                <li>Comply with legal obligations</li>
                <li>Generate anonymized, aggregated statistics (no individual identification)</li>
                <li>Improve platform features and performance</li>
            </ul>

            <h3>Legal Bases for Processing (GDPR)</h3>
            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Legal Basis</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Account creation and service delivery</td>
                        <td><strong>Contract:</strong> Necessary for the performance of the membership agreement</td>
                    </tr>
                    <tr>
                        <td>Security and fraud prevention</td>
                        <td><strong>Legitimate Interest:</strong> Protecting members and the platform</td>
                    </tr>
                    <tr>
                        <td>Legal compliance</td>
                        <td><strong>Legal Obligation:</strong> Required by law</td>
                    </tr>
                    <tr>
                        <td>Marketing communications</td>
                        <td><strong>Consent:</strong> Only with explicit opt-in</td>
                    </tr>
                    <tr>
                        <td>Platform improvement and analytics</td>
                        <td><strong>Legitimate Interest:</strong> Improving services (anonymized data)</td>
                    </tr>
                    <tr>
                        <td>Federation features (cross-tenant visibility)</td>
                        <td><strong>Consent:</strong> Explicit opt-in required</td>
                    </tr>
                </tbody>
            </table>

            <div class="pp-important success">
                <span class="important-icon"><i class="fa-solid fa-circle-check"></i></span>
                <div class="important-content">
                    <h4>No Sale of Personal Data</h4>
                    <p>Neither the Platform Provider nor Tenant Operators may sell, rent, or lease member personal data to third parties. This is strictly prohibited under the Platform Terms of Service.</p>
                </div>
            </div>
        </div>

        <!-- Section 4: Data Sharing -->
        <div class="pp-section" id="section-4">
            <span class="section-number">4</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <h2>Data Sharing & Disclosure</h2>
            </div>

            <h3>Within the Platform</h3>
            <ul>
                <li><strong>Other Members:</strong> Your profile information is visible to verified members of your timebank to facilitate exchanges</li>
                <li><strong>Federation (Opt-In):</strong> If you enable federation features, limited profile data may be visible to members of other timebanks in the federation</li>
                <li><strong>Tenant Administrators:</strong> Timebank administrators can view member data necessary for managing the community</li>
            </ul>

            <h3>Third-Party Service Providers</h3>
            <p>The Platform Provider may share data with trusted service providers who assist in operating the platform:</p>
            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Provider Type</th>
                        <th>Purpose</th>
                        <th>Data Shared</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cloud Hosting</td>
                        <td>Platform infrastructure</td>
                        <td>All data (encrypted)</td>
                    </tr>
                    <tr>
                        <td>Email Services</td>
                        <td>Transactional emails</td>
                        <td>Email addresses, names</td>
                    </tr>
                    <tr>
                        <td>Analytics</td>
                        <td>Usage statistics</td>
                        <td>Anonymized/aggregated data only</td>
                    </tr>
                    <tr>
                        <td>Security Services</td>
                        <td>Fraud prevention, DDoS protection</td>
                        <td>IP addresses, technical data</td>
                    </tr>
                    <tr>
                        <td>Payment Processors</td>
                        <td>Subscription payments (Tenant Operators only)</td>
                        <td>Billing information</td>
                    </tr>
                </tbody>
            </table>

            <p>All third-party providers are bound by Data Processing Agreements and are prohibited from using data for their own purposes.</p>

            <h3>Legal Disclosures</h3>
            <p>Data may be disclosed when required by law, including:</p>
            <ul>
                <li>Court orders, subpoenas, or legal process</li>
                <li>Requests from law enforcement with proper authority</li>
                <li>To protect the rights, property, or safety of the Platform, Tenant Operators, or Members</li>
                <li>To comply with regulatory requirements</li>
            </ul>

            <div class="pp-important info">
                <span class="important-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="important-content">
                    <h4>Notification of Legal Requests</h4>
                    <p>Where legally permitted, we will notify the affected Tenant Operator before disclosing their members' data in response to legal requests, giving them the opportunity to challenge the request.</p>
                </div>
            </div>
        </div>

        <!-- Section 5: Your Rights -->
        <div class="pp-section" id="section-5">
            <span class="section-number">5</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-user-shield"></i></div>
                <h2>Your Privacy Rights</h2>
            </div>

            <p>Depending on your location, you may have the following rights regarding your personal data:</p>

            <div class="pp-rights-grid">
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-eye"></i></span>
                    <h4>Right to Access</h4>
                    <p>Request a copy of all personal data we hold about you</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-pen"></i></span>
                    <h4>Right to Rectification</h4>
                    <p>Correct any inaccurate or incomplete information</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-trash"></i></span>
                    <h4>Right to Erasure</h4>
                    <p>Request deletion of your data ("right to be forgotten")</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-hand"></i></span>
                    <h4>Right to Restrict Processing</h4>
                    <p>Limit how your data is used in certain circumstances</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-download"></i></span>
                    <h4>Right to Portability</h4>
                    <p>Receive your data in a machine-readable format</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-ban"></i></span>
                    <h4>Right to Object</h4>
                    <p>Object to processing based on legitimate interests</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-robot"></i></span>
                    <h4>Automated Decision-Making</h4>
                    <p>Not be subject to solely automated decisions with legal effects</p>
                </div>
                <div class="pp-right-card">
                    <span class="right-icon"><i class="fa-solid fa-rotate-left"></i></span>
                    <h4>Right to Withdraw Consent</h4>
                    <p>Withdraw consent at any time where processing is consent-based</p>
                </div>
            </div>

            <div class="pp-important warning">
                <span class="important-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div class="important-content">
                    <h4>How to Exercise Your Rights</h4>
                    <p><strong>Members:</strong> Contact your Tenant Operator (the timebank you belong to) first, as they are the Data Controller responsible for your data. They will coordinate with the Platform Provider as needed.</p>
                    <p><strong>Tenant Operators:</strong> Contact the Platform Provider directly at <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>
                </div>
            </div>

            <h3>Response Timeframes</h3>
            <ul>
                <li><strong>EU/EEA/UK:</strong> Within 30 days (extendable by 60 days for complex requests)</li>
                <li><strong>California (CCPA):</strong> Within 45 days (extendable by 45 days)</li>
                <li><strong>Brazil (LGPD):</strong> Within 15 days</li>
                <li><strong>Other jurisdictions:</strong> Within 30 days or as required by local law</li>
            </ul>

            <h3>Right to Complain</h3>
            <p>If you are not satisfied with how your data protection request was handled, you have the right to lodge a complaint with a supervisory authority:</p>
            <ul>
                <li><strong>Ireland:</strong> Data Protection Commission (<a href="https://www.dataprotection.ie" target="_blank" rel="noopener">www.dataprotection.ie</a>)</li>
                <li><strong>UK:</strong> Information Commissioner's Office (<a href="https://ico.org.uk" target="_blank" rel="noopener">ico.org.uk</a>)</li>
                <li><strong>EU:</strong> Your local Data Protection Authority</li>
            </ul>
        </div>

        <!-- Section 6: International Data Transfers -->
        <div class="pp-section" id="section-6">
            <span class="section-number">6</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-earth-europe"></i></div>
                <h2>International Data Transfers</h2>
            </div>

            <p>Timebank Global is a global platform, and personal data may be transferred to and processed in countries outside your jurisdiction.</p>

            <h3>Primary Data Location</h3>
            <p>Platform data is primarily stored and processed in the <strong>European Union</strong>, which provides strong data protection under the GDPR.</p>

            <h3>Transfer Safeguards</h3>
            <p>When data is transferred outside the EU/EEA, we ensure appropriate safeguards are in place:</p>

            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Destination</th>
                        <th>Safeguard</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Countries with EU Adequacy Decision</td>
                        <td>Transfer permitted under adequacy finding</td>
                    </tr>
                    <tr>
                        <td>United States</td>
                        <td>EU-US Data Privacy Framework (where applicable) or Standard Contractual Clauses</td>
                    </tr>
                    <tr>
                        <td>Other Countries</td>
                        <td>Standard Contractual Clauses (SCCs) approved by the European Commission</td>
                    </tr>
                    <tr>
                        <td>UK</td>
                        <td>UK GDPR and UK International Data Transfer Agreement (IDTA)</td>
                    </tr>
                </tbody>
            </table>

            <h3>Regional Contracting Entities</h3>
            <p>Depending on your location, you may be contracting with a regional entity of Timebank Global. See our <a href="<?= $basePath ?>/terms#schedule-1">Terms of Service Schedule 1</a> for the full list of contracting entities by region.</p>

            <div class="pp-important info">
                <span class="important-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="important-content">
                    <h4>Copies of Transfer Mechanisms</h4>
                    <p>You may request a copy of the Standard Contractual Clauses or other transfer mechanisms by contacting <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>
                </div>
            </div>
        </div>

        <!-- Section 7: Cookies & Tracking -->
        <div class="pp-section" id="section-7">
            <span class="section-number">7</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-cookie-bite"></i></div>
                <h2>Cookies & Tracking Technologies</h2>
            </div>

            <p>The platform uses cookies and similar technologies to provide functionality, ensure security, and improve user experience.</p>

            <h3>Types of Cookies Used</h3>
            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Cookie Type</th>
                        <th>Purpose</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Essential</strong></td>
                        <td>Authentication, security, session management</td>
                        <td>Session / Up to 30 days</td>
                    </tr>
                    <tr>
                        <td><strong>Functional</strong></td>
                        <td>Preferences (theme, language, settings)</td>
                        <td>Up to 1 year</td>
                    </tr>
                    <tr>
                        <td><strong>Analytics</strong></td>
                        <td>Anonymized usage statistics to improve the platform</td>
                        <td>Up to 2 years</td>
                    </tr>
                </tbody>
            </table>

            <div class="pp-important success">
                <span class="important-icon"><i class="fa-solid fa-circle-check"></i></span>
                <div class="important-content">
                    <h4>No Advertising Cookies</h4>
                    <p>The platform does <strong>NOT</strong> use advertising, marketing, or behavioral tracking cookies. We do not share data with advertising networks or data brokers.</p>
                </div>
            </div>

            <h3>Managing Cookies</h3>
            <p>You can manage cookie preferences through:</p>
            <ul>
                <li><strong>Browser Settings:</strong> Most browsers allow you to block or delete cookies</li>
                <li><strong>Platform Settings:</strong> Logged-in users can adjust privacy preferences in account settings</li>
            </ul>

            <p>Note: Disabling essential cookies may prevent you from using platform features that require authentication.</p>

            <h3>Do Not Track</h3>
            <p>The platform respects "Do Not Track" (DNT) browser signals. When DNT is enabled, we disable non-essential analytics tracking.</p>
        </div>

        <!-- Section 8: Data Security -->
        <div class="pp-section" id="section-8">
            <span class="section-number">8</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-lock"></i></div>
                <h2>Data Security</h2>
            </div>

            <p>We implement comprehensive technical and organizational measures to protect personal data:</p>

            <h3>Technical Measures</h3>
            <ul>
                <li><strong>Encryption in Transit:</strong> All data transmitted over HTTPS/TLS 1.3</li>
                <li><strong>Encryption at Rest:</strong> Database and file storage encrypted using AES-256</li>
                <li><strong>Password Security:</strong> Passwords hashed using bcrypt with salt</li>
                <li><strong>Access Controls:</strong> Role-based access controls (RBAC) and least-privilege principles</li>
                <li><strong>Audit Logging:</strong> Comprehensive logging of access and changes to personal data</li>
                <li><strong>Network Security:</strong> Firewalls, intrusion detection, and DDoS protection</li>
                <li><strong>Regular Updates:</strong> Security patches applied promptly</li>
            </ul>

            <h3>Organizational Measures</h3>
            <ul>
                <li><strong>Staff Training:</strong> Regular data protection and security training</li>
                <li><strong>Confidentiality Agreements:</strong> All staff bound by confidentiality obligations</li>
                <li><strong>Vendor Management:</strong> Due diligence on all third-party providers</li>
                <li><strong>Incident Response:</strong> Documented procedures for security incidents</li>
                <li><strong>Regular Audits:</strong> Periodic security assessments and penetration testing</li>
            </ul>

            <h3>Data Breach Notification</h3>
            <p>In the event of a personal data breach:</p>
            <ol>
                <li>We will notify the relevant supervisory authority within 72 hours (where required)</li>
                <li>We will notify affected Tenant Operators without undue delay</li>
                <li>Tenant Operators are responsible for notifying their affected members</li>
                <li>We will document all breaches and remediation actions taken</li>
            </ol>
        </div>

        <!-- Section 9: Data Retention -->
        <div class="pp-section" id="section-9">
            <span class="section-number">9</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <h2>Data Retention</h2>
            </div>

            <p>Personal data is retained only as long as necessary for the purposes for which it was collected:</p>

            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Data Type</th>
                        <th>Retention Period</th>
                        <th>Basis</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Active member accounts</td>
                        <td>Duration of membership</td>
                        <td>Contract performance</td>
                    </tr>
                    <tr>
                        <td>Deleted member accounts</td>
                        <td>30 days (recoverable), then purged</td>
                        <td>Account recovery</td>
                    </tr>
                    <tr>
                        <td>Transaction records</td>
                        <td>7 years after last activity</td>
                        <td>Legal/audit requirements</td>
                    </tr>
                    <tr>
                        <td>Messages (deleted)</td>
                        <td>30 days</td>
                        <td>User recovery</td>
                    </tr>
                    <tr>
                        <td>Security logs</td>
                        <td>1 year</td>
                        <td>Security investigations</td>
                    </tr>
                    <tr>
                        <td>Backup data</td>
                        <td>90 days rolling</td>
                        <td>Disaster recovery</td>
                    </tr>
                    <tr>
                        <td>Inactive Tenant accounts</td>
                        <td>2 years of inactivity, then archived</td>
                        <td>Service continuity</td>
                    </tr>
                </tbody>
            </table>

            <p>After retention periods expire, data is securely deleted or anonymized so that individuals cannot be identified.</p>
        </div>

        <!-- Section 10: Children's Privacy -->
        <div class="pp-section" id="section-10">
            <span class="section-number">10</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-child"></i></div>
                <h2>Children's Privacy</h2>
            </div>

            <p>The Timebank Global platform is not intended for children under the age of 16 (or the applicable age of digital consent in the relevant jurisdiction).</p>

            <div class="pp-important danger">
                <span class="important-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <div class="important-content">
                    <h4>Age Restrictions</h4>
                    <p>We do not knowingly collect personal data from children under 16. If we become aware that a child under 16 has provided personal data, we will take steps to delete such information. Parents or guardians who believe their child has provided data should contact <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a>.</p>
                </div>
            </div>

            <p>Tenant Operators who wish to allow younger members must:</p>
            <ul>
                <li>Obtain verifiable parental/guardian consent</li>
                <li>Implement appropriate safeguards</li>
                <li>Comply with COPPA (US), GDPR-K (EU), and other applicable child protection laws</li>
                <li>Notify the Platform Provider of such arrangements</li>
            </ul>
        </div>

        <!-- Section 11: Changes to This Policy -->
        <div class="pp-section" id="section-11">
            <span class="section-number">11</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                <h2>Changes to This Policy</h2>
            </div>

            <p>We may update this Privacy Policy from time to time to reflect changes in our practices, technology, legal requirements, or other factors.</p>

            <h3>Notification of Changes</h3>
            <ul>
                <li><strong>Material Changes:</strong> We will notify Tenant Operators at least 30 days before material changes take effect</li>
                <li><strong>Minor Changes:</strong> Updated on the website with a new "Last Updated" date</li>
                <li><strong>Member Notification:</strong> Tenant Operators are responsible for informing their members of relevant changes</li>
            </ul>

            <p>Continued use of the platform after changes become effective constitutes acceptance of the updated Policy.</p>

            <h3>Version History</h3>
            <table class="pp-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Date</th>
                        <th>Summary of Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1.0</td>
                        <td>16 January 2026</td>
                        <td>Initial Platform Privacy Policy</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 12: Contact Us -->
        <div class="pp-section" id="section-12">
            <span class="section-number">12</span>
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-envelope"></i></div>
                <h2>Contact Us</h2>
            </div>

            <h3>Platform Provider (Data Processor)</h3>
            <dl>
                <dt>Organization</dt>
                <dd>hOUR Timebank CLG (RBN: Timebank Ireland)<br>Registered Charity Number: 20162023</dd>

                <dt>Address</dt>
                <dd>21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>

                <dt>Email (All Inquiries)</dt>
                <dd><a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></dd>
            </dl>

            <h3>For Members</h3>
            <p>If you are a member of a timebank on the platform, please contact your timebank directly for privacy inquiries. Your Tenant Operator is the Data Controller responsible for your personal data.</p>

            <h3>EU Representative</h3>
            <p>As hOUR Timebank CLG is established in Ireland (an EU member state), no separate EU representative is required under GDPR Article 27.</p>

            <h3>UK Representative</h3>
            <p>For UK data subjects, inquiries may be directed to: <a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></p>
        </div>

        <!-- Contact CTA -->
        <div class="pp-cta">
            <h2><i class="fa-solid fa-shield-halved"></i> Your Privacy Matters</h2>
            <p>We're committed to protecting your personal data. If you have questions about this policy or want to exercise your privacy rights, we're here to help.</p>
            <div>
                <a href="mailto:jasper@hour-timebank.ie" class="pp-cta-btn">
                    <i class="fa-solid fa-envelope"></i>
                    Contact Privacy Team
                </a>
                <a href="<?= $basePath ?>/terms" class="pp-cta-btn secondary">
                    <i class="fa-solid fa-file-contract"></i>
                    View Terms of Service
                </a>
            </div>
        </div>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#platform-privacy-wrapper .pp-nav-btn').forEach(btn => {
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
document.querySelectorAll('#platform-privacy-wrapper .pp-nav-btn, #platform-privacy-wrapper .pp-cta-btn').forEach(btn => {
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

<?php require __DIR__ . '/../../../../layouts/modern/footer.php'; ?>
