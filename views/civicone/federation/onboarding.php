<?php
/**
 * Federation Onboarding Wizard
 * CivicOne Theme - GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Get Started with Federation";
$hideHero = true;
$bodyClass = 'civicone--federation';

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
<div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-margin-bottom-4 hidden" id="offlineBanner" role="alert" aria-live="polite">
    <div class="govuk-notification-banner__content">
        <p class="govuk-body">
            <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
            No internet connection
        </p>
    </div>
</div>

<div class="govuk-width-container govuk-!-padding-top-6 govuk-!-padding-bottom-9">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds-from-desktop" style="margin: 0 auto; float: none;">
            <!-- Progress Bar -->
            <nav class="govuk-!-margin-bottom-6" aria-label="Setup progress">
                <ol class="govuk-list" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
                    <li data-step="1" class="govuk-!-padding-2 govuk-!-font-weight-bold" style="background: #1d70b8; color: white; border-radius: 50%; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;" aria-current="step">
                        <span class="govuk-visually-hidden">Step </span>1
                    </li>
                    <li data-line="1" style="width: 3rem; height: 2px; background: #b1b4b6;" aria-hidden="true"></li>
                    <li data-step="2" class="govuk-!-padding-2" style="background: #f3f2f1; border-radius: 50%; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;">
                        <span class="govuk-visually-hidden">Step </span>2
                    </li>
                    <li data-line="2" style="width: 3rem; height: 2px; background: #b1b4b6;" aria-hidden="true"></li>
                    <li data-step="3" class="govuk-!-padding-2" style="background: #f3f2f1; border-radius: 50%; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;">
                        <span class="govuk-visually-hidden">Step </span>3
                    </li>
                    <li data-line="3" style="width: 3rem; height: 2px; background: #b1b4b6;" aria-hidden="true"></li>
                    <li data-step="4" class="govuk-!-padding-2" style="background: #f3f2f1; border-radius: 50%; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <span class="govuk-visually-hidden">Complete</span>
                    </li>
                </ol>
            </nav>

            <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6;" role="main">
            <!-- Step 1: Welcome -->
            <section data-step="1" aria-labelledby="step1-title" class="govuk-!-text-align-center">
                <div class="govuk-!-margin-bottom-4" aria-hidden="true">
                    <i class="fa-solid fa-globe fa-3x" style="color: #1d70b8;"></i>
                </div>
                <h1 id="step1-title" class="govuk-heading-l">Connect Beyond Borders</h1>
                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    Join <?= $partnerCount ?> partner timebank<?= $partnerCount !== 1 ? 's' : '' ?> and connect with members from communities around the world.
                </p>

                <p class="govuk-!-margin-bottom-6">
                    <span class="govuk-tag govuk-tag--blue">
                        <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i>
                        <?= $partnerCount ?> Partner Timebank<?= $partnerCount !== 1 ? 's' : '' ?> Available
                    </span>
                </p>

                <div class="govuk-form-group govuk-!-text-align-left">
                    <fieldset class="govuk-fieldset" aria-label="Federation choice">
                        <legend class="govuk-visually-hidden">Would you like to enable federation?</legend>
                        <div class="govuk-radios" data-module="govuk-radios">
                            <div class="govuk-radios__item govuk-!-padding-4 govuk-!-margin-bottom-2" style="border: 2px solid #1d70b8; background: #f0f4f8;">
                                <input class="govuk-radios__input" id="fed-yes" name="federation_choice" type="radio" value="yes" checked data-value="yes">
                                <label class="govuk-label govuk-radios__label" for="fed-yes">
                                    <strong>Yes, let's get started!</strong>
                                    <i class="fa-solid fa-rocket govuk-!-margin-left-2" aria-hidden="true"></i>
                                </label>
                                <div class="govuk-hint govuk-radios__hint">
                                    Enable federation and connect with partner communities
                                </div>
                            </div>
                            <div class="govuk-radios__item govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
                                <input class="govuk-radios__input" id="fed-no" name="federation_choice" type="radio" value="no" data-value="no">
                                <label class="govuk-label govuk-radios__label" for="fed-no">
                                    <strong>Not right now</strong>
                                    <i class="fa-solid fa-clock govuk-!-margin-left-2" aria-hidden="true"></i>
                                </label>
                                <div class="govuk-hint govuk-radios__hint">
                                    You can enable this later in your settings
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="govuk-button-group govuk-!-margin-top-6">
                    <button type="button" class="govuk-button" data-module="govuk-button" id="step1Next">
                        Continue <i class="fa-solid fa-arrow-right govuk-!-margin-left-1" aria-hidden="true"></i>
                    </button>
                </div>

                <p class="govuk-body govuk-!-margin-top-4">
                    <a href="<?= $basePath ?>/federation" class="govuk-link">Skip for now</a>
                </p>
            </section>

            <!-- Step 2: Privacy Level -->
            <section data-step="2" aria-labelledby="step2-title" hidden class="govuk-!-text-align-center">
                <div class="govuk-!-margin-bottom-4" aria-hidden="true">
                    <i class="fa-solid fa-shield-halved fa-3x" style="color: #1d70b8;"></i>
                </div>
                <h1 id="step2-title" class="govuk-heading-l">Choose Your Privacy Level</h1>
                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    Control what partner timebank members can see and do.
                </p>

                <div class="govuk-form-group govuk-!-text-align-left">
                    <fieldset class="govuk-fieldset" id="privacyOptions" aria-label="Privacy level">
                        <legend class="govuk-visually-hidden">Select your privacy level</legend>
                        <div class="govuk-radios" data-module="govuk-radios">
                            <div class="govuk-radios__item govuk-!-padding-4 govuk-!-margin-bottom-2" style="border: 1px solid #b1b4b6;" data-value="discovery">
                                <input class="govuk-radios__input" id="privacy-discovery-wiz" name="privacy_level_wizard" type="radio" value="discovery">
                                <label class="govuk-label govuk-radios__label" for="privacy-discovery-wiz">
                                    <strong>Discovery</strong>
                                    <i class="fa-solid fa-eye govuk-!-margin-left-2" aria-hidden="true"></i>
                                </label>
                                <div class="govuk-hint govuk-radios__hint">
                                    Name, avatar, and bio visible. Browse only.
                                </div>
                            </div>
                            <div class="govuk-radios__item govuk-!-padding-4 govuk-!-margin-bottom-2" style="border: 2px solid #1d70b8; background: #f0f4f8;" data-value="social">
                                <input class="govuk-radios__input" id="privacy-social-wiz" name="privacy_level_wizard" type="radio" value="social" checked>
                                <label class="govuk-label govuk-radios__label" for="privacy-social-wiz">
                                    <strong>Social</strong> <span class="govuk-tag govuk-tag--green">Recommended</span>
                                    <i class="fa-solid fa-comments govuk-!-margin-left-2" aria-hidden="true"></i>
                                </label>
                                <div class="govuk-hint govuk-radios__hint">
                                    Plus skills, location, and messaging.
                                </div>
                            </div>
                            <div class="govuk-radios__item govuk-!-padding-4" style="border: 1px solid #b1b4b6;" data-value="economic">
                                <input class="govuk-radios__input" id="privacy-economic-wiz" name="privacy_level_wizard" type="radio" value="economic">
                                <label class="govuk-label govuk-radios__label" for="privacy-economic-wiz">
                                    <strong>Economic</strong>
                                    <i class="fa-solid fa-coins govuk-!-margin-left-2" aria-hidden="true"></i>
                                </label>
                                <div class="govuk-hint govuk-radios__hint">
                                    Full access including time credit exchanges.
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="govuk-button-group govuk-!-margin-top-6">
                    <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" id="step2Back">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i> Back
                    </button>
                    <button type="button" class="govuk-button" data-module="govuk-button" id="step2Next">
                        Continue <i class="fa-solid fa-arrow-right govuk-!-margin-left-1" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <!-- Step 3: Fine-tune Settings -->
            <section data-step="3" aria-labelledby="step3-title" hidden class="govuk-!-text-align-center">
                <div class="govuk-!-margin-bottom-4" aria-hidden="true">
                    <i class="fa-solid fa-sliders fa-3x" style="color: #1d70b8;"></i>
                </div>
                <h1 id="step3-title" class="govuk-heading-l">Fine-tune Your Settings</h1>
                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    Customize exactly what you share with partner timebanks.
                </p>

                <div class="govuk-form-group govuk-!-text-align-left">
                    <fieldset class="govuk-fieldset" aria-label="Sharing options">
                        <legend class="govuk-visually-hidden">Sharing options</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="toggleLocation" name="show_location" type="checkbox" <?= $showLocation ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="toggleLocation">
                                    <strong>Show my location</strong>
                                </label>
                                <div class="govuk-hint govuk-checkboxes__hint">
                                    City/region visible to partners
                                </div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="toggleSkills" name="show_skills" type="checkbox" <?= $showSkills ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="toggleSkills">
                                    <strong>Show my skills</strong>
                                </label>
                                <div class="govuk-hint govuk-checkboxes__hint">
                                    Skills searchable by partners
                                </div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="toggleMessaging" name="messaging_enabled" type="checkbox" <?= $messagingEnabled ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="toggleMessaging">
                                    <strong>Allow messages</strong>
                                </label>
                                <div class="govuk-hint govuk-checkboxes__hint">
                                    Receive messages from partners
                                </div>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="toggleTransactions" name="transactions_enabled" type="checkbox" <?= $transactionsEnabled ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="toggleTransactions">
                                    <strong>Allow transactions</strong>
                                </label>
                                <div class="govuk-hint govuk-checkboxes__hint">
                                    Exchange time credits across timebanks
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- Profile Preview -->
                <div class="govuk-!-padding-4 govuk-!-margin-top-6 govuk-!-text-align-center" style="background: #f3f2f1;" aria-label="Profile preview">
                    <div class="govuk-!-margin-bottom-2" style="width: 60px; height: 60px; border-radius: 50%; background: #1d70b8; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                        <?php if (!empty($userProfile['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($userProfile['avatar_url']) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <span><?= strtoupper(substr($displayName, 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="govuk-heading-s govuk-!-margin-bottom-1"><?= htmlspecialchars($displayName) ?></p>
                    <span class="govuk-tag govuk-tag--blue">
                        <i class="fa-solid fa-globe govuk-!-margin-right-1" aria-hidden="true"></i>
                        Federated Member
                    </span>
                </div>

                <div class="govuk-button-group govuk-!-margin-top-6">
                    <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" id="step3Back">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i> Back
                    </button>
                    <button type="button" class="govuk-button" data-module="govuk-button" id="step3Next">
                        Finish Setup <i class="fa-solid fa-check govuk-!-margin-left-1" aria-hidden="true"></i>
                    </button>
                </div>
            </section>

            <!-- Step 4: Success -->
            <section data-step="4" aria-labelledby="step4-title" hidden class="govuk-!-text-align-center">
                <div class="govuk-!-margin-bottom-4" aria-hidden="true" style="width: 80px; height: 80px; border-radius: 50%; background: #00703c; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-check fa-2x" style="color: white;"></i>
                </div>
                <h1 id="step4-title" class="govuk-heading-l">You're All Set!</h1>
                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    Welcome to the federation! You can now connect with members from partner timebanks.
                </p>

                <div class="govuk-button-group" style="flex-direction: column; align-items: center;">
                    <a href="<?= $basePath ?>/federation/members" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i> Browse Members
                    </a>
                    <a href="<?= $basePath ?>/federation" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-home govuk-!-margin-right-1" aria-hidden="true"></i> Go to Hub
                    </a>
                </div>
            </section>

            <!-- Declined Step -->
            <section data-step="declined" aria-labelledby="declined-title" hidden class="govuk-!-text-align-center">
                <div class="govuk-!-margin-bottom-4" aria-hidden="true">
                    <i class="fa-solid fa-clock fa-3x" style="color: #505a5f;"></i>
                </div>
                <h1 id="declined-title" class="govuk-heading-l">No Problem!</h1>
                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    You can enable federation anytime in your settings. Your local timebank experience remains unchanged.
                </p>

                <div class="govuk-button-group" style="flex-direction: column; align-items: center;">
                    <a href="<?= $basePath ?>/federation" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i> Back to Hub
                    </a>
                    <a href="<?= $basePath ?>/settings?section=federation" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-cog govuk-!-margin-right-1" aria-hidden="true"></i> Federation Settings
                    </a>
                </div>
            </section>
            </div>
        </div>
    </div>
</div>

<canvas id="confetti" aria-hidden="true" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1000;"></canvas>

<script src="/assets/js/federation-onboarding.js?v=<?= time() ?>"></script>
<script>
    window.federationOnboardingConfig = {
        basePath: '<?= $basePath ?>',
        csrfToken: '<?= \Nexus\Core\Csrf::getToken() ?>'
    };
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
