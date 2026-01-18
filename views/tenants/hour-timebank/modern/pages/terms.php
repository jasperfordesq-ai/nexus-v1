<?php
/**
 * Terms of Service - Ireland Timebank
 * Theme Color: Blue (#3b82f6)
 * Tenant: Hour Timebank Ireland
 * Legal Entity: hOUR Timebank CLG (RCN 20162023)
 */
$pageTitle = 'Terms of Service';
$hideHero = true;

require __DIR__ . '/../../../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'hOUR Timebank Ireland';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'hOUR Timebank Ireland';
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
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #terms-glass-wrapper {
        padding-top: 120px;
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

[data-theme="light"] #terms-glass-wrapper .terms-section .section-number,
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

/* Entity Info Box */
#terms-glass-wrapper .entity-info {
    padding: 1.5rem;
    border-radius: 14px;
    margin: 1.5rem 0;
}

[data-theme="light"] #terms-glass-wrapper .entity-info {
    background: rgba(59, 130, 246, 0.05);
    border: 1px solid rgba(59, 130, 246, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .entity-info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

#terms-glass-wrapper .entity-info dl {
    margin: 0;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.5rem 1.5rem;
}

#terms-glass-wrapper .entity-info dt {
    font-weight: 700;
    color: var(--htb-text-main);
    font-size: 0.9rem;
}

#terms-glass-wrapper .entity-info dd {
    margin: 0;
    color: var(--htb-text-muted);
    font-size: 0.95rem;
}

#terms-glass-wrapper .entity-info dd a {
    color: var(--terms-theme);
    text-decoration: none;
}

#terms-glass-wrapper .entity-info dd a:hover {
    text-decoration: underline;
}

/* Partner Org Features */
#terms-glass-wrapper .partner-features {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

#terms-glass-wrapper .partner-feature {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 12px;
}

[data-theme="light"] #terms-glass-wrapper .partner-feature {
    background: rgba(34, 197, 94, 0.08);
    border: 1px solid rgba(34, 197, 94, 0.15);
}

[data-theme="dark"] #terms-glass-wrapper .partner-feature {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.2);
}

#terms-glass-wrapper .partner-feature .feature-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(34, 197, 94, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #22c55e;
    font-size: 1rem;
    flex-shrink: 0;
}

#terms-glass-wrapper .partner-feature .feature-text {
    flex: 1;
}

#terms-glass-wrapper .partner-feature .feature-text strong {
    display: block;
    color: var(--htb-text-main);
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

