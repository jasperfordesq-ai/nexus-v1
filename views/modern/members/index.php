<?php
// Members Directory - Glassmorphism 2025
$pageTitle = "Community Directory";
$pageSubtitle = "Connect with fellow community members";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Directory - Connect with Members');
Nexus\Core\SEO::setDescription('Browse and connect with members of your local community. Find neighbors, discover skills, and build meaningful connections.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="members-glass-wrapper">

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
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .glass-member-card {
                animation: fadeInUp 0.4s ease-out;
            }

            /* Button Press States */
            .htb-btn:active,
            button:active,
            .nexus-smart-btn:active,
            .view-profile-btn:active {
                transform: scale(0.96) !important;
                transition: transform 0.1s ease !important;
            }

            /* Touch Targets - WCAG 2.1 AA (44px minimum) */
            .htb-btn,
            button,
            .nexus-smart-btn,
            .view-profile-btn,
            .page-btn,
            input[type="text"],
            input[type="range"] {
                min-height: 44px;
            }

            input[type="text"] {
                font-size: 16px !important;
                /* Prevent iOS zoom */
            }

            /* Focus Visible */
            .htb-btn:focus-visible,
            button:focus-visible,
            a:focus-visible,
            input:focus-visible,
            .nexus-smart-btn:focus-visible,
            .view-profile-btn:focus-visible {
                outline: 3px solid rgba(6, 182, 212, 0.5);
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
                .view-profile-btn,
                .page-btn {
                    min-height: 48px;
                }
            }

            /* ===================================
           GLASSMORPHISM MEMBERS DIRECTORY
           Cyan/Blue Theme for Community
           =================================== */

            /* Animated Gradient Background */
            #members-glass-wrapper::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: -1;
                pointer-events: none;
            }

            [data-theme="light"] #members-glass-wrapper::before {
                background: linear-gradient(135deg,
                        rgba(6, 182, 212, 0.08) 0%,
                        rgba(34, 211, 238, 0.08) 25%,
                        rgba(103, 232, 249, 0.06) 50%,
                        rgba(165, 243, 252, 0.06) 75%,
                        rgba(207, 250, 254, 0.08) 100%);
                background-size: 400% 400%;
                animation: gradientShift 15s ease infinite;
            }

            [data-theme="dark"] #members-glass-wrapper::before {
                background: radial-gradient(circle at 20% 30%,
                        rgba(6, 182, 212, 0.15) 0%, transparent 50%),
                    radial-gradient(circle at 80% 70%,
                        rgba(34, 211, 238, 0.12) 0%, transparent 50%);
            }

            @keyframes gradientShift {

                0%,
                100% {
                    background-position: 0% 50%;
                }

                50% {
                    background-position: 100% 50%;
                }
            }

            /* SEO Description Text */
            #members-glass-wrapper .seo-description {
                text-align: center;
                max-width: 650px;
                margin: 0 auto 20px auto;
                font-size: 1.05rem;
                line-height: 1.6;
                color: var(--htb-text-main);
                font-weight: 500;
            }

            /* Quick Actions Bar */
            #members-glass-wrapper .quick-actions-bar {
                margin: 30px 0 20px 0;
                display: flex;
                gap: 12px;
                justify-content: center;
                flex-wrap: wrap;
            }

            #members-glass-wrapper .quick-action-btn {
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

            #members-glass-wrapper .quick-action-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(34, 211, 238, 0.08));
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            #members-glass-wrapper .quick-action-btn:hover::before {
                opacity: 1;
            }

            #members-glass-wrapper .quick-action-btn:hover {
                transform: translateY(-3px);
                border-color: rgba(6, 182, 212, 0.4);
                box-shadow: 0 8px 20px rgba(6, 182, 212, 0.15),
                    0 0 0 1px rgba(6, 182, 212, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.5);
            }

            [data-theme="dark"] #members-glass-wrapper .quick-action-btn {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 2px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.06);
            }

            [data-theme="dark"] #members-glass-wrapper .quick-action-btn:hover {
                border-color: rgba(6, 182, 212, 0.3);
                box-shadow: 0 8px 20px rgba(6, 182, 212, 0.25),
                    0 0 0 1px rgba(6, 182, 212, 0.2),
                    0 0 30px rgba(34, 211, 238, 0.12);
            }

            #members-glass-wrapper .quick-action-icon {
                font-size: 1.5rem;
                line-height: 1;
            }

            #members-glass-wrapper .quick-action-text {
                font-size: 0.95rem;
                font-weight: 700;
                color: var(--htb-text-main);
                position: relative;
                z-index: 1;
            }

            /* Glass Search Card */
            #members-glass-wrapper .glass-search-card {
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

            [data-theme="dark"] #members-glass-wrapper .glass-search-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                backdrop-filter: blur(24px) saturate(150%);
                -webkit-backdrop-filter: blur(24px) saturate(150%);
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 80px rgba(6, 182, 212, 0.1),
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            /* Glass Search Input */
            #members-glass-wrapper .glass-search-input {
                width: 100%;
                padding: 16px 20px 16px 50px;
                border: 2px solid rgba(255, 255, 255, 0.4);
                border-radius: 16px;
                font-size: 1.05rem;
                background: rgba(255, 255, 255, 0.6);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1),
                    inset 0 1px 0 rgba(255, 255, 255, 0.6);
                transition: all 0.3s ease;
                box-sizing: border-box;
                color: var(--htb-text-main);
            }

            #members-glass-wrapper .glass-search-input:focus {
                border-color: rgba(6, 182, 212, 0.6);
                box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.15),
                    0 8px 24px rgba(6, 182, 212, 0.2);
                outline: none;
                background: rgba(255, 255, 255, 0.8);
            }

            [data-theme="dark"] #members-glass-wrapper .glass-search-input {
                background: rgba(15, 23, 42, 0.7);
                border: 2px solid rgba(255, 255, 255, 0.2);
                color: #f8fafc;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            [data-theme="dark"] #members-glass-wrapper .glass-search-input::placeholder {
                color: #cbd5e1;
            }

            [data-theme="dark"] #members-glass-wrapper .glass-search-input:focus {
                border-color: rgba(6, 182, 212, 0.5);
                box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.2),
                    0 8px 24px rgba(6, 182, 212, 0.3);
                background: rgba(15, 23, 42, 0.85);
            }

            /* Section Headers */
            #members-glass-wrapper .section-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 40px 0 24px 0;
                padding-left: 16px;
                border-left: 4px solid #06b6d4;
            }

            #members-glass-wrapper .section-header h2 {
                font-size: 1.35rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0;
            }

            /* Clickable Member Cards */
            #members-glass-wrapper a.glass-member-card {
                display: block;
                text-decoration: none;
                color: inherit;
                cursor: pointer;
            }

            /* Glass Member Cards */
            #members-glass-wrapper .glass-member-card {
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
                text-align: center;
            }

            #members-glass-wrapper .glass-member-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2),
                    0 0 0 1px rgba(6, 182, 212, 0.2),
                    inset 0 1px 0 rgba(255, 255, 255, 0.6);
            }

            [data-theme="dark"] #members-glass-wrapper .glass-member-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                backdrop-filter: blur(24px) saturate(150%);
                -webkit-backdrop-filter: blur(24px) saturate(150%);
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(6, 182, 212, 0.08),
                    inset 0 1px 0 rgba(255, 255, 255, 0.08);
            }

            [data-theme="dark"] #members-glass-wrapper .glass-member-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                    0 0 0 1px rgba(6, 182, 212, 0.25),
                    0 0 80px rgba(34, 211, 238, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.12);
            }

            /* Card Body */
            #members-glass-wrapper .card-body {
                padding: 30px 24px;
            }

            /* Avatar Container */
            #members-glass-wrapper .avatar-container {
                position: relative;
                width: 110px;
                height: 110px;
                margin: 0 auto 20px auto;
            }

            #members-glass-wrapper .avatar-ring {
                position: absolute;
                inset: -4px;
                background: linear-gradient(135deg, #06b6d4, #22d3ee, #67e8f9);
                border-radius: 50%;
                animation: avatarPulse 3s ease-in-out infinite;
            }

            @keyframes avatarPulse {

                0%,
                100% {
                    opacity: 1;
                    transform: scale(1);
                }

                50% {
                    opacity: 0.8;
                    transform: scale(1.02);
                }
            }

            #members-glass-wrapper .avatar-img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                border: 4px solid white;
                position: relative;
                z-index: 1;
                box-sizing: border-box;
                opacity: 1 !important; /* Fix for lazy-loaded images stuck at opacity 0 */
            }

            [data-theme="dark"] #members-glass-wrapper .avatar-img {
                border-color: #1e293b;
            }

            /* Member Name */
            #members-glass-wrapper .member-name {
                font-size: 1.3rem;
                font-weight: 700;
                margin: 0 0 8px 0;
            }

            #members-glass-wrapper .member-name a {
                color: var(--htb-text-main);
                text-decoration: none;
                transition: color 0.2s;
            }

            #members-glass-wrapper .member-name a:hover {
                color: #06b6d4;
            }

            /* Location */
            #members-glass-wrapper .member-location {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            #members-glass-wrapper .member-location i {
                color: #06b6d4;
            }

            /* Member Since Badge */
            #members-glass-wrapper .member-since {
                display: inline-block;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(34, 211, 238, 0.1));
                color: #0891b2;
                margin-bottom: 20px;
            }

            [data-theme="dark"] #members-glass-wrapper .member-since {
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(34, 211, 238, 0.15));
                color: #67e8f9;
            }

            /* Organization Role Badges */
            [data-theme="dark"] #members-glass-wrapper .org-role-badge.owner {
                background: linear-gradient(135deg, rgba(251, 191, 36, 0.25), rgba(245, 158, 11, 0.25)) !important;
                color: #fbbf24 !important;
            }

            [data-theme="dark"] #members-glass-wrapper .org-role-badge.admin {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(124, 58, 237, 0.25)) !important;
                color: #a78bfa !important;
            }

            /* View Profile Button */
            #members-glass-wrapper .view-profile-btn {
                display: block;
                width: 100%;
                padding: 12px 0;
                border: 2px solid rgba(6, 182, 212, 0.3);
                border-radius: 12px;
                color: #06b6d4;
                font-weight: 700;
                text-decoration: none;
                transition: all 0.3s ease;
                background: transparent;
            }

            #members-glass-wrapper .view-profile-btn:hover {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                border-color: transparent;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
            }

            [data-theme="dark"] #members-glass-wrapper .view-profile-btn {
                border-color: rgba(6, 182, 212, 0.4);
                color: #22d3ee;
            }

            /* Grid Layout */
            #members-glass-wrapper .members-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 30px;
            }

            /* Glass Empty State */
            #members-glass-wrapper .glass-empty-state {
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

            [data-theme="dark"] #members-glass-wrapper .glass-empty-state {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            }

            /* Pagination */
            #members-glass-wrapper .pagination {
                margin-top: 50px;
                display: flex;
                justify-content: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            #members-glass-wrapper .page-btn {
                padding: 10px 18px;
                border-radius: 12px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.7),
                        rgba(255, 255, 255, 0.5));
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: var(--htb-text-main);
            }

            #members-glass-wrapper .page-btn:hover {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                color: white;
                border-color: transparent;
                transform: translateY(-2px);
            }

            #members-glass-wrapper .page-btn.active {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                color: white;
                border-color: transparent;
                cursor: default;
            }

            [data-theme="dark"] #members-glass-wrapper .page-btn {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            /* Spinner */
            #members-glass-wrapper .spinner {
                width: 20px;
                height: 20px;
                border: 2px solid rgba(6, 182, 212, 0.2);
                border-top-color: #06b6d4;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Nearby Controls Panel */
            #members-glass-wrapper .nearby-controls {
                display: none;
                margin-top: 20px;
                padding: 24px;
                border-radius: 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.4);
            }

            #members-glass-wrapper .nearby-controls.active {
                display: block;
            }

            [data-theme="dark"] #members-glass-wrapper .nearby-controls {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    inset 0 1px 0 rgba(255, 255, 255, 0.08);
            }

            #members-glass-wrapper .nearby-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 20px;
            }

            #members-glass-wrapper .nearby-title {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
            }

            #members-glass-wrapper .nearby-title i {
                color: #06b6d4;
            }

            #members-glass-wrapper .location-status {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
            }

            #members-glass-wrapper .location-status.detecting {
                background: rgba(234, 179, 8, 0.15);
                color: #ca8a04;
            }

            #members-glass-wrapper .location-status.success {
                background: rgba(34, 197, 94, 0.15);
                color: #16a34a;
            }

            #members-glass-wrapper .location-status.error {
                background: rgba(239, 68, 68, 0.15);
                color: #dc2626;
            }

            [data-theme="dark"] #members-glass-wrapper .location-status.detecting {
                background: rgba(234, 179, 8, 0.25);
                color: #fbbf24;
            }

            [data-theme="dark"] #members-glass-wrapper .location-status.success {
                background: rgba(34, 197, 94, 0.25);
                color: #4ade80;
            }

            [data-theme="dark"] #members-glass-wrapper .location-status.error {
                background: rgba(239, 68, 68, 0.25);
                color: #f87171;
            }

            /* Radius Slider */
            #members-glass-wrapper .radius-control {
                display: flex;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }

            #members-glass-wrapper .radius-label {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--htb-text-main);
                min-width: 80px;
            }

            #members-glass-wrapper .radius-slider-wrapper {
                flex: 1;
                min-width: 200px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            #members-glass-wrapper .radius-slider {
                flex: 1;
                height: 8px;
                border-radius: 4px;
                background: linear-gradient(90deg, rgba(6, 182, 212, 0.2), rgba(6, 182, 212, 0.4));
                outline: none;
                -webkit-appearance: none;
                appearance: none;
            }

            #members-glass-wrapper .radius-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(6, 182, 212, 0.4);
                transition: transform 0.2s, box-shadow 0.2s;
            }

            #members-glass-wrapper .radius-slider::-webkit-slider-thumb:hover {
                transform: scale(1.15);
                box-shadow: 0 4px 12px rgba(6, 182, 212, 0.5);
            }

            #members-glass-wrapper .radius-slider::-moz-range-thumb {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                cursor: pointer;
                border: none;
                box-shadow: 0 2px 8px rgba(6, 182, 212, 0.4);
            }

            #members-glass-wrapper .radius-value {
                min-width: 70px;
                padding: 8px 14px;
                border-radius: 10px;
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(34, 211, 238, 0.1));
                color: #0891b2;
                font-weight: 700;
                font-size: 0.95rem;
                text-align: center;
            }

            [data-theme="dark"] #members-glass-wrapper .radius-value {
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(34, 211, 238, 0.15));
                color: #67e8f9;
            }

            /* Nearby Active Button State */
            #members-glass-wrapper .quick-action-btn.nearby-active {
                background: linear-gradient(135deg, #06b6d4, #22d3ee) !important;
                border-color: transparent !important;
                color: white !important;
            }

            #members-glass-wrapper .quick-action-btn.nearby-active .quick-action-text {
                color: white !important;
            }

            /* Distance Badge on Cards */
            #members-glass-wrapper .distance-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(34, 211, 238, 0.1));
                color: #0891b2;
                margin-bottom: 12px;
            }

            [data-theme="dark"] #members-glass-wrapper .distance-badge {
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.25), rgba(34, 211, 238, 0.2));
                color: #67e8f9;
            }

            /* Responsive */
            @media (max-width: 768px) {
                #members-glass-wrapper {
                    padding-top: 0 !important;
                    margin-top: 0 !important;
                }

                #members-glass-wrapper .quick-actions-bar {
                    flex-direction: column;
                    align-items: stretch;
                }

                #members-glass-wrapper .quick-action-btn {
                    justify-content: center;
                }

                #members-glass-wrapper .radius-control {
                    flex-direction: column;
                    align-items: stretch;
                }

                #members-glass-wrapper .radius-slider-wrapper {
                    width: 100%;
                }
            }

            @media (max-width: 640px) {

                #members-glass-wrapper .glass-search-card,
                #members-glass-wrapper .glass-member-card {
                    backdrop-filter: blur(8px) saturate(110%);
                    -webkit-backdrop-filter: blur(8px) saturate(110%);
                }

                #members-glass-wrapper .glass-member-card:hover {
                    transform: none;
                }
            }

            /* Fallback for unsupported browsers */
            @supports not (backdrop-filter: blur(10px)) {

                #members-glass-wrapper .glass-search-card,
                #members-glass-wrapper .glass-member-card {
                    background: rgba(255, 255, 255, 0.95);
                }

                [data-theme="dark"] #members-glass-wrapper .glass-search-card,
                [data-theme="dark"] #members-glass-wrapper .glass-member-card {
                    background: rgba(15, 23, 42, 0.95);
                }
            }

            /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
            #members-glass-wrapper .nexus-welcome-hero {
                background: linear-gradient(135deg,
                        rgba(6, 182, 212, 0.12) 0%,
                        rgba(34, 211, 238, 0.12) 50%,
                        rgba(103, 232, 249, 0.08) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                padding: 28px 24px;
                margin-bottom: 30px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(6, 182, 212, 0.1);
            }

            [data-theme="dark"] #members-glass-wrapper .nexus-welcome-hero {
                background: linear-gradient(135deg,
                        rgba(6, 182, 212, 0.15) 0%,
                        rgba(34, 211, 238, 0.15) 50%,
                        rgba(103, 232, 249, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            #members-glass-wrapper .nexus-welcome-title {
                font-size: 1.75rem;
                font-weight: 800;
                background: linear-gradient(135deg, #0891b2, #06b6d4, #22d3ee);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0 0 8px 0;
                line-height: 1.2;
            }

            #members-glass-wrapper .nexus-welcome-subtitle {
                font-size: 0.95rem;
                color: var(--htb-text-muted);
                margin: 0 0 20px 0;
                line-height: 1.5;
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }

            #members-glass-wrapper .nexus-smart-buttons {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
            }

            #members-glass-wrapper .nexus-smart-btn {
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

            #members-glass-wrapper .nexus-smart-btn i {
                font-size: 1rem;
            }

            #members-glass-wrapper .nexus-smart-btn-primary {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                color: white;
                box-shadow: 0 4px 14px rgba(6, 182, 212, 0.35);
            }

            #members-glass-wrapper .nexus-smart-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(6, 182, 212, 0.45);
            }

            #members-glass-wrapper .nexus-smart-btn-secondary {
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(34, 211, 238, 0.1));
                color: #0891b2;
                border-color: rgba(6, 182, 212, 0.3);
            }

            [data-theme="dark"] #members-glass-wrapper .nexus-smart-btn-secondary {
                background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(34, 211, 238, 0.15));
                color: #67e8f9;
                border-color: rgba(6, 182, 212, 0.4);
            }

            #members-glass-wrapper .nexus-smart-btn-secondary:hover {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                color: white;
                border-color: transparent;
                transform: translateY(-2px);
            }

            #members-glass-wrapper .nexus-smart-btn-outline {
                background: rgba(255, 255, 255, 0.5);
                color: var(--htb-text-main);
                border-color: rgba(6, 182, 212, 0.3);
            }

            [data-theme="dark"] #members-glass-wrapper .nexus-smart-btn-outline {
                background: rgba(15, 23, 42, 0.5);
                border-color: rgba(6, 182, 212, 0.4);
            }

            #members-glass-wrapper .nexus-smart-btn-outline:hover {
                background: linear-gradient(135deg, #06b6d4, #22d3ee);
                color: white;
                border-color: transparent;
                transform: translateY(-2px);
            }

            @media (max-width: 640px) {
                #members-glass-wrapper .nexus-welcome-hero {
                    padding: 20px 16px;
                    border-radius: 16px;
                }

                #members-glass-wrapper .nexus-welcome-title {
                    font-size: 1.35rem;
                }

                #members-glass-wrapper .nexus-smart-buttons {
                    gap: 8px;
                }

                #members-glass-wrapper .nexus-smart-btn {
                    padding: 10px 14px;
                    font-size: 0.8rem;
                }
            }
        </style>

        <!-- Smart Welcome Hero Section -->
        <div class="nexus-welcome-hero">
            <h1 class="nexus-welcome-title">Community Directory</h1>
            <?php if (\Nexus\Services\MemberRankingService::isEnabled()): ?>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(6, 182, 212, 0.15)); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 20px; padding: 4px 12px; font-size: 0.75rem; color: #10b981; margin-bottom: 8px;">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>CommunityRank Active</span>
                </div>
            <?php endif; ?>
            <p class="nexus-welcome-subtitle">Browse and connect with members of your local community. Find neighbors, discover skills, and build meaningful connections.</p>

            <div class="nexus-smart-buttons">
                <a href="<?= $basePath ?>/members" class="nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-users"></i>
                    <span>All Members</span>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $basePath ?>/members?filter=nearby" class="nexus-smart-btn nexus-smart-btn-secondary" id="nearby-btn">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Nearby</span>
                    </a>
                    <a href="<?= $basePath ?>/members?filter=new" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-star"></i>
                        <span>New Members</span>
                    </a>
                <?php endif; ?>
                <a href="<?= $basePath ?>/members?filter=active" class="nexus-smart-btn nexus-smart-btn-outline">
                    <i class="fa-solid fa-fire"></i>
                    <span>Most Active</span>
                </a>
                <?php
                // Show federation link if federation is enabled for this tenant
                $tenantId = \Nexus\Core\TenantContext::getId();
                $federationEnabled = \Nexus\Services\FederationFeatureService::isGloballyEnabled()
                    && \Nexus\Services\FederationFeatureService::isTenantWhitelisted($tenantId)
                    && \Nexus\Services\FederationFeatureService::isTenantFederationEnabled($tenantId);
                if ($federationEnabled && isset($_SESSION['user_id'])):
                ?>
                    <a href="<?= $basePath ?>/federation/members" class="nexus-smart-btn nexus-smart-btn-outline" style="border-color: rgba(139, 92, 246, 0.3); color: #8b5cf6;">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Partner Timebanks</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nearby Controls Panel -->
        <div class="nearby-controls<?= ($nearbyMode ?? false) ? ' active' : '' ?>" id="nearby-controls">
            <div class="nearby-header">
                <div class="nearby-title">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    <span>Find Nearby Members</span>
                </div>
                <div class="location-status detecting" id="location-status">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Loading your location...</span>
                </div>
            </div>

            <div class="radius-control">
                <span class="radius-label">Search Radius:</span>
                <div class="radius-slider-wrapper">
                    <input type="range"
                        class="radius-slider"
                        id="radius-slider"
                        min="1"
                        max="100"
                        value="25"
                        step="1">
                    <span class="radius-value" id="radius-value">25 km</span>
                </div>
            </div>
        </div>

        <!-- Glass Search Card -->
        <div class="glass-search-card">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 10px;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0;">Find Members</h2>
                    <span id="members-count" style="font-size: 0.9rem; font-weight: 600; color: var(--htb-text-muted);">
                        Showing <?= count($members) ?> of <?= $total_members ?? count($members) ?> members
                        <!-- DEBUG: TenantID=<?= \Nexus\Core\TenantContext::getId() ?> -->
                    </span>
                </div>

                <div style="position: relative; width: 100%;">
                    <input type="text" id="member-search" placeholder="Search by name, bio, location, skills..."
                        class="glass-search-input">
                    <i class="fa-solid fa-search" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1rem;"></i>
                    <div id="search-spinner" class="spinner" style="display: none; position: absolute; right: 18px; top: 50%; transform: translateY(-50%);"></div>
                </div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <i class="fa-solid fa-users" style="color: #06b6d4; font-size: 1.1rem;"></i>
            <h2>Community Members</h2>
        </div>

        <!-- Members Grid -->
        <div id="members-grid" class="members-grid">
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $member): ?>
                    <?php $memberOrgRoles = $orgLeadership[$member['id']] ?? []; ?>
                    <?= render_glass_member_card($member, $basePath, $memberOrgRoles) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">👥</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No members found</h3>
                    <p style="color: var(--htb-text-muted);">Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Infinite Scroll Sentinel & Spinner -->
        <div id="infinite-scroll-trigger" style="height: 20px; margin-bottom: 20px;"></div>

        <div id="load-more-spinner" style="display: none; justify-content: center; margin-bottom: 40px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: var(--htb-accent);"></i>
        </div>

    </div><!-- #members-glass-wrapper -->
