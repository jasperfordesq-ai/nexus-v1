<?php
/**
 * Share Target Compose Page
 * Displays options for what to do with shared content
 */

// Determine layout
$layout = layout(); // Fixed: centralized detection

// Get shared data
$shareData = $_SESSION['share_data'] ?? [];
$title = htmlspecialchars($shareData['title'] ?? '');
$text = htmlspecialchars($shareData['text'] ?? '');
$url = htmlspecialchars($shareData['url'] ?? '');
$combined = htmlspecialchars($shareData['combined'] ?? '');
$media = $shareData['media'] ?? null;

// Include appropriate header
if ($layout === 'civicone') {
    require dirname(__DIR__) . '/layouts/civicone/header.php';
} else {
    require dirname(__DIR__) . '/layouts/modern/header.php';
}
?>

<div class="share-compose-page">
    <div class="share-container">
        <div class="share-header">
            <h1>Share Content</h1>
            <p class="share-subtitle">Choose what you'd like to do with this content</p>
        </div>

        <!-- Preview of shared content -->
        <div class="share-preview">
            <h3>Shared Content</h3>
            <?php if ($media): ?>
                <div class="share-media">
                    <?php if (strpos($media, '.mp4') !== false || strpos($media, '.webm') !== false): ?>
                        <video src="<?= $media ?>" controls style="max-width: 100%; border-radius: 8px;"></video>
                    <?php else: ?>
                        <img src="<?= $media ?>" alt="Shared media" style="max-width: 100%; border-radius: 8px;">
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($title): ?>
                <div class="share-title"><strong><?= $title ?></strong></div>
            <?php endif; ?>

            <?php if ($text): ?>
                <div class="share-text"><?= nl2br($text) ?></div>
            <?php endif; ?>

            <?php if ($url): ?>
                <div class="share-url">
                    <a href="<?= $url ?>" target="_blank" rel="noopener"><?= $url ?></a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action options -->
        <div class="share-actions">
            <form method="POST" action="/share-target/create" class="share-action-form">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="action" value="post">
                <button type="submit" class="share-action-btn share-action-post">
                    <i class="fas fa-comment-alt"></i>
                    <span class="action-label">Create Post</span>
                    <span class="action-desc">Share to your feed</span>
                </button>
            </form>

            <form method="POST" action="/share-target/create" class="share-action-form">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="action" value="message">
                <button type="submit" class="share-action-btn share-action-message">
                    <i class="fas fa-envelope"></i>
                    <span class="action-label">Send Message</span>
                    <span class="action-desc">Share with a member</span>
                </button>
            </form>

            <form method="POST" action="/share-target/create" class="share-action-form">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="action" value="listing">
                <button type="submit" class="share-action-btn share-action-listing">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="action-label">Create Listing</span>
                    <span class="action-desc">Offer or request</span>
                </button>
            </form>
        </div>

        <div class="share-cancel">
            <a href="/" class="btn-cancel">Cancel</a>
        </div>
    </div>
</div>

<style>
.share-compose-page {
    min-height: 100vh;
    padding: 20px;
    background: var(--bg-primary, #0f172a);
}

.share-container {
    max-width: 500px;
    margin: 0 auto;
}

.share-header {
    text-align: center;
    margin-bottom: 24px;
}

.share-header h1 {
    font-size: 1.5rem;
    color: var(--text-primary, #f1f5f9);
    margin: 0 0 8px 0;
}

.share-subtitle {
    color: var(--text-secondary, #94a3b8);
    margin: 0;
}

.share-preview {
    background: var(--bg-secondary, #1e293b);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.share-preview h3 {
    font-size: 0.875rem;
    color: var(--text-secondary, #94a3b8);
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.share-media {
    margin-bottom: 12px;
}

.share-title {
    color: var(--text-primary, #f1f5f9);
    font-size: 1rem;
    margin-bottom: 8px;
}

.share-text {
    color: var(--text-secondary, #94a3b8);
    font-size: 0.9rem;
    margin-bottom: 8px;
    white-space: pre-wrap;
    word-break: break-word;
}

.share-url {
    margin-top: 8px;
}

.share-url a {
    color: var(--accent-primary, #6366f1);
    font-size: 0.85rem;
    word-break: break-all;
}

.share-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.share-action-form {
    margin: 0;
}

.share-action-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: var(--bg-secondary, #1e293b);
    border: 1px solid var(--border-color, #334155);
    border-radius: 12px;
    color: var(--text-primary, #f1f5f9);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}

.share-action-btn:hover {
    background: var(--bg-tertiary, #334155);
    transform: translateY(-2px);
}

.share-action-btn:active {
    transform: translateY(0);
}

.share-action-btn i {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    flex-shrink: 0;
}

.share-action-post i {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.share-action-message i {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.share-action-listing i {
    background: rgba(168, 85, 247, 0.2);
    color: #a855f7;
}

.action-label {
    font-weight: 600;
    font-size: 1rem;
    display: block;
}

.action-desc {
    font-size: 0.85rem;
    color: var(--text-secondary, #94a3b8);
    display: block;
    margin-top: 2px;
}

.share-cancel {
    text-align: center;
    margin-top: 24px;
}

.btn-cancel {
    color: var(--text-secondary, #94a3b8);
    text-decoration: none;
    padding: 12px 24px;
    display: inline-block;
}

.btn-cancel:hover {
    color: var(--text-primary, #f1f5f9);
}

/* Safe area for notched phones */
@supports (padding-bottom: env(safe-area-inset-bottom)) {
    .share-compose-page {
        padding-bottom: calc(20px + env(safe-area-inset-bottom));
    }
}
</style>

<?php
// Include appropriate footer
if ($layout === 'civicone') {
    require dirname(__DIR__) . '/layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/layouts/modern/footer.php';
}
?>