#terms-glass-wrapper .partner-feature .feature-text span {
    font-size: 0.85rem;
    color: var(--htb-text-muted);
    line-height: 1.4;
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

    #terms-glass-wrapper .prohibited-grid,
    #terms-glass-wrapper .partner-features {
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

    #terms-glass-wrapper .entity-info dl {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }

    #terms-glass-wrapper .entity-info dt {
        margin-top: 0.75rem;
    }

    #terms-glass-wrapper .entity-info dt:first-child {
        margin-top: 0;
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
            <p>Terms and conditions for hOUR Timebank Ireland members and partner organisations</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="terms-quick-nav">
            <a href="#who-we-are" class="terms-nav-btn">
                <i class="fa-solid fa-building"></i> Who We Are
            </a>
            <a href="#safeguarding" class="terms-nav-btn">
                <i class="fa-solid fa-shield-halved"></i> Safeguarding
            </a>
            <a href="#relationship" class="terms-nav-btn">
                <i class="fa-solid fa-handshake-simple"></i> Relationship
            </a>
            <a href="#assumption-of-risk" class="terms-nav-btn">
                <i class="fa-solid fa-person-falling"></i> Risk
            </a>
            <a href="#liability" class="terms-nav-btn">
                <i class="fa-solid fa-scale-balanced"></i> Liability
            </a>
            <a href="#disputes" class="terms-nav-btn">
                <i class="fa-solid fa-gavel"></i> Disputes
            </a>
        </div>

        <!-- Introduction -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-gavel"></i></div>
                <h2>Agreement to Terms</h2>
            </div>
            <p>By accessing or using hOUR Timebank Ireland (accessible at <strong>hour-timebank.ie</strong>), you agree to be bound by these Terms of Service. Please read them carefully before participating in our community.</p>
            <p>These terms establish a framework for <strong>fair, respectful, and meaningful exchanges</strong> between community members across Ireland. Our goal is to create a trusted environment where everyone's time is valued equally.</p>
            <p>If you are using the platform on behalf of an organisation, you represent that you have authority to bind that organisation to these terms.</p>
        </div>

        <!-- Who We Are -->
        <div class="terms-section" id="who-we-are">
            <div class="section-header">
                <div class="section-number">1</div>
                <h2>Who We Are</h2>
            </div>
            <p>hOUR Timebank Ireland is operated by <strong>hOUR Timebank CLG</strong>, an Irish registered charity dedicated to building community through time-based exchange.</p>

            <div class="entity-info">
                <dl>
                    <dt>Legal Name</dt>
                    <dd>hOUR Timebank CLG (Company Limited by Guarantee)</dd>

                    <dt>Registered Business Name</dt>
                    <dd>Timebank Ireland</dd>

                    <dt>Charity Registration</dt>
                    <dd>RCN 20162023 (Charities Regulator)</dd>

                    <dt>Registered Address</dt>
                    <dd>21 Páirc Goodman, Skibbereen, Co. Cork, P81 AK26, Ireland</dd>

                    <dt>Contact</dt>
                    <dd><a href="mailto:jasper@hour-timebank.ie">jasper@hour-timebank.ie</a></dd>
                </dl>
            </div>

            <p>As a registered charity, we operate on a not-for-profit basis. All platform activities support our charitable mission of fostering community connections and mutual aid throughout Ireland.</p>
        </div>

        <!-- Time Credit System -->
        <div class="terms-section" id="time-credits">
            <div class="section-header">
                <div class="section-number">2</div>
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
                    <p>Time Credits have no monetary value and cannot be exchanged for cash. They exist solely to facilitate community exchanges and are not considered payment for services under Irish law.</p>
                </div>
            </div>

            <ul>
                <li>One hour of service provided equals one Time Credit earned</li>
                <li>Credits can be used to receive services from other members</li>
                <li>The type of service does not affect the credit value — all time is equal</li>
                <li>Credits are tracked automatically through the platform</li>
                <li>Credits cannot be transferred, sold, or inherited</li>
            </ul>
        </div>

        <!-- Safeguarding - Prohibited Services -->
        <div class="terms-section highlight" id="safeguarding">
            <div class="section-header">
                <div class="section-number">3</div>
                <h2>Safeguarding & Prohibited Services</h2>
            </div>
            <p>hOUR Timebank CLG takes safeguarding seriously. Under the <strong>National Vetting Bureau (Children and Vulnerable Persons) Acts 2012-2016</strong>, certain activities involving children or vulnerable persons require Garda Vetting by a Registered Organisation.</p>

            <div class="terms-notice" style="border-left: 4px solid #ef4444;">
                <span class="notice-icon" style="color: #ef4444;"><i class="fa-solid fa-exclamation-triangle"></i></span>
                <div class="notice-content">
                    <h4>Absolute Prohibition</h4>
                    <p><strong>hOUR Timebank CLG does NOT provide Garda Vetting services. Therefore, ALL services involving children or vulnerable persons are strictly prohibited on this platform.</strong> No exceptions.</p>
                </div>
            </div>

            <p><strong>Definitions Under Irish Law:</strong></p>

            <p><strong>"Child"</strong> means a person under the age of 18 years other than a person who is or has been married.</p>

            <p><strong>"Vulnerable Person"</strong> means a person, other than a child, who:</p>
            <ul>
                <li>Is suffering from a disorder of the mind, whether as a result of mental illness, dementia, or intellectual disability</li>
                <li>Has a physical impairment, whether as a result of injury, illness, or age</li>
                <li>Has a physical disability</li>
                <li>Is receiving health or personal social services from a healthcare provider</li>
            </ul>

            <p><strong>Absolutely Prohibited Services (No Exceptions):</strong></p>
            <div class="prohibited-grid">
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-child"></i></span>
                    <span>Childminding or babysitting</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-book-open"></i></span>
                    <span>Tutoring or teaching children</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-car"></i></span>
                    <span>Transport for children/vulnerable persons</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-user-nurse"></i></span>
                    <span>Care assistance for vulnerable adults</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-house-user"></i></span>
                    <span>Home visits to vulnerable persons</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-hands-holding-child"></i></span>
                    <span>Youth group or children's activities</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-user-group"></i></span>
                    <span>Befriending vulnerable adults</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-universal-access"></i></span>
                    <span>Any contact with protected groups</span>
                </div>
            </div>

            <p><strong>Why This Prohibition Exists:</strong></p>
            <p>Irish law requires that persons undertaking "Relevant Work" (work involving access to children or vulnerable persons) must be vetted by the National Vetting Bureau through a Registered Organisation. hOUR Timebank CLG is <strong>not a Registered Organisation</strong> for the purposes of Garda Vetting. Therefore, we cannot facilitate any services that would constitute Relevant Work under the Acts.</p>

            <p><strong>Compliance & Enforcement:</strong></p>
            <ul>
                <li>Offering or requesting prohibited services will result in <strong>immediate account termination</strong></li>
                <li>This is a <strong>legal requirement</strong> under Irish law, not a policy choice</li>
                <li>Violations may be reported to <strong>An Garda Síochána</strong></li>
                <li>Members who need to provide such services should seek vetting through an appropriate Registered Organisation (e.g., their employer, a sports club, or another charity)</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="notice-content">
                    <h4>Adults-Only Platform</h4>
                    <p>All timebank exchanges on this platform must be between adults (18+) for services that do not involve children or vulnerable persons. Examples of permitted services include: gardening, DIY, cooking lessons for adults, language exchange between adults, pet sitting, admin help, IT support, etc.</p>
                </div>
            </div>
        </div>

        <!-- Partner Organisations -->
        <div class="terms-section highlight" id="partners">
            <div class="section-header">
                <div class="section-number">4</div>
                <h2>Partner Organisations</h2>
            </div>
            <p>hOUR Timebank Ireland provides platform services to Irish community groups, voluntary organisations, charities, and other relevant bodies ("Partner Organisations") who wish to operate timebanking within their communities.</p>

            <div class="partner-features">
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-users-gear"></i></span>
                    <div class="feature-text">
                        <strong>Branded Communities</strong>
                        <span>Partners can operate their own branded timebank community</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-sliders"></i></span>
                    <div class="feature-text">
                        <strong>Local Management</strong>
                        <span>Manage members and exchanges within your community</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <div class="feature-text">
                        <strong>Reporting Tools</strong>
                        <span>Track community impact and social value created</span>
                    </div>
                </div>
                <div class="partner-feature">
                    <span class="feature-icon"><i class="fa-solid fa-headset"></i></span>
                    <div class="feature-text">
                        <strong>Support & Training</strong>
                        <span>Access resources and guidance for successful operation</span>
                    </div>
                </div>
            </div>

            <p><strong>Partner Responsibilities:</strong></p>
            <ul>
                <li>Ensure compliance with these Terms within their community</li>
                <li>Maintain accurate records of members and exchanges</li>
                <li>Handle member data in accordance with GDPR and our Privacy Policy</li>
                <li>Report any issues or concerns to hOUR Timebank CLG promptly</li>
                <li>Uphold the values and mission of timebanking in Ireland</li>
            </ul>

            <p>Partner Organisations operate as independent entities. hOUR Timebank CLG provides the platform but does not control or supervise individual community operations.</p>
        </div>

        <!-- Account Responsibilities -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">5</div>
                <h2>Account Responsibilities</h2>
            </div>
            <p>When you create an account, you agree to:</p>
            <ul>
                <li><strong>Provide accurate information:</strong> Your profile must reflect your true identity and skills</li>
                <li><strong>Maintain security:</strong> Keep your login credentials confidential and secure</li>
                <li><strong>Use one account:</strong> Each person may only maintain one active account</li>
                <li><strong>Stay current:</strong> Update your profile when your skills or availability change</li>
                <li><strong>Be reachable:</strong> Respond to messages and requests in a timely manner</li>
                <li><strong>Be 18 or older:</strong> You must be at least 18 years old to create an account</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-sliders"></i></span>
                <div class="notice-content">
                    <h4>Privacy Settings Are Your Responsibility</h4>
                    <p>Your account includes privacy and visibility settings that control how your information is shared. <strong>You are solely responsible for reviewing, understanding, and maintaining these settings.</strong> This includes settings for profile visibility, federation opt-in, notifications, and data sharing preferences. We recommend reviewing your privacy settings regularly. hOUR Timebank CLG is not liable for any disclosure of information that occurs because you enabled sharing features or failed to configure your privacy settings appropriately.</p>
                </div>
            </div>
        </div>

        <!-- Community Guidelines -->
        <div class="terms-section" id="community">
            <div class="section-header">
                <div class="section-number">6</div>
                <h2>Community Guidelines</h2>
            </div>
            <p>Our community is built on <strong>trust, respect, and mutual support</strong>. All members must:</p>
            <ol>
                <li><strong>Treat everyone with respect</strong> — Be kind and courteous in all interactions</li>
                <li><strong>Honor your commitments</strong> — If you agree to an exchange, follow through</li>
                <li><strong>Communicate clearly</strong> — Keep other members informed about your availability</li>
                <li><strong>Be inclusive</strong> — Welcome members of all backgrounds and abilities</li>
                <li><strong>Give honest feedback</strong> — Help the community by providing fair reviews</li>
                <li><strong>Respect boundaries</strong> — Only contact members through the platform unless invited otherwise</li>
            </ol>
        </div>

        <!-- Prohibited Activities -->
        <div class="terms-section" id="prohibited">
            <div class="section-header">
                <div class="section-number">7</div>
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
                    <span>Commercial exploitation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Impersonation</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Sharing others' private info</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Requesting cash payments</span>
                </div>
                <div class="prohibited-item">
                    <span class="prohibited-icon"><i class="fa-solid fa-ban"></i></span>
                    <span>Multi-level marketing</span>
                </div>
            </div>
        </div>

        <!-- Safety -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">8</div>
                <h2>Safety & Meetings</h2>
            </div>
            <p>Your safety is important. We recommend following these guidelines:</p>
            <ul>
                <li><strong>First meetings:</strong> Meet in public places for initial exchanges</li>
                <li><strong>Verify identity:</strong> Confirm the member's profile before meeting</li>
                <li><strong>Trust your instincts:</strong> If something feels wrong, don't proceed</li>
                <li><strong>Report concerns:</strong> Let us know about any suspicious behaviour</li>
                <li><strong>Keep records:</strong> Document exchanges through the platform</li>
                <li><strong>Inform someone:</strong> Let a friend or family member know about your plans</li>
            </ul>
        </div>

        <!-- Nature of Relationship -->
        <div class="terms-section highlight" id="relationship">
            <div class="section-header">
                <div class="section-number">9</div>
                <h2>Nature of Relationship</h2>
            </div>
            <p>It is essential that all members understand the legal nature of their relationship with hOUR Timebank CLG and with other members:</p>

            <div class="terms-notice" style="border-left: 4px solid #3b82f6;">
                <span class="notice-icon" style="color: #3b82f6;"><i class="fa-solid fa-handshake-simple"></i></span>
                <div class="notice-content">
                    <h4>No Employment Relationship</h4>
                    <p>Members are <strong>NOT</strong> employees, workers, agents, or contractors of hOUR Timebank CLG. No employment relationship, contract of service, or agency relationship exists or is created by participation in the timebank.</p>
                </div>
            </div>

            <p><strong>Key Legal Points:</strong></p>
            <ul>
                <li>hOUR Timebank CLG is <strong>not an employer</strong> — we do not direct, control, or supervise your activities</li>
                <li>You are <strong>not entitled</strong> to any employment benefits, workers' compensation, or employment protections from hOUR Timebank CLG</li>
                <li>Exchanges between members are <strong>personal arrangements</strong> between independent individuals</li>
                <li>You are responsible for your own <strong>tax obligations</strong>, if any arise (note: Time Credits are not monetary income)</li>
                <li>You must provide your own <strong>tools, equipment, and materials</strong> for any services you offer</li>
                <li>You are free to accept or decline any exchange request at your sole discretion</li>
            </ul>

            <p>This clarification is made pursuant to Irish employment law to ensure there is no ambiguity regarding the independent status of members.</p>
        </div>

        <!-- Assumption of Risk -->
        <div class="terms-section" id="assumption-of-risk">
            <div class="section-header">
                <div class="section-number">10</div>
                <h2>Assumption of Risk & Personal Responsibility</h2>
            </div>
            <p>By participating in the timebank, you acknowledge and accept the following:</p>

            <p><strong>Voluntary Participation:</strong></p>
            <p>In accordance with the principle of <em>volenti non fit injuria</em> (voluntary assumption of risk) as recognised under Irish law and the Occupiers' Liability Act 1995 (as amended by the Courts & Civil Law (Miscellaneous Provisions) Act 2023):</p>
            <ul>
                <li>You <strong>voluntarily choose</strong> to participate in timebank exchanges</li>
                <li>You acknowledge that exchanges carry <strong>inherent risks</strong> that cannot be entirely eliminated</li>
                <li>You accept responsibility for <strong>assessing the suitability</strong> of any exchange before agreeing to participate</li>
                <li>You are capable of <strong>comprehending the nature and extent</strong> of risks involved in exchanges</li>
            </ul>

            <p><strong>Personal Responsibility (Civil Liability Act 1961, Section 34):</strong></p>
            <ul>
                <li>You are responsible for taking <strong>reasonable care for your own safety</strong></li>
                <li>Any damages may be reduced in proportion to your own <strong>contributory negligence</strong></li>
                <li>You must disclose any <strong>health conditions, limitations, or circumstances</strong> that may affect your ability to safely participate in an exchange</li>
                <li>You should <strong>not proceed</strong> with any exchange that you believe may be unsafe</li>
            </ul>

            <p><strong>No Skill or Qualification Verification:</strong></p>
            <p>hOUR Timebank CLG does <strong>NOT</strong>:</p>
            <ul>
                <li>Verify the qualifications, certifications, or professional credentials of any member</li>
                <li>Assess the competence or skill level of members offering services</li>
                <li>Guarantee the quality or standard of any service provided</li>
                <li>Inspect, test, or certify any work performed between members</li>
            </ul>
            <p>You are solely responsible for assessing whether a member is suitable for your needs. For services requiring professional qualifications (e.g., electrical work, gas fitting, legal advice), you should always verify appropriate certifications independently.</p>
        </div>

        <!-- Insurance Recommendations -->
        <div class="terms-section" id="insurance">
            <div class="section-header">
                <div class="section-number">11</div>
                <h2>Insurance Recommendations</h2>
            </div>
            <p>While not legally required, we <strong>strongly recommend</strong> the following insurance considerations:</p>

            <p><strong>For All Members:</strong></p>
            <ul>
                <li><strong>Personal Accident Insurance:</strong> Consider coverage for injuries sustained during exchanges</li>
                <li><strong>Home Insurance:</strong> Check that your policy covers visitors to your property and activities you undertake</li>
                <li><strong>Motor Insurance:</strong> If providing transport services, ensure your policy covers carrying non-paying passengers</li>
            </ul>

            <p><strong>For Members Offering Professional-Type Services:</strong></p>
            <ul>
                <li><strong>Public Liability Insurance:</strong> Recommended if providing services that could cause injury to others or damage to property</li>
                <li><strong>Professional Indemnity Insurance:</strong> Consider if offering advice or consultancy-type services</li>
            </ul>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <div class="notice-content">
                    <h4>hOUR Timebank CLG Insurance</h4>
                    <p>hOUR Timebank CLG maintains public liability insurance for organised timebank events and activities. This does <strong>NOT</strong> extend to individual exchanges between members, which are personal arrangements conducted at your own risk.</p>
                </div>
            </div>
        </div>

        <!-- Liability -->
        <div class="terms-section highlight" id="liability">
            <div class="section-header">
                <div class="section-number">12</div>
                <h2>Limitation of Liability</h2>
            </div>
            <p>hOUR Timebank CLG provides a platform for community members to connect and exchange services. To the maximum extent permitted by Irish law:</p>

            <p><strong>Platform Limitations:</strong></p>
            <ul>
                <li>We do not guarantee the quality, safety, legality, or suitability of any services exchanged</li>
                <li>We are not responsible for disputes, disagreements, or conflicts between members</li>
                <li>We do not supervise, direct, or control exchanges between members</li>
                <li>We do not verify the identity, background, qualifications, or skills of members (except where Garda Vetting is undertaken)</li>
                <li>We are not liable for any loss, damage, or injury arising from exchanges</li>
            </ul>

            <p><strong>Indemnification:</strong></p>
            <p>You agree to <strong>indemnify, defend, and hold harmless</strong> hOUR Timebank CLG, its charity trustees, directors, officers, employees, volunteers, and agents from and against any claims, liabilities, damages, losses, costs, or expenses (including reasonable legal fees) arising from:</p>
            <ul>
                <li>Your breach of these Terms of Service</li>
                <li>Your violation of any applicable law or regulation</li>
                <li>Your exchanges or interactions with other members</li>
                <li>Any content you submit, post, or transmit through the platform</li>
                <li>Your negligent or wrongful conduct</li>
            </ul>

            <p><strong>Statutory Protections (Cannot Be Excluded):</strong></p>
            <p>In accordance with Irish consumer protection law, including the Consumer Rights Act 2022 and the European Communities (Unfair Terms in Consumer Contracts) Regulations 1995, nothing in these terms shall exclude or limit liability for:</p>
            <ul>
                <li>Death or personal injury caused by negligence</li>
                <li>Fraud or fraudulent misrepresentation</li>
                <li>Any liability that cannot lawfully be excluded or limited under Irish law</li>
            </ul>
        </div>

        <!-- Dispute Resolution -->
        <div class="terms-section" id="disputes">
            <div class="section-header">
                <div class="section-number">13</div>
                <h2>Dispute Resolution</h2>
            </div>
            <p>We encourage members to resolve disputes amicably. If a dispute arises:</p>

            <p><strong>Step 1: Direct Communication</strong></p>
            <p>First, attempt to resolve the matter directly with the other member. Many misunderstandings can be resolved through open dialogue.</p>

            <p><strong>Step 2: Platform Mediation</strong></p>
            <p>If direct communication fails, you may request informal mediation assistance from hOUR Timebank CLG. We can facilitate communication but are <strong>not arbitrators</strong> and cannot impose binding decisions.</p>

            <p><strong>Step 3: External Mediation</strong></p>
            <p>For unresolved disputes, we recommend the <strong>Mediators' Institute of Ireland (MII)</strong> or another accredited mediation service before pursuing legal action.</p>

            <p><strong>Step 4: Legal Proceedings</strong></p>
            <p>If mediation fails, disputes shall be subject to the exclusive jurisdiction of the Irish courts under Section 12 (Governing Law).</p>

            <div class="terms-notice">
                <span class="notice-icon"><i class="fa-solid fa-circle-info"></i></span>
                <div class="notice-content">
                    <h4>Small Claims Procedure</h4>
                    <p>For claims under €2,000, you may use the <strong>Small Claims Court</strong> procedure, which is a low-cost, informal way to resolve disputes without needing a solicitor.</p>
                </div>
            </div>
        </div>

        <!-- Intellectual Property -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">14</div>
                <h2>Intellectual Property</h2>
            </div>
            <p>The hOUR Timebank platform, including its design, features, and content (excluding user-generated content), is owned by hOUR Timebank CLG and protected by intellectual property laws.</p>
            <ul>
                <li>You may not copy, modify, or distribute platform content without permission</li>
                <li>The hOUR Timebank name and logo are trademarks of hOUR Timebank CLG</li>
                <li>Content you create (profiles, messages, reviews) remains yours, but you grant us a licence to display it on the platform</li>
            </ul>
        </div>

        <!-- Termination -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">15</div>
                <h2>Account Termination</h2>
            </div>
            <p>We reserve the right to suspend or terminate accounts that violate these terms. Reasons for termination include:</p>
            <ul>
                <li>Repeated violation of community guidelines</li>
                <li>Fraudulent or deceptive behaviour</li>
                <li>Harassment of other members</li>
                <li>Extended inactivity (over 12 months without notice)</li>
                <li>Providing false information</li>
            </ul>
            <p>You may also close your account at any time through your account settings or by contacting us. Upon termination, your Time Credits will be forfeited as they have no monetary value.</p>
        </div>

        <!-- Governing Law -->
        <div class="terms-section highlight">
            <div class="section-header">
                <div class="section-number">16</div>
                <h2>Governing Law</h2>
            </div>
            <p>These Terms of Service are governed by and construed in accordance with the <strong>laws of the Republic of Ireland</strong>.</p>
            <p>Any disputes arising from these terms or your use of the platform shall be subject to the exclusive jurisdiction of the <strong>Irish courts</strong>.</p>
            <p>If any provision of these terms is found to be unenforceable, the remaining provisions will continue in full force and effect.</p>
        </div>

        <!-- Changes to Terms -->
        <div class="terms-section">
            <div class="section-header">
                <div class="section-number">17</div>
                <h2>Changes to These Terms</h2>
            </div>
            <p>We may update these terms from time to time to reflect changes in our practices, legal requirements, or platform features. When we make significant changes:</p>
            <ul>
                <li>We will notify you via email or platform notification</li>
                <li>The updated date will be shown at the top of this page</li>
                <li>Continued use of the platform after changes constitutes acceptance of the new terms</li>
                <li>If you disagree with changes, you may close your account</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="terms-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Have Questions?</h2>
            <p>If you have any questions about these Terms of Service, need clarification, or are interested in becoming a Partner Organisation, please get in touch.</p>
            <a href="mailto:jasper@hour-timebank.ie" class="terms-cta-btn">
                <i class="fa-solid fa-envelope"></i>
                jasper@hour-timebank.ie
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

<?php require __DIR__ . '/../../../../layouts/modern/footer.php'; ?>
