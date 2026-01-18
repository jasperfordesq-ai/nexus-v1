<?php
// Phoenix View: Partner With Us - Glassmorphism 2025 + Gold Standard v6.1
$pageTitle = 'Partner With Us';
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
   Theme Color: Violet (#7c3aed)
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

#partner-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.quick-action-btn:active,
.glass-btn-primary:active,
.glass-btn-secondary:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.quick-action-btn,
.glass-btn-primary,
.glass-btn-secondary {
    min-height: 44px !important;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.quick-action-btn:focus-visible,
.glass-btn-primary:focus-visible,
.glass-btn-secondary:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(124, 58, 237, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .quick-action-btn,
    .glass-btn-primary,
    .glass-btn-secondary {
        min-height: 48px !important;
    }
}
</style>

<div class="htb-container-full">
<div id="partner-glass-wrapper">

    <style>
        /* ===================================
           GLASSMORPHISM PARTNER PAGE
           Theme Color: Violet (#7c3aed)
           =================================== */

        /* Animated Gradient Background */
        #partner-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #partner-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(124, 58, 237, 0.08) 0%,
                rgba(139, 92, 246, 0.08) 25%,
                rgba(167, 139, 250, 0.08) 50%,
                rgba(196, 181, 253, 0.08) 75%,
                rgba(124, 58, 237, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #partner-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(124, 58, 237, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(139, 92, 246, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #partner-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(124, 58, 237, 0.12) 0%,
                rgba(139, 92, 246, 0.12) 50%,
                rgba(167, 139, 250, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(124, 58, 237, 0.1);
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        [data-theme="dark"] #partner-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(124, 58, 237, 0.15) 0%,
                rgba(139, 92, 246, 0.15) 50%,
                rgba(167, 139, 250, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #partner-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #partner-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #partner-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #partner-glass-wrapper .nexus-smart-btn {
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

        #partner-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #partner-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: white;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
        }

        #partner-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.45);
        }

        #partner-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(124, 58, 237, 0.3);
        }

        [data-theme="dark"] #partner-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(139, 92, 246, 0.4);
        }

        #partner-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            #partner-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
            #partner-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #partner-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
            #partner-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
            #partner-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
        }

        /* Legacy Page Header - hidden, replaced by welcome hero */
        #partner-glass-wrapper .page-header {
            display: none;
        }

        /* Quick Actions Bar */
        #partner-glass-wrapper .quick-actions-bar {
            margin: 0 0 30px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #partner-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            padding: 14px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
        }

        #partner-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(139, 92, 246, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #partner-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #partner-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(124, 58, 237, 0.4);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.15),
                        0 0 0 1px rgba(124, 58, 237, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #partner-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #partner-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(124, 58, 237, 0.3);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.25),
                        0 0 0 1px rgba(124, 58, 237, 0.2),
                        0 0 30px rgba(139, 92, 246, 0.12);
        }

        #partner-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #partner-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Section Headers */
        #partner-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #7c3aed;
        }

        #partner-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        /* Glass Cards */
        #partner-glass-wrapper .glass-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 24px;
        }

        #partner-glass-wrapper .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.18),
                        0 0 0 1px rgba(124, 58, 237, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #partner-glass-wrapper .glass-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(124, 58, 237, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #partner-glass-wrapper .glass-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(124, 58, 237, 0.25),
                        0 0 80px rgba(139, 92, 246, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #partner-glass-wrapper .card-header-gradient {
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #partner-glass-wrapper .card-header-gradient h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }

        #partner-glass-wrapper .card-header-gradient .icon {
            font-size: 1.5rem;
        }

        #partner-glass-wrapper .card-header-gradient .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Card Body */
        #partner-glass-wrapper .card-body {
            padding: 24px;
        }

        #partner-glass-wrapper .card-body p {
            color: var(--htb-text-muted);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0 0 16px 0;
        }

        #partner-glass-wrapper .card-body p:last-child {
            margin-bottom: 0;
        }

        /* Content Grid */
        #partner-glass-wrapper .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }

        @media (max-width: 900px) {
            #partner-glass-wrapper .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Check List */
        #partner-glass-wrapper .check-list {
            list-style: none;
            padding: 0;
            margin: 20px 0 0 0;
        }

        #partner-glass-wrapper .check-list li {
            padding: 12px 16px 12px 44px;
            margin-bottom: 10px;
            border-radius: 10px;
            background: rgba(124, 58, 237, 0.04);
            border: 1px solid rgba(124, 58, 237, 0.1);
            position: relative;
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            transition: all 0.3s ease;
        }

        #partner-glass-wrapper .check-list li:hover {
            background: rgba(124, 58, 237, 0.08);
            transform: translateX(4px);
        }

        [data-theme="dark"] #partner-glass-wrapper .check-list li {
            background: rgba(124, 58, 237, 0.1);
            border-color: rgba(124, 58, 237, 0.2);
        }

        [data-theme="dark"] #partner-glass-wrapper .check-list li:hover {
            background: rgba(124, 58, 237, 0.15);
        }

        #partner-glass-wrapper .check-list li::before {
            content: '✓';
            position: absolute;
            left: 16px;
            color: #7c3aed;
            font-weight: 700;
            font-size: 1rem;
        }

        /* Feature Image */
        #partner-glass-wrapper .feature-image {
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        /* Impact Grid */
        #partner-glass-wrapper .impact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        #partner-glass-wrapper .impact-card {
            padding: 24px;
            background: rgba(124, 58, 237, 0.04);
            border-radius: 16px;
            border: 1px solid rgba(124, 58, 237, 0.1);
            transition: all 0.3s ease;
        }

        #partner-glass-wrapper .impact-card:hover {
            background: rgba(124, 58, 237, 0.08);
            transform: translateY(-3px);
        }

        [data-theme="dark"] #partner-glass-wrapper .impact-card {
            background: rgba(124, 58, 237, 0.1);
            border-color: rgba(124, 58, 237, 0.2);
        }

        [data-theme="dark"] #partner-glass-wrapper .impact-card:hover {
            background: rgba(124, 58, 237, 0.15);
        }

        #partner-glass-wrapper .impact-card h4 {
            color: #7c3aed;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 12px 0;
        }

        [data-theme="dark"] #partner-glass-wrapper .impact-card h4 {
            color: #a78bfa;
        }

        #partner-glass-wrapper .impact-card p {
            color: var(--htb-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* CTA Box */
        #partner-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 2px dashed rgba(124, 58, 237, 0.3);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
        }

        [data-theme="dark"] #partner-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border-color: rgba(124, 58, 237, 0.4);
        }

        #partner-glass-wrapper .cta-box h3 {
            color: var(--htb-text-main);
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 12px 0;
        }

        #partner-glass-wrapper .cta-box p {
            color: var(--htb-text-muted);
            font-size: 1.05rem;
            line-height: 1.7;
            margin: 0 0 24px 0;
        }

        /* Partners Section */
        #partner-glass-wrapper .partners-section {
            text-align: center;
            padding: 30px;
        }

        #partner-glass-wrapper .partners-section h4 {
            color: var(--htb-text-muted);
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 24px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        #partner-glass-wrapper .partners-logos {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            align-items: center;
        }

        #partner-glass-wrapper .partners-logos img {
            height: 80px;
            width: auto;
            opacity: 0.8;
            transition: all 0.3s ease;
            filter: grayscale(20%);
        }

        #partner-glass-wrapper .partners-logos img:hover {
            opacity: 1;
            transform: scale(1.05);
            filter: grayscale(0%);
        }

        [data-theme="dark"] #partner-glass-wrapper .partners-logos img {
            filter: grayscale(20%) brightness(1.1);
        }

        [data-theme="dark"] #partner-glass-wrapper .partners-logos img:hover {
            filter: grayscale(0%) brightness(1.2);
        }

        /* Glass Primary Button */
        #partner-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(124, 58, 237, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #partner-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        #partner-glass-wrapper .glass-btn-secondary {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--htb-text-main);
            border: 2px solid rgba(124, 58, 237, 0.3);
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #partner-glass-wrapper .glass-btn-secondary:hover {
            transform: translateY(-2px);
            border-color: rgba(124, 58, 237, 0.5);
            background: rgba(255, 255, 255, 0.85);
        }

        [data-theme="dark"] #partner-glass-wrapper .glass-btn-secondary {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border-color: rgba(124, 58, 237, 0.4);
        }

        [data-theme="dark"] #partner-glass-wrapper .glass-btn-secondary:hover {
            background: rgba(15, 23, 42, 0.8);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #partner-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #partner-glass-wrapper .quick-action-btn {
                justify-content: center;
            }

            #partner-glass-wrapper .impact-grid {
                grid-template-columns: 1fr;
            }

            #partner-glass-wrapper .cta-box {
                padding: 30px 20px;
            }

            #partner-glass-wrapper .cta-box h3 {
                font-size: 1.5rem;
            }

            #partner-glass-wrapper .partners-logos {
                gap: 24px;
            }

            #partner-glass-wrapper .partners-logos img {
                height: 60px;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #partner-glass-wrapper .glass-card,
            #partner-glass-wrapper .quick-action-btn {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #partner-glass-wrapper .glass-card,
            [data-theme="dark"] #partner-glass-wrapper .quick-action-btn {
                background: rgba(15, 23, 42, 0.95);
            }
        }
    </style>

    <!-- Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Partner With Us</h1>
        <p class="nexus-welcome-subtitle">A 1:16 Return on Social Investment. Seeking partners to secure core operations and execute our 2026-2030 Strategic Plan.</p>
        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/contact" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-envelope"></i>
                <span>Contact Us</span>
            </a>
            <a href="<?= $basePath ?>/impact-report" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-chart-line"></i>
                <span>Impact Report</span>
            </a>
            <a href="<?= $basePath ?>/strategic-plan" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Strategic Plan</span>
            </a>
        </div>
    </div>

    <!-- Funding Gap Section -->
    <div class="section-header">
        <h2>Addressing the Funding Gap</h2>
    </div>

    <div class="glass-card">
        <div class="card-header-gradient">
            <span class="icon">🎯</span>
            <h3>Our Most Urgent Priority</h3>
            <span class="badge">Critical</span>
        </div>
        <div class="card-body">
            <div class="content-grid">
                <div>
                    <p>The closure of our primary social enterprise income stream has created a funding gap. Our <strong>most urgent priority</strong> is funding the central <strong>Hub Coordinator (Broker)</strong> role for our West Cork Centre of Excellence.</p>

                    <ul class="check-list">
                        <li>The Coordinator was identified as the "key enabler for expansion" and positive outcomes.</li>
                        <li>This investment transitions us from 100% grant reliance to a diversified, sustainable model.</li>
                        <li>Your funding of this role is the foundational first step to unlocking our entire national growth plan.</li>
                    </ul>
                </div>
                <div>
                    <img src="/uploads/tenants/hour-timebank/SRI.jpg" alt="Social Return on Investment" class="feature-image">
                </div>
            </div>
        </div>
    </div>

    <!-- Impact Section -->
    <div class="section-header">
        <h2>Deliver Measurable Social Impact</h2>
    </div>

    <div class="glass-card">
        <div class="card-body">
            <div class="impact-grid">
                <div class="impact-card">
                    <h4>Exceptional Social Value</h4>
                    <p>Align your brand with a model proven to return <strong>€16 in social value for every €1 invested</strong>. We tackle social isolation, a critical public health issue in Ireland.</p>
                </div>
                <div class="impact-card">
                    <h4>Proof and Transparency</h4>
                    <p>Our impact is validated by an independent <strong>2023 Social Impact Study</strong>. We provide clear, data-driven reporting that showcases your commitment to CSR.</p>
                </div>
                <div class="impact-card">
                    <h4>Strategic Growth</h4>
                    <p>Invest in a resilient organisation that has a clear <strong>5-year roadmap</strong> to scale from a single region to a national network of over 2,500 active members.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="section-header">
        <h2>Become a Pathfinder Partner</h2>
    </div>

    <div class="glass-card">
        <div class="card-body">
            <div class="cta-box">
                <h3>Let's Discuss Your Investment</h3>
                <p>Join us in building a more connected, resilient Ireland. We're seeking strategic partners who share our vision for community empowerment.</p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= $basePath ?>/contact" class="glass-btn-primary">
                        📧 Contact Strategy Team
                    </a>
                    <a href="<?= $basePath ?>/strategic-plan" class="glass-btn-secondary">
                        📋 View Strategic Plan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Partners Section -->
    <div class="section-header">
        <h2>Our Partners & Supporters</h2>
    </div>

    <div class="glass-card">
        <div class="partners-section">
            <h4>Trusted By</h4>
            <div class="partners-logos">
                <img src="/uploads/tenants/hour-timebank/timebank_ireland_west_cork_partnership.webp" alt="West Cork Partnership">
                <img src="/uploads/tenants/hour-timebank/rethink_ireland_awardee.webp" alt="Rethink Ireland">
                <img src="/uploads/tenants/hour-timebank/Bantry-TT-logo-transparent.webp" alt="Tidy Towns">
            </div>
        </div>
    </div>

</div><!-- #partner-glass-wrapper -->
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
document.querySelectorAll('.quick-action-btn, .glass-btn-primary, .glass-btn-secondary').forEach(btn => {
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
        meta.content = '#7c3aed';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#7c3aed');
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
