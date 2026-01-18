<?php
// Goals Index - Glassmorphism 2025
$pageTitle = "Community Goals";
$pageSubtitle = "Set goals and find accountability buddies";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Goals - Track Progress & Find Buddies');
Nexus\Core\SEO::setDescription('Set personal goals, track your progress, and connect with accountability buddies in your community.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<div class="htb-container-full">
<div id="goals-glass-wrapper">

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

        .goals-grid {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Button Press States */
        .htb-btn:active,
        .glass-btn-primary:active,
        .nexus-smart-btn:active,
        .quick-action-btn:active,
        button:active {
            transform: scale(0.96) !important;
            transition: transform 0.1s ease !important;
        }

        /* Touch Targets - WCAG 2.1 AA (44px minimum) */
        .htb-btn,
        .glass-btn-primary,
        .nexus-smart-btn,
        .quick-action-btn,
        button {
            min-height: 44px;
        }

        /* iOS Zoom Prevention */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="search" aria-label="Search"],
        input[type="tel"],
        input[type="url"],
        input[type="date"],
        textarea,
        select {
            font-size: 16px !important;
        }

        /* Focus Visible */
        .htb-btn:focus-visible,
        .glass-btn-primary:focus-visible,
        .nexus-smart-btn:focus-visible,
        .quick-action-btn:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(132, 204, 22, 0.5);
            outline-offset: 2px;
        }

        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile Responsive Enhancements */
        @media (max-width: 768px) {
            .htb-btn,
            .glass-btn-primary,
            .nexus-smart-btn,
            .quick-action-btn,
            button {
                min-height: 48px;
            }
        }

        /* Clickable Goal Card */
        #goals-glass-wrapper a.glass-goal-card {
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        #goals-glass-wrapper a.glass-goal-card .view-link {
            color: #84cc16;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] #goals-glass-wrapper a.glass-goal-card .view-link {
            color: #a3e635;
        }

        /* ===================================
           GLASSMORPHISM GOALS INDEX
           Lime/Green Theme for Growth
           =================================== */

        /* Animated Gradient Background */
        #goals-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #goals-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(132, 204, 22, 0.08) 0%,
                rgba(163, 230, 53, 0.08) 25%,
                rgba(190, 242, 100, 0.06) 50%,
                rgba(217, 249, 157, 0.06) 75%,
                rgba(236, 252, 203, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #goals-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(132, 204, 22, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(163, 230, 53, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* SEO Description Text */
        #goals-glass-wrapper .seo-description {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 20px auto;
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--htb-text-main);
            font-weight: 500;
        }

        /* Quick Actions Bar */
        #goals-glass-wrapper .quick-actions-bar {
            margin: 30px 0 20px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #goals-glass-wrapper .quick-action-btn {
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

        #goals-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(132, 204, 22, 0.08), rgba(163, 230, 53, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #goals-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #goals-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(132, 204, 22, 0.4);
            box-shadow: 0 8px 20px rgba(132, 204, 22, 0.15),
                        0 0 0 1px rgba(132, 204, 22, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        #goals-glass-wrapper .quick-action-btn.active {
            border-color: rgba(132, 204, 22, 0.5);
            background: linear-gradient(135deg,
                rgba(132, 204, 22, 0.15),
                rgba(163, 230, 53, 0.1));
        }

        [data-theme="dark"] #goals-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #goals-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(132, 204, 22, 0.3);
            box-shadow: 0 8px 20px rgba(132, 204, 22, 0.25),
                        0 0 0 1px rgba(132, 204, 22, 0.2),
                        0 0 30px rgba(163, 230, 53, 0.12);
        }

        [data-theme="dark"] #goals-glass-wrapper .quick-action-btn.active {
            border-color: rgba(132, 204, 22, 0.4);
            background: linear-gradient(135deg,
                rgba(132, 204, 22, 0.2),
                rgba(163, 230, 53, 0.15));
        }

        #goals-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #goals-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Glass Info Card */
        #goals-glass-wrapper .glass-info-card {
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

        [data-theme="dark"] #goals-glass-wrapper .glass-info-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 80px rgba(132, 204, 22, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        /* Glass Primary Button */
        #goals-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #84cc16 0%, #65a30d 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(132, 204, 22, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #goals-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(132, 204, 22, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Section Headers */
        #goals-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #84cc16;
        }

        #goals-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        /* Glass Goal Cards */
        #goals-glass-wrapper .glass-goal-card {
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

        #goals-glass-wrapper .glass-goal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2),
                        0 0 0 1px rgba(132, 204, 22, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #goals-glass-wrapper .glass-goal-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(132, 204, 22, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #goals-glass-wrapper .glass-goal-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(132, 204, 22, 0.25),
                        0 0 80px rgba(163, 230, 53, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #goals-glass-wrapper .card-header {
            background: linear-gradient(135deg, #84cc16 0%, #65a30d 100%);
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
        }

        #goals-glass-wrapper .card-header::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        #goals-glass-wrapper .card-header .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #goals-glass-wrapper .card-header .goal-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #goals-glass-wrapper .card-header .goal-icon i {
            font-size: 1.5rem;
            color: white;
        }

        /* Status Badge */
        #goals-glass-wrapper .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        #goals-glass-wrapper .status-badge.active {
            background: rgba(255, 255, 255, 0.95);
            color: #16a34a;
        }

        #goals-glass-wrapper .status-badge.completed {
            background: rgba(255, 255, 255, 0.95);
            color: #0891b2;
        }

        #goals-glass-wrapper .status-badge.paused {
            background: rgba(255, 255, 255, 0.8);
            color: #f59e0b;
        }

        /* Card Body */
        #goals-glass-wrapper .card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        #goals-glass-wrapper .goal-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 12px 0;
            line-height: 1.4;
        }

        #goals-glass-wrapper .goal-title a {
            color: var(--htb-text-main);
            text-decoration: none;
            transition: color 0.2s;
        }

        #goals-glass-wrapper .goal-title a:hover {
            color: #84cc16;
        }

        #goals-glass-wrapper .goal-author {
            font-size: 0.9rem;
            color: var(--htb-text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #goals-glass-wrapper .goal-author i {
            color: #84cc16;
        }

        #goals-glass-wrapper .goal-desc {
            color: var(--htb-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            flex-grow: 1;
            margin-bottom: 16px;
        }

        /* Card Footer */
        #goals-glass-wrapper .card-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        [data-theme="dark"] #goals-glass-wrapper .card-footer {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        #goals-glass-wrapper .buddy-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--htb-text-muted);
        }

        #goals-glass-wrapper .buddy-info i {
            color: #84cc16;
        }

        #goals-glass-wrapper .buddy-info strong {
            color: var(--htb-text-main);
        }

        #goals-glass-wrapper .view-link {
            color: #84cc16;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        #goals-glass-wrapper .view-link:hover {
            color: #65a30d;
            gap: 10px;
        }

        [data-theme="dark"] #goals-glass-wrapper .view-link {
            color: #a3e635;
        }

        /* Create Goal Card */
        #goals-glass-wrapper .glass-create-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 2px dashed rgba(132, 204, 22, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            transition: all 0.3s ease;
        }

        #goals-glass-wrapper .glass-create-card:hover {
            border-color: rgba(132, 204, 22, 0.5);
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.6),
                rgba(255, 255, 255, 0.4));
            transform: translateY(-4px);
        }

        [data-theme="dark"] #goals-glass-wrapper .glass-create-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border: 2px dashed rgba(132, 204, 22, 0.4);
        }

        [data-theme="dark"] #goals-glass-wrapper .glass-create-card:hover {
            border-color: rgba(132, 204, 22, 0.6);
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.5),
                rgba(30, 41, 59, 0.4));
        }

        /* Grid Layout */
        #goals-glass-wrapper .goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }

        /* Glass Empty State */
        #goals-glass-wrapper .glass-empty-state {
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

        [data-theme="dark"] #goals-glass-wrapper .glass-empty-state {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #goals-glass-wrapper {
                padding-top: 0 !important;
                margin-top: 0 !important;
            }

            #goals-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #goals-glass-wrapper .quick-action-btn {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            #goals-glass-wrapper .glass-info-card,
            #goals-glass-wrapper .glass-goal-card {
                backdrop-filter: blur(8px) saturate(110%);
                -webkit-backdrop-filter: blur(8px) saturate(110%);
            }

            #goals-glass-wrapper .glass-goal-card:hover {
                transform: none;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #goals-glass-wrapper .glass-info-card,
            #goals-glass-wrapper .glass-goal-card {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #goals-glass-wrapper .glass-info-card,
            [data-theme="dark"] #goals-glass-wrapper .glass-goal-card {
                background: rgba(15, 23, 42, 0.95);
            }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #goals-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(132, 204, 22, 0.12) 0%,
                rgba(163, 230, 53, 0.12) 50%,
                rgba(190, 242, 100, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(132, 204, 22, 0.1);
        }

        [data-theme="dark"] #goals-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(132, 204, 22, 0.15) 0%,
                rgba(163, 230, 53, 0.15) 50%,
                rgba(190, 242, 100, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #goals-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #65a30d, #84cc16, #a3e635);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #goals-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #goals-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #goals-glass-wrapper .nexus-smart-btn {
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

        #goals-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #goals-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #84cc16, #a3e635);
            color: white;
            box-shadow: 0 4px 14px rgba(132, 204, 22, 0.35);
        }

        #goals-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(132, 204, 22, 0.45);
        }

        #goals-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(132, 204, 22, 0.1), rgba(163, 230, 53, 0.1));
            color: #65a30d;
            border-color: rgba(132, 204, 22, 0.3);
        }

        [data-theme="dark"] #goals-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(132, 204, 22, 0.2), rgba(163, 230, 53, 0.15));
            color: #bef264;
            border-color: rgba(132, 204, 22, 0.4);
        }

        #goals-glass-wrapper .nexus-smart-btn-secondary:hover,
        #goals-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #84cc16, #a3e635);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        #goals-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(132, 204, 22, 0.3);
        }

        [data-theme="dark"] #goals-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(132, 204, 22, 0.4);
        }

        @media (max-width: 640px) {
            #goals-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
            #goals-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #goals-glass-wrapper .nexus-smart-buttons { gap: 8px; }
            #goals-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
        }
    </style>

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Goal Tracker</h1>
        <p class="nexus-welcome-subtitle">Set personal goals, track your progress, and connect with accountability buddies who can help you succeed.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/goals?view=my-goals" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-bullseye"></i>
                <span>My Goals</span>
            </a>
            <a href="<?= $basePath ?>/goals?view=finder" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-user-group"></i>
                <span>Find a Buddy</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/goals?view=completed" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-circle-check"></i>
                <span>Completed</span>
            </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/compose?type=goal" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-plus"></i>
                <span>Set Goal</span>
            </a>
        </div>
    </div>

    <!-- Glass Info Card -->
    <div class="glass-info-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">
                    <?= ($view ?? 'my-goals') === 'my-goals' ? 'Your Goals' : 'Find Accountability Buddies' ?>
                </h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= ($view ?? 'my-goals') === 'my-goals'
                        ? count($goals ?? []) . ' goals in progress'
                        : 'Connect with others working on similar goals' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-bullseye" style="color: #84cc16; font-size: 1.1rem;"></i>
        <h2><?= ($view ?? 'my-goals') === 'my-goals' ? 'Active Goals' : 'Goals Looking for Buddies' ?></h2>
    </div>

    <!-- Goals Grid -->
    <div class="goals-grid">
        <?php if (!empty($goals)): ?>
            <?php foreach ($goals as $g): ?>
                <a href="<?= $basePath ?>/goals/<?= $g['id'] ?>" class="glass-goal-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <div class="goal-icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </div>
                            <span class="status-badge <?= strtolower($g['status'] ?? 'active') ?>">
                                <?= ucfirst($g['status'] ?? 'Active') ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="goal-title">
                            <?= htmlspecialchars($g['title']) ?>
                        </h3>

                        <?php if (($view ?? '') === 'finder' && !empty($g['author_name'])): ?>
                        <div class="goal-author">
                            <i class="fa-solid fa-user"></i>
                            by <?= htmlspecialchars($g['author_name']) ?>
                        </div>
                        <?php endif; ?>

                        <p class="goal-desc">
                            <?= htmlspecialchars(substr($g['description'] ?? '', 0, 120)) ?>...
                        </p>
                    </div>

                    <div class="card-footer">
                        <?php if (($view ?? 'my-goals') === 'my-goals'): ?>
                        <div class="buddy-info">
                            <i class="fa-solid fa-handshake"></i>
                            <span>Buddy: </span>
                            <?php if (!empty($g['mentor_name'])): ?>
                                <strong><?= htmlspecialchars($g['mentor_name']) ?></strong>
                            <?php else: ?>
                                <span style="opacity: 0.7;">None yet</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="buddy-info">
                            <i class="fa-solid fa-calendar"></i>
                            <span><?= !empty($g['created_at']) ? date('M d, Y', strtotime($g['created_at'])) : 'Recently' ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="view-link">
                            View <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">🎯</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No Goals Found</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">
                    <?= ($view ?? 'my-goals') === 'my-goals'
                        ? 'Start your journey today. Set a new goal!'
                        : 'No active buddy requests found right now.' ?>
                </p>
                <?php if (($view ?? 'my-goals') === 'my-goals'): ?>
                <a href="<?= $basePath ?>/compose?type=goal" class="glass-btn-primary">
                    <i class="fa-solid fa-plus"></i> Get Started
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #goals-glass-wrapper -->
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
document.querySelectorAll('.htb-btn, .glass-btn-primary, .nexus-smart-btn, .quick-action-btn, button').forEach(btn => {
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
        meta.content = '#84cc16';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#84cc16');
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
