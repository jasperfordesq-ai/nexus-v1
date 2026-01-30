<?php
/**
 * CivicOne View: Federation Transaction Create
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Send Hours";
$hideHero = true;

\Nexus\Core\SEO::setTitle('Send Hours - Federated Transaction');
\Nexus\Core\SEO::setDescription('Send hours to a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
require_once dirname(dirname(__DIR__)) . '/components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$recipient = $recipient ?? null;
$recipientTenantId = $recipientTenantId ?? 0;
$balance = $balance ?? 0;
$isExternalTransaction = $isExternalTransaction ?? (!empty($recipient['is_external']));
$externalPartner = $externalPartner ?? null;

$recipientName = $recipient['name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($recipientName) . '&background=00796B&color=fff&size=200';
$recipientAvatar = !empty($recipient['avatar_url']) ? $recipient['avatar_url'] : $fallbackAvatar;
$hasInsufficientBalance = $balance < 0.5;
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Federation', 'href' => $basePath . '/federation'],
        ['text' => 'Transactions', 'href' => $basePath . '/federation/transactions'],
        ['text' => 'Send Hours']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/federation/members<?= $recipient ? '/' . $recipient['id'] : '' ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
            Send Hours
        </h1>

        <p class="govuk-body-l" role="status" aria-live="polite">
            Your balance: <strong><?= number_format($balance, 1) ?> hours</strong>
        </p>

        <?php if ($recipient): ?>
            <!-- Recipient Info -->
            <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-settings-card">
                <div class="civicone-flex-gap">
                    <img src="<?= htmlspecialchars($recipientAvatar) ?>"
                         onerror="this.src='<?= $fallbackAvatar ?>'"
                         alt=""
                         class="civicone-avatar-md"
                         loading="lazy">
                    <div>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1"><?= htmlspecialchars($recipientName) ?></h2>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                            <i class="fa-solid <?= $isExternalTransaction ? 'fa-globe' : 'fa-building' ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($recipient['tenant_name'] ?? 'Partner Timebank') ?>
                            <?php if ($isExternalTransaction): ?>
                            <span class="govuk-tag govuk-tag--blue civicone-tag-inline-small">External</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($hasInsufficientBalance): ?>
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">There is a problem</h2>
                        <div class="govuk-error-summary__body">
                            <p class="govuk-body">
                                <i class="fa-solid fa-exclamation-triangle govuk-!-margin-right-1" aria-hidden="true"></i>
                                Insufficient balance. You need at least 0.5 hours to send a transaction.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Send Form -->
            <form action="<?= $basePath ?>/federation/transactions/send" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="receiver_id" value="<?= $recipient['id'] ?>">
                <?php if ($isExternalTransaction && $externalPartner): ?>
                    <input type="hidden" name="external_partner_id" value="<?= $externalPartner['id'] ?>">
                    <input type="hidden" name="receiver_name" value="<?= htmlspecialchars($recipientName) ?>">
                <?php else: ?>
                    <input type="hidden" name="receiver_tenant_id" value="<?= $recipientTenantId ?>">
                <?php endif; ?>

                <!-- Amount -->
                <div class="govuk-form-group <?= $hasInsufficientBalance ? 'govuk-form-group--error' : '' ?>">
                    <label class="govuk-label" for="amount-input">Amount (Hours)</label>
                    <?php if ($hasInsufficientBalance): ?>
                        <p id="amount-error" class="govuk-error-message">
                            <span class="govuk-visually-hidden">Error:</span> You need at least 0.5 hours to send
                        </p>
                    <?php else: ?>
                        <div id="amount-hint" class="govuk-hint">Enter the number of hours to send (minimum 0.5)</div>
                    <?php endif; ?>
                    <input type="number"
                           name="amount"
                           id="amount-input"
                           class="govuk-input govuk-input--width-5 <?= $hasInsufficientBalance ? 'govuk-input--error' : '' ?>"
                           min="0.5"
                           max="<?= max(0.5, min($balance, 100)) ?>"
                           step="0.5"
                           value="<?= min(1, $balance) ?>"
                           required
                           aria-describedby="<?= $hasInsufficientBalance ? 'amount-error' : 'amount-hint' ?>"
                           <?= $hasInsufficientBalance ? 'disabled' : '' ?>>

                    <?php if (!$hasInsufficientBalance): ?>
                    <div class="govuk-!-margin-top-2">
                        <span class="govuk-body-s civicone-secondary-text">Quick amounts: </span>
                        <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-compact" onclick="document.getElementById('amount-input').value = 0.5">0.5</button>
                        <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-compact" onclick="document.getElementById('amount-input').value = 1">1</button>
                        <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-compact" onclick="document.getElementById('amount-input').value = 2">2</button>
                        <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-compact" onclick="document.getElementById('amount-input').value = 5">5</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="govuk-form-group">
                    <label class="govuk-label" for="description-input">
                        Description <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                    </label>
                    <div id="description-hint" class="govuk-hint">What is this payment for?</div>
                    <textarea name="description"
                              id="description-input"
                              class="govuk-textarea"
                              rows="3"
                              maxlength="500"
                              aria-describedby="description-hint"></textarea>
                </div>

                <button type="submit"
                        class="govuk-button"
                        data-module="govuk-button"
                        <?= $hasInsufficientBalance ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                    Send Hours
                </button>

                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-1" aria-hidden="true"></i>
                        <strong>Federated Transaction</strong><br>
                        This transfer will be recorded in both timebanks. Hours will be deducted from your balance immediately.
                    </p>
                </div>
            </form>
        <?php else: ?>
            <div class="govuk-inset-text">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">No Recipient Selected</h3>
                <p class="govuk-body govuk-!-margin-bottom-2">Please select a federated member to send hours to.</p>
                <a href="<?= $basePath ?>/federation/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                    Browse Members
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
