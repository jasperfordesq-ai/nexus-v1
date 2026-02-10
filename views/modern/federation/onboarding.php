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
                    'X-CSRF-Token': '<?= \Nexus\Core\Csrf::token() ?>'
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
