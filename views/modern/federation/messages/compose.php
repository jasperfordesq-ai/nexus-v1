<?php
// Federation Messages Compose - Glassmorphism 2025
$pageTitle = $pageTitle ?? "New Federated Message";
$hideHero = true;

Nexus\Core\SEO::setTitle($pageTitle);
Nexus\Core\SEO::setDescription('Send a message to a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$recipient = $recipient ?? null;
$isExternalMember = $isExternalMember ?? false;
$externalPartner = $externalPartner ?? null;

$recipientName = $recipient['name'] ?? 'Member';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($recipientName) . '&background=8b5cf6&color=fff&size=200';
$recipientAvatar = !empty($recipient['avatar_url']) ? $recipient['avatar_url'] : $fallbackAvatar;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-compose-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/members" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Directory
        </a>

        <?php if ($recipient): ?>
        <!-- Recipient Header -->
        <div class="compose-header">
            <div class="recipient-info">
                <img src="<?= htmlspecialchars($recipientAvatar) ?>"
                     onerror="this.src='<?= $fallbackAvatar ?>'"
                     alt="<?= htmlspecialchars($recipientName) ?>"
                     class="recipient-avatar">
                <div class="recipient-details">
                    <h2 class="recipient-name">Message <?= htmlspecialchars($recipientName) ?></h2>
                    <span class="recipient-tenant<?= $isExternalMember ? ' external' : '' ?>">
                        <i class="fa-solid <?= $isExternalMember ? 'fa-globe' : 'fa-building' ?>"></i>
                        <?= htmlspecialchars($recipient['tenant_name'] ?? 'Partner Timebank') ?>
                        <?php if ($isExternalMember): ?>
                        <span class="external-tag">External Partner</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($isExternalMember): ?>
        <div class="external-notice">
            <i class="fa-solid fa-info-circle"></i>
            <div>
                <strong>External Federation Message</strong><br>
                This message will be sent to <strong><?= htmlspecialchars($externalPartner['partner_name'] ?? $externalPartner['name'] ?? 'the external partner') ?></strong> via their API.
                The recipient will receive your message through their timebank's messaging system.
            </div>
        </div>
        <?php endif; ?>

        <!-- Compose Form -->
        <form action="<?= $basePath ?>/federation/messages/send" method="POST" class="compose-form-card">
            <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
            <input type="hidden" name="receiver_id" value="<?= $recipient['id'] ?? 0 ?>">

            <?php if ($isExternalMember && $externalPartner): ?>
                <input type="hidden" name="external_partner_id" value="<?= $externalPartner['id'] ?>">
                <input type="hidden" name="receiver_name" value="<?= htmlspecialchars($recipient['name'] ?? 'Member') ?>">
                <input type="hidden" name="external_tenant_id" value="<?= $recipient['external_tenant_id'] ?? 1 ?>">
            <?php else: ?>
                <input type="hidden" name="receiver_tenant_id" value="<?= $recipient['tenant_id'] ?? 0 ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="subject" class="form-label">Subject (Optional)</label>
                <input type="text"
                       name="subject"
                       id="subject"
                       class="form-input"
                       placeholder="What's this about?">
            </div>

            <div class="form-group">
                <label for="body" class="form-label">Message <span class="required">*</span></label>
                <textarea name="body"
                          id="body"
                          class="form-textarea"
                          placeholder="Write your message here..."
                          required
                          rows="6"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="send-btn">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Message
                </button>
                <a href="<?= $basePath ?>/federation/members" class="cancel-btn">
                    Cancel
                </a>
            </div>
        </form>

        <?php else: ?>
        <!-- No recipient selected -->
        <div class="no-recipient">
            <i class="fa-solid fa-user-slash"></i>
            <h3>No Recipient Selected</h3>
            <p>Please select a member from the federation directory to send them a message.</p>
            <a href="<?= $basePath ?>/federation/members" class="browse-btn">
                <i class="fa-solid fa-users"></i>
                Browse Federated Members
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
#fed-compose-wrapper {
    max-width: 700px;
    margin: 0 auto;
    padding: 20px;
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

.compose-header {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.recipient-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.recipient-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid rgba(139, 92, 246, 0.3);
    object-fit: cover;
}

.recipient-details {
    flex: 1;
}

.recipient-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 5px;
}

.recipient-tenant {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--htb-text-muted);
    background: rgba(139, 92, 246, 0.15);
    padding: 4px 10px;
    border-radius: 20px;
}

.recipient-tenant.external {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.external-tag {
    background: rgba(59, 130, 246, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.external-notice {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: #60a5fa;
    font-size: 0.9rem;
}

.external-notice i {
    font-size: 1.25rem;
    margin-top: 2px;
}

.compose-form-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-main);
    margin-bottom: 8px;
}

.form-label .required {
    color: #ef4444;
}

.form-input,
.form-textarea {
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 12px 16px;
    color: var(--htb-text-main);
    font-size: 1rem;
    transition: all 0.2s;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: var(--htb-text-muted);
}

.form-textarea {
    resize: vertical;
    min-height: 150px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.send-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
}

.cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(100, 116, 139, 0.2);
    border: none;
    border-radius: 10px;
    color: var(--htb-text-muted);
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.2s;
}

.cancel-btn:hover {
    background: rgba(100, 116, 139, 0.3);
}

.no-recipient {
    text-align: center;
    padding: 60px 20px;
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
}

.no-recipient i {
    font-size: 4rem;
    color: #8b5cf6;
    opacity: 0.3;
    margin-bottom: 20px;
}

.no-recipient h3 {
    color: var(--htb-text-main);
    font-size: 1.5rem;
    margin: 0 0 10px;
}

.no-recipient p {
    color: var(--htb-text-muted);
    margin: 0 0 25px;
}

.browse-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}

.browse-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
}

@media (max-width: 640px) {
    .form-actions {
        flex-direction: column;
    }

    .send-btn,
    .cancel-btn {
        justify-content: center;
    }
}
</style>

<script>
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
