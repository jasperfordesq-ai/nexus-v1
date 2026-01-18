<?php
// Federation Settings - User Preferences Page
$pageTitle = $pageTitle ?? "Federation Settings";
$pageSubtitle = "Manage your federation preferences";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Extract data
$userSettings = $userSettings ?? [];
$userProfile = $userProfile ?? [];
$partnerCount = $partnerCount ?? 0;
$stats = $stats ?? [];

$isOptedIn = !empty($userSettings['federation_optin']);
$privacyLevel = $userSettings['privacy_level'] ?? 'discovery';
$serviceReach = $userSettings['service_reach'] ?? 'local_only';
?>

<div class="htb-container-full">
    <div id="federation-settings-wrapper">

        <style>
            /* ============================================
               FEDERATION SETTINGS PAGE
               Purple/Violet Theme
               ============================================ */

            #federation-settings-wrapper {
                padding: 120px 20px 60px;
                max-width: 800px;
                margin: 0 auto;
            }

            @media (max-width: 768px) {
                #federation-settings-wrapper {
                    padding: 100px 16px 100px;
                }
            }

            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Hero Section */
            .fed-hero {
                text-align: center;
                margin-bottom: 32px;
                animation: fadeInUp 0.5s ease-out;
            }

            .fed-hero-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #8b5cf6, #6366f1);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
                font-size: 2rem;
                color: white;
                box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
            }

            .fed-hero h1 {
                font-size: 2.25rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 12px;
            }

            [data-theme="dark"] .fed-hero h1 {
                color: #f1f5f9;
            }

            .fed-hero-subtitle {
                font-size: 1.1rem;
                color: var(--htb-text-secondary, #6b7280);
                max-width: 600px;
                margin: 0 auto;
                line-height: 1.6;
            }

            [data-theme="dark"] .fed-hero-subtitle {
                color: #94a3b8;
            }

            /* Header */
            .fed-settings-header {
                text-align: center;
                margin-bottom: 32px;
            }

            .fed-settings-header h1 {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 8px;
            }

            [data-theme="dark"] .fed-settings-header h1 {
                color: #f1f5f9;
            }

            .fed-settings-header p {
                color: var(--htb-text-secondary, #6b7280);
                margin: 0;
            }

            [data-theme="dark"] .fed-settings-header p {
                color: #94a3b8;
            }

            .fed-settings-links {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 16px;
            }

            .fed-settings-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                background: rgba(139, 92, 246, 0.1);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 20px;
                color: #8b5cf6;
                font-size: 0.85rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .fed-settings-link:hover {
                background: rgba(139, 92, 246, 0.2);
                transform: translateY(-2px);
            }

            [data-theme="dark"] .fed-settings-link {
                background: rgba(139, 92, 246, 0.15);
                color: #a78bfa;
            }

            /* Status Banner */
            .fed-status-banner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 20px 24px;
                border-radius: 16px;
                margin-bottom: 24px;
            }

            .fed-status-banner.enabled {
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
                border: 1px solid rgba(16, 185, 129, 0.2);
            }

            .fed-status-banner.disabled {
                background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
                border: 1px solid rgba(239, 68, 68, 0.2);
            }

            .fed-status-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .fed-status-icon {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
            }

            .fed-status-banner.enabled .fed-status-icon {
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
            }

            .fed-status-banner.disabled .fed-status-icon {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
            }

            .fed-status-text h3 {
                font-size: 1rem;
                font-weight: 700;
                margin: 0 0 2px;
                color: var(--htb-text-main, #1f2937);
            }

            [data-theme="dark"] .fed-status-text h3 {
                color: #f1f5f9;
            }

            .fed-status-text p {
                font-size: 0.85rem;
                color: var(--htb-text-secondary, #6b7280);
                margin: 0;
            }

            [data-theme="dark"] .fed-status-text p {
                color: #94a3b8;
            }

            .fed-status-toggle {
                padding: 10px 20px;
                border-radius: 10px;
                font-weight: 600;
                font-size: 0.9rem;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                min-height: 44px;
            }

            .fed-status-banner.enabled .fed-status-toggle {
                background: rgba(239, 68, 68, 0.1);
                color: #ef4444;
            }

            .fed-status-banner.enabled .fed-status-toggle:hover {
                background: rgba(239, 68, 68, 0.2);
            }

            .fed-status-banner.disabled .fed-status-toggle {
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
            }

            .fed-status-banner.disabled .fed-status-toggle:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            }

            @media (max-width: 500px) {
                .fed-status-banner {
                    flex-direction: column;
                    text-align: center;
                }

                .fed-status-info {
                    flex-direction: column;
                }

                .fed-status-toggle {
                    width: 100%;
                }
            }

            /* Settings Card */
            .fed-settings-card {
                background: rgba(255, 255, 255, 0.8);
                border: 1px solid rgba(139, 92, 246, 0.1);
                border-radius: 20px;
                padding: 24px;
                margin-bottom: 20px;
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }

            [data-theme="dark"] .fed-settings-card {
                background: rgba(30, 41, 59, 0.8);
                border-color: rgba(139, 92, 246, 0.15);
            }

            .fed-settings-card h2 {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 4px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            [data-theme="dark"] .fed-settings-card h2 {
                color: #f1f5f9;
            }

            .fed-settings-card h2 i {
                color: #8b5cf6;
            }

            .fed-settings-card > p {
                font-size: 0.9rem;
                color: var(--htb-text-secondary, #6b7280);
                margin: 0 0 20px;
            }

            [data-theme="dark"] .fed-settings-card > p {
                color: #94a3b8;
            }

            /* Privacy Level Selector */
            .fed-privacy-options {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .fed-privacy-option {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 16px;
                border-radius: 12px;
                border: 2px solid transparent;
                background: rgba(139, 92, 246, 0.03);
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .fed-privacy-option:hover {
                background: rgba(139, 92, 246, 0.08);
            }

            .fed-privacy-option.selected {
                border-color: #8b5cf6;
                background: rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] .fed-privacy-option {
                background: rgba(139, 92, 246, 0.05);
            }

            [data-theme="dark"] .fed-privacy-option:hover {
                background: rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] .fed-privacy-option.selected {
                background: rgba(139, 92, 246, 0.15);
            }

            .fed-privacy-option input {
                display: none;
            }

            .fed-privacy-radio {
                width: 22px;
                height: 22px;
                border-radius: 50%;
                border: 2px solid #d1d5db;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 2px;
                transition: all 0.2s ease;
            }

            [data-theme="dark"] .fed-privacy-radio {
                border-color: #4b5563;
            }

            .fed-privacy-option.selected .fed-privacy-radio {
                border-color: #8b5cf6;
                background: #8b5cf6;
            }

            .fed-privacy-option.selected .fed-privacy-radio::after {
                content: '';
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: white;
            }

            .fed-privacy-content h4 {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 4px;
            }

            [data-theme="dark"] .fed-privacy-content h4 {
                color: #f1f5f9;
            }

            .fed-privacy-content p {
                font-size: 0.85rem;
                color: var(--htb-text-secondary, #6b7280);
                margin: 0;
                line-height: 1.5;
            }

            [data-theme="dark"] .fed-privacy-content p {
                color: #94a3b8;
            }

            .fed-privacy-badge {
                display: inline-block;
                padding: 2px 8px;
                font-size: 0.7rem;
                font-weight: 600;
                border-radius: 100px;
                background: rgba(139, 92, 246, 0.1);
                color: #8b5cf6;
                margin-left: 8px;
            }

            /* Toggle Settings */
            .fed-toggle-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .fed-toggle-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 12px 0;
                border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            }

            .fed-toggle-item:last-child {
                border-bottom: none;
            }

            .fed-toggle-info h4 {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
                margin: 0 0 2px;
            }

            [data-theme="dark"] .fed-toggle-info h4 {
                color: #f1f5f9;
            }

            .fed-toggle-info p {
                font-size: 0.8rem;
                color: var(--htb-text-secondary, #6b7280);
                margin: 0;
            }

            [data-theme="dark"] .fed-toggle-info p {
                color: #94a3b8;
            }

            /* Toggle Switch */
            .fed-toggle-switch {
                position: relative;
                width: 52px;
                height: 28px;
                flex-shrink: 0;
            }

            .fed-toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .fed-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #d1d5db;
                border-radius: 28px;
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .fed-toggle-slider {
                background: #4b5563;
            }

            .fed-toggle-slider::before {
                position: absolute;
                content: '';
                height: 22px;
                width: 22px;
                left: 3px;
                bottom: 3px;
                background: white;
                border-radius: 50%;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .fed-toggle-switch input:checked + .fed-toggle-slider {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            }

            .fed-toggle-switch input:checked + .fed-toggle-slider::before {
                transform: translateX(24px);
            }

            .fed-toggle-switch input:focus-visible + .fed-toggle-slider {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Service Reach */
            .fed-reach-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 12px;
            }

            .fed-reach-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                padding: 16px;
                border-radius: 12px;
                border: 2px solid transparent;
                background: rgba(139, 92, 246, 0.03);
                cursor: pointer;
                transition: all 0.2s ease;
                text-align: center;
            }

            .fed-reach-option:hover {
                background: rgba(139, 92, 246, 0.08);
            }

            .fed-reach-option.selected {
                border-color: #8b5cf6;
                background: rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] .fed-reach-option {
                background: rgba(139, 92, 246, 0.05);
            }

            .fed-reach-option input {
                display: none;
            }

            .fed-reach-option i {
                font-size: 1.5rem;
                color: #8b5cf6;
            }

            .fed-reach-option span {
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--htb-text-main, #1f2937);
            }

            [data-theme="dark"] .fed-reach-option span {
                color: #f1f5f9;
            }

            /* Stats Summary */
            .fed-stats-summary {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            @media (max-width: 400px) {
                .fed-stats-summary {
                    grid-template-columns: 1fr;
                }
            }

            .fed-stat-item {
                text-align: center;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border-radius: 12px;
            }

            .fed-stat-value {
                font-size: 1.5rem;
                font-weight: 800;
                color: #8b5cf6;
            }

            .fed-stat-label {
                font-size: 0.8rem;
                color: var(--htb-text-secondary, #6b7280);
                margin-top: 4px;
            }

            [data-theme="dark"] .fed-stat-label {
                color: #94a3b8;
            }

            /* Save Button */
            .fed-save-section {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 32px;
            }

            .fed-save-btn {
                padding: 14px 32px;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                min-height: 48px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .fed-save-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            .fed-save-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }

            .fed-back-btn {
                padding: 14px 24px;
                background: transparent;
                color: var(--htb-text-secondary, #6b7280);
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 12px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                min-height: 48px;
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .fed-back-btn:hover {
                background: rgba(139, 92, 246, 0.05);
                border-color: rgba(139, 92, 246, 0.3);
            }

            /* Toast */
            .fed-toast {
                position: fixed;
                bottom: 100px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                padding: 14px 24px;
                background: #1f2937;
                color: white;
                border-radius: 12px;
                font-weight: 500;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                z-index: 10000;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            .fed-toast.success {
                background: linear-gradient(135deg, #10b981, #059669);
            }

            .fed-toast.error {
                background: linear-gradient(135deg, #ef4444, #dc2626);
            }

            .fed-toast.visible {
                opacity: 1;
                visibility: visible;
                transform: translateX(-50%) translateY(0);
            }

            /* Focus Styles */
            .fed-privacy-option:focus-within,
            .fed-reach-option:focus-within {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }
        </style>

        <!-- Hero Section -->
        <div class="fed-hero">
            <div class="fed-hero-icon">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <h1>Federation Settings</h1>
            <p class="fed-hero-subtitle">
                Control your privacy, visibility, and how you appear to members from partner timebanks.
            </p>
        </div>

        <?php $currentPage = 'settings'; $userOptedIn = $isOptedIn; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

        <!-- Status Banner -->
        <div class="fed-status-banner <?= $isOptedIn ? 'enabled' : 'disabled' ?>">
            <div class="fed-status-info">
                <div class="fed-status-icon">
                    <i class="fa-solid <?= $isOptedIn ? 'fa-check' : 'fa-eye-slash' ?>"></i>
                </div>
                <div class="fed-status-text">
                    <h3>Federation is <?= $isOptedIn ? 'Enabled' : 'Disabled' ?></h3>
                    <p><?= $isOptedIn
                        ? 'Your profile is visible to ' . $partnerCount . ' partner timebank' . ($partnerCount !== 1 ? 's' : '')
                        : 'Your profile is hidden from partner timebanks' ?></p>
                </div>
            </div>
            <button class="fed-status-toggle" id="statusToggle">
                <?= $isOptedIn ? 'Disable Federation' : 'Enable Federation' ?>
            </button>
        </div>

        <form id="settingsForm">
            <!-- Privacy Level -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-shield-halved"></i> Privacy Level</h2>
                <p>Choose how much of your profile to share with partner timebanks</p>

                <div class="fed-privacy-options">
                    <label class="fed-privacy-option <?= $privacyLevel === 'discovery' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="discovery" <?= $privacyLevel === 'discovery' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Discovery Only</h4>
                            <p>Only your name and avatar are visible. Good for browsing without sharing details.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'social' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="social" <?= $privacyLevel === 'social' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Social <span class="fed-privacy-badge">Recommended</span></h4>
                            <p>Share your skills, bio, and location. Enables messaging with partner members.</p>
                        </div>
                    </label>

                    <label class="fed-privacy-option <?= $privacyLevel === 'economic' ? 'selected' : '' ?>">
                        <input type="radio" name="privacy_level" value="economic" <?= $privacyLevel === 'economic' ? 'checked' : '' ?>>
                        <span class="fed-privacy-radio"></span>
                        <div class="fed-privacy-content">
                            <h4>Economic</h4>
                            <p>Full profile sharing plus ability to send/receive time credits across timebanks.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Fine-tune Settings -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-sliders"></i> Visibility Options</h2>
                <p>Fine-tune what information is shared</p>

                <div class="fed-toggle-list">
                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show in Federated Search</h4>
                            <p>Appear in search results for partner timebank members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="appear_in_search" <?= !empty($userSettings['appear_in_federated_search']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Profile Visible</h4>
                            <p>Allow partner members to view your full profile</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="profile_visible" <?= !empty($userSettings['profile_visible_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show Location</h4>
                            <p>Display your city/region to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_location" <?= !empty($userSettings['show_location_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Show Skills</h4>
                            <p>Display your skills and services to partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="show_skills" <?= !empty($userSettings['show_skills_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Receive Messages</h4>
                            <p>Allow partner members to send you messages</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="messaging_enabled" <?= !empty($userSettings['messaging_enabled_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="fed-toggle-item">
                        <div class="fed-toggle-info">
                            <h4>Accept Transactions</h4>
                            <p>Allow receiving time credits from partner members</p>
                        </div>
                        <label class="fed-toggle-switch">
                            <input type="checkbox" name="transactions_enabled" <?= !empty($userSettings['transactions_enabled_federated']) ? 'checked' : '' ?>>
                            <span class="fed-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Service Reach -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-location-dot"></i> Service Reach</h2>
                <p>How far are you willing to travel for exchanges?</p>

                <div class="fed-reach-options">
                    <label class="fed-reach-option <?= $serviceReach === 'local_only' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="local_only" <?= $serviceReach === 'local_only' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-house"></i>
                        <span>Local Only</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'will_travel' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="will_travel" <?= $serviceReach === 'will_travel' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-car"></i>
                        <span>Will Travel</span>
                    </label>

                    <label class="fed-reach-option <?= $serviceReach === 'remote_ok' ? 'selected' : '' ?>">
                        <input type="radio" name="service_reach" value="remote_ok" <?= $serviceReach === 'remote_ok' ? 'checked' : '' ?>>
                        <i class="fa-solid fa-laptop"></i>
                        <span>Remote OK</span>
                    </label>
                </div>
            </div>

            <!-- Activity Summary -->
            <div class="fed-settings-card">
                <h2><i class="fa-solid fa-chart-simple"></i> Your Federation Activity</h2>
                <p>Summary of your cross-timebank interactions</p>

                <div class="fed-stats-summary">
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format(($stats['messages_sent'] ?? 0) + ($stats['messages_received'] ?? 0)) ?></div>
                        <div class="fed-stat-label">Messages Exchanged</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format($stats['transactions_count'] ?? 0) ?></div>
                        <div class="fed-stat-label">Transactions</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= number_format($stats['hours_exchanged'] ?? 0, 1) ?></div>
                        <div class="fed-stat-label">Hours Exchanged</div>
                    </div>
                    <div class="fed-stat-item">
                        <div class="fed-stat-value"><?= $partnerCount ?></div>
                        <div class="fed-stat-label">Partner Timebanks</div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="fed-save-section">
                <a href="<?= $basePath ?>/federation/dashboard" class="fed-back-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back
                </a>
                <button type="submit" class="fed-save-btn" id="saveBtn">
                    <i class="fa-solid fa-check"></i>
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Toast notification -->
        <div class="fed-toast" id="toast"></div>

    </div>
</div>

<script>
(function() {
    const form = document.getElementById('settingsForm');
    const saveBtn = document.getElementById('saveBtn');
    const statusToggle = document.getElementById('statusToggle');
    const toast = document.getElementById('toast');
    const csrfToken = '<?= \Nexus\Core\Csrf::token() ?>';
    let isOptedIn = <?= $isOptedIn ? 'true' : 'false' ?>;

    // Privacy level selection
    document.querySelectorAll('.fed-privacy-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-privacy-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
    });

    // Service reach selection
    document.querySelectorAll('.fed-reach-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.fed-reach-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
    });

    // Show toast
    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.className = 'fed-toast ' + type + ' visible';
        setTimeout(() => {
            toast.classList.remove('visible');
        }, 3000);
    }

    // Status toggle (enable/disable federation)
    statusToggle.addEventListener('click', async function() {
        const action = isOptedIn ? 'disable' : 'enable';
        const confirmMsg = isOptedIn
            ? 'Are you sure you want to disable federation? Your profile will be hidden from all partner timebanks.'
            : 'Enable federation to make your profile visible to partner timebanks?';

        if (!confirm(confirmMsg)) return;

        this.disabled = true;
        this.textContent = 'Processing...';

        try {
            const response = await fetch('<?= $basePath ?>/federation/settings/' + action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({})
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1000);
                } else {
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                showToast(data.error || 'Failed to update', 'error');
                this.disabled = false;
                this.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
            this.disabled = false;
            this.textContent = isOptedIn ? 'Disable Federation' : 'Enable Federation';
        }
    });

    // Save settings
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const formData = {
            federation_optin: isOptedIn,
            privacy_level: form.querySelector('input[name="privacy_level"]:checked')?.value || 'discovery',
            service_reach: form.querySelector('input[name="service_reach"]:checked')?.value || 'local_only',
            appear_in_search: form.querySelector('input[name="appear_in_search"]').checked,
            profile_visible: form.querySelector('input[name="profile_visible"]').checked,
            show_location: form.querySelector('input[name="show_location"]').checked,
            show_skills: form.querySelector('input[name="show_skills"]').checked,
            messaging_enabled: form.querySelector('input[name="messaging_enabled"]').checked,
            transactions_enabled: form.querySelector('input[name="transactions_enabled"]').checked
        };

        try {
            const response = await fetch('<?= $basePath ?>/federation/settings/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.error || 'Failed to save settings', 'error');
            }
        } catch (error) {
            showToast('Network error. Please try again.', 'error');
        }

        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Save Settings';
    });
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
