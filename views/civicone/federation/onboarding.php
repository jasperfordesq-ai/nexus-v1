<?php
// Federation Onboarding Wizard - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Get Started with Federation";
$hideHero = true;

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
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
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div id="onboarding-wrapper">
    <div class="wizard-container">
        <!-- Progress Bar -->
        <nav class="wizard-progress" aria-label="Setup progress">
            <div class="progress-step active" data-step="1" aria-current="step">
                <span class="visually-hidden">Step </span>1
            </div>
            <div class="progress-line" data-line="1" aria-hidden="true"></div>
            <div class="progress-step" data-step="2">
                <span class="visually-hidden">Step </span>2
            </div>
            <div class="progress-line" data-line="2" aria-hidden="true"></div>
            <div class="progress-step" data-step="3">
                <span class="visually-hidden">Step </span>3
            </div>
            <div class="progress-line" data-line="3" aria-hidden="true"></div>
            <div class="progress-step" data-step="4">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <span class="visually-hidden">Complete</span>
            </div>
        </nav>

        <main class="wizard-card" role="main">
            <!-- Step 1: Welcome -->
            <section class="wizard-step active" data-step="1" aria-labelledby="step1-title">
                <div class="step-icon" aria-hidden="true">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <h1 id="step1-title" class="step-title">Connect Beyond Borders</h1>
                <p class="step-desc">
                    Join <?= $partnerCount ?> partner timebank<?= $partnerCount !== 1 ? 's' : '' ?> and connect with members from communities around the world.
                </p>

                <div class="partner-badge" role="status">
                    <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                    <span><?= $partnerCount ?> Partner Timebank<?= $partnerCount !== 1 ? 's' : '' ?> Available</span>
                </div>

                <fieldset class="option-group" role="radiogroup" aria-label="Federation choice">
                    <legend class="visually-hidden">Would you like to enable federation?</legend>

                    <div class="option-card selected" data-value="yes" tabindex="0" role="radio" aria-checked="true">
                        <div class="option-radio" aria-hidden="true"></div>
                        <div class="option-content">
                            <p class="option-title">Yes, let's get started!</p>
                            <p class="option-desc">Enable federation and connect with partner communities</p>
                        </div>
                        <i class="fa-solid fa-rocket option-icon" aria-hidden="true"></i>
                    </div>
                    <div class="option-card" data-value="no" tabindex="0" role="radio" aria-checked="false">
                        <div class="option-radio" aria-hidden="true"></div>
                        <div class="option-content">
                            <p class="option-title">Not right now</p>
                            <p class="option-desc">You can enable this later in your settings</p>
                        </div>
                        <i class="fa-solid fa-clock option-icon" aria-hidden="true"></i>
                    </div>
                </fieldset>

                <div class="wizard-buttons">
                    <button type="button" class="wizard-btn wizard-btn-primary" id="step1Next">
                        Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>

                <a href="<?= $basePath ?>/federation" class="skip-link">
                    Skip for now
                </a>
            </section>

            <!-- Step 2: Privacy Level -->
            <section class="wizard-step" data-step="2" aria-labelledby="step2-title" hidden>
                <div class="step-icon" aria-hidden="true">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 id="step2-title" class="step-title">Choose Your Privacy Level</h1>
                <p class="step-desc">
                    Control what partner timebank members can see and do.
                </p>

                <fieldset class="option-group" id="privacyOptions" role="radiogroup" aria-label="Privacy level">
                    <legend class="visually-hidden">Select your privacy level</legend>

                    <div class="option-card" data-value="discovery" tabindex="0" role="radio" aria-checked="false">
                        <div class="option-radio" aria-hidden="true"></div>
                        <div class="option-content">
                            <p class="option-title">Discovery</p>
                            <p class="option-desc">Name, avatar, and bio visible. Browse only.</p>
                        </div>
                        <i class="fa-solid fa-eye option-icon" aria-hidden="true"></i>
                    </div>
                    <div class="option-card selected" data-value="social" tabindex="0" role="radio" aria-checked="true">
                        <div class="option-radio" aria-hidden="true"></div>
                        <div class="option-content">
                            <p class="option-title">Social (Recommended)</p>
                            <p class="option-desc">Plus skills, location, and messaging.</p>
                        </div>
                        <i class="fa-solid fa-comments option-icon" aria-hidden="true"></i>
                    </div>
                    <div class="option-card" data-value="economic" tabindex="0" role="radio" aria-checked="false">
                        <div class="option-radio" aria-hidden="true"></div>
                        <div class="option-content">
                            <p class="option-title">Economic</p>
                            <p class="option-desc">Full access including time credit exchanges.</p>
                        </div>
                        <i class="fa-solid fa-coins option-icon" aria-hidden="true"></i>
                    </div>
                </fieldset>

                <div class="wizard-buttons">
                    <button type="button" class="wizard-btn wizard-btn-secondary" id="step2Back">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <button type="button" class="wizard-btn wizard-btn-primary" id="step2Next">
                        Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <!-- Step 3: Fine-tune Settings -->
            <section class="wizard-step" data-step="3" aria-labelledby="step3-title" hidden>
                <div class="step-icon" aria-hidden="true">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <h1 id="step3-title" class="step-title">Fine-tune Your Settings</h1>
                <p class="step-desc">
                    Customize exactly what you share with partner timebanks.
                </p>

                <div class="toggle-group" role="group" aria-label="Sharing options">
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title" id="location-label">Show my location</p>
                            <p class="toggle-desc" id="location-desc">City/region visible to partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleLocation" <?= $showLocation ? 'checked' : '' ?> aria-labelledby="location-label" aria-describedby="location-desc">
                            <span class="toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title" id="skills-label">Show my skills</p>
                            <p class="toggle-desc" id="skills-desc">Skills searchable by partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleSkills" <?= $showSkills ? 'checked' : '' ?> aria-labelledby="skills-label" aria-describedby="skills-desc">
                            <span class="toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title" id="messaging-label">Allow messages</p>
                            <p class="toggle-desc" id="messaging-desc">Receive messages from partners</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleMessaging" <?= $messagingEnabled ? 'checked' : '' ?> aria-labelledby="messaging-label" aria-describedby="messaging-desc">
                            <span class="toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-label">
                            <p class="toggle-title" id="transactions-label">Allow transactions</p>
                            <p class="toggle-desc" id="transactions-desc">Exchange time credits across timebanks</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggleTransactions" <?= $transactionsEnabled ? 'checked' : '' ?> aria-labelledby="transactions-label" aria-describedby="transactions-desc">
                            <span class="toggle-slider" aria-hidden="true"></span>
                        </label>
                    </div>
                </div>

                <!-- Profile Preview -->
                <div class="profile-preview" aria-label="Profile preview">
                    <div class="preview-avatar">
                        <?php if (!empty($userProfile['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <?= strtoupper(substr($displayName, 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <p class="preview-name"><?= htmlspecialchars($displayName) ?></p>
                    <div class="preview-badge">
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                        Federated Member
                    </div>
                </div>

                <div class="wizard-buttons">
                    <button type="button" class="wizard-btn wizard-btn-secondary" id="step3Back">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <button type="button" class="wizard-btn wizard-btn-primary" id="step3Next">
                        Finish Setup <i class="fa-solid fa-check" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <!-- Step 4: Success -->
            <section class="wizard-step" data-step="4" aria-labelledby="step4-title" hidden>
                <div class="success-animation">
                    <div class="success-icon" aria-hidden="true">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h1 id="step4-title" class="step-title">You're All Set!</h1>
                    <p class="step-desc">
                        Welcome to the federation! You can now connect with members from partner timebanks.
                    </p>

                    <div class="wizard-buttons wizard-buttons-vertical">
                        <a href="<?= $basePath ?>/federation/members" class="wizard-btn wizard-btn-primary">
                            <i class="fa-solid fa-users" aria-hidden="true"></i> Browse Members
                        </a>
                        <a href="<?= $basePath ?>/federation" class="wizard-btn wizard-btn-secondary">
                            <i class="fa-solid fa-home" aria-hidden="true"></i> Go to Hub
                        </a>
                    </div>
                </div>
            </section>

            <!-- Declined Step -->
            <section class="wizard-step" data-step="declined" aria-labelledby="declined-title" hidden>
                <div class="step-icon step-icon-muted" aria-hidden="true">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h1 id="declined-title" class="step-title">No Problem!</h1>
                <p class="step-desc">
                    You can enable federation anytime in your settings. Your local timebank experience remains unchanged.
                </p>

                <div class="wizard-buttons wizard-buttons-vertical">
                    <a href="<?= $basePath ?>/federation" class="wizard-btn wizard-btn-primary">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Hub
                    </a>
                    <a href="<?= $basePath ?>/settings?section=federation" class="wizard-btn wizard-btn-secondary">
                        <i class="fa-solid fa-cog" aria-hidden="true"></i> Federation Settings
                    </a>
                </div>
            </section>
        </main>
    </div>
</div>

<canvas class="confetti" id="confetti" aria-hidden="true"></canvas>

<script src="/assets/js/federation-onboarding.js?v=<?= time() ?>"></script>
<script>
    window.federationOnboardingConfig = {
        basePath: '<?= $basePath ?>',
        csrfToken: '<?= \Nexus\Core\Csrf::getToken() ?>'
    };
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
