<?php
/**
 * GOV.UK Password Input Component
 * Source: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/password-input
 * Reference: https://design-system.service.gov.uk/components/password-input/
 *
 * WCAG 2.1 AA Compliance:
 * - 1.3.1 Info and Relationships (Level A)
 * - 1.3.5 Identify Input Purpose (Level AA)
 * - 4.1.2 Name, Role, Value (Level A)
 *
 * @param array $params Configuration parameters:
 *   - id (string, required): Input ID
 *   - name (string, required): Input name attribute
 *   - label (string, required): Label text
 *   - labelSize (string): 's', 'm', 'l', 'xl' - defaults to 'm'
 *   - isPageHeading (bool): Wrap label in h1
 *   - hint (string): Hint text
 *   - errorMessage (string): Error message text
 *   - value (string): Pre-filled value
 *   - autocomplete (string): 'new-password' or 'current-password' - defaults to 'current-password'
 *   - classes (string): Additional classes for the input
 *   - formGroupClasses (string): Additional classes for the form group
 *   - showPasswordText (string): Button text for "show" state - defaults to 'Show'
 *   - hidePasswordText (string): Button text for "hide" state - defaults to 'Hide'
 *   - showPasswordAriaLabel (string): Aria label for show - defaults to 'Show password'
 *   - hidePasswordAriaLabel (string): Aria label for hide - defaults to 'Hide password'
 *   - passwordShownAnnouncement (string): Screen reader announcement - defaults to 'Your password is visible'
 *   - passwordHiddenAnnouncement (string): Screen reader announcement - defaults to 'Your password is hidden'
 *
 * Usage:
 * <?php
 * require_once __DIR__ . '/password-input.php';
 * civicone_govuk_password_input([
 *     'id' => 'password',
 *     'name' => 'password',
 *     'label' => 'Password',
 *     'hint' => 'Must be at least 12 characters',
 *     'autocomplete' => 'new-password'
 * ]);
 * ?>
 */

function civicone_govuk_password_input(array $params): void
{
    // Required parameters
    $id = $params['id'] ?? 'password';
    $name = $params['name'] ?? 'password';
    $label = $params['label'] ?? 'Password';

    // Optional parameters
    $labelSize = $params['labelSize'] ?? 'm';
    $isPageHeading = $params['isPageHeading'] ?? false;
    $hint = $params['hint'] ?? '';
    $errorMessage = $params['errorMessage'] ?? '';
    $value = $params['value'] ?? '';
    $autocomplete = $params['autocomplete'] ?? 'current-password';
    $classes = $params['classes'] ?? '';
    $formGroupClasses = $params['formGroupClasses'] ?? '';

    // Internationalisation strings
    $showPasswordText = $params['showPasswordText'] ?? 'Show';
    $hidePasswordText = $params['hidePasswordText'] ?? 'Hide';
    $showPasswordAriaLabel = $params['showPasswordAriaLabel'] ?? 'Show password';
    $hidePasswordAriaLabel = $params['hidePasswordAriaLabel'] ?? 'Hide password';
    $passwordShownAnnouncement = $params['passwordShownAnnouncement'] ?? 'Your password is visible';
    $passwordHiddenAnnouncement = $params['passwordHiddenAnnouncement'] ?? 'Your password is hidden';

    // Build describedby list
    $describedBy = [];
    if ($hint) {
        $describedBy[] = $id . '-hint';
    }
    if ($errorMessage) {
        $describedBy[] = $id . '-error';
    }
    $describedByAttr = !empty($describedBy) ? 'aria-describedby="' . implode(' ', $describedBy) . '"' : '';

    // Build CSS classes
    $formGroupClass = 'govuk-form-group';
    if ($errorMessage) {
        $formGroupClass .= ' govuk-form-group--error';
    }
    if ($formGroupClasses) {
        $formGroupClass .= ' ' . htmlspecialchars($formGroupClasses);
    }

    $inputClass = 'govuk-input govuk-password-input__input govuk-js-password-input-input';
    if ($errorMessage) {
        $inputClass .= ' govuk-input--error';
    }
    if ($classes) {
        $inputClass .= ' ' . htmlspecialchars($classes);
    }

    $labelClass = 'govuk-label';
    if ($labelSize) {
        $labelClass .= ' govuk-label--' . htmlspecialchars($labelSize);
    }
    ?>
    <div class="<?= $formGroupClass ?>">
        <?php if ($isPageHeading): ?>
        <h1 class="govuk-label-wrapper">
            <label class="<?= $labelClass ?>" for="<?= htmlspecialchars($id) ?>">
                <?= htmlspecialchars($label) ?>
            </label>
        </h1>
        <?php else: ?>
        <label class="<?= $labelClass ?>" for="<?= htmlspecialchars($id) ?>">
            <?= htmlspecialchars($label) ?>
        </label>
        <?php endif; ?>

        <?php if ($hint): ?>
        <div id="<?= htmlspecialchars($id) ?>-hint" class="govuk-hint">
            <?= htmlspecialchars($hint) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <p id="<?= htmlspecialchars($id) ?>-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($errorMessage) ?>
        </p>
        <?php endif; ?>

        <div class="govuk-password-input"
             data-module="govuk-password-input"
             data-i18n.show-password="<?= htmlspecialchars($showPasswordText) ?>"
             data-i18n.hide-password="<?= htmlspecialchars($hidePasswordText) ?>"
             data-i18n.show-password-aria-label="<?= htmlspecialchars($showPasswordAriaLabel) ?>"
             data-i18n.hide-password-aria-label="<?= htmlspecialchars($hidePasswordAriaLabel) ?>"
             data-i18n.password-shown-announcement="<?= htmlspecialchars($passwordShownAnnouncement) ?>"
             data-i18n.password-hidden-announcement="<?= htmlspecialchars($passwordHiddenAnnouncement) ?>">
            <div class="govuk-password-input__wrapper">
                <input class="<?= $inputClass ?>"
                       id="<?= htmlspecialchars($id) ?>"
                       name="<?= htmlspecialchars($name) ?>"
                       type="password"
                       <?php if ($value): ?>value="<?= htmlspecialchars($value) ?>"<?php endif; ?>
                       autocomplete="<?= htmlspecialchars($autocomplete) ?>"
                       autocapitalize="none"
                       spellcheck="false"
                       <?= $describedByAttr ?>
                       required>
                <button type="button"
                        class="govuk-button govuk-button--secondary govuk-password-input__toggle govuk-js-password-input-toggle"
                        data-module="govuk-button"
                        aria-controls="<?= htmlspecialchars($id) ?>"
                        aria-label="<?= htmlspecialchars($showPasswordAriaLabel) ?>"
                        hidden>
                    <?= htmlspecialchars($showPasswordText) ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}
