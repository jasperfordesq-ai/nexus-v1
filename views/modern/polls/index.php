<?php
// Polls Index - Glassmorphism 2025
$pageTitle = "Community Polls";
$pageSubtitle = "Make your voice heard on community decisions";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Polls - Vote on Local Decisions');
Nexus\Core\SEO::setDescription('Participate in community polls and make your voice heard. Vote on local decisions and help shape your neighborhood.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
<div id="polls-glass-wrapper">

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

        .glass-poll-card {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Button Press States */
        .htb-btn:active,
        button:active,
        .nexus-smart-btn:active,
        .glass-btn-primary:active,
        .vote-link:active {
            transform: scale(0.96) !important;
            transition: transform 0.1s ease !important;
        }

        /* Touch Targets - WCAG 2.1 AA (44px minimum) */
        .htb-btn,
        button,
        .nexus-smart-btn,
        .glass-btn-primary,
        input[type="text"],
        input[type="search" aria-label="Search"] {
            min-height: 44px;
        }

        input[type="text"],
        input[type="search" aria-label="Search"] {
            font-size: 16px !important; /* Prevent iOS zoom */
        }

        /* Focus Visible */
        .htb-btn:focus-visible,
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        .nexus-smart-btn:focus-visible,
        .glass-btn-primary:focus-visible {
            outline: 3px solid rgba(139, 92, 246, 0.5);
            outline-offset: 2px;
        }

        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile Responsive Enhancements */
        @media (max-width: 768px) {
            .htb-container-full {
                padding: 0 15px 100px 15px;
            }

            .htb-btn,
            .nexus-smart-btn,
            .glass-btn-primary {
                min-height: 48px;
            }
        }

        /* ===================================
           GLASSMORPHISM POLLS INDEX
           Purple/Violet Theme for Governance
           =================================== */

        /* Animated Gradient Background */
        #polls-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #polls-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(139, 92, 246, 0.08) 0%,
                rgba(167, 139, 250, 0.08) 25%,
                rgba(196, 181, 253, 0.06) 50%,
                rgba(221, 214, 254, 0.06) 75%,
                rgba(237, 233, 254, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #polls-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(139, 92, 246, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(167, 139, 250, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* SEO Description Text */
        #polls-glass-wrapper .seo-description {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 20px auto;
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--htb-text-main);
            font-weight: 500;
        }

        /* Quick Actions Bar */
        #polls-glass-wrapper .quick-actions-bar {
            margin: 30px 0 20px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #polls-glass-wrapper .quick-action-btn {
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

        #polls-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(167, 139, 250, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #polls-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #polls-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.15),
                        0 0 0 1px rgba(139, 92, 246, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #polls-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #polls-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.25),
                        0 0 0 1px rgba(139, 92, 246, 0.2),
                        0 0 30px rgba(167, 139, 250, 0.12);
        }

        #polls-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #polls-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Glass Search Card */
        #polls-glass-wrapper .glass-search-card {
            margin-top: 30px !important;
            position: relative;
            z-index: 10;
            padding: 25px;
            border-radius: 20px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15),
                        inset 0 0 0 1px rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-search-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 80px rgba(139, 92, 246, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        /* Glass Primary Button */
        #polls-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #polls-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Section Headers */
        #polls-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #8b5cf6;
        }

        #polls-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        #polls-glass-wrapper .section-header.closed {
            border-left-color: #9ca3af;
        }

        [data-theme="dark"] #polls-glass-wrapper .section-header.closed {
            border-left-color: #6b7280;
        }

        /* Glass Poll Cards */
        #polls-glass-wrapper .glass-poll-card {
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
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        #polls-glass-wrapper .glass-poll-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2),
                        0 0 0 1px rgba(139, 92, 246, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-poll-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(139, 92, 246, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-poll-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(139, 92, 246, 0.25),
                        0 0 80px rgba(167, 139, 250, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #polls-glass-wrapper .card-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
        }

        #polls-glass-wrapper .card-header::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        #polls-glass-wrapper .card-header .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #polls-glass-wrapper .card-header .poll-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #polls-glass-wrapper .card-header .poll-icon i {
            font-size: 1.5rem;
            color: white;
        }

        /* Status Badge */
        #polls-glass-wrapper .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        #polls-glass-wrapper .status-badge.open {
            background: rgba(255, 255, 255, 0.95);
            color: #16a34a;
        }

        #polls-glass-wrapper .status-badge.closed {
            background: rgba(255, 255, 255, 0.8);
            color: #6b7280;
        }

        /* Card Body */
        #polls-glass-wrapper .card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        #polls-glass-wrapper .poll-question {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 16px 0;
            line-height: 1.4;
            color: var(--htb-text-main);
        }

        #polls-glass-wrapper .poll-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            color: var(--htb-text-muted);
        }

        #polls-glass-wrapper .poll-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #polls-glass-wrapper .poll-meta-item i {
            color: #8b5cf6;
        }

        #polls-glass-wrapper .poll-desc {
            color: var(--htb-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            flex-grow: 1;
        }

        /* Card Footer */
        #polls-glass-wrapper .card-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        [data-theme="dark"] #polls-glass-wrapper .card-footer {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        #polls-glass-wrapper .vote-count {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--htb-text-main);
        }

        #polls-glass-wrapper .vote-count i {
            color: #8b5cf6;
        }

        #polls-glass-wrapper .vote-link {
            color: #8b5cf6;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        #polls-glass-wrapper .vote-link:hover {
            color: #7c3aed;
            gap: 10px;
        }

        [data-theme="dark"] #polls-glass-wrapper .vote-link {
            color: #a78bfa;
        }

        /* Clickable Card Link Wrapper */
        #polls-glass-wrapper a.glass-poll-card {
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        #polls-glass-wrapper a.glass-poll-card:hover {
            text-decoration: none;
        }

        /* Create Poll Card */
        #polls-glass-wrapper .glass-create-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 2px dashed rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            transition: all 0.3s ease;
        }

        #polls-glass-wrapper .glass-create-card:hover {
            border-color: rgba(139, 92, 246, 0.5);
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.6),
                rgba(255, 255, 255, 0.4));
            transform: translateY(-4px);
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-create-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border: 2px dashed rgba(139, 92, 246, 0.4);
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-create-card:hover {
            border-color: rgba(139, 92, 246, 0.6);
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.5),
                rgba(30, 41, 59, 0.4));
        }

        /* Grid Layout */
        #polls-glass-wrapper .polls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }

        /* Glass Empty State */
        #polls-glass-wrapper .glass-empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.7),
                rgba(255, 255, 255, 0.5));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
        }

        [data-theme="dark"] #polls-glass-wrapper .glass-empty-state {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #polls-glass-wrapper {
                padding-top: 0 !important;
                margin-top: 0 !important;
            }

            #polls-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #polls-glass-wrapper .quick-action-btn {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            #polls-glass-wrapper .glass-search-card,
            #polls-glass-wrapper .glass-poll-card {
                backdrop-filter: blur(8px) saturate(110%);
                -webkit-backdrop-filter: blur(8px) saturate(110%);
            }

            #polls-glass-wrapper .glass-poll-card:hover {
                transform: none;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #polls-glass-wrapper .glass-search-card,
            #polls-glass-wrapper .glass-poll-card {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #polls-glass-wrapper .glass-search-card,
            [data-theme="dark"] #polls-glass-wrapper .glass-poll-card {
                background: rgba(15, 23, 42, 0.95);
            }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #polls-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(139, 92, 246, 0.12) 0%,
                rgba(167, 139, 250, 0.12) 50%,
                rgba(196, 181, 253, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
        }

        [data-theme="dark"] #polls-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(139, 92, 246, 0.15) 0%,
                rgba(167, 139, 250, 0.15) 50%,
                rgba(196, 181, 253, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #polls-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #polls-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #polls-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #polls-glass-wrapper .nexus-smart-btn {
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

        #polls-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #polls-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
        }

        #polls-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
        }

        #polls-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(167, 139, 250, 0.1));
            color: #7c3aed;
            border-color: rgba(139, 92, 246, 0.3);
        }

        [data-theme="dark"] #polls-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(167, 139, 250, 0.15));
            color: #c4b5fd;
            border-color: rgba(139, 92, 246, 0.4);
        }

        #polls-glass-wrapper .nexus-smart-btn-secondary:hover,
        #polls-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        #polls-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(139, 92, 246, 0.3);
        }

        [data-theme="dark"] #polls-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(139, 92, 246, 0.4);
        }

        @media (max-width: 640px) {
            #polls-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
            #polls-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #polls-glass-wrapper .nexus-smart-buttons { gap: 8px; }
            #polls-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
        }
    </style>

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Community Polls</h1>
        <p class="nexus-welcome-subtitle">Participate in community polls and make your voice heard. Vote on local decisions and help shape your neighborhood.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/polls" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-check-to-slot"></i>
                <span>All Polls</span>
            </a>
            <a href="<?= $basePath ?>/polls?filter=open" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-circle-check"></i>
                <span>Open Polls</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/polls?filter=voted" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-square-check"></i>
                <span>My Votes</span>
            </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/compose?type=poll" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-plus"></i>
                <span>Create Poll</span>
            </a>
        </div>
    </div>

    <!-- Glass Info Card -->
    <div class="glass-search-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">Community Governance</h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= count($polls ?? []) ?> polls available - Your vote matters!
                </p>
            </div>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-square-poll-vertical" style="color: #8b5cf6; font-size: 1.1rem;"></i>
        <h2>Active Polls</h2>
    </div>

    <!-- Polls Grid -->
    <div class="polls-grid">
        <?php if (!empty($polls)): ?>
            <?php foreach ($polls as $poll): ?>
                <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="glass-poll-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <div class="poll-icon">
                                <i class="fa-solid fa-square-poll-vertical"></i>
                            </div>
                            <span class="status-badge <?= $poll['status'] === 'open' ? 'open' : 'closed' ?>">
                                <?= ucfirst($poll['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="poll-question">
                            <?= htmlspecialchars($poll['question']) ?>
                        </h3>

                        <div class="poll-meta">
                            <?php if (!empty($poll['options_count'])): ?>
                            <div class="poll-meta-item">
                                <i class="fa-solid fa-list-check"></i>
                                <span><?= $poll['options_count'] ?> options</span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($poll['created_at'])): ?>
                            <div class="poll-meta-item">
                                <i class="fa-solid fa-calendar"></i>
                                <span><?= date('M d, Y', strtotime($poll['created_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <p class="poll-desc">
                            Make your voice heard on this community decision.
                        </p>
                    </div>

                    <div class="card-footer">
                        <div class="vote-count">
                            <i class="fa-solid fa-users"></i>
                            <span><?= $poll['vote_count'] ?? 0 ?> votes</span>
                        </div>
                        <span class="vote-link">
                            <?= $poll['status'] === 'open' ? 'Vote Now' : 'View Results' ?> <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">🗳️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No active polls</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Start a discussion by creating a poll!</p>
                <a href="<?= $basePath ?>/compose?type=poll" class="glass-btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Poll
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #polls-glass-wrapper -->
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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.htb-btn, button, .nexus-smart-btn, .glass-btn-primary, .vote-link').forEach(btn => {
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
        meta.content = '#8b5cf6';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
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

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
