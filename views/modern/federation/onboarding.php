<?php
// Federation Onboarding Wizard - Mobile-First Design
$pageTitle = $pageTitle ?? "Get Started with Federation";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
$userSettings = $userSettings ?? [];
$userProfile = $userProfile ?? [];
$partnerCount = $partnerCount ?? 0;

// Current settings
$isOptedIn = !empty($userSettings['federation_optin']);
$privacyLevel = $userSettings['privacy_level'] ?? 'discovery';
$serviceReach = $userSettings['service_reach'] ?? 'local_only';
$showLocation = !empty($userSettings['show_location_federated']);
$showSkills = !empty($userSettings['show_skills_federated']);
$messagingEnabled = !empty($userSettings['messaging_enabled_federated']);
$transactionsEnabled = !empty($userSettings['transactions_enabled_federated']);

// User display name
$displayName = $userProfile['name'] ?? trim(($userProfile['first_name'] ?? '') . ' ' . ($userProfile['last_name'] ?? '')) ?: 'Member';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="onboarding-wrapper">
    <style>
        /* Mobile-First Onboarding Wizard */
        * { box-sizing: border-box; }

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
            transition: transform 0.3s ease;
        }

        .offline-banner.visible {
            transform: translateY(0);
        }

        #onboarding-wrapper {
            min-height: 100vh;
            padding: 80px 16px 120px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        [data-theme="dark"] #onboarding-wrapper {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        @media (min-width: 768px) {
            #onboarding-wrapper {
                padding: 100px 24px 60px;
            }
        }

        .wizard-container {
            max-width: 500px;
            margin: 0 auto;
        }

        /* Progress Bar */
        .wizard-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }

        .progress-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            border: 2px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }

        .progress-step.active {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border-color: #8b5cf6;
            transform: scale(1.1);
        }

        .progress-step.completed {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .progress-line {
            width: 40px;
            height: 3px;
            background: rgba(139, 92, 246, 0.2);
            border-radius: 2px;
            transition: background 0.3s ease;
        }

        .progress-line.completed {
            background: #10b981;
        }

        /* Wizard Card */
        .wizard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 32px 24px;
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        [data-theme="dark"] .wizard-card {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Step Content */
        .wizard-step {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .wizard-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
        }

        .step-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--htb-text-main, #1f2937);
            text-align: center;
            margin: 0 0 12px;
        }

        [data-theme="dark"] .step-title {
            color: #f1f5f9;
        }

        .step-desc {
            font-size: 1rem;
            color: var(--htb-text-muted, #6b7280);
            text-align: center;
            margin: 0 0 28px;
            line-height: 1.6;
        }

        /* Option Cards */
        .option-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .option-card {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            background: rgba(139, 92, 246, 0.05);
            border: 2px solid transparent;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .option-card:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .option-card.selected {
            background: rgba(139, 92, 246, 0.1);
            border-color: #8b5cf6;
        }

        [data-theme="dark"] .option-card {
            background: rgba(139, 92, 246, 0.1);
        }

        [data-theme="dark"] .option-card.selected {
            background: rgba(139, 92, 246, 0.2);
        }

        .option-radio {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            transition: all 0.2s ease;
        }

        .option-card.selected .option-radio {
            border-color: #8b5cf6;
            background: #8b5cf6;
        }

        .option-radio::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .option-card.selected .option-radio::after {
            opacity: 1;
        }

        .option-content {
            flex: 1;
        }

        .option-title {
            font-weight: 700;
            color: var(--htb-text-main, #1f2937);
            margin: 0 0 4px;
            font-size: 1rem;
        }

        [data-theme="dark"] .option-title {
            color: #f1f5f9;
        }

        .option-desc {
            font-size: 0.85rem;
            color: var(--htb-text-muted, #6b7280);
            margin: 0;
            line-height: 1.5;
        }

        .option-icon {
            font-size: 1.25rem;
            color: #8b5cf6;
        }

        /* Toggle Switches */
        .toggle-group {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
        }

        .toggle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: rgba(139, 92, 246, 0.05);
            border-radius: 14px;
        }

        [data-theme="dark"] .toggle-item {
            background: rgba(139, 92, 246, 0.1);
        }

        .toggle-label {
            flex: 1;
        }

        .toggle-title {
            font-weight: 600;
            color: var(--htb-text-main, #1f2937);
            margin: 0 0 2px;
            font-size: 0.95rem;
        }

        [data-theme="dark"] .toggle-title {
            color: #f1f5f9;
        }

        .toggle-desc {
            font-size: 0.8rem;
            color: var(--htb-text-muted, #6b7280);
            margin: 0;
        }

        .toggle-switch {
            position: relative;
            width: 52px;
            height: 28px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
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

        .toggle-slider::before {
            position: absolute;
            content: '';
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(24px);
        }

        /* Profile Preview */
        .profile-preview {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05));
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: center;
        }

        .preview-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 12px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            overflow: hidden;
        }

        .preview-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--htb-text-main, #1f2937);
            margin: 0 0 4px;
        }

        [data-theme="dark"] .preview-name {
            color: #f1f5f9;
        }

        .preview-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(139, 92, 246, 0.2);
            color: #7c3aed;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Buttons */
        .wizard-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .wizard-btn {
            flex: 1;
            padding: 16px 24px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 54px;
            -webkit-tap-highlight-color: transparent;
        }

        .wizard-btn-secondary {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .wizard-btn-secondary:hover {
            background: rgba(139, 92, 246, 0.2);
        }

        .wizard-btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
        }

        .wizard-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        .wizard-btn-primary:active {
            transform: translateY(0);
        }

        .wizard-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Success Animation */
        .success-animation {
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 3rem;
            color: white;
            animation: successPop 0.5s ease;
        }

        @keyframes successPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        /* Skip Link */
        .skip-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--htb-text-muted, #6b7280);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .skip-link:hover {
            color: #8b5cf6;
        }

        /* Partner Count Badge */
        .partner-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.1));
            border-radius: 100px;
            margin-bottom: 20px;
        }

        .partner-badge i {
            color: #8b5cf6;
        }

        .partner-badge span {
            font-weight: 700;
            color: var(--htb-text-main, #1f2937);
        }

        [data-theme="dark"] .partner-badge span {
            color: #f1f5f9;
        }

        /* Focus styles */
        .option-card:focus-visible,
        .wizard-btn:focus-visible,
        .toggle-switch input:focus-visible + .toggle-slider {
            outline: 3px solid rgba(139, 92, 246, 0.5);
            outline-offset: 2px;
        }
    </style>

    <div class="wizard-container">
        <!-- Progress Bar -->
        <div class="wizard-progress">
            <div class="progress-step active" data-step="1">1</div>
            <div class="progress-line" data-line="1"></div>
            <div class="progress-step" data-step="2">2</div>
            <div class="progress-line" data-line="2"></div>
            <div class="progress-step" data-step="3">3</div>
            <div class="progress-line" data-line="3"></div>
            <div class="progress-step" data-step="4"><i class="fa-solid fa-check"></i></div>
        </div>

        <div class="wizard-card">
            <!-- Step 1: Welcome -->
            <div class="wizard-step active" data-step="1">
                <div class="step-icon">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <h1 class="step-title">Connect Beyond Borders</h1>
                <p class="step-desc">
                    Join <?= $partnerCount ?> partner timebank<?= $partnerCount !== 1 ? 's' : '' ?> and connect with members from communities around the world.
                </p>

                <div class="partner-badge">
                    <i class="fa-solid fa-handshake"></i>
                    <span><?= $partnerCount ?> Partner Timebank<?= $partnerCount !== 1 ? 's' : '' ?> Available</span>
                </div>

                <div class="option-group">
                    <div class="option-card selected" data-value="yes" tabindex="0" role="button">
                        <div class="option-radio"></div>
                        <div class="option-content">
                            <p class="option-title">Yes, let's get started!</p>
                            <p class="option-desc">Enable federation and connect with partner communities</p>
                        </div>
                        <i class="fa-solid fa-rocket option-icon"></i>
                    </div>
                    <div class="option-card" data-value="no" tabindex="0" role="button">
                        <div class="option-radio"></div>
                        <div class="option-content">
                            <p class="option-title">Not right now</p>
                            <p class="option-desc">You can enable this later in your settings</p>
                        </div>
                        <i class="fa-solid fa-clock option-icon"></i>
                    </div>
                </div>

                <div class="wizard-buttons">
                    <button class="wizard-btn wizard-btn-primary" id="step1Next">
                        Continue <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <a href="<?= $basePath ?>/federation" class="skip-link">
                    Skip for now
                </a>
            </div>

            <!-- Step 2: Privacy Level -->
            <div class="wizard-step" data-step="2">
                <div class="step-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="step-title">Choose Your Privacy Level</h1>
                <p class="step-desc">
                    Control what partner timebank members can see and do.
                </p>

                <div class="option-group" id="privacyOptions">
                    <div class="option-card" data-value="discovery" tabindex="0" role="button">
                        <div class="option-radio"></div>
                        <div class="option-content">
                            <p class="option-title">Discovery</p>
                            <p class="option-desc">Name, avatar, and bio visible. Browse only.</p>
                        </div>
                        <i class="fa-solid fa-eye option-icon"></i>
                    </div>
                    <div class="option-card selected" data-value="social" tabindex="0" role="button">
                        <div class="option-radio"></div>
                        <div class="option-content">
                            <p class="option-title">Social (Recommended)</p>
                            <p class="option-desc">Plus skills, location, and messaging.</p>
                        </div>
                        <i class="fa-solid fa-comments option-icon"></i>
                    </div>
                    <div class="option-card" data-value="economic" tabindex="0" role="button">
                        <div class="option-radio"></div>
                        <div class="option-content">
                            <p class="option-title">Economic</p>
                            <p class="option-desc">Full access including time credit exchanges.</p>
                        </div>
                        <i class="fa-solid fa-coins option-icon"></i>
                    </div>
                </div>

                <div class="wizard-buttons">
                    <button class="wizard-btn wizard-btn-secondary" id="step2Back">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <button class="wizard-btn wizard-btn-primary" id="step2Next">
                        Continue <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Fine-tune Settings -->
            <div class="wizard-step" data-step="3">
                <div class="step-icon">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <h1 class="step-title">Fine-tune Your Settings</h1>
                <p class="step-desc">
                    Customize exactly what you share with partner timebanks.
                </p>

                <div class="toggle-group">
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title">Show my location</p>
                            <p class="toggle-desc">City/region visible to partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleLocation" <?= $showLocation ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title">Show my skills</p>
                            <p class="toggle-desc">Skills searchable by partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleSkills" <?= $showSkills ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title">Allow messages</p>
                            <p class="toggle-desc">Receive messages from partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleMessaging" <?= $messagingEnabled ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title">Allow transactions</p>
                            <p class="toggle-desc">Exchange time credits across timebanks</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleTransactions" <?= $transactionsEnabled ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Profile Preview -->
                <div class="profile-preview">
                    <div class="preview-avatar">
                        <?php if (!empty($userProfile['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="Avatar">
                        <?php else: ?>
                            <?= strtoupper(substr($displayName, 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <p class="preview-name"><?= htmlspecialchars($displayName) ?></p>
                    <div class="preview-badge">
                        <i class="fa-solid fa-globe"></i>
                        Federated Member
                    </div>
                </div>

                <div class="wizard-buttons">
                    <button class="wizard-btn wizard-btn-secondary" id="step3Back">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <button class="wizard-btn wizard-btn-primary" id="step3Next">
                        Finish Setup <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </div>

            <!-- Step 4: Success -->
            <div class="wizard-step" data-step="4">
                <div class="success-animation">
                    <div class="success-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h1 class="step-title">You're All Set!</h1>
                    <p class="step-desc">
                        Welcome to the federation! You can now connect with members from partner timebanks.
                    </p>

                    <div class="wizard-buttons" style="flex-direction: column;">
                        <a href="<?= $basePath ?>/federation/members" class="wizard-btn wizard-btn-primary" style="text-decoration: none;">
                            <i class="fa-solid fa-users"></i> Browse Members
                        </a>
                        <a href="<?= $basePath ?>/federation" class="wizard-btn wizard-btn-secondary" style="text-decoration: none;">
                            <i class="fa-solid fa-home"></i> Go to Hub
                        </a>
                    </div>
                </div>
            </div>

            <!-- Declined Step -->
            <div class="wizard-step" data-step="declined">
                <div class="step-icon" style="background: linear-gradient(135deg, #64748b, #475569);">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h1 class="step-title">No Problem!</h1>
                <p class="step-desc">
                    You can enable federation anytime in your settings. Your local timebank experience remains unchanged.
                </p>

                <div class="wizard-buttons" style="flex-direction: column;">
                    <a href="<?= $basePath ?>/federation" class="wizard-btn wizard-btn-primary" style="text-decoration: none;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Hub
                    </a>
                    <a href="<?= $basePath ?>/settings?section=federation" class="wizard-btn wizard-btn-secondary" style="text-decoration: none;">
                        <i class="fa-solid fa-cog"></i> Federation Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<canvas class="confetti" id="confetti"></canvas>

<script>
(function() {
    // State
    let currentStep = 1;
    let enableFederation = true;
    let privacyLevel = 'social';

    // Elements
    const steps = document.querySelectorAll('.wizard-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const progressLines = document.querySelectorAll('.progress-line');

    function showStep(stepNum) {
        steps.forEach(s => s.classList.remove('active'));
        const step = document.querySelector(`.wizard-step[data-step="${stepNum}"]`);
        if (step) step.classList.add('active');

        // Update progress
        progressSteps.forEach((ps, i) => {
            const num = i + 1;
            ps.classList.remove('active', 'completed');
            if (num < stepNum) {
                ps.classList.add('completed');
                ps.innerHTML = '<i class="fa-solid fa-check"></i>';
            } else if (num === stepNum) {
                ps.classList.add('active');
                if (num < 4) ps.textContent = num;
            } else {
                if (num < 4) ps.textContent = num;
            }
        });

        progressLines.forEach((pl, i) => {
            pl.classList.toggle('completed', i + 1 < stepNum);
        });

        currentStep = stepNum;
    }

    // Option card selection
    document.querySelectorAll('.option-group').forEach(group => {
        group.querySelectorAll('.option-card').forEach(card => {
            card.addEventListener('click', () => {
                group.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    });

    // Step 1: Enable federation choice
    document.getElementById('step1Next').addEventListener('click', () => {
        const selected = document.querySelector('.wizard-step[data-step="1"] .option-card.selected');
        enableFederation = selected && selected.dataset.value === 'yes';

        if (enableFederation) {
            showStep(2);
        } else {
            showStep('declined');
        }
    });

    // Step 2: Privacy level
    document.getElementById('step2Back').addEventListener('click', () => showStep(1));
    document.getElementById('step2Next').addEventListener('click', () => {
        const selected = document.querySelector('#privacyOptions .option-card.selected');
        privacyLevel = selected ? selected.dataset.value : 'social';

        // Auto-set toggles based on privacy level
        const toggleLocation = document.getElementById('toggleLocation');
        const toggleSkills = document.getElementById('toggleSkills');
        const toggleMessaging = document.getElementById('toggleMessaging');
        const toggleTransactions = document.getElementById('toggleTransactions');

        if (privacyLevel === 'discovery') {
            toggleLocation.checked = false;
            toggleSkills.checked = false;
            toggleMessaging.checked = false;
            toggleTransactions.checked = false;
        } else if (privacyLevel === 'social') {
            toggleLocation.checked = true;
            toggleSkills.checked = true;
            toggleMessaging.checked = true;
            toggleTransactions.checked = false;
        } else if (privacyLevel === 'economic') {
            toggleLocation.checked = true;
            toggleSkills.checked = true;
            toggleMessaging.checked = true;
            toggleTransactions.checked = true;
        }

        showStep(3);
    });

    // Step 3: Fine-tune and save
    document.getElementById('step3Back').addEventListener('click', () => showStep(2));
    document.getElementById('step3Next').addEventListener('click', async () => {
        const btn = document.getElementById('step3Next');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const data = {
            federation_optin: true,
            privacy_level: privacyLevel,
            service_reach: 'local_only',
            show_location: document.getElementById('toggleLocation').checked,
            show_skills: document.getElementById('toggleSkills').checked,
            messaging_enabled: document.getElementById('toggleMessaging').checked,
            transactions_enabled: document.getElementById('toggleTransactions').checked
        };

        try {
            const response = await fetch('<?= $basePath ?>/federation/onboarding/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= \Nexus\Core\Csrf::getToken() ?>'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showStep(4);
                launchConfetti();
            } else {
                throw new Error(result.error || 'Failed to save');
            }
        } catch (error) {
            alert('Failed to save settings. Please try again.');
            btn.disabled = false;
            btn.innerHTML = 'Finish Setup <i class="fa-solid fa-check"></i>';
        }
    });

    // Confetti animation
    function launchConfetti() {
        const canvas = document.getElementById('confetti');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const pieces = [];
        const colors = ['#8b5cf6', '#7c3aed', '#10b981', '#f59e0b', '#ec4899'];

        for (let i = 0; i < 150; i++) {
            pieces.push({
                x: canvas.width / 2,
                y: canvas.height / 2,
                vx: (Math.random() - 0.5) * 20,
                vy: (Math.random() - 0.5) * 20 - 10,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 8 + 4,
                rotation: Math.random() * 360
            });
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            let active = false;
            pieces.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.5;
                p.rotation += 5;

                if (p.y < canvas.height + 50) {
                    active = true;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation * Math.PI / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                    ctx.restore();
                }
            });

            if (active) {
                requestAnimationFrame(animate);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        animate();
    }

    // Offline indicator
    const banner = document.getElementById('offlineBanner');
    if (banner) {
        window.addEventListener('online', () => banner.classList.remove('visible'));
        window.addEventListener('offline', () => banner.classList.add('visible'));
        if (!navigator.onLine) banner.classList.add('visible');
    }
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
