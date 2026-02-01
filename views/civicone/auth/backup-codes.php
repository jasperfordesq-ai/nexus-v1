<?php
/**
 * CivicOne View: Backup Codes
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Full-page display for backup codes after 2FA setup
 */
$pageTitle = 'Backup codes';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <?php if (!empty($backup_codes)): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        Two-factor authentication is now enabled on your account.
                    </p>
                </div>
            </div>

            <h1 class="govuk-heading-xl">Save your backup codes</h1>

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">Warning</span>
                    These codes will only be shown once. Save them now.
                    If you lose your authenticator app and do not have these codes, you will be locked out of your account.
                </strong>
            </div>

            <div class="govuk-!-margin-bottom-6">
                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--m govuk-visually-hidden">Your backup codes</caption>
                    <tbody class="govuk-table__body">
                        <?php
                        $chunks = array_chunk($backup_codes, 2);
                        foreach ($chunks as $row):
                        ?>
                        <tr class="govuk-table__row">
                            <?php foreach ($row as $code): ?>
                                <td class="govuk-table__cell backup-code-govuk">
                                    <?= htmlspecialchars($code) ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if (count($row) === 1): ?>
                                <td class="govuk-table__cell"></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="govuk-button-group govuk-!-margin-bottom-6">
                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="copyBackupCodes()">
                    Copy all codes
                </button>
                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="downloadBackupCodes()">
                    Download as file
                </button>
                <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="window.print()">
                    Print
                </button>
            </div>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <form id="continue-form">
                <div class="govuk-form-group">
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="confirm-saved" name="confirm-saved" type="checkbox" value="yes">
                            <label class="govuk-label govuk-checkboxes__label" for="confirm-saved">
                                I have saved these codes in a safe place
                            </label>
                        </div>
                    </div>
                </div>

                <a href="<?= $basePath ?>/dashboard" id="continue-btn" class="govuk-button govuk-button--disabled" data-module="govuk-button" aria-disabled="true">
                    Continue to dashboard
                </a>
            </form>

        <?php else: ?>
            <h1 class="govuk-heading-xl">Your backup codes</h1>

            <p class="govuk-body-l">
                You have <strong><?= $codes_remaining ?></strong> backup codes remaining.
            </p>

            <p class="govuk-body">Each code can only be used once. If you are running low on codes, generate new ones.</p>

            <?php if ($codes_remaining <= 3): ?>
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        You are running low on backup codes. Consider generating new ones.
                    </strong>
                </div>
            <?php endif; ?>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <h2 class="govuk-heading-m">Generate new backup codes</h2>

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">Warning</span>
                    Generating new codes will invalidate all your existing unused codes.
                </strong>
            </div>

            <form action="<?= $basePath ?>/settings/2fa/regenerate-backup" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="password">
                        Confirm your password
                    </label>
                    <div id="password-hint" class="govuk-hint">
                        Enter your current password to generate new backup codes
                    </div>
                    <input class="govuk-input govuk-input--width-20"
                           id="password"
                           name="password"
                           type="password"
                           required
                           aria-describedby="password-hint">
                </div>

                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                    Generate new backup codes
                </button>
            </form>

            <p class="govuk-body govuk-!-margin-top-6">
                <a href="<?= $basePath ?>/settings/2fa" class="govuk-link">Back to 2FA settings</a>
            </p>
        <?php endif; ?>

    </div>
</div>

<style>
.backup-code-govuk {
    font-family: monospace;
    font-size: 1.1rem;
    letter-spacing: 0.1em;
    font-weight: 700;
    background: #f3f2f1;
}

.govuk-button--disabled {
    pointer-events: none;
    opacity: 0.5;
}

@media print {
    .govuk-button-group,
    #continue-form,
    .govuk-notification-banner {
        display: none !important;
    }

    .backup-code-govuk {
        background: white !important;
        border: 1px solid #333;
    }
}
</style>

<?php if (!empty($backup_codes)): ?>
<script>
var backupCodes = <?= json_encode($backup_codes) ?>;

function copyBackupCodes() {
    var text = "Backup Codes\n" +
               "Generated: <?= date('Y-m-d H:i') ?>\n\n" +
               backupCodes.join("\n") +
               "\n\nEach code can only be used once.";

    navigator.clipboard.writeText(text).then(function() {
        alert('Backup codes copied to clipboard');
    }).catch(function() {
        alert('Failed to copy. Please manually select and copy the codes.');
    });
}

function downloadBackupCodes() {
    var text = "Backup Codes\n" +
               "Generated: <?= date('Y-m-d H:i') ?>\n\n" +
               backupCodes.join("\n") +
               "\n\nEach code can only be used once.\n" +
               "Keep these codes in a safe place!";

    var blob = new Blob([text], { type: 'text/plain' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'backup-codes.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

document.getElementById('confirm-saved').addEventListener('change', function() {
    var btn = document.getElementById('continue-btn');
    if (this.checked) {
        btn.classList.remove('govuk-button--disabled');
        btn.removeAttribute('aria-disabled');
    } else {
        btn.classList.add('govuk-button--disabled');
        btn.setAttribute('aria-disabled', 'true');
    }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
