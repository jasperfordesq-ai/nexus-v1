<?php
// Resources Index - Glassmorphism 2025
$pageTitle = "Resource Library";
$pageSubtitle = "Tools, guides, and documents for the community";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Resource Library - Community Guides & Tools');
Nexus\Core\SEO::setDescription('Access community resources, guides, templates, and tools. Share knowledge and download helpful documents.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Main content wrapper (main tag opened in header.php) -->
<div class="htb-container-full">
<div id="resources-glass-wrapper">

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

        .resources-grid {
            animation: fadeInUp 0.4s ease-out;
        }

        /* Button Press States */
        .htb-btn:active,
        .glass-btn-primary:active,
        .nexus-smart-btn:active,
        .quick-action-btn:active,
        .download-btn:active,
        button:active {
            transform: scale(0.96) !important;
            transition: transform 0.1s ease !important;
        }

        /* Touch Targets - WCAG 2.1 AA (44px minimum) */
        .htb-btn,
        .glass-btn-primary,
        .nexus-smart-btn,
        .quick-action-btn,
        .download-btn,
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
        .download-btn:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(99, 102, 241, 0.5);
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
            .download-btn,
            button {
                min-height: 48px;
            }
        }

        /* ===================================
           GLASSMORPHISM RESOURCES INDEX
           Indigo/Blue Theme for Knowledge
           =================================== */

        /* Animated Gradient Background */
        #resources-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #resources-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.08) 0%,
                rgba(129, 140, 248, 0.08) 25%,
                rgba(165, 180, 252, 0.06) 50%,
                rgba(199, 210, 254, 0.06) 75%,
                rgba(224, 231, 255, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #resources-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(99, 102, 241, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(129, 140, 248, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* SEO Description Text */
        #resources-glass-wrapper .seo-description {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 20px auto;
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--htb-text-main);
            font-weight: 500;
        }

        /* Quick Actions Bar */
        #resources-glass-wrapper .quick-actions-bar {
            margin: 30px 0 20px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #resources-glass-wrapper .quick-action-btn {
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

        #resources-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(129, 140, 248, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #resources-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #resources-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.15),
                        0 0 0 1px rgba(99, 102, 241, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #resources-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #resources-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25),
                        0 0 0 1px rgba(99, 102, 241, 0.2),
                        0 0 30px rgba(129, 140, 248, 0.12);
        }

        #resources-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #resources-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Glass Search Card */
        #resources-glass-wrapper .glass-search-card {
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

        [data-theme="dark"] #resources-glass-wrapper .glass-search-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 80px rgba(99, 102, 241, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        /* Category Pills */
        #resources-glass-wrapper .category-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        #resources-glass-wrapper .category-pill {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--htb-text-main);
        }

        #resources-glass-wrapper .category-pill:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
        }

        #resources-glass-wrapper .category-pill.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border-color: transparent;
        }

        [data-theme="dark"] #resources-glass-wrapper .category-pill {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        [data-theme="dark"] #resources-glass-wrapper .category-pill:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.4);
        }

        /* Glass Primary Button */
        #resources-glass-wrapper .glass-btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #resources-glass-wrapper .glass-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Section Headers */
        #resources-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #6366f1;
        }

        #resources-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        /* Glass Resource Cards */
        #resources-glass-wrapper .glass-resource-card {
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

        #resources-glass-wrapper .glass-resource-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2),
                        0 0 0 1px rgba(99, 102, 241, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #resources-glass-wrapper .glass-resource-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(99, 102, 241, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #resources-glass-wrapper .glass-resource-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(99, 102, 241, 0.25),
                        0 0 80px rgba(129, 140, 248, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #resources-glass-wrapper .card-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
        }

        #resources-glass-wrapper .card-header::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        #resources-glass-wrapper .card-header .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #resources-glass-wrapper .card-header .file-icon {
            font-size: 2.5rem;
            line-height: 1;
        }

        #resources-glass-wrapper .card-header .file-meta {
            text-align: right;
        }

        #resources-glass-wrapper .file-size-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 6px;
        }

        #resources-glass-wrapper .file-category {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }

        /* Card Body */
        #resources-glass-wrapper .card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        #resources-glass-wrapper .resource-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 12px 0;
            line-height: 1.4;
            color: var(--htb-text-main);
        }

        #resources-glass-wrapper .resource-desc {
            color: var(--htb-text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            flex-grow: 1;
            margin-bottom: 16px;
        }

        /* Card Footer */
        #resources-glass-wrapper .card-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        [data-theme="dark"] #resources-glass-wrapper .card-footer {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        #resources-glass-wrapper .uploader-info {
            font-size: 0.85rem;
            color: var(--htb-text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #resources-glass-wrapper .uploader-info i {
            color: #6366f1;
        }

        #resources-glass-wrapper .download-stats {
            font-size: 0.85rem;
            color: var(--htb-text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #resources-glass-wrapper .download-stats i {
            color: #6366f1;
        }

        /* Download Button */
        #resources-glass-wrapper .download-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 16px;
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.1),
                rgba(129, 140, 248, 0.05));
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            color: #6366f1;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        #resources-glass-wrapper .download-btn:hover {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        [data-theme="dark"] #resources-glass-wrapper .download-btn {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.15),
                rgba(129, 140, 248, 0.1));
            border-top-color: rgba(255, 255, 255, 0.1);
            color: #818cf8;
        }

        /* Upload Card */
        #resources-glass-wrapper .glass-upload-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 2px dashed rgba(99, 102, 241, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            transition: all 0.3s ease;
        }

        #resources-glass-wrapper .glass-upload-card:hover {
            border-color: rgba(99, 102, 241, 0.5);
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.6),
                rgba(255, 255, 255, 0.4));
            transform: translateY(-4px);
        }

        [data-theme="dark"] #resources-glass-wrapper .glass-upload-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border: 2px dashed rgba(99, 102, 241, 0.4);
        }

        [data-theme="dark"] #resources-glass-wrapper .glass-upload-card:hover {
            border-color: rgba(99, 102, 241, 0.6);
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.5),
                rgba(30, 41, 59, 0.4));
        }

        /* Grid Layout */
        #resources-glass-wrapper .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        /* Glass Empty State */
        #resources-glass-wrapper .glass-empty-state {
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

        [data-theme="dark"] #resources-glass-wrapper .glass-empty-state {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #resources-glass-wrapper {
                padding-top: 0 !important;
                margin-top: 0 !important;
            }

            #resources-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #resources-glass-wrapper .quick-action-btn {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            #resources-glass-wrapper .glass-search-card,
            #resources-glass-wrapper .glass-resource-card {
                backdrop-filter: blur(8px) saturate(110%);
                -webkit-backdrop-filter: blur(8px) saturate(110%);
            }

            #resources-glass-wrapper .glass-resource-card:hover {
                transform: none;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #resources-glass-wrapper .glass-search-card,
            #resources-glass-wrapper .glass-resource-card {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #resources-glass-wrapper .glass-search-card,
            [data-theme="dark"] #resources-glass-wrapper .glass-resource-card {
                background: rgba(15, 23, 42, 0.95);
            }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #resources-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.12) 0%,
                rgba(129, 140, 248, 0.12) 50%,
                rgba(165, 180, 252, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
        }

        [data-theme="dark"] #resources-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(99, 102, 241, 0.15) 0%,
                rgba(129, 140, 248, 0.15) 50%,
                rgba(165, 180, 252, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #resources-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #6366f1, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #resources-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #resources-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #resources-glass-wrapper .nexus-smart-btn {
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

        #resources-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #resources-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }

        #resources-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
        }

        #resources-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
            color: #4f46e5;
            border-color: rgba(99, 102, 241, 0.3);
        }

        [data-theme="dark"] #resources-glass-wrapper .nexus-smart-btn-secondary {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(129, 140, 248, 0.15));
            color: #a5b4fc;
            border-color: rgba(99, 102, 241, 0.4);
        }

        #resources-glass-wrapper .nexus-smart-btn-secondary:hover,
        #resources-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        #resources-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(99, 102, 241, 0.3);
        }

        [data-theme="dark"] #resources-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(99, 102, 241, 0.4);
        }

        @media (max-width: 640px) {
            #resources-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; }
            #resources-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #resources-glass-wrapper .nexus-smart-buttons { gap: 8px; }
            #resources-glass-wrapper .nexus-smart-btn { padding: 10px 14px; font-size: 0.8rem; }
        }
    </style>

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Resource Library</h1>
        <p class="nexus-welcome-subtitle">Access community resources, guides, templates, and tools. Share knowledge and download helpful documents.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/resources" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-book"></i>
                <span>All Resources</span>
            </a>
            <a href="<?= $basePath ?>/resources?sort=popular" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-fire"></i>
                <span>Most Downloaded</span>
            </a>
            <a href="<?= $basePath ?>/resources?sort=recent" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-clock"></i>
                <span>Recently Added</span>
            </a>
            <a href="<?= $basePath ?>/resources/create" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-upload"></i>
                <span>Upload File</span>
            </a>
        </div>
    </div>

    <!-- Glass Search Card -->
    <div class="glass-search-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">Library Files</h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= count($resources ?? []) ?> resources available
                </p>
            </div>
            <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                <i class="fa-solid fa-upload"></i> Upload File
            </a>
        </div>

        <!-- Category Pills -->
        <div class="category-pills">
            <a href="<?= $basePath ?>/resources" class="category-pill <?= !isset($_GET['cat']) ? 'active' : '' ?>">
                All
            </a>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <a href="?cat=<?= $cat['id'] ?>" class="category-pill <?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-folder-open" style="color: #6366f1; font-size: 1.1rem;"></i>
        <h2>Available Resources</h2>
    </div>

    <!-- Resources Grid -->
    <div class="resources-grid">
        <!-- Upload Card -->
        <div class="glass-upload-card">
            <div style="text-align: center; padding: 30px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: #6366f1;"></i>
                </div>
                <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 10px; color: var(--htb-text-main);">Share a Resource</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px; font-size: 0.95rem;">
                    Help the community with guides and tools.
                </p>
                <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload File
                </a>
            </div>
        </div>

        <?php if (!empty($resources)): ?>
            <?php foreach ($resources as $res): ?>
                <?php
                $icon = '📄';
                if (strpos($res['file_type'] ?? '', 'image') !== false) $icon = '🖼️';
                if (strpos($res['file_type'] ?? '', 'zip') !== false) $icon = '📦';
                if (strpos($res['file_type'] ?? '', 'pdf') !== false) $icon = '📕';
                if (strpos($res['file_type'] ?? '', 'doc') !== false) $icon = '📝';
                if (strpos($res['file_type'] ?? '', 'xls') !== false) $icon = '📊';
                if (strpos($res['file_type'] ?? '', 'video') !== false) $icon = '🎬';

                $size = round(($res['file_size'] ?? 0) / 1024) . ' KB';
                if (($res['file_size'] ?? 0) > 1024 * 1024) $size = round(($res['file_size'] ?? 0) / 1024 / 1024, 1) . ' MB';

                $isOwner = isset($_SESSION['user_id']) && ($res['user_id'] ?? 0) == $_SESSION['user_id'];
                $isAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'];
                ?>
                <article class="glass-resource-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <span class="file-icon"><?= $icon ?></span>
                            <div class="file-meta">
                                <div class="file-size-badge"><?= $size ?></div>
                                <?php if (!empty($res['category_name'])): ?>
                                <div class="file-category"><?= htmlspecialchars($res['category_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="resource-title">
                            <?= htmlspecialchars($res['title']) ?>
                        </h3>

                        <p class="resource-desc">
                            <?= htmlspecialchars(substr($res['description'] ?? '', 0, 100)) ?>...
                        </p>
                    </div>

                    <div class="card-footer">
                        <div class="uploader-info">
                            <i class="fa-solid fa-user"></i>
                            <?= htmlspecialchars($res['uploader_name'] ?? 'Unknown') ?>
                        </div>
                        <?php if ($isOwner || $isAdmin): ?>
                            <a href="<?= $basePath ?>/resources/<?= $res['id'] ?>/edit" style="color: #6366f1; text-decoration: none; font-weight: 600;">
                                <i class="fa-solid fa-pen"></i> Edit
                            </a>
                        <?php else: ?>
                            <div class="download-stats">
                                <i class="fa-solid fa-download"></i>
                                <?= $res['downloads'] ?? 0 ?> downloads
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= $basePath ?>/resources/<?= $res['id'] ?>/download" class="download-btn">
                        <i class="fa-solid fa-download"></i> Download File
                    </a>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Library is Empty</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Share the first guide or toolkit!</p>
                <a href="<?= $basePath ?>/resources/create" class="glass-btn-primary">
                    <i class="fa-solid fa-upload"></i> Upload Resource
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #resources-glass-wrapper -->
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
document.querySelectorAll('.htb-btn, .glass-btn-primary, .nexus-smart-btn, .quick-action-btn, .download-btn, button').forEach(btn => {
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
        meta.content = '#6366f1';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#6366f1');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Download links now go to a dedicated download page with countdown
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
