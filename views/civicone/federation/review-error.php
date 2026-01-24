<?php
/**
 * Review Error Page
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = 'Cannot Submit Review';
\Nexus\Core\SEO::setTitle('Cannot Submit Review - Federation');
\Nexus\Core\SEO::setDescription('Unable to submit your review at this time.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$error = $error ?? 'Unable to submit review';
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
            <h1 class="govuk-error-summary__title" id="error-summary-title">
                Cannot Submit Review
            </h1>
            <div class="govuk-error-summary__body">
                <p class="govuk-body"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>

        <div class="govuk-button-group">
            <a href="<?= $basePath ?>/federation/transactions" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-2" aria-hidden="true"></i>
                Back to Transactions
            </a>
            <a href="<?= $basePath ?>/federation/reviews/pending" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-star govuk-!-margin-right-2" aria-hidden="true"></i>
                View Pending Reviews
            </a>
        </div>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
