<?php
/**
 * GOV.UK Exit This Page Component
 * Source: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/exit-this-page
 * Reference: https://design-system.service.gov.uk/components/exit-this-page/
 *
 * A safety component that allows users to quickly exit sensitive content.
 * Useful for pages about domestic abuse, mental health, or other sensitive topics.
 *
 * WCAG 2.1 AA Compliance:
 * - 2.1.1 Keyboard (Level A) - Shift key shortcut
 * - 2.4.4 Link Purpose (Level A)
 * - 3.2.2 On Input (Level A)
 *
 * Features:
 * - Prominent warning-styled button
 * - Keyboard shortcut (Shift pressed 3 times)
 * - Clears browser history by replacing location
 * - Redirects to safe external page (default: BBC Weather)
 *
 * @param array $params Configuration parameters:
 *   - redirectUrl (string): URL to redirect to - defaults to 'https://www.bbc.co.uk/weather'
 *   - text (string): Button text - defaults to 'Exit this page'
 *   - id (string): Element ID
 *   - classes (string): Additional CSS classes
 *   - activatedText (string): Screen reader text when activated - defaults to 'Loading.'
 *   - timedOutText (string): Screen reader text on timeout - defaults to 'Exit this page expired.'
 *   - pressTwoMoreTimesText (string): Guidance text - defaults to 'Shift, press 2 more times to exit.'
 *   - pressOneMoreTimeText (string): Guidance text - defaults to 'Shift, press 1 more time to exit.'
 *
 * Usage:
 * <?php
 * require_once __DIR__ . '/exit-this-page.php';
 * civicone_govuk_exit_this_page([
 *     'redirectUrl' => 'https://www.google.com'
 * ]);
 * ?>
 */

function civicone_govuk_exit_this_page(array $params = []): void
{
    // Default configuration
    $redirectUrl = $params['redirectUrl'] ?? 'https://www.bbc.co.uk/weather';
    $text = $params['text'] ?? 'Exit this page';
    $id = $params['id'] ?? '';
    $classes = $params['classes'] ?? '';

    // i18n strings
    $activatedText = $params['activatedText'] ?? 'Loading.';
    $timedOutText = $params['timedOutText'] ?? 'Exit this page expired.';
    $pressTwoMoreTimesText = $params['pressTwoMoreTimesText'] ?? 'Shift, press 2 more times to exit.';
    $pressOneMoreTimeText = $params['pressOneMoreTimeText'] ?? 'Shift, press 1 more time to exit.';

    // Build CSS classes
    $containerClasses = 'govuk-exit-this-page';
    if ($classes) {
        $containerClasses .= ' ' . htmlspecialchars($classes);
    }

    // Build ID attribute
    $idAttr = $id ? 'id="' . htmlspecialchars($id) . '"' : '';
    ?>
    <div class="<?= $containerClasses ?>"
         <?= $idAttr ?>
         data-module="govuk-exit-this-page"
         data-i18n.activated="<?= htmlspecialchars($activatedText) ?>"
         data-i18n.timed-out="<?= htmlspecialchars($timedOutText) ?>"
         data-i18n.press-two-more-times="<?= htmlspecialchars($pressTwoMoreTimesText) ?>"
         data-i18n.press-one-more-time="<?= htmlspecialchars($pressOneMoreTimeText) ?>">

        <a href="<?= htmlspecialchars($redirectUrl) ?>"
           role="button"
           draggable="false"
           class="govuk-button govuk-button--warning govuk-exit-this-page__button govuk-js-exit-this-page-button"
           data-module="govuk-button"
           rel="nofollow noreferrer">
            <span class="govuk-visually-hidden">Emergency </span>
            <?= htmlspecialchars($text) ?>
        </a>
    </div>
    <?php
}

/**
 * Renders the Exit This Page component with skip link overlay
 * This is the full implementation with the skip link for keyboard users
 *
 * @param array $params Same as civicone_govuk_exit_this_page()
 */
function civicone_govuk_exit_this_page_with_skiplink(array $params = []): void
{
    $redirectUrl = $params['redirectUrl'] ?? 'https://www.bbc.co.uk/weather';
    ?>
    <!-- Exit This Page Skip Link (appears before main content for keyboard nav) -->
    <div class="govuk-exit-this-page__skiplink-container" hidden>
        <a href="<?= htmlspecialchars($redirectUrl) ?>"
           class="govuk-skip-link govuk-exit-this-page__skiplink"
           rel="nofollow noreferrer">
            Press Shift 3 times to exit
        </a>
    </div>
    <?php
    civicone_govuk_exit_this_page($params);
}
