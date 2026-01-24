<?php
/**
 * Pending Reviews List
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? 'Pending Reviews';
$hideHero = true;

Nexus\Core\SEO::setTitle('Pending Reviews - Federation');
Nexus\Core\SEO::setDescription('Leave feedback for your completed federated exchanges.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$pendingReviews = $pendingReviews ?? [];
?>

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/transactions" class="govuk-back-link govuk-!-margin-top-4">
        Back to Transactions
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <!-- Header -->
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">
            <i class="fa-solid fa-star govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
            Pending Reviews
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6" style="color: #505a5f;">
            Leave feedback for your federated exchanges
        </p>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <?php if (empty($pendingReviews)): ?>
                    <!-- Empty State -->
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #00703c;">
                        <i class="fa-solid fa-check-circle fa-3x govuk-!-margin-bottom-4" style="color: #00703c;" aria-hidden="true"></i>
                        <h2 class="govuk-heading-m">All caught up!</h2>
                        <p class="govuk-body govuk-!-margin-bottom-4">You've reviewed all your completed federated exchanges.</p>
                        <a href="<?= $basePath ?>/federation/transactions" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-exchange-alt govuk-!-margin-right-2" aria-hidden="true"></i>
                            View Transactions
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Pending Reviews List -->
                    <div role="list" aria-label="Pending reviews">
                        <?php foreach ($pendingReviews as $review): ?>
                            <?php
                            $otherPartyName = htmlspecialchars($review['other_party_name'] ?? 'Member');
                            $timebank = htmlspecialchars($review['other_party_timebank'] ?? 'Unknown Timebank');
                            $amount = number_format((float)($review['amount'] ?? 0), 2);
                            $description = htmlspecialchars($review['description'] ?? '');
                            $completedAt = $review['completed_at'] ?? null;
                            $direction = $review['direction'] ?? 'sent';
                            $transactionId = $review['id'] ?? 0;
                            $directionColor = $direction === 'sent' ? '#d4351c' : '#00703c';
                            ?>
                            <article class="govuk-!-padding-4 govuk-!-margin-bottom-4" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid <?= $directionColor ?>;" role="listitem">
                                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: <?= $directionColor ?>; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fa-solid fa-<?= $direction === 'sent' ? 'arrow-up' : 'arrow-down' ?>" aria-hidden="true"></i>
                                    </div>

                                    <div style="flex: 1; min-width: 200px;">
                                        <p class="govuk-body-l govuk-!-font-weight-bold govuk-!-margin-bottom-1"><?= $otherPartyName ?></p>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= $timebank ?>
                                        </p>
                                    </div>

                                    <div style="text-align: right;">
                                        <p class="govuk-heading-m govuk-!-margin-bottom-1" style="color: <?= $directionColor ?>;">
                                            <?= $direction === 'sent' ? '-' : '+' ?><?= $amount ?> hrs
                                        </p>
                                        <?php if ($completedAt): ?>
                                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                                <time datetime="<?= date('c', strtotime($completedAt)) ?>">
                                                    <?= date('j M Y', strtotime($completedAt)) ?>
                                                </time>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <a href="<?= $basePath ?>/federation/review/<?= $transactionId ?>"
                                       class="govuk-button govuk-!-margin-bottom-0" style="background: #f47738;" data-module="govuk-button">
                                        <i class="fa-solid fa-star govuk-!-margin-right-2" aria-hidden="true"></i>
                                        Leave Review
                                    </a>
                                </div>

                                <?php if ($description): ?>
                                    <p class="govuk-body-s govuk-!-margin-top-3 govuk-!-margin-bottom-0" style="color: #505a5f; border-top: 1px solid #b1b4b6; padding-top: 12px;">
                                        <?= $description ?>
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Info Box -->
                    <div class="govuk-inset-text">
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-info-circle govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                            <strong>Why leave reviews?</strong> Reviews help build trust across timebanks. Your feedback helps other members make informed decisions and rewards great community members with recognition.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Federation offline indicator -->
<script>
(function() {
    'use strict';
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
