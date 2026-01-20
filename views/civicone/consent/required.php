<?php
/**
 * CivicOne Consent Re-acceptance Page
 * Displayed when user needs to accept updated terms/privacy policy
 */
$pageTitle = $pageTitle ?? 'Terms Update Required';
$hero_title = 'Terms Update';
$hero_subtitle = 'Action Required';

require dirname(__DIR__) . '/../layouts/civicone/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$consents = $consents ?? [];
$csrfToken = \Nexus\Core\Csrf::generate();
?>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/consent-required.css?v=<?= $cssVersionTimestamp ?? time() ?>">

<div class="consent-required-page">
    <div class="consent-container">
        <div class="consent-header">
            <div class="consent-icon">
                <i class="fa-solid fa-file-contract"></i>
            </div>
            <h1>Important Updates to Our Terms</h1>
            <p class="consent-intro">
                We've updated our terms and conditions. Please review and accept
                the following to continue using your account.
            </p>
        </div>

        <form id="consentForm" class="consent-form">
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $csrfToken ?>">

            <?php foreach ($consents as $consent): ?>
            <div class="consent-item">
                <div class="consent-item-header">
                    <h3><?= htmlspecialchars($consent['name']) ?></h3>
                    <?php if (($consent['reason'] ?? '') === 'version_outdated'): ?>
                    <span class="consent-badge updated">Updated to v<?= htmlspecialchars($consent['current_version']) ?></span>
                    <?php else: ?>
                    <span class="consent-badge new">New Requirement</span>
                    <?php endif; ?>
                </div>

                <div class="consent-text-preview">
                    <p><?= nl2br(htmlspecialchars($consent['description'] ?? 'Please review and accept these terms.')) ?></p>
                    <button type="button" class="view-full-text" onclick="toggleFullText('<?= htmlspecialchars($consent['slug']) ?>')">
                        View full text <i class="fa-solid fa-chevron-down" id="chevron_<?= htmlspecialchars($consent['slug']) ?>"></i>
                    </button>
                </div>

                <div class="consent-full-text" id="fullText_<?= htmlspecialchars($consent['slug']) ?>">
                    <div class="full-text-content">
                        <?= nl2br(htmlspecialchars($consent['current_text'] ?? '')) ?>
                    </div>
                </div>

                <label class="consent-checkbox">
                    <input type="checkbox" name="consents[]" class="consent-check"
                           value="<?= htmlspecialchars($consent['slug']) ?>" required>
                    <span class="checkbox-label">
                        I have read and agree to the <?= htmlspecialchars($consent['name']) ?>
                        <span class="version-info">(Version <?= htmlspecialchars($consent['current_version']) ?>)</span>
                    </span>
                </label>
            </div>
            <?php endforeach; ?>

            <div class="consent-actions">
                <button type="submit" class="civic-btn civic-btn-primary" id="acceptBtn" disabled>
                    <i class="fa-solid fa-check"></i> Accept and Continue
                </button>
                <a href="<?= $basePath ?>/consent/decline" class="consent-decline-link">
                    I do not agree to these terms
                </a>
            </div>
        </form>

        <div class="consent-footer">
            <p>
                <i class="fa-solid fa-shield-halved"></i>
                Your privacy is important to us.
                <a href="<?= $basePath ?>/privacy" target="_blank">View our Privacy Policy</a>
            </p>
        </div>
    </div>
</div>

<script>
function toggleFullText(slug) {
    const el = document.getElementById('fullText_' + slug);
    const chevron = document.getElementById('chevron_' + slug);
    if (el.classList.contains('expanded')) {
        el.classList.remove('expanded');
        chevron.classList.remove('fa-chevron-up');
        chevron.classList.add('fa-chevron-down');
    } else {
        el.classList.add('expanded');
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('consentForm');
    const checkboxes = form.querySelectorAll('.consent-check');
    const submitBtn = document.getElementById('acceptBtn');

    function updateButtonState() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        submitBtn.disabled = !allChecked;
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateButtonState));

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const consents = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch('<?= $basePath ?>/consent/accept', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                },
                body: JSON.stringify({
                    consents: consents,
                    csrf_token: document.getElementById('csrf_token').value
                })
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Failed to save consent. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Accept and Continue';
            }
        } catch (err) {
            console.error('Consent submission error:', err);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Accept and Continue';
        }
    });
});
</script>

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
