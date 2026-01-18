<?php
/**
 * Dynamic Legal Page - Modern Glassmorphism Design
 * Renders custom Privacy/Terms text for tenants
 * Theme Color: Indigo (#6366f1) for Privacy, Blue (#3b82f6) for Terms
 */
$hTitle = $pageTitle ?? 'Legal Document';
$hideHero = true;

require __DIR__ . '/../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}

// Determine theme based on page type
$isPrivacy = stripos($pageTitle ?? '', 'privacy') !== false;
$themeColor = $isPrivacy ? '#6366f1' : '#3b82f6';
$themeColorRgb = $isPrivacy ? '99, 102, 241' : '59, 130, 246';
$themeColorLight = $isPrivacy ? '#818cf8' : '#60a5fa';
$themeColorDark = $isPrivacy ? '#4f46e5' : '#2563eb';
$pageIcon = $isPrivacy ? 'fa-shield-halved' : 'fa-file-contract';
?>

<style>
/* ============================================
   LEGAL DYNAMIC - GLASSMORPHISM 2025
   ============================================ */

#legal-glass-wrapper {
    --legal-theme: #6366f1;
    --legal-theme-rgb: 99, 102, 241;
    --legal-theme-light: #818cf8;
    --legal-theme-dark: #4f46e5;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #legal-glass-wrapper {
        padding-top: 120px;
    }
}

#legal-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #legal-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(99, 102, 241, 0.04) 50%,
        rgba(99, 102, 241, 0.08) 100%);
    background-size: 400% 400%;
    animation: legalGradientShift 15s ease infinite;
}

[data-theme="dark"] #legal-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
}

@keyframes legalGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Content Reveal Animation */
@keyframes legalFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#legal-glass-wrapper {
    animation: legalFadeInUp 0.4s ease-out;
}

#legal-glass-wrapper .legal-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#legal-glass-wrapper .legal-page-header {
    text-align: center;
    margin-bottom: 2rem;
}

#legal-glass-wrapper .legal-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#legal-glass-wrapper .legal-page-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--legal-theme) 0%, var(--legal-theme-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

#legal-glass-wrapper .legal-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0 auto;
    max-width: 600px;
}

#legal-glass-wrapper .legal-page-header .last-updated {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #legal-glass-wrapper .legal-page-header .last-updated {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
}

[data-theme="dark"] #legal-glass-wrapper .legal-page-header .last-updated {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

/* Content Card */
#legal-glass-wrapper .legal-content-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #legal-glass-wrapper .legal-content-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #legal-glass-wrapper .legal-content-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* Typography for dynamic content */
#legal-glass-wrapper .legal-typography {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--htb-text-main);
    line-height: 1.8;
    font-size: 1.05rem;
}

#legal-glass-wrapper .legal-typography p,
#legal-glass-wrapper .legal-typography div {
    margin-bottom: 1.25rem;
}

#legal-glass-wrapper .legal-typography strong,
#legal-glass-wrapper .legal-typography b {
    font-weight: 700;
    color: var(--htb-text-main);
}

/* Style headings in the content */
#legal-glass-wrapper .legal-typography h1,
#legal-glass-wrapper .legal-typography h2,
#legal-glass-wrapper .legal-typography h3,
#legal-glass-wrapper .legal-typography h4 {
    color: var(--htb-text-main);
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

#legal-glass-wrapper .legal-typography h1 { font-size: 1.75rem; }
#legal-glass-wrapper .legal-typography h2 { font-size: 1.5rem; }
#legal-glass-wrapper .legal-typography h3 { font-size: 1.25rem; }
#legal-glass-wrapper .legal-typography h4 { font-size: 1.1rem; }

/* Style numbered sections like "1. Introduction" */
#legal-glass-wrapper .legal-typography br + br {
    display: block;
    content: '';
    margin-top: 1.5rem;
}

/* Links */
#legal-glass-wrapper .legal-typography a {
    color: var(--legal-theme);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
}

#legal-glass-wrapper .legal-typography a:hover {
    color: var(--legal-theme-dark);
    text-decoration: underline;
}

/* Lists */
#legal-glass-wrapper .legal-typography ul,
#legal-glass-wrapper .legal-typography ol {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

#legal-glass-wrapper .legal-typography li {
    margin-bottom: 0.5rem;
    color: var(--htb-text-muted);
}

/* Back to Legal Link */
#legal-glass-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="light"] #legal-glass-wrapper .back-link {
    color: #4f46e5;
}

[data-theme="dark"] #legal-glass-wrapper .back-link {
    color: #818cf8;
}

#legal-glass-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Contact CTA */
#legal-glass-wrapper .legal-cta {
    text-align: center;
    padding: 2.5rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #legal-glass-wrapper .legal-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #legal-glass-wrapper .legal-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

#legal-glass-wrapper .legal-cta h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#legal-glass-wrapper .legal-cta p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.6;
    max-width: 500px;
    margin: 0 auto 1.25rem auto;
}

#legal-glass-wrapper .legal-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--legal-theme) 0%, var(--legal-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
}

#legal-glass-wrapper .legal-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #legal-glass-wrapper {
        padding: 120px 1rem 3rem;
    }

    #legal-glass-wrapper .legal-page-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #legal-glass-wrapper .legal-page-header .header-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }

    #legal-glass-wrapper .legal-page-header p {
        font-size: 1rem;
    }

    #legal-glass-wrapper .legal-content-card {
        padding: 1.5rem;
    }

    #legal-glass-wrapper .legal-typography {
        font-size: 1rem;
    }

    #legal-glass-wrapper .legal-cta {
        padding: 2rem 1.5rem;
    }

    #legal-glass-wrapper .legal-cta h2 {
        font-size: 1.25rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes legalGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Touch Targets */
#legal-glass-wrapper .legal-cta-btn {
    min-height: 44px;
}

@media (max-width: 768px) {
    #legal-glass-wrapper .legal-cta-btn {
        min-height: 48px;
    }
}

/* Button Press States */
#legal-glass-wrapper .legal-cta-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Focus Visible */
#legal-glass-wrapper .legal-cta-btn:focus-visible,
#legal-glass-wrapper .back-link:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.5);
    outline-offset: 2px;
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #legal-glass-wrapper .legal-content-card,
    [data-theme="light"] #legal-glass-wrapper .legal-cta {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #legal-glass-wrapper .legal-content-card,
    [data-theme="dark"] #legal-glass-wrapper .legal-cta {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

<div id="legal-glass-wrapper">
    <div class="legal-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="legal-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid <?= $pageIcon ?>"></i></span>
                <?= htmlspecialchars($pageTitle ?? 'Legal Document') ?>
            </h1>
            <p><?= htmlspecialchars($tenantName) ?> <?= $pageTitle ?></p>
        </div>

        <!-- Content Card -->
        <div class="legal-content-card">
            <div class="legal-typography">
                <?= nl2br(htmlspecialchars($content ?? '')) ?>
            </div>
        </div>

        <!-- Contact CTA -->
        <div class="legal-cta">
            <h2><i class="fa-solid fa-question-circle"></i> Questions?</h2>
            <p>If you have any questions about this policy, please don't hesitate to reach out.</p>
            <a href="<?= $basePath ?>/contact" class="legal-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Us
            </a>
        </div>

    </div>
</div>

<script>
// Button press states
document.querySelectorAll('#legal-glass-wrapper .legal-cta-btn').forEach(btn => {
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

<?php require __DIR__ . '/../layouts/modern/footer.php'; ?>
