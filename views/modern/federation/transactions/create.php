<?php
// Federation Transaction Create - Modern Theme
$pageTitle = $pageTitle ?? "Send Hours";
$hideHero = true;

Nexus\Core\SEO::setTitle('Send Hours - Federated Transaction');
Nexus\Core\SEO::setDescription('Send hours to a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$recipient = $recipient ?? null;
$recipientTenantId = $recipientTenantId ?? 0;
$balance = $balance ?? 0;

$recipientName = $recipient['name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($recipientName) . '&background=8b5cf6&color=fff&size=200';
$recipientAvatar = !empty($recipient['avatar_url']) ? $recipient['avatar_url'] : $fallbackAvatar;
$hasInsufficientBalance = $balance < 0.5;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-send-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/members<?= $recipient ? '/' . $recipient['id'] : '' ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>

        <div class="send-card">
            <div class="send-header">
                <h1>
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Hours
                </h1>
                <p class="balance-display">
                    Your balance: <strong><?= number_format($balance, 1) ?> hours</strong>
                </p>
            </div>

            <?php if ($recipient): ?>
                <!-- Recipient Info -->
                <div class="recipient-section">
                    <div class="recipient-info">
                        <img src="<?= htmlspecialchars($recipientAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt="<?= htmlspecialchars($recipientName) ?>"
                             class="recipient-avatar">
                        <div class="recipient-details">
                            <h3><?= htmlspecialchars($recipientName) ?></h3>
                            <span class="recipient-tenant">
                                <i class="fa-solid fa-building"></i>
                                <?= htmlspecialchars($recipient['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Send Form -->
                <form action="<?= $basePath ?>/federation/transactions/send" method="POST" class="send-form">
                    <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                    <input type="hidden" name="receiver_id" value="<?= $recipient['id'] ?>">
                    <input type="hidden" name="receiver_tenant_id" value="<?= $recipientTenantId ?>">

                    <div class="form-group">
                        <label class="form-label">Amount (Hours)</label>
                        <div class="amount-input-wrapper">
                            <input type="number"
                                   name="amount"
                                   id="amount-input"
                                   class="amount-input"
                                   min="0.5"
                                   max="<?= max(0.5, min($balance, 100)) ?>"
                                   step="0.5"
                                   value="<?= min(1, $balance) ?>"
                                   required
                                   <?= $hasInsufficientBalance ? 'disabled' : '' ?>>
                            <span class="amount-suffix">hours</span>
                        </div>
                        <?php if ($hasInsufficientBalance): ?>
                        <div class="send-balance-alert">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            Insufficient balance. You need at least 0.5 hours to send a transaction.
                        </div>
                        <?php else: ?>
                        <div class="quick-amounts">
                            <button type="button" class="quick-amount-btn" onclick="setAmount(0.5)">0.5</button>
                            <button type="button" class="quick-amount-btn" onclick="setAmount(1)">1</button>
                            <button type="button" class="quick-amount-btn" onclick="setAmount(2)">2</button>
                            <button type="button" class="quick-amount-btn" onclick="setAmount(5)">5</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description"
                                  class="description-input"
                                  placeholder="What is this payment for?"
                                  maxlength="500"></textarea>
                    </div>

                    <button type="submit"
                            class="send-btn<?= $hasInsufficientBalance ? ' send-btn-disabled' : '' ?>"
                            id="send-btn"
                            <?= $hasInsufficientBalance ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-paper-plane"></i>
                        Send Hours
                    </button>

                    <div class="privacy-notice">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Federated Transaction</strong><br>
                            This transfer will be recorded in both timebanks. Hours will be deducted from your balance immediately.
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="no-recipient">
                    <div class="no-recipient-icon">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                    <h3>No Recipient Selected</h3>
                    <p>Please select a federated member to send hours to.</p>
                    <a href="<?= $basePath ?>/federation/members" class="send-btn">
                        <i class="fa-solid fa-users"></i>
                        Browse Members
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="/assets/js/federation-send.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
