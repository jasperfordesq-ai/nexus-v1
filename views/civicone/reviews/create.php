<?php
/**
 * Review Creation Form - GOV.UK Design System
 * Template D: Form/Flow
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Write a review';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Wallet', 'href' => $basePath . '/wallet'],
            ['text' => 'Write a review']
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Write a review</h1>

                <?php if (!empty($receiver)): ?>
                    <p class="govuk-body-l">
                        Share your experience with <?= htmlspecialchars($receiver['name'] ?? $receiver['first_name'] . ' ' . $receiver['last_name']) ?>.
                    </p>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                        <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>
                                    <a href="#rating"><?= htmlspecialchars($_GET['error']) ?></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="<?= $basePath ?>/reviews/store" method="POST" novalidate>
                    <?= Csrf::input() ?>
                    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction_id ?? '') ?>">
                    <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($receiver['id'] ?? '') ?>">

                    <!-- Rating -->
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    How would you rate this exchange?
                                </h2>
                            </legend>
                            <div id="rating-hint" class="govuk-hint">
                                Select a rating from 1 (poor) to 5 (excellent)
                            </div>
                            <div class="govuk-radios govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="rating-1" name="rating" type="radio" value="1" aria-describedby="rating-hint">
                                    <label class="govuk-label govuk-radios__label" for="rating-1">1</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="rating-2" name="rating" type="radio" value="2">
                                    <label class="govuk-label govuk-radios__label" for="rating-2">2</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="rating-3" name="rating" type="radio" value="3">
                                    <label class="govuk-label govuk-radios__label" for="rating-3">3</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="rating-4" name="rating" type="radio" value="4">
                                    <label class="govuk-label govuk-radios__label" for="rating-4">4</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="rating-5" name="rating" type="radio" value="5" checked>
                                    <label class="govuk-label govuk-radios__label" for="rating-5">5</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Review Comment -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="comment">
                            Your review
                        </label>
                        <div id="comment-hint" class="govuk-hint">
                            Share your experience. What went well? Would you recommend this member to others?
                        </div>
                        <textarea class="govuk-textarea"
                                  id="comment"
                                  name="comment"
                                  rows="5"
                                  aria-describedby="comment-hint"></textarea>
                    </div>

                    <!-- Would Recommend -->
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Would you recommend this member?
                                </h2>
                            </legend>
                            <div class="govuk-radios" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="recommend-yes" name="recommend" type="radio" value="1" checked>
                                    <label class="govuk-label govuk-radios__label" for="recommend-yes">Yes</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="recommend-no" name="recommend" type="radio" value="0">
                                    <label class="govuk-label govuk-radios__label" for="recommend-no">No</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        Submit review
                    </button>

                </form>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-s">Review guidelines</h2>

                    <ul class="govuk-list govuk-list--bullet">
                        <li>Be honest and constructive</li>
                        <li>Focus on the exchange experience</li>
                        <li>Respect other members' privacy</li>
                        <li>Keep feedback relevant and helpful</li>
                    </ul>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Why reviews matter</h2>
                    <p class="govuk-body">
                        Reviews help build trust in our community. They help members make informed decisions
                        about who to exchange with.
                    </p>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
