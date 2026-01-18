<?php
// Federation Transaction Create - Glassmorphism 2025
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
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-send-wrapper">

        <style>
            /* Offline Banner */
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
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #fed-send-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            .send-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
                overflow: hidden;
            }

            [data-theme="dark"] .send-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .send-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                padding: 30px;
                text-align: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            .send-header h1 {
                font-size: 1.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0 0 8px 0;
            }

            .balance-display {
                font-size: 0.9rem;
                color: var(--htb-text-muted);
            }

            .balance-display strong {
                color: #10b981;
            }

            /* Recipient Section */
            .recipient-section {
                padding: 24px 30px;
                border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            }

            .recipient-info {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .recipient-avatar {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid rgba(139, 92, 246, 0.3);
            }

            .recipient-details h3 {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .recipient-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 10px;
                font-size: 0.8rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            [data-theme="dark"] .recipient-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            /* Form */
            .send-form {
                padding: 30px;
            }

            .form-group {
                margin-bottom: 24px;
            }

            .form-label {
                display: block;
                font-size: 0.9rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin-bottom: 8px;
            }

            .amount-input-wrapper {
                position: relative;
            }

            .amount-input {
                width: 100%;
                padding: 18px 20px;
                padding-right: 80px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 14px;
                font-size: 1.5rem;
                font-weight: 700;
                background: rgba(255, 255, 255, 0.8);
                color: var(--htb-text-main);
                text-align: center;
            }

            .amount-input:focus {
                outline: none;
                border-color: rgba(139, 92, 246, 0.5);
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] .amount-input {
                background: rgba(15, 23, 42, 0.8);
            }

            .amount-suffix {
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 1rem;
                color: var(--htb-text-muted);
                font-weight: 600;
            }

            .quick-amounts {
                display: flex;
                gap: 10px;
                margin-top: 12px;
                flex-wrap: wrap;
            }

            .quick-amount-btn {
                flex: 1;
                min-width: 60px;
                padding: 10px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 10px;
                background: transparent;
                color: #8b5cf6;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .quick-amount-btn:hover {
                background: rgba(139, 92, 246, 0.1);
                border-color: rgba(139, 92, 246, 0.4);
            }

            .description-input {
                width: 100%;
                padding: 14px 18px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 14px;
                font-size: 1rem;
                background: rgba(255, 255, 255, 0.8);
                color: var(--htb-text-main);
                resize: vertical;
                min-height: 100px;
            }

            .description-input:focus {
                outline: none;
                border-color: rgba(139, 92, 246, 0.5);
            }

            [data-theme="dark"] .description-input {
                background: rgba(15, 23, 42, 0.8);
            }

            .send-btn {
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border: none;
                border-radius: 14px;
                font-size: 1.1rem;
                font-weight: 800;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .send-btn:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            .send-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Privacy Notice */
            .privacy-notice {
                margin-top: 20px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
                display: flex;
                align-items: flex-start;
                gap: 10px;
            }

            .privacy-notice i {
                color: #8b5cf6;
                margin-top: 2px;
            }

            /* No Recipient */
            .no-recipient {
                text-align: center;
                padding: 40px;
            }

            .no-recipient-icon {
                font-size: 3rem;
                color: #8b5cf6;
                margin-bottom: 16px;
            }

            /* Touch Targets */
            .send-btn,
            .quick-amount-btn,
            .amount-input,
            .description-input {
                min-height: 44px;
            }

            .amount-input,
            .description-input {
                font-size: 16px !important;
            }

            /* Focus Visible */
            .send-btn:focus-visible,
            .quick-amount-btn:focus-visible,
            .amount-input:focus-visible,
            .description-input:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                #fed-send-wrapper {
                    padding: 15px;
                }

                .send-header,
                .recipient-section,
                .send-form {
                    padding: 20px;
                }
            }
        </style>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/members<?= $recipient ? '/' . $recipient['id'] : '' ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>

        <div class="send-card">
            <div class="send-header">
                <h1>
                    <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>
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
                                   <?= $balance < 0.5 ? 'disabled' : '' ?>>
                            <span class="amount-suffix">hours</span>
                        </div>
                        <?php if ($balance < 0.5): ?>
                        <div class="alert alert-warning" style="margin-top: 0.5rem; padding: 0.75rem; border-radius: 8px; background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24;">
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

                    <button type="submit" class="send-btn" id="send-btn" <?= $balance < 0.5 ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
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
                    <h3 style="color: var(--htb-text-main); margin: 0 0 10px 0;">No Recipient Selected</h3>
                    <p style="color: var(--htb-text-muted); margin: 0 0 20px 0;">
                        Please select a federated member to send hours to.
                    </p>
                    <a href="<?= $basePath ?>/federation/members" class="send-btn" style="display: inline-flex; width: auto; padding: 12px 24px;">
                        <i class="fa-solid fa-users"></i>
                        Browse Members
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function setAmount(val) {
    document.getElementById('amount-input').value = val;
}

document.querySelector('form')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('send-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
});

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
