<?php
/**
 * Modern View: Request Exchange
 * Form to request an exchange for a listing
 */
$hTitle = 'Request Exchange';
$hSubtitle = 'Request an exchange for this listing';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Exchange';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
?>

<div class="exchange-request-form">
    <!-- Back Button -->
    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="glass-button glass-button--ghost glass-button--sm" style="margin-bottom: var(--space-4);">
        <i class="fa-solid fa-arrow-left"></i> Back to Listing
    </a>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="glass-alert glass-alert--danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="exchange-request-card">
        <h1 style="margin-bottom: var(--space-6);">Request Exchange</h1>

        <!-- Listing Preview -->
        <div class="exchange-listing-preview">
            <?php if (!empty($listing['image_url'])): ?>
                <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                     alt=""
                     class="exchange-listing-preview-image">
            <?php endif; ?>
            <div class="exchange-listing-preview-info">
                <div class="exchange-listing-preview-title">
                    <?= htmlspecialchars($listing['title']) ?>
                </div>
                <span class="exchange-listing-preview-type exchange-listing-preview-type--<?= $listing['type'] ?>">
                    <?= ucfirst($listing['type']) ?>
                </span>
                <?php if (!empty($listing['author_name'])): ?>
                    <p style="margin-top: var(--space-2); color: var(--color-text-muted); font-size: var(--font-size-sm);">
                        by <?= htmlspecialchars($listing['author_name']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Exchange Request Form -->
        <form action="<?= $basePath ?>/exchanges" method="POST">
            <?= Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">

            <div class="exchange-form-group">
                <label for="proposed_hours" class="exchange-form-label">
                    Proposed Hours
                </label>
                <input type="number"
                       id="proposed_hours"
                       name="proposed_hours"
                       value="<?= number_format($defaultHours, 1) ?>"
                       min="0.25"
                       max="24"
                       step="0.25"
                       class="exchange-form-input"
                       required>
                <p class="exchange-form-hint">
                    Enter the number of time credits you're proposing for this exchange.
                    The listing suggests <?= number_format($defaultHours, 1) ?> hour(s).
                </p>
            </div>

            <div class="exchange-form-group">
                <label for="message" class="exchange-form-label">
                    Message (Optional)
                </label>
                <textarea id="message"
                          name="message"
                          class="exchange-form-input"
                          rows="4"
                          placeholder="Add a message to the provider..."></textarea>
                <p class="exchange-form-hint">
                    Include any relevant details or questions about the exchange.
                </p>
            </div>

            <!-- Info Notice -->
            <div class="glass-alert glass-alert--info" style="margin-bottom: var(--space-6);">
                <i class="fa-solid fa-info-circle"></i>
                <span>
                    <strong>How it works:</strong>
                    <ol style="margin: var(--space-2) 0 0 var(--space-4); padding: 0;">
                        <li>Your request is sent to the provider</li>
                        <li>They can accept or decline</li>
                        <li>Once work is complete, both parties confirm the hours</li>
                        <li>Time credits are transferred automatically</li>
                    </ol>
                </span>
            </div>

            <div style="display: flex; gap: var(--space-3);">
                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="glass-button glass-button--outline" style="flex: 1; justify-content: center;">
                    Cancel
                </a>
                <button type="submit" class="glass-button glass-button--primary" style="flex: 2; justify-content: center;">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Request
                </button>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
