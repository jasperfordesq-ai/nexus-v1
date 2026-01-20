<?php
/**
 * Federation Transaction Create
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Send Hours";
$hideHero = true;

Nexus\Core\SEO::setTitle('Send Hours - Federated Transaction');
Nexus\Core\SEO::setDescription('Send hours to a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$recipient = $recipient ?? null;
$recipientTenantId = $recipientTenantId ?? 0;
$balance = $balance ?? 0;

$recipientName = $recipient['name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($recipientName) . '&background=00796B&color=fff&size=200';
$recipientAvatar = !empty($recipient['avatar_url']) ? $recipient['avatar_url'] : $fallbackAvatar;
$hasInsufficientBalance = $balance < 0.5;
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/members<?= $recipient ? '/' . $recipient['id'] : '' ?>" class="civic-fed-back-link" aria-label="Go back">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back
    </a>

    <div class="civic-fed-send-card" role="main">
        <header class="civic-fed-send-header">
            <h1>
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Send Hours
            </h1>
            <p class="civic-fed-balance-display" role="status" aria-live="polite">
                Your balance: <strong><?= number_format($balance, 1) ?> hours</strong>
            </p>
        </header>

        <?php if ($recipient): ?>
            <!-- Recipient Info -->
            <section class="civic-fed-recipient-section" aria-labelledby="recipient-heading">
                <h2 id="recipient-heading" class="visually-hidden">Recipient</h2>
                <div class="civic-fed-recipient-info">
                    <img src="<?= htmlspecialchars($recipientAvatar) ?>"
                         onerror="this.src='<?= $fallbackAvatar ?>'"
                         alt=""
                         class="civic-fed-avatar"
                         loading="lazy">
                    <div class="civic-fed-recipient-details">
                        <h3><?= htmlspecialchars($recipientName) ?></h3>
                        <span class="civic-fed-recipient-tenant">
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                            <?= htmlspecialchars($recipient['tenant_name'] ?? 'Partner Timebank') ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Send Form -->
            <form action="<?= $basePath ?>/federation/transactions/send" method="POST" class="civic-fed-form" aria-label="Send hours form">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="receiver_id" value="<?= $recipient['id'] ?>">
                <input type="hidden" name="receiver_tenant_id" value="<?= $recipientTenantId ?>">

                <div class="civic-fed-form-group">
                    <label for="amount-input" class="civic-fed-label">Amount (Hours)</label>
                    <div class="civic-fed-amount-wrapper">
                        <input type="number"
                               name="amount"
                               id="amount-input"
                               class="civic-fed-input civic-fed-input--amount"
                               min="0.5"
                               max="<?= max(0.5, min($balance, 100)) ?>"
                               step="0.5"
                               value="<?= min(1, $balance) ?>"
                               required
                               aria-describedby="<?= $hasInsufficientBalance ? 'balance-error' : 'amount-help' ?>"
                               <?= $hasInsufficientBalance ? 'disabled aria-invalid="true"' : '' ?>>
                        <span class="civic-fed-amount-suffix" aria-hidden="true">hours</span>
                    </div>
                    <?php if ($hasInsufficientBalance): ?>
                    <div class="civic-fed-alert civic-fed-alert--error" id="balance-error" role="alert">
                        <i class="fa-solid fa-exclamation-triangle" aria-hidden="true"></i>
                        Insufficient balance. You need at least 0.5 hours to send a transaction.
                    </div>
                    <?php else: ?>
                    <div class="civic-fed-quick-amounts" id="amount-help" role="group" aria-label="Quick amount selection">
                        <button type="button" class="civic-fed-quick-btn" aria-label="Set amount to 0.5 hours" onclick="setAmount(0.5)">0.5</button>
                        <button type="button" class="civic-fed-quick-btn" aria-label="Set amount to 1 hour" onclick="setAmount(1)">1</button>
                        <button type="button" class="civic-fed-quick-btn" aria-label="Set amount to 2 hours" onclick="setAmount(2)">2</button>
                        <button type="button" class="civic-fed-quick-btn" aria-label="Set amount to 5 hours" onclick="setAmount(5)">5</button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="civic-fed-form-group">
                    <label for="description-input" class="civic-fed-label">Description (Optional)</label>
                    <textarea name="description"
                              id="description-input"
                              class="civic-fed-textarea"
                              placeholder="What is this payment for?"
                              maxlength="500"
                              aria-describedby="description-help"></textarea>
                    <span id="description-help" class="visually-hidden">Enter a description for this transaction, maximum 500 characters</span>
                </div>

                <button type="submit"
                        class="civic-fed-btn civic-fed-btn--primary civic-fed-btn--full"
                        id="send-btn"
                        <?= $hasInsufficientBalance ? 'disabled aria-disabled="true"' : '' ?>>
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    Send Hours
                </button>

                <aside class="civic-fed-notice" role="note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <div>
                        <strong>Federated Transaction</strong><br>
                        This transfer will be recorded in both timebanks. Hours will be deducted from your balance immediately.
                    </div>
                </aside>
            </form>
        <?php else: ?>
            <div class="civic-fed-empty" role="status">
                <div class="civic-fed-empty-icon" aria-hidden="true">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <h3>No Recipient Selected</h3>
                <p>Please select a federated member to send hours to.</p>
                <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--primary">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    Browse Members
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/assets/js/federation-send.js?v=<?= time() ?>"></script>
<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
