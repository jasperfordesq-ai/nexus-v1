<?php
/**
 * GOV.UK Character Count Component
 * Source: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/character-count
 * Reference: https://design-system.service.gov.uk/components/character-count/
 *
 * WCAG 2.1 AA Compliance:
 * - 1.3.1 Info and Relationships (Level A)
 * - 3.3.2 Labels or Instructions (Level A)
 * - 4.1.2 Name, Role, Value (Level A)
 *
 * @param array $params Configuration parameters:
 *   - id (string, required): Textarea ID
 *   - name (string, required): Textarea name attribute
 *   - label (string, required): Label text
 *   - labelSize (string): 's', 'm', 'l', 'xl' - defaults to 'm'
 *   - isPageHeading (bool): Wrap label in h1
 *   - hint (string): Hint text
 *   - errorMessage (string): Error message text
 *   - value (string): Pre-filled value
 *   - maxlength (int): Maximum character count
 *   - maxwords (int): Maximum word count (alternative to maxlength)
 *   - threshold (int): Percentage at which to show count (0-100) - defaults to 0
 *   - rows (int): Number of textarea rows - defaults to 5
 *   - spellcheck (bool): Enable spellcheck - defaults to true
 *   - classes (string): Additional classes for the textarea
 *   - formGroupClasses (string): Additional classes for the form group
 *
 * Usage:
 * <?php
 * require_once __DIR__ . '/character-count.php';
 * civicone_govuk_character_count([
 *     'id' => 'description',
 *     'name' => 'description',
 *     'label' => 'Can you provide more detail?',
 *     'maxlength' => 500,
 *     'hint' => 'Do not include personal or financial information.'
 * ]);
 * ?>
 */

function civicone_govuk_character_count(array $params): void
{
    // Required parameters
    $id = $params['id'] ?? 'character-count';
    $name = $params['name'] ?? 'character-count';
    $label = $params['label'] ?? '';

    // Optional parameters
    $labelSize = $params['labelSize'] ?? 'm';
    $isPageHeading = $params['isPageHeading'] ?? false;
    $hint = $params['hint'] ?? '';
    $errorMessage = $params['errorMessage'] ?? '';
    $value = $params['value'] ?? '';
    $maxlength = $params['maxlength'] ?? null;
    $maxwords = $params['maxwords'] ?? null;
    $threshold = $params['threshold'] ?? 0;
    $rows = $params['rows'] ?? 5;
    $spellcheck = $params['spellcheck'] ?? true;
    $classes = $params['classes'] ?? '';
    $formGroupClasses = $params['formGroupClasses'] ?? '';

    // Build describedby list
    $describedBy = [];
    if ($hint) {
        $describedBy[] = $id . '-hint';
    }
    if ($errorMessage) {
        $describedBy[] = $id . '-error';
    }
    $describedBy[] = $id . '-info'; // Always include the count message
    $describedByAttr = 'aria-describedby="' . implode(' ', $describedBy) . '"';

    // Build CSS classes
    $formGroupClass = 'govuk-form-group govuk-character-count';
    if ($errorMessage) {
        $formGroupClass .= ' govuk-form-group--error';
    }
    if ($formGroupClasses) {
        $formGroupClass .= ' ' . htmlspecialchars($formGroupClasses);
    }

    $textareaClass = 'govuk-textarea govuk-js-character-count';
    if ($errorMessage) {
        $textareaClass .= ' govuk-textarea--error';
    }
    if ($classes) {
        $textareaClass .= ' ' . htmlspecialchars($classes);
    }

    $labelClass = 'govuk-label';
    if ($labelSize) {
        $labelClass .= ' govuk-label--' . htmlspecialchars($labelSize);
    }

    // Build the count message
    $countType = $maxwords ? 'words' : 'characters';
    $countLimit = $maxwords ?: $maxlength;
    $countMessage = $countLimit
        ? "You can enter up to {$countLimit} {$countType}"
        : '';

    // Data attributes for JavaScript
    $dataAttrs = 'data-module="govuk-character-count"';
    if ($maxlength) {
        $dataAttrs .= ' data-maxlength="' . (int)$maxlength . '"';
    }
    if ($maxwords) {
        $dataAttrs .= ' data-maxwords="' . (int)$maxwords . '"';
    }
    if ($threshold > 0) {
        $dataAttrs .= ' data-threshold="' . (int)$threshold . '"';
    }
    ?>
    <div class="<?= $formGroupClass ?>" <?= $dataAttrs ?>>
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

        <textarea class="<?= $textareaClass ?>"
                  id="<?= htmlspecialchars($id) ?>"
                  name="<?= htmlspecialchars($name) ?>"
                  rows="<?= (int)$rows ?>"
                  <?php if (!$spellcheck): ?>spellcheck="false"<?php endif; ?>
                  <?= $describedByAttr ?>><?= htmlspecialchars($value) ?></textarea>

        <div id="<?= htmlspecialchars($id) ?>-info" class="govuk-hint govuk-character-count__message">
            <?= htmlspecialchars($countMessage) ?>
        </div>
    </div>
    <?php
}