</div>

<?php
function render_glass_member_card($member, $basePath, $orgRoles = [])
{
    ob_start();

    // Ensure required fields exist - prefer display_name, then construct from first/last, then fallback
    $memberName = $member['display_name'] ?? $member['name'] ?? null;
    if (empty($memberName) || trim($memberName) === '' || trim($memberName) === ' ') {
        // Construct from first_name and last_name
        $firstName = trim($member['first_name'] ?? '');
        $lastName = trim($member['last_name'] ?? '');
        $memberName = trim($firstName . ' ' . $lastName);
    }
    if (empty($memberName) || trim($memberName) === '') {
        $memberName = 'Member';
    }
    $fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=0891b2&color=fff&size=200';

    // Use database avatar_url if it exists and is not empty, otherwise use fallback
    $avatarUrl = (!empty($member['avatar_url']) && trim($member['avatar_url']) !== '') ? $member['avatar_url'] : $fallbackUrl;

    $profileUrl = $basePath . '/profile/' . $member['id'] . '?from=members';

    // Check online status - active within 5 minutes
    $memberLastActive = $member['last_active_at'] ?? null;
    $isMemberOnline = $memberLastActive && (strtotime($memberLastActive) > strtotime('-5 minutes'));
?>
    <a href="<?= $profileUrl ?>" class="glass-member-card">
        <div class="card-body">
            <div class="avatar-container">
                <div class="avatar-ring"></div>
                <?= webp_avatar($avatarUrl, $memberName, 80) ?>
                <?php if ($isMemberOnline): ?>
                    <span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>
                <?php endif; ?>
            </div>

            <h3 class="member-name">
                <?= htmlspecialchars($memberName) ?>
            </h3>

            <?php if (!empty($orgRoles)): ?>
                <div class="member-org-roles" style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-bottom: 10px;">
                    <?php foreach (array_slice($orgRoles, 0, 2) as $org): ?>
                        <span class="org-role-badge <?= $org['role'] ?>" style="
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 0.7rem;
                    font-weight: 600;
                    <?php if ($org['role'] === 'owner'): ?>
                    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.15));
                    color: #b45309;
                    <?php else: ?>
                    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
                    color: #7c3aed;
                    <?php endif; ?>
                ">
                            <i class="fa-solid <?= $org['role'] === 'owner' ? 'fa-crown' : 'fa-shield' ?>" style="font-size: 0.65rem;"></i>
                            <?= htmlspecialchars(strlen($org['org_name']) > 15 ? substr($org['org_name'], 0, 15) . '...' : $org['org_name']) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($orgRoles) > 2): ?>
                        <span style="font-size: 0.7rem; color: var(--htb-text-muted);">+<?= count($orgRoles) - 2 ?> more</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="member-location">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($member['location'] ?: 'Unknown Location') ?>
            </div>

            <div class="member-since">
                <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
                Member since <?= !empty($member['created_at']) ? date('M Y', strtotime($member['created_at'])) : 'Unknown' ?>
            </div>

            <span class="view-profile-btn">
                View Profile
            </span>
        </div>
    </a>
