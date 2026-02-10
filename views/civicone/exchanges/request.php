<?php
/**
 * CivicOne View: Request Exchange
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Request Exchange';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Listings', 'href' => $basePath . '/listings'],
        ['text' => htmlspecialchars($listing['title']), 'href' => $basePath . '/listings/' . $listing['id']],
        ['text' => 'Request exchange']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back to listing</a>

<!-- Flash Messages -->
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="govuk-error-summary" data-module="govuk-error-summary">
        <h2 class="govuk-error-summary__title">There is a problem</h2>
        <div class="govuk-error-summary__body">
            <p><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">Request exchange</h1>

        <!-- Listing Summary -->
        <div class="govuk-inset-text govuk-!-margin-bottom-6">
            <p class="govuk-body">
                <strong><?= htmlspecialchars($listing['title']) ?></strong><br>
                <span class="govuk-tag <?= $listing['type'] === 'offer' ? 'govuk-tag--green' : 'govuk-tag--blue' ?>">
                    <?= ucfirst($listing['type']) ?>
                </span>
                <?php if (!empty($listing['author_name'])): ?>
                    by <?= htmlspecialchars($listing['author_name']) ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- How it works -->
        <details class="govuk-details govuk-!-margin-bottom-6" data-module="govuk-details" open>
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">How exchanges work</span>
            </summary>
            <div class="govuk-details__text">
                <ol class="govuk-list govuk-list--number">
                    <li>Your request is sent to the provider</li>
                    <li>They can accept or decline your request</li>
                    <li>Once work is complete, both parties confirm the hours</li>
                    <li>Time credits are transferred automatically</li>
                </ol>
            </div>
        </details>

        <!-- Exchange Request Form -->
        <form action="<?= $basePath ?>/exchanges" method="POST">
            <?= Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="proposed_hours">
                    Proposed hours
                </label>
                <div class="govuk-hint" id="hours-hint">
                    Enter the number of time credits you're proposing for this exchange.
                    The listing suggests <?= number_format($defaultHours, 1) ?> hour(s).
                </div>
                <input class="govuk-input govuk-input--width-4"
                       id="proposed_hours"
                       name="proposed_hours"
                       type="number"
                       value="<?= number_format($defaultHours, 1) ?>"
                       min="0.25"
                       max="24"
                       step="0.25"
                       aria-describedby="hours-hint"
                       required>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="message">
                    Message (optional)
                </label>
                <div class="govuk-hint" id="message-hint">
                    Include any relevant details or questions about the exchange.
                </div>
                <textarea class="govuk-textarea"
                          id="message"
                          name="message"
                          rows="5"
                          aria-describedby="message-hint"></textarea>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    Send request
                </button>
                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
