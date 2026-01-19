<?php
// Phoenix Create Poll View - Full Holographic Glassmorphism Edition
$hero_title = "Create a Poll";
$hero_subtitle = "Start a community discussion.";
$hero_gradient = 'htb-hero-gradient-polls';
$hero_type = 'Poll';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>


<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-poll-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-poll-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">🗳️</div>
            <h1 class="holo-page-title">Create a Poll</h1>
            <p class="holo-page-subtitle">Start a community discussion or decision</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/polls/store" method="POST" id="createPollForm">
                <?= \Nexus\Core\Csrf::input() ?>

                <!-- Question -->
                <div class="holo-section">
                    <label class="holo-label" for="question">Question</label>
                    <input type="text" name="question" id="question" class="holo-input" placeholder="What would you like to ask?" required>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description <span class="holo-label-optional">(Optional)</span></label>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="Provide more context for your question..."></textarea>
                </div>

                <!-- Options -->
                <div class="holo-section">
                    <label class="holo-label">Voting Options</label>
                    <div class="holo-options-container" id="optionsContainer">
                        <div class="holo-option-row">
                            <div class="holo-option-number">1</div>
                            <input type="text" name="options[]" class="holo-input" placeholder="Option 1" required>
                        </div>
                        <div class="holo-option-row">
                            <div class="holo-option-number">2</div>
                            <input type="text" name="options[]" class="holo-input" placeholder="Option 2" required>
                        </div>
                    </div>
                    <button type="button" class="holo-add-option-btn" onclick="addOption()">
                        <i class="fa-solid fa-plus"></i>
                        Add Another Option
                    </button>
                </div>

                <!-- End Date -->
                <div class="holo-section">
                    <label class="holo-label" for="end_date">End Date <span class="holo-label-optional">(Optional)</span></label>
                    <input type="date" name="end_date" id="end_date" class="holo-input">
                </div>

                <hr class="holo-divider">

                <!-- Submit Button -->
                <button type="submit" class="holo-submit-btn" id="submitBtn">
                    <i class="fa-solid fa-chart-bar" style="margin-right: 10px;"></i>
                    Publish Poll
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let optionCount = 2;

function addOption() {
    optionCount++;
    const container = document.getElementById('optionsContainer');

    const row = document.createElement('div');
    row.className = 'holo-option-row';
    row.innerHTML = `
        <div class="holo-option-number">${optionCount}</div>
        <input type="text" name="options[]" class="holo-input" placeholder="Option ${optionCount}" required>
        <button type="button" class="holo-remove-option" onclick="removeOption(this)">
            <i class="fa-solid fa-times"></i>
        </button>
    `;

    container.appendChild(row);
    row.querySelector('input').focus();
}

function removeOption(btn) {
    const row = btn.closest('.holo-option-row');
    row.remove();
    renumberOptions();
}

function renumberOptions() {
    const rows = document.querySelectorAll('.holo-option-row');
    rows.forEach((row, index) => {
        row.querySelector('.holo-option-number').textContent = index + 1;
        row.querySelector('input').placeholder = `Option ${index + 1}`;
    });
    optionCount = rows.length;
}

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

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createPollForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to publish your poll.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';
        });
    }

    // Touch feedback
    document.querySelectorAll('.holo-add-option-btn, .holo-submit-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    let metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        metaTheme = document.createElement('meta');
        metaTheme.name = 'theme-color';
        document.head.appendChild(metaTheme);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        metaTheme.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
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