<?php
    return ob_get_clean();
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('member-search');
        const grid = document.getElementById('members-grid');
        const countLabel = document.getElementById('members-count');
        const spinner = document.getElementById('search-spinner');
        const nearbyBtn = document.getElementById('nearby-btn');
        const nearbyControls = document.getElementById('nearby-controls');
        const radiusSlider = document.getElementById('radius-slider');
        const radiusValue = document.getElementById('radius-value');
        const locationStatus = document.getElementById('location-status');

        let debounceTimer;
        let nearbyMode = <?= $nearbyMode ? 'true' : 'false' ?>;
        let userLocation = null;

        // Search functionality
        searchInput.addEventListener('keyup', function(e) {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            spinner.style.display = 'block';

            debounceTimer = setTimeout(() => {
                fetchMembers(query);
            }, 300);
        });

        // Radius slider
        if (radiusSlider) {
            radiusSlider.addEventListener('input', function() {
                radiusValue.textContent = this.value + ' km';
            });

            radiusSlider.addEventListener('change', function() {
                if (nearbyMode) {
                    fetchNearbyMembers();
                }
            });
        }

        // Initialize nearby mode if active
        if (nearbyMode) {
            if (nearbyBtn) nearbyBtn.classList.add('nearby-active');

            // IMPORTANT: Don't auto-fetch on page load
            if (window.location.search.includes('filter=nearby')) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, '', cleanUrl);
            }
        }

        function updateLocationStatus(type, message) {
            if (!locationStatus) return;
            locationStatus.className = 'location-status ' + type;
            let icon = '';
            if (type === 'detecting') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
            else if (type === 'success') icon = '<i class="fa-solid fa-check-circle"></i>';
            else if (type === 'error') icon = '<i class="fa-solid fa-exclamation-circle"></i>';
            locationStatus.innerHTML = icon + '<span>' + message + '</span>';
        }

        function fetchNearbyMembers() {
            const radius = radiusSlider ? radiusSlider.value : 25;
            spinner.style.display = 'block';
            updateLocationStatus('detecting', 'Finding nearby members...');

            const url = window.location.pathname + '?filter=nearby&radius=' + radius;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('HTTP error ' + res.status);
                    }
                    return res.json();
                })
                .then(data => {
                    if (data && data.success) {
                        userLocation = data.userLocation;
                        updateLocationStatus('success', 'Using: ' + (userLocation || 'Your profile location'));
                        renderNearbyGrid(data.data || []);
                        countLabel.textContent = `Showing ${(data.data || []).length} nearby members`;
                    } else if (data && data.error === 'no_location') {
                        showNoLocationError();
                    } else if (data && data.error) {
                        showLoginError();
                    } else {
                        console.error('Unexpected response:', data);
                        renderNearbyGrid([]);
                        updateLocationStatus('success', 'No nearby members with coordinates');
                    }
                    spinner.style.display = 'none';
                })
                .catch(err => {
                    console.error('Nearby fetch error:', err);
                    spinner.style.display = 'none';
                    updateLocationStatus('error', 'Connection error');
                    // Show helpful error in grid
                    grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">⚠️</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Connection Error</h3>
                    <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Unable to load nearby members. Please try again.</p>
                    <button onclick="location.reload()" class="view-profile-btn" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #06b6d4, #22d3ee); color: white; border: none; cursor: pointer;">
                        <i class="fa-solid fa-refresh" style="margin-right: 6px;"></i> Retry
                    </button>
                </div>
            `;
                });
        }

        function fetchMembers(query) {
            const url = query.length > 0 ?
                window.location.pathname + '?q=' + encodeURIComponent(query) :
                window.location.pathname;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (res.headers.get('content-type')?.includes('json')) {
                        return res.json();
                    } else {
                        if (query.length === 0) {
                            window.location.reload();
                            return null;
                        }
                        return null;
                    }
                })
                .then(data => {
                    if (!data) return;
                    renderGrid(data.data);
                    countLabel.textContent = `Showing ${data.data.length} members`;
                    spinner.style.display = 'none';
                })
                .catch(err => {
                    console.error(err);
                    spinner.style.display = 'none';
                });
        }

        function renderNearbyGrid(members) {
            // Clear grid and render results
            grid.innerHTML = '';

            if (members.length === 0) {
                grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📍</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No nearby members found</h3>
                    <p style="color: var(--htb-text-muted);">Try increasing your search radius or check back later.</p>
                </div>
            `;
                return;
            }

            const basePath = "<?= $basePath ?>";

            members.forEach(member => {
                // Ensure member name exists - prefer display_name, then construct from first/last, then fallback
                const memberName = member.display_name || member.name || (member.first_name || member.last_name ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : 'Member') || 'Member';
                const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=0891b2&color=fff&size=200`;
                // Use fallback ONLY if avatar_url is null/empty, not for any other case
                let avatarUrl = (member.avatar_url && member.avatar_url.trim() !== '') ? member.avatar_url : fallbackUrl;

                const dateStr = member.created_at ? new Date(member.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                }) : 'Unknown';
                const distance = member.distance_km ? parseFloat(member.distance_km).toFixed(1) : null;
                const profileUrl = `${basePath}/profile/${member.id}?from=members`;

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? `<span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>` : '';

                const card = document.createElement('a');
                card.href = profileUrl;
                card.className = 'glass-member-card';
                card.innerHTML = `
                <div class="card-body">
                    <div class="avatar-container">
                        <div class="avatar-ring"></div>
                        <img src="${avatarUrl}" 
                             onerror="this.onerror=null; this.src='${fallbackUrl}'"
                             alt="${escapeHtml(memberName)}" 
                             class="avatar-img" 
                             loading="lazy">
                        ${onlineIndicator}
                    </div>
                    <h3 class="member-name">${escapeHtml(memberName)}</h3>
                    ${distance ? `<div class="distance-badge"><i class="fa-solid fa-route"></i> ${distance} km away</div>` : ''}
                    <div class="member-location">
                        <i class="fa-solid fa-location-dot"></i>
                        ${escapeHtml(member.location || 'Unknown Location')}
                    </div>
                    <div class="member-since">
                        <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
                        Member since ${dateStr}
                    </div>
                    <span class="view-profile-btn">View Profile</span>
                </div>
            `;
                grid.appendChild(card);
            });
        }

        function renderGrid(members, append = false) {
            if (!append) {
                grid.innerHTML = '';
            }

            if (members.length === 0 && !append) {
                grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🔍</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No members found</h3>
                    <p style="color: var(--htb-text-muted);">Try a different search term.</p>
                </div>
            `;
                return;
            }

            const basePath = "<?= $basePath ?>";

            members.forEach(member => {
                // Ensure member name exists - prefer display_name, then construct from first/last, then fallback
                const memberName = member.display_name || member.name || (member.first_name || member.last_name ? `${member.first_name || ''} ${member.last_name || ''}`.trim() : 'Member') || 'Member';
                const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=0891b2&color=fff&size=200`;
                // Use fallback ONLY if avatar_url is null/empty, not for any other case
                let avatarUrl = (member.avatar_url && member.avatar_url.trim() !== '') ? member.avatar_url : fallbackUrl;
                const dateStr = member.created_at ? new Date(member.created_at).toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                }) : 'Unknown';
                const profileUrl = `${basePath}/profile/${member.id}?from=members`;

                // Check online status - active within 5 minutes
                const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
                const onlineIndicator = isOnline ? `<span class="online-indicator" style="position:absolute;bottom:4px;right:4px;width:16px;height:16px;background:#10b981;border:3px solid white;border-radius:50%;box-shadow:0 2px 4px rgba(16,185,129,0.4);" title="Active now"></span>` : '';

                const card = document.createElement('a');
                card.href = profileUrl;
                card.className = 'glass-member-card';
                card.innerHTML = `
                <div class="card-body">
                    <div class="avatar-container">
                        <div class="avatar-ring"></div>
                        <img src="${avatarUrl}" 
                             onerror="this.onerror=null; this.src='${fallbackUrl}'"
                             alt="${escapeHtml(memberName)}" 
                             class="avatar-img" 
                             loading="lazy">
                        ${onlineIndicator}
                    </div>
                    <h3 class="member-name">${escapeHtml(memberName)}</h3>
                    <div class="member-location">
                        <i class="fa-solid fa-location-dot"></i>
                        ${escapeHtml(member.location || 'Unknown Location')}
                    </div>
                    <div class="member-since">
                        <i class="fa-solid fa-calendar" style="margin-right: 6px;"></i>
                        Member since ${dateStr}
                    </div>
                    <span class="view-profile-btn">View Profile</span>
                </div>
            `;
                grid.appendChild(card);
            });
        }

        function showNoLocationError() {
            updateLocationStatus('error', 'No location in profile');
            grid.innerHTML = `
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📍</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">Location Required</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Please add your location in your profile settings to find nearby members.</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= $basePath ?>/profile/edit" class="view-profile-btn" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #06b6d4, #22d3ee); color: white; border: none;">
                        <i class="fa-solid fa-user-pen" style="margin-right: 6px;"></i> Edit Profile
                    </a>
                    <a href="<?= $basePath ?>/members" class="view-profile-btn" style="display: inline-block; padding: 12px 24px;">
                        View All Members
                    </a>
                </div>
            </div>
        `;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // ============================================
        // INFINITE SCROLL
        // ============================================
        const infiniteScrollTrigger = document.getElementById('infinite-scroll-trigger');
        const loadMoreSpinner = document.getElementById('load-more-spinner');
        let currentOffset = <?= count($members) ?>;
        const initialTotal = <?= $total_members ?? count($members) ?>;
        const batchSize = 30;
        let isLoading = false;
        let hasMore = currentOffset < initialTotal;

        if (infiniteScrollTrigger && hasMore) {
            const observerOptions = {
                root: null, // viewport
                rootMargin: '100px', // fetch before user hits absolute bottom
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && hasMore && !isLoading) {
                        loadMoreMembers();
                    }
                });
            }, observerOptions);

            observer.observe(infiniteScrollTrigger);

            function loadMoreMembers() {
                isLoading = true;
                loadMoreSpinner.style.display = 'flex';

                const url = window.location.pathname + '?loadmore=1&offset=' + currentOffset + '&limit=' + batchSize;

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        loadMoreSpinner.style.display = 'none';
                        isLoading = false;

                        if (data && data.data && data.data.length > 0) {
                            // Append new members
                            renderGrid(data.data, true);

                            currentOffset += data.data.length;
                            countLabel.textContent = `Showing ${currentOffset} of ${data.total} members`;

                            if (!data.hasMore) {
                                hasMore = false;
                                observer.disconnect(); // Stop observing if no more data
                            }
                        } else {
                            hasMore = false;
                            observer.disconnect();
                        }
                    })
                    .catch(err => {
                        console.error('Infinite scroll error:', err);
                        loadMoreSpinner.style.display = 'none';
                        isLoading = false;
                    });
            }
        }
    });
</script>

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
    document.querySelectorAll('.htb-btn, button, .nexus-smart-btn, .view-profile-btn, .page-btn').forEach(btn => {
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
            meta.content = '#06b6d4';
            document.head.appendChild(meta);
        }

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', isDark ? '#0f172a' : '#06b6d4');
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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>