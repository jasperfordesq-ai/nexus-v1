<?php
// Phoenix View: Timebanking Guide - Glassmorphism 2025 + Gold Standard v6.1
$pageTitle = 'How Timebanking Works';
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
   Theme Color: Teal (#0d9488)
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

#guide-glass-wrapper {
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
    outline: 3px solid rgba(13, 148, 136, 0.5);
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
<div id="guide-glass-wrapper">

    <style>
        /* ===================================
           GLASSMORPHISM TIMEBANKING GUIDE
           Theme Color: Teal (#0d9488)
           =================================== */

        /* Animated Gradient Background */
        #guide-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #guide-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(13, 148, 136, 0.08) 0%,
                rgba(20, 184, 166, 0.08) 25%,
                rgba(45, 212, 191, 0.08) 50%,
                rgba(94, 234, 212, 0.08) 75%,
                rgba(13, 148, 136, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #guide-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(13, 148, 136, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(20, 184, 166, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #guide-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(13, 148, 136, 0.12) 0%,
                rgba(20, 184, 166, 0.12) 50%,
                rgba(45, 212, 191, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(13, 148, 136, 0.1);
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        [data-theme="dark"] #guide-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(13, 148, 136, 0.15) 0%,
                rgba(20, 184, 166, 0.15) 50%,
                rgba(45, 212, 191, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #guide-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0d9488, #14b8a6, #2dd4bf);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #guide-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #guide-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #guide-glass-wrapper .nexus-smart-btn {
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

        #guide-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #guide-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #0d9488, #14b8a6);
            color: white;
            box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35);
        }

        #guide-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 148, 136, 0.45);
        }

        #guide-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(13, 148, 136, 0.3);
        }

        [data-theme="dark"] #guide-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(20, 184, 166, 0.4);
        }

        #guide-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #0d9488, #14b8a6);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            #guide-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
            #guide-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #guide-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
            #guide-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
            #guide-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
        }

        /* Legacy Page Header - hidden, replaced by welcome hero */
        #guide-glass-wrapper .page-header {
            display: none;
        }

        /* Quick Actions Bar */
        #guide-glass-wrapper .quick-actions-bar {
            margin: 0 0 30px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #guide-glass-wrapper .quick-action-btn {
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

        #guide-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(13, 148, 136, 0.08), rgba(20, 184, 166, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #guide-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #guide-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(13, 148, 136, 0.4);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.15),
                        0 0 0 1px rgba(13, 148, 136, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #guide-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #guide-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(13, 148, 136, 0.3);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.25),
                        0 0 0 1px rgba(13, 148, 136, 0.2),
                        0 0 30px rgba(20, 184, 166, 0.12);
        }

        #guide-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #guide-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Section Headers */
        #guide-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #0d9488;
        }

        #guide-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        /* Glass Cards */
        #guide-glass-wrapper .glass-card {
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

        #guide-glass-wrapper .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.18),
                        0 0 0 1px rgba(13, 148, 136, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #guide-glass-wrapper .glass-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(13, 148, 136, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #guide-glass-wrapper .glass-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(13, 148, 136, 0.25),
                        0 0 80px rgba(20, 184, 166, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #guide-glass-wrapper .card-header-gradient {
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 50%, #2dd4bf 100%);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #guide-glass-wrapper .card-header-gradient h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }

        #guide-glass-wrapper .card-header-gradient .icon {
            font-size: 1.5rem;
        }

        #guide-glass-wrapper .card-header-gradient .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Card Body */
        #guide-glass-wrapper .card-body {
            padding: 24px;
        }

        #guide-glass-wrapper .card-body p {
            color: var(--htb-text-muted);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0 0 16px 0;
        }

        #guide-glass-wrapper .card-body p:last-child {
            margin-bottom: 0;
        }

        /* Stats Grid */
        #guide-glass-wrapper .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        #guide-glass-wrapper .stat-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(31, 38, 135, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        #guide-glass-wrapper .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(31, 38, 135, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #guide-glass-wrapper .stat-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        #guide-glass-wrapper .stat-card .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--htb-text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        #guide-glass-wrapper .stat-card .stat-value {
            font-size: 3rem;
            font-weight: 900;
            line-height: 1;
        }

        #guide-glass-wrapper .stat-card .stat-value.teal { color: #0d9488; }
        #guide-glass-wrapper .stat-card .stat-value.pink { color: #db2777; }
        #guide-glass-wrapper .stat-card .stat-value.green { color: #16a34a; }

        /* Steps Grid */
        #guide-glass-wrapper .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        #guide-glass-wrapper .step-card {
            text-align: center;
            padding: 30px 24px;
            background: rgba(13, 148, 136, 0.04);
            border-radius: 16px;
            border: 1px solid rgba(13, 148, 136, 0.1);
            transition: all 0.3s ease;
        }

        #guide-glass-wrapper .step-card:hover {
            background: rgba(13, 148, 136, 0.08);
            transform: translateY(-3px);
        }

        [data-theme="dark"] #guide-glass-wrapper .step-card {
            background: rgba(13, 148, 136, 0.1);
            border-color: rgba(13, 148, 136, 0.2);
        }

        [data-theme="dark"] #guide-glass-wrapper .step-card:hover {
            background: rgba(13, 148, 136, 0.15);
        }

        #guide-glass-wrapper .step-card .step-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        #guide-glass-wrapper .step-card .step-icon.blue {
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
        }

        #guide-glass-wrapper .step-card .step-icon.pink {
            background: rgba(219, 39, 119, 0.1);
            color: #db2777;
        }

        #guide-glass-wrapper .step-card .step-icon.green {
            background: rgba(22, 163, 74, 0.1);
            color: #16a34a;
        }

        [data-theme="dark"] #guide-glass-wrapper .step-card .step-icon.blue {
            background: rgba(37, 99, 235, 0.2);
        }

        [data-theme="dark"] #guide-glass-wrapper .step-card .step-icon.pink {
            background: rgba(219, 39, 119, 0.2);
        }

        [data-theme="dark"] #guide-glass-wrapper .step-card .step-icon.green {
            background: rgba(22, 163, 74, 0.2);
        }

        #guide-glass-wrapper .step-card h4 {
            color: var(--htb-text-main);
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 12px 0;
        }

        #guide-glass-wrapper .step-card p {
            color: var(--htb-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Values List */
        #guide-glass-wrapper .values-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #guide-glass-wrapper .values-list li {
            padding: 16px 20px;
            margin-bottom: 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        #guide-glass-wrapper .values-list li:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: translateX(4px);
        }

        [data-theme="dark"] #guide-glass-wrapper .values-list li {
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] #guide-glass-wrapper .values-list li:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        #guide-glass-wrapper .values-list li:nth-child(1) { border-left-color: #4f46e5; }
        #guide-glass-wrapper .values-list li:nth-child(2) { border-left-color: #db2777; }
        #guide-glass-wrapper .values-list li:nth-child(3) { border-left-color: #16a34a; }
        #guide-glass-wrapper .values-list li:nth-child(4) { border-left-color: #f59e0b; }

        #guide-glass-wrapper .values-list li strong {
            color: var(--htb-text-main);
            font-weight: 700;
        }

        #guide-glass-wrapper .values-list li span {
            color: var(--htb-text-muted);
        }

        /* CTA Box */
        #guide-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 2px dashed rgba(13, 148, 136, 0.3);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
        }

        [data-theme="dark"] #guide-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border-color: rgba(13, 148, 136, 0.4);
        }

        #guide-glass-wrapper .cta-box .cta-badge {
            display: inline-block;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.2);
            color: #7c3aed;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        [data-theme="dark"] #guide-glass-wrapper .cta-box .cta-badge {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.15));
            border-color: rgba(139, 92, 246, 0.3);
            color: #a78bfa;
        }

        #guide-glass-wrapper .cta-box h3 {
            color: var(--htb-text-main);
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 12px 0;
        }

        #guide-glass-wrapper .cta-box p {
            color: var(--htb-text-muted);
            font-size: 1.05rem;
            line-height: 1.7;
            margin: 0 0 24px 0;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Glass Primary Button */
        #guide-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(13, 148, 136, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #guide-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(13, 148, 136, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        #guide-glass-wrapper .glass-btn-secondary {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--htb-text-main);
            border: 2px solid rgba(13, 148, 136, 0.3);
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

        #guide-glass-wrapper .glass-btn-secondary:hover {
            transform: translateY(-2px);
            border-color: rgba(13, 148, 136, 0.5);
            background: rgba(255, 255, 255, 0.85);
        }

        [data-theme="dark"] #guide-glass-wrapper .glass-btn-secondary {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border-color: rgba(13, 148, 136, 0.4);
        }

        [data-theme="dark"] #guide-glass-wrapper .glass-btn-secondary:hover {
            background: rgba(15, 23, 42, 0.8);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #guide-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #guide-glass-wrapper .quick-action-btn {
                justify-content: center;
            }

            #guide-glass-wrapper .stats-grid,
            #guide-glass-wrapper .steps-grid {
                grid-template-columns: 1fr;
            }

            #guide-glass-wrapper .stat-card .stat-value {
                font-size: 2.5rem;
            }

            #guide-glass-wrapper .cta-box {
                padding: 30px 20px;
            }

            #guide-glass-wrapper .cta-box h3 {
                font-size: 1.5rem;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #guide-glass-wrapper .glass-card,
            #guide-glass-wrapper .stat-card,
            #guide-glass-wrapper .quick-action-btn {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #guide-glass-wrapper .glass-card,
            [data-theme="dark"] #guide-glass-wrapper .stat-card,
            [data-theme="dark"] #guide-glass-wrapper .quick-action-btn {
                background: rgba(15, 23, 42, 0.95);
            }
        }
    </style>

    <!-- Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">How Timebanking Works</h1>
        <p class="nexus-welcome-subtitle">Give an hour, get an hour. It's that simple. Join Ireland's most impactful community exchange network.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/register" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-user-plus"></i>
                <span>Join Community</span>
            </a>
            <a href="<?= $basePath ?>/impact-report" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-chart-pie"></i>
                <span>See Impact</span>
            </a>
            <a href="<?= $basePath ?>/about" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-book-open"></i>
                <span>Our Story</span>
            </a>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="section-header">
        <h2>Our Verified Impact</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Social Return</div>
            <div class="stat-value teal">16:1</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Improved Wellbeing</div>
            <div class="stat-value pink">100%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Socially Connected</div>
            <div class="stat-value green">95%</div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="section-header">
        <h2>3 Simple Steps</h2>
    </div>

    <div class="glass-card">
        <div class="card-body">
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-icon blue">
                        <i class="fa-solid fa-handshake"></i>
                    </div>
                    <h4>Give an Hour</h4>
                    <p>Share a skill you love—from practical help to a friendly chat or a lift to the shops.</p>
                </div>

                <div class="step-card">
                    <div class="step-icon pink">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <h4>Earn a Credit</h4>
                    <p>You automatically earn one Time Credit for every hour you spend helping another member.</p>
                </div>

                <div class="step-card">
                    <div class="step-icon green">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <h4>Get Help</h4>
                    <p>Spend your credit to get support, learn a new skill, or join a community work day.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Values Section -->
    <div class="section-header">
        <h2>Our Fundamental Values</h2>
    </div>

    <div class="glass-card">
        <div class="card-header-gradient">
            <span class="icon">💎</span>
            <h3>What We Believe</h3>
        </div>
        <div class="card-body">
            <p style="text-align: center; margin-bottom: 24px; font-weight: 500;">
                At hOUR Timebank, we believe that true wealth is found in our connections with one another. Our community is built on four fundamental values:
            </p>

            <ul class="values-list">
                <li>
                    <strong>We Are All Assets:</strong>
                    <span> Every human being has something of value to contribute.</span>
                </li>
                <li>
                    <strong>Redefining Work:</strong>
                    <span> We honour the real work of family and community.</span>
                </li>
                <li>
                    <strong>Reciprocity:</strong>
                    <span> Helping works better as a two-way street.</span>
                </li>
                <li>
                    <strong>Social Networks:</strong>
                    <span> People flourish in community and perish in isolation.</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="section-header">
        <h2>Partner With Us</h2>
    </div>

    <div class="glass-card">
        <div class="card-body">
            <div class="cta-box">
                <span class="cta-badge">Social Impact</span>
                <h3>A 1:16 Return on Investment</h3>
                <p>We have a proven, independently validated model. We are now seeking strategic partners to help us secure our core operations and scale our impact across Ireland.</p>
                <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= $basePath ?>/partner" class="glass-btn-primary">
                        🤝 Partner With Us
                    </a>
                    <a href="<?= $basePath ?>/impact-report" class="glass-btn-secondary">
                        📊 View Full Report
                    </a>
                </div>
            </div>
        </div>
    </div>

</div><!-- #guide-glass-wrapper -->
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
        meta.content = '#0d9488';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#0d9488');
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
