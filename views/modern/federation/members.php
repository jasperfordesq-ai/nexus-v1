<?php
// Federation Members Directory - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Members";
$pageSubtitle = "Connect with members from partner timebanks";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Members - Partner Timebank Directory');
Nexus\Core\SEO::setDescription('Browse and connect with members from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$members = $members ?? [];
$partnerTenants = $partnerTenants ?? [];
$filters = $filters ?? [];
$partnerships = $partnerships ?? [];
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-glass-wrapper">

        <style>
            /* ============================================
               FEDERATION MEMBERS - Glassmorphism Theme
               Purple/Violet for Federation Features
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

            /* Touch Targets */
            .htb-btn,
            button,
            .view-profile-btn,
            input[type="text"],
            select {
                min-height: 44px;
            }

            input[type="text"],
            select {
                font-size: 16px !important;
            }

            /* Animated Gradient Background */
            #federation-glass-wrapper::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: -1;
                pointer-events: none;
            }

            [data-theme="light"] #federation-glass-wrapper::before {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.08) 0%,
                        rgba(168, 85, 247, 0.08) 25%,
                        rgba(192, 132, 252, 0.06) 50%,
                        rgba(216, 180, 254, 0.06) 75%,
                        rgba(233, 213, 255, 0.08) 100%);
                background-size: 400% 400%;
                animation: gradientShift 15s ease infinite;
            }

            [data-theme="dark"] #federation-glass-wrapper::before {
                background: radial-gradient(circle at 20% 30%,
                        rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                    radial-gradient(circle at 80% 70%,
                        rgba(168, 85, 247, 0.12) 0%, transparent 50%);
            }

            @keyframes gradientShift {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }

            /* Welcome Hero */
            #federation-glass-wrapper .nexus-welcome-hero {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                padding: 28px 24px;
                margin-bottom: 30px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] #federation-glass-wrapper .nexus-welcome-hero {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            #federation-glass-wrapper .nexus-welcome-title {
                font-size: 1.75rem;
                font-weight: 800;
                background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0 0 8px 0;
                line-height: 1.2;
            }

            #federation-glass-wrapper .nexus-welcome-subtitle {
                font-size: 0.95rem;
                color: var(--htb-text-muted);
                margin: 0 0 20px 0;
                line-height: 1.5;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }

            /* Federation Badge */
            .federation-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.15));
                border: 1px solid rgba(139, 92, 246, 0.3);
                border-radius: 20px;
                padding: 4px 12px;
                font-size: 0.75rem;
                color: #8b5cf6;
                margin-bottom: 12px;
            }

            [data-theme="dark"] .federation-badge {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(168, 85, 247, 0.2));
                color: #a78bfa;
            }

            /* Glass Search/Filter Card */
            #federation-glass-wrapper .glass-search-card {
                margin-top: 20px !important;
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

            [data-theme="dark"] #federation-glass-wrapper .glass-search-card {
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

            /* Glass Search Input */
            #federation-glass-wrapper .glass-search-input {
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

            #federation-glass-wrapper .glass-search-input:focus {
                border-color: rgba(139, 92, 246, 0.6);
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15),
                    0 8px 24px rgba(139, 92, 246, 0.2);
                outline: none;
                background: rgba(255, 255, 255, 0.8);
            }

            [data-theme="dark"] #federation-glass-wrapper .glass-search-input {
                background: rgba(15, 23, 42, 0.7);
                border: 2px solid rgba(255, 255, 255, 0.2);
                color: #f8fafc;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
            }

            [data-theme="dark"] #federation-glass-wrapper .glass-search-input:focus {
                border-color: rgba(139, 92, 246, 0.5);
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2),
                    0 8px 24px rgba(139, 92, 246, 0.3);
                background: rgba(15, 23, 42, 0.85);
            }

            /* Filter Select */
            #federation-glass-wrapper .glass-select {
                padding: 12px 16px;
                border: 2px solid rgba(255, 255, 255, 0.4);
                border-radius: 12px;
                font-size: 0.95rem;
                background: rgba(255, 255, 255, 0.6);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                color: var(--htb-text-main);
                cursor: pointer;
                transition: all 0.3s ease;
            }

            #federation-glass-wrapper .glass-select:focus {
                border-color: rgba(139, 92, 246, 0.6);
                outline: none;
            }

            [data-theme="dark"] #federation-glass-wrapper .glass-select {
                background: rgba(15, 23, 42, 0.7);
                border: 2px solid rgba(255, 255, 255, 0.2);
                color: #f8fafc;
            }

            /* Section Headers */
            #federation-glass-wrapper .section-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 40px 0 24px 0;
                padding-left: 16px;
                border-left: 4px solid #8b5cf6;
            }

            #federation-glass-wrapper .section-header h2 {
                font-size: 1.35rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0;
            }

            /* Glass Member Cards */
            #federation-glass-wrapper a.glass-member-card {
                display: block;
                text-decoration: none;
                color: inherit;
                cursor: pointer;
            }

            #federation-glass-wrapper .glass-member-card {
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

            #federation-glass-wrapper .glass-member-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2),
                    0 0 0 1px rgba(139, 92, 246, 0.2),
                    inset 0 1px 0 rgba(255, 255, 255, 0.6);
            }

            [data-theme="dark"] #federation-glass-wrapper .glass-member-card {
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

            [data-theme="dark"] #federation-glass-wrapper .glass-member-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                    0 0 0 1px rgba(139, 92, 246, 0.25),
                    0 0 80px rgba(168, 85, 247, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.12);
            }

            /* Card Body */
            #federation-glass-wrapper .card-body {
                padding: 30px 24px;
            }

            /* Avatar Container */
            #federation-glass-wrapper .avatar-container {
                position: relative;
                width: 110px;
                height: 110px;
                margin: 0 auto 20px auto;
            }

            #federation-glass-wrapper .avatar-ring {
                position: absolute;
                inset: -4px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa, #c4b5fd);
                border-radius: 50%;
                animation: avatarPulse 3s ease-in-out infinite;
            }

            @keyframes avatarPulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.8; transform: scale(1.02); }
            }

            #federation-glass-wrapper .avatar-img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                border: 4px solid white;
                position: relative;
                z-index: 1;
                box-sizing: border-box;
            }

            [data-theme="dark"] #federation-glass-wrapper .avatar-img {
                border-color: #1e293b;
            }

            /* Member Name */
            #federation-glass-wrapper .member-name {
                font-size: 1.3rem;
                font-weight: 700;
                margin: 0 0 8px 0;
                color: var(--htb-text-main);
            }

            /* Tenant Badge */
            #federation-glass-wrapper .tenant-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
                color: #7c3aed;
                margin-bottom: 12px;
            }

            [data-theme="dark"] #federation-glass-wrapper .tenant-badge {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.15));
                color: #a78bfa;
            }

            /* Service Reach Badge */
            #federation-glass-wrapper .reach-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 600;
                margin-bottom: 10px;
            }

            #federation-glass-wrapper .reach-badge.local {
                background: rgba(234, 179, 8, 0.15);
                color: #ca8a04;
            }

            #federation-glass-wrapper .reach-badge.remote {
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
            }

            #federation-glass-wrapper .reach-badge.travel {
                background: rgba(59, 130, 246, 0.15);
                color: #2563eb;
            }

            [data-theme="dark"] #federation-glass-wrapper .reach-badge.local {
                background: rgba(234, 179, 8, 0.25);
                color: #fbbf24;
            }

            [data-theme="dark"] #federation-glass-wrapper .reach-badge.remote {
                background: rgba(16, 185, 129, 0.25);
                color: #34d399;
            }

            [data-theme="dark"] #federation-glass-wrapper .reach-badge.travel {
                background: rgba(59, 130, 246, 0.25);
                color: #60a5fa;
            }

            /* Location */
            #federation-glass-wrapper .member-location {
                color: var(--htb-text-muted);
                font-size: 0.95rem;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            #federation-glass-wrapper .member-location i {
                color: #8b5cf6;
            }

            /* Bio */
            #federation-glass-wrapper .member-bio {
                font-size: 0.9rem;
                color: var(--htb-text-muted);
                line-height: 1.5;
                margin-bottom: 15px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            /* View Profile Button */
            #federation-glass-wrapper .view-profile-btn {
                display: block;
                width: 100%;
                padding: 12px 0;
                border: 2px solid rgba(139, 92, 246, 0.3);
                border-radius: 12px;
                color: #8b5cf6;
                font-weight: 700;
                text-decoration: none;
                transition: all 0.3s ease;
                background: transparent;
            }

            #federation-glass-wrapper .view-profile-btn:hover {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                border-color: transparent;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            }

            [data-theme="dark"] #federation-glass-wrapper .view-profile-btn {
                border-color: rgba(139, 92, 246, 0.4);
                color: #a78bfa;
            }

            /* Grid Layout */
            #federation-glass-wrapper .members-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 30px;
            }

            /* Glass Empty State */
            #federation-glass-wrapper .glass-empty-state {
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

            [data-theme="dark"] #federation-glass-wrapper .glass-empty-state {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            }

            /* Spinner */
            #federation-glass-wrapper .spinner {
                width: 20px;
                height: 20px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-top-color: #8b5cf6;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Responsive */
            @media (max-width: 768px) {
                .htb-container-full {
                    padding: 0 15px 100px 15px;
                }

                #federation-glass-wrapper .nexus-welcome-hero {
                    padding: 20px 16px;
                }

                #federation-glass-wrapper .nexus-welcome-title {
                    font-size: 1.35rem;
                }

                #federation-glass-wrapper .filter-row {
                    flex-direction: column;
                }
            }

            @media (max-width: 640px) {
                #federation-glass-wrapper .glass-member-card:hover {
                    transform: none;
                }
            }

            /* Filter Row */
            .filter-row {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-top: 15px;
            }

            .filter-row > div {
                flex: 1;
                min-width: 150px;
            }

            .filter-label {
                display: block;
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--htb-text-muted);
                margin-bottom: 6px;
            }

            /* Advanced Filters Toggle */
            .advanced-toggle {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: rgba(139, 92, 246, 0.1);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 10px;
                color: #8b5cf6;
                font-size: 0.9rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .advanced-toggle:hover {
                background: rgba(139, 92, 246, 0.15);
            }

            .advanced-toggle i {
                transition: transform 0.2s ease;
            }

            .advanced-toggle.open i {
                transform: rotate(180deg);
            }

            /* Advanced Filters Panel */
            .advanced-filters {
                display: none;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid rgba(139, 92, 246, 0.15);
            }

            .advanced-filters.open {
                display: block;
            }

            /* Skills Input & Tags */
            .skills-input-container {
                position: relative;
            }

            .skills-input {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid rgba(255, 255, 255, 0.4);
                border-radius: 12px;
                font-size: 0.95rem;
                background: rgba(255, 255, 255, 0.6);
                color: var(--htb-text-main);
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .skills-input {
                background: rgba(15, 23, 42, 0.7);
                border-color: rgba(255, 255, 255, 0.2);
                color: #f8fafc;
            }

            .skills-input:focus {
                border-color: rgba(139, 92, 246, 0.6);
                outline: none;
            }

            .skills-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 12px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 100;
                display: none;
                margin-top: 4px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            }

            [data-theme="dark"] .skills-suggestions {
                background: rgba(15, 23, 42, 0.95);
                border-color: rgba(255, 255, 255, 0.15);
            }

            .skills-suggestions.show {
                display: block;
            }

            .skill-suggestion {
                padding: 10px 16px;
                cursor: pointer;
                font-size: 0.9rem;
                color: var(--htb-text-main);
                transition: background 0.15s ease;
            }

            .skill-suggestion:hover {
                background: rgba(139, 92, 246, 0.1);
            }

            .skill-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 10px;
            }

            .skill-tag {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1));
                border: 1px solid rgba(139, 92, 246, 0.3);
                border-radius: 20px;
                font-size: 0.85rem;
                color: #8b5cf6;
            }

            [data-theme="dark"] .skill-tag {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(168, 85, 247, 0.15));
                color: #a78bfa;
            }

            .skill-tag .remove {
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.15s ease;
            }

            .skill-tag .remove:hover {
                opacity: 1;
            }

            /* Popular Skills */
            .popular-skills {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 10px;
            }

            .popular-skill {
                padding: 6px 12px;
                background: rgba(139, 92, 246, 0.08);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 20px;
                font-size: 0.8rem;
                color: var(--htb-text-muted);
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .popular-skill:hover {
                background: rgba(139, 92, 246, 0.15);
                color: #8b5cf6;
                border-color: rgba(139, 92, 246, 0.3);
            }

            /* Toggle Checkboxes */
            .filter-toggles {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .filter-toggle {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                font-size: 0.9rem;
                color: var(--htb-text-muted);
            }

            .filter-toggle input[type="checkbox"] {
                width: 18px;
                height: 18px;
                accent-color: #8b5cf6;
                cursor: pointer;
            }

            .filter-toggle:hover {
                color: #8b5cf6;
            }

            /* Search Stats Bar */
            .search-stats {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                padding: 12px 16px;
                background: rgba(139, 92, 246, 0.06);
                border-radius: 12px;
                margin-bottom: 20px;
            }

            .stat-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .stat-item i {
                color: #8b5cf6;
            }

            .stat-item strong {
                color: var(--htb-text-main);
            }

            /* Sort dropdown */
            .sort-select {
                padding: 8px 12px;
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.6);
                font-size: 0.85rem;
                color: var(--htb-text-main);
                cursor: pointer;
            }

            [data-theme="dark"] .sort-select {
                background: rgba(15, 23, 42, 0.7);
                border-color: rgba(255, 255, 255, 0.15);
                color: #f8fafc;
            }

            /* Active Filters Badge */
            .active-filters-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                background: #8b5cf6;
                color: white;
                border-radius: 10px;
                font-size: 0.75rem;
                font-weight: 700;
                margin-left: 6px;
            }

            /* Clear filters button */
            .clear-filters {
                padding: 6px 12px;
                background: transparent;
                border: 1px solid rgba(239, 68, 68, 0.3);
                border-radius: 8px;
                color: #ef4444;
                font-size: 0.85rem;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .clear-filters:hover {
                background: rgba(239, 68, 68, 0.1);
            }

            /* Back link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }
        </style>

        <!-- Back to Members -->
        <a href="<?= $basePath ?>/members" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Local Members
        </a>

        <!-- Welcome Hero -->
        <div class="nexus-welcome-hero">
            <div class="federation-badge">
                <i class="fa-solid fa-network-wired"></i>
                <span>Federation Network</span>
            </div>
            <h1 class="nexus-welcome-title">Federated Members</h1>
            <p class="nexus-welcome-subtitle">
                Discover members from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
                Connect, collaborate, and exchange services across communities.
            </p>
        </div>

        <?php
        // Calculate active filters count
        $activeFiltersCount = 0;
        if (!empty($filters['tenant_id'])) $activeFiltersCount++;
        if (!empty($filters['service_reach'])) $activeFiltersCount++;
        if (!empty($filters['skills'])) $activeFiltersCount++;
        if (!empty($filters['location'])) $activeFiltersCount++;
        if (!empty($filters['messaging_enabled'])) $activeFiltersCount++;
        if (!empty($filters['transactions_enabled'])) $activeFiltersCount++;

        $searchStats = $searchStats ?? [];
        $popularSkills = $popularSkills ?? [];
        ?>

        <!-- Search Stats Bar -->
        <?php if (!empty($searchStats) && ($searchStats['total_members'] ?? 0) > 0): ?>
        <div class="search-stats">
            <div class="stat-item">
                <i class="fa-solid fa-users"></i>
                <strong><?= number_format($searchStats['total_members']) ?></strong> federated members
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-laptop-house"></i>
                <strong><?= number_format($searchStats['remote_available']) ?></strong> offer remote services
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-car"></i>
                <strong><?= number_format($searchStats['travel_available']) ?></strong> will travel
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-comments"></i>
                <strong><?= number_format($searchStats['messaging_enabled']) ?></strong> accept messages
            </div>
        </div>
        <?php endif; ?>

        <!-- Search & Filters -->
        <div class="glass-search-card">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0;">
                        <i class="fa-solid fa-search" style="color: #8b5cf6; margin-right: 8px;"></i>
                        Find Federated Members
                    </h2>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <select id="sort-filter" class="sort-select">
                            <option value="name" <?= ($filters['sort'] ?? 'name') === 'name' ? 'selected' : '' ?>>Sort: Name</option>
                            <option value="recent" <?= ($filters['sort'] ?? '') === 'recent' ? 'selected' : '' ?>>Sort: Newest</option>
                            <option value="active" <?= ($filters['sort'] ?? '') === 'active' ? 'selected' : '' ?>>Sort: Most Active</option>
                        </select>
                        <span id="members-count" style="font-size: 0.9rem; font-weight: 600; color: var(--htb-text-muted);">
                            <?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> found
                        </span>
                    </div>
                </div>

                <div style="position: relative; width: 100%;">
                    <input type="text"
                           id="federation-search"
                           placeholder="Search by name, skills, bio..."
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                           class="glass-search-input">
                    <i class="fa-solid fa-search" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1rem;"></i>
                    <div id="search-spinner" class="spinner" style="display: none; position: absolute; right: 18px; top: 50%; transform: translateY(-50%);"></div>
                </div>

                <div class="filter-row">
                    <div>
                        <label class="filter-label">Partner Timebank</label>
                        <select id="tenant-filter" class="glass-select" style="width: 100%;">
                            <option value="">All Partners</option>
                            <?php foreach ($partnerTenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tenant['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="filter-label">Service Reach</label>
                        <select id="reach-filter" class="glass-select" style="width: 100%;">
                            <option value="">Any</option>
                            <option value="remote_ok" <?= ($filters['service_reach'] ?? '') === 'remote_ok' ? 'selected' : '' ?>>Remote Services</option>
                            <option value="travel_ok" <?= ($filters['service_reach'] ?? '') === 'travel_ok' ? 'selected' : '' ?>>Will Travel</option>
                        </select>
                    </div>
                </div>

                <!-- Advanced Filters Toggle -->
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="advanced-toggle" id="advanced-toggle">
                        <i class="fa-solid fa-sliders"></i>
                        Advanced Filters
                        <?php if ($activeFiltersCount > 0): ?>
                        <span class="active-filters-count"><?= $activeFiltersCount ?></span>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <?php if ($activeFiltersCount > 0): ?>
                    <button type="button" class="clear-filters" id="clear-filters">
                        <i class="fa-solid fa-times"></i> Clear Filters
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Advanced Filters Panel -->
                <div class="advanced-filters" id="advanced-filters">
                    <div class="filter-row">
                        <!-- Skills Filter -->
                        <div style="flex: 2;">
                            <label class="filter-label">Skills</label>
                            <div class="skills-input-container">
                                <input type="text"
                                       id="skills-input"
                                       placeholder="Type to search skills..."
                                       class="skills-input"
                                       autocomplete="off">
                                <div id="skills-suggestions" class="skills-suggestions"></div>
                            </div>
                            <div id="skill-tags" class="skill-tags">
                                <?php
                                $selectedSkills = [];
                                if (!empty($filters['skills'])) {
                                    $selectedSkills = is_array($filters['skills'])
                                        ? $filters['skills']
                                        : array_map('trim', explode(',', $filters['skills']));
                                }
                                foreach ($selectedSkills as $skill):
                                    if (!empty($skill)):
                                ?>
                                <span class="skill-tag" data-skill="<?= htmlspecialchars($skill) ?>">
                                    <?= htmlspecialchars($skill) ?>
                                    <i class="fa-solid fa-times remove"></i>
                                </span>
                                <?php endif; endforeach; ?>
                            </div>
                            <?php if (!empty($popularSkills)): ?>
                            <div class="popular-skills">
                                <span style="font-size: 0.75rem; color: var(--htb-text-muted);">Popular:</span>
                                <?php foreach (array_slice($popularSkills, 0, 8) as $skill): ?>
                                <span class="popular-skill" data-skill="<?= htmlspecialchars($skill) ?>">
                                    <?= htmlspecialchars($skill) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Location Filter -->
                        <div>
                            <label class="filter-label">Location</label>
                            <input type="text"
                                   id="location-filter"
                                   placeholder="City or region..."
                                   value="<?= htmlspecialchars($filters['location'] ?? '') ?>"
                                   class="skills-input">
                        </div>
                    </div>

                    <!-- Availability Toggles -->
                    <div style="margin-top: 15px;">
                        <label class="filter-label">Availability</label>
                        <div class="filter-toggles">
                            <label class="filter-toggle">
                                <input type="checkbox" id="messaging-filter" <?= !empty($filters['messaging_enabled']) ? 'checked' : '' ?>>
                                <i class="fa-solid fa-comments"></i>
                                Accepts Messages
                            </label>
                            <label class="filter-toggle">
                                <input type="checkbox" id="transactions-filter" <?= !empty($filters['transactions_enabled']) ? 'checked' : '' ?>>
                                <i class="fa-solid fa-exchange-alt"></i>
                                Accepts Transactions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <i class="fa-solid fa-globe" style="color: #8b5cf6; font-size: 1.1rem;"></i>
            <h2>Members from Partner Timebanks</h2>
        </div>

        <!-- Members Grid -->
        <div id="members-grid" class="members-grid">
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $member): ?>
                    <?= renderFederatedMemberCard($member, $basePath) ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">
                        <i class="fa-solid fa-network-wired" style="color: #8b5cf6;"></i>
                    </div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No federated members found</h3>
                    <p style="color: var(--htb-text-muted);">
                        <?php if (empty($partnerTenants)): ?>
                            Your timebank doesn't have any active partnerships yet.
                        <?php else: ?>
                            Try adjusting your search filters or check back later.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Load More / Infinite Scroll Trigger -->
        <div id="infinite-scroll-trigger" style="height: 20px; margin-bottom: 20px;"></div>
        <div id="load-more-spinner" style="display: none; justify-content: center; margin-bottom: 40px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; color: #8b5cf6;"></i>
        </div>

    </div><!-- #federation-glass-wrapper -->
</div>

<?php
function renderFederatedMemberCard($member, $basePath)
{
    ob_start();

    $memberName = $member['name'] ?? 'Member';
    $fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=8b5cf6&color=fff&size=200';
    $avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;
    $profileUrl = $basePath . '/federation/members/' . $member['id'];

    $reachClass = '';
    $reachLabel = '';
    $reachIcon = '';
    switch ($member['service_reach'] ?? 'local_only') {
        case 'remote_ok':
            $reachClass = 'remote';
            $reachLabel = 'Remote OK';
            $reachIcon = 'fa-laptop-house';
            break;
        case 'travel_ok':
            $reachClass = 'travel';
            $reachLabel = 'Will Travel';
            $reachIcon = 'fa-car';
            break;
        default:
            $reachClass = 'local';
            $reachLabel = 'Local Only';
            $reachIcon = 'fa-location-dot';
    }
?>
    <a href="<?= $profileUrl ?>" class="glass-member-card">
        <div class="card-body">
            <div class="avatar-container">
                <div class="avatar-ring"></div>
                <img src="<?= htmlspecialchars($avatarUrl) ?>"
                     onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                     loading="lazy"
                     alt="<?= htmlspecialchars($memberName) ?>"
                     class="avatar-img">
            </div>

            <h3 class="member-name"><?= htmlspecialchars($memberName) ?></h3>

            <div class="tenant-badge">
                <i class="fa-solid fa-building"></i>
                <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
            </div>

            <div class="reach-badge <?= $reachClass ?>">
                <i class="fa-solid <?= $reachIcon ?>"></i>
                <?= $reachLabel ?>
            </div>

            <?php if (!empty($member['location'])): ?>
                <div class="member-location">
                    <i class="fa-solid fa-map-marker-alt"></i>
                    <?= htmlspecialchars($member['location']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($member['bio'])): ?>
                <div class="member-bio">
                    <?= htmlspecialchars($member['bio']) ?>
                </div>
            <?php endif; ?>

            <span class="view-profile-btn">
                <i class="fa-solid fa-user" style="margin-right: 6px;"></i>
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
    const searchInput = document.getElementById('federation-search');
    const tenantFilter = document.getElementById('tenant-filter');
    const reachFilter = document.getElementById('reach-filter');
    const sortFilter = document.getElementById('sort-filter');
    const locationFilter = document.getElementById('location-filter');
    const messagingFilter = document.getElementById('messaging-filter');
    const transactionsFilter = document.getElementById('transactions-filter');
    const skillsInput = document.getElementById('skills-input');
    const skillsSuggestions = document.getElementById('skills-suggestions');
    const skillTags = document.getElementById('skill-tags');
    const advancedToggle = document.getElementById('advanced-toggle');
    const advancedFilters = document.getElementById('advanced-filters');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const grid = document.getElementById('members-grid');
    const countLabel = document.getElementById('members-count');
    const spinner = document.getElementById('search-spinner');
    const loadMoreSpinner = document.getElementById('load-more-spinner');

    let debounceTimer;
    let skillsDebounceTimer;
    let currentOffset = <?= count($members) ?>;
    let isLoading = false;
    let hasMore = <?= count($members) >= 30 ? 'true' : 'false' ?>;
    let selectedSkills = <?= json_encode($selectedSkills ?? []) ?>;

    // Advanced filters toggle
    advancedToggle.addEventListener('click', function() {
        this.classList.toggle('open');
        advancedFilters.classList.toggle('open');
    });

    // Open advanced filters if any are active
    <?php if ($activeFiltersCount > 0): ?>
    advancedToggle.classList.add('open');
    advancedFilters.classList.add('open');
    <?php endif; ?>

    // Clear filters
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            tenantFilter.value = '';
            reachFilter.value = '';
            sortFilter.value = 'name';
            locationFilter.value = '';
            messagingFilter.checked = false;
            transactionsFilter.checked = false;
            selectedSkills = [];
            renderSkillTags();
            currentOffset = 0;
            hasMore = true;
            fetchMembers();
        });
    }

    // Search functionality
    searchInput.addEventListener('keyup', function(e) {
        clearTimeout(debounceTimer);
        spinner.style.display = 'block';
        debounceTimer = setTimeout(() => {
            currentOffset = 0;
            hasMore = true;
            fetchMembers();
        }, 300);
    });

    // Filter change handlers
    tenantFilter.addEventListener('change', triggerSearch);
    reachFilter.addEventListener('change', triggerSearch);
    sortFilter.addEventListener('change', triggerSearch);
    messagingFilter.addEventListener('change', triggerSearch);
    transactionsFilter.addEventListener('change', triggerSearch);

    // Location filter with debounce
    locationFilter.addEventListener('keyup', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerSearch, 400);
    });

    function triggerSearch() {
        currentOffset = 0;
        hasMore = true;
        fetchMembers();
    }

    // Skills autocomplete
    skillsInput.addEventListener('keyup', function(e) {
        const query = this.value.trim();

        if (e.key === 'Enter' && query) {
            addSkill(query);
            this.value = '';
            skillsSuggestions.classList.remove('show');
            return;
        }

        clearTimeout(skillsDebounceTimer);
        if (query.length < 2) {
            skillsSuggestions.classList.remove('show');
            return;
        }

        skillsDebounceTimer = setTimeout(() => {
            fetch('<?= $basePath ?>/federation/members/skills?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.skills.length > 0) {
                        skillsSuggestions.innerHTML = data.skills
                            .filter(s => !selectedSkills.includes(s))
                            .map(s => `<div class="skill-suggestion" data-skill="${escapeHtml(s)}">${escapeHtml(s)}</div>`)
                            .join('');
                        skillsSuggestions.classList.add('show');
                    } else {
                        skillsSuggestions.classList.remove('show');
                    }
                });
        }, 200);
    });

    // Hide suggestions on blur
    skillsInput.addEventListener('blur', function() {
        setTimeout(() => skillsSuggestions.classList.remove('show'), 200);
    });

    // Handle skill suggestion click
    skillsSuggestions.addEventListener('click', function(e) {
        if (e.target.classList.contains('skill-suggestion')) {
            addSkill(e.target.dataset.skill);
            skillsInput.value = '';
            skillsSuggestions.classList.remove('show');
        }
    });

    // Handle popular skill click
    document.querySelectorAll('.popular-skill').forEach(el => {
        el.addEventListener('click', function() {
            addSkill(this.dataset.skill);
        });
    });

    // Handle skill tag removal
    skillTags.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove') || e.target.closest('.remove')) {
            const tag = e.target.closest('.skill-tag');
            if (tag) {
                removeSkill(tag.dataset.skill);
            }
        }
    });

    function addSkill(skill) {
        skill = skill.trim();
        if (skill && !selectedSkills.includes(skill)) {
            selectedSkills.push(skill);
            renderSkillTags();
            triggerSearch();
        }
    }

    function removeSkill(skill) {
        selectedSkills = selectedSkills.filter(s => s !== skill);
        renderSkillTags();
        triggerSearch();
    }

    function renderSkillTags() {
        skillTags.innerHTML = selectedSkills.map(skill => `
            <span class="skill-tag" data-skill="${escapeHtml(skill)}">
                ${escapeHtml(skill)}
                <i class="fa-solid fa-times remove"></i>
            </span>
        `).join('');
    }

    function fetchMembers(append = false) {
        const params = new URLSearchParams({
            q: searchInput.value,
            tenant: tenantFilter.value,
            reach: reachFilter.value,
            sort: sortFilter.value,
            location: locationFilter.value,
            messaging: messagingFilter.checked ? '1' : '',
            transactions: transactionsFilter.checked ? '1' : '',
            skills: selectedSkills.join(','),
            offset: append ? currentOffset : 0,
            limit: 30
        });

        if (!append) {
            spinner.style.display = 'block';
        }

        fetch('<?= $basePath ?>/federation/members/api?' + params.toString())
            .then(res => res.json())
            .then(data => {
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;

                if (data.success) {
                    if (append) {
                        appendMembers(data.members);
                        currentOffset += data.members.length;
                    } else {
                        renderGrid(data.members);
                        currentOffset = data.members.length;
                    }
                    hasMore = data.hasMore;
                    countLabel.textContent = `${append ? currentOffset : data.members.length} member${data.members.length !== 1 ? 's' : ''} found`;
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                spinner.style.display = 'none';
                loadMoreSpinner.style.display = 'none';
                isLoading = false;
            });
    }

    function renderGrid(members) {
        if (members.length === 0) {
            grid.innerHTML = `
                <div class="glass-empty-state">
                    <div style="font-size: 4rem; margin-bottom: 20px;">
                        <i class="fa-solid fa-search" style="color: #8b5cf6;"></i>
                    </div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No members found</h3>
                    <p style="color: var(--htb-text-muted);">Try adjusting your search or filters.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = '';
        members.forEach(member => {
            grid.appendChild(createMemberCard(member));
        });
    }

    function appendMembers(members) {
        members.forEach(member => {
            grid.appendChild(createMemberCard(member));
        });
    }

    function createMemberCard(member) {
        const basePath = "<?= $basePath ?>";
        const memberName = member.name || 'Member';
        const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(memberName)}&background=8b5cf6&color=fff&size=200`;
        const avatarUrl = member.avatar_url || fallbackUrl;
        const profileUrl = `${basePath}/federation/members/${member.id}`;

        let reachClass = 'local';
        let reachLabel = 'Local Only';
        let reachIcon = 'fa-location-dot';

        if (member.service_reach === 'remote_ok') {
            reachClass = 'remote';
            reachLabel = 'Remote OK';
            reachIcon = 'fa-laptop-house';
        } else if (member.service_reach === 'travel_ok') {
            reachClass = 'travel';
            reachLabel = 'Will Travel';
            reachIcon = 'fa-car';
        }

        const card = document.createElement('a');
        card.href = profileUrl;
        card.className = 'glass-member-card';
        card.innerHTML = `
            <div class="card-body">
                <div class="avatar-container">
                    <div class="avatar-ring"></div>
                    <img src="${escapeHtml(avatarUrl)}"
                         onerror="this.onerror=null; this.src='${fallbackUrl}'"
                         loading="lazy"
                         alt="${escapeHtml(memberName)}"
                         class="avatar-img">
                </div>
                <h3 class="member-name">${escapeHtml(memberName)}</h3>
                <div class="tenant-badge">
                    <i class="fa-solid fa-building"></i>
                    ${escapeHtml(member.tenant_name || 'Partner Timebank')}
                </div>
                <div class="reach-badge ${reachClass}">
                    <i class="fa-solid ${reachIcon}"></i>
                    ${reachLabel}
                </div>
                ${member.location ? `
                    <div class="member-location">
                        <i class="fa-solid fa-map-marker-alt"></i>
                        ${escapeHtml(member.location)}
                    </div>
                ` : ''}
                ${member.bio ? `<div class="member-bio">${escapeHtml(member.bio)}</div>` : ''}
                <span class="view-profile-btn">
                    <i class="fa-solid fa-user" style="margin-right: 6px;"></i>
                    View Profile
                </span>
            </div>
        `;
        return card;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Infinite scroll
    const infiniteScrollTrigger = document.getElementById('infinite-scroll-trigger');
    if (infiniteScrollTrigger) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    isLoading = true;
                    loadMoreSpinner.style.display = 'flex';
                    fetchMembers(true);
                }
            });
        }, { rootMargin: '100px', threshold: 0.1 });
        observer.observe(infiniteScrollTrigger);
    }
});

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
