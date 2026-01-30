<?php
/**
 * GOV.UK Label Component
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/label/template.njk
 * DOCS: https://design-system.service.gov.uk/components/text-input/#labels
 *
 * ACCESSIBILITY:
 * - Labels must be associated with form fields via the 'for' attribute
 * - When isPageHeading is true, wraps label in <h1> for proper document structure
 * - Size modifiers: govuk-label--xl, govuk-label--l, govuk-label--m, govuk-label--s
 *
 * @param array $params {
 *     @type string $text          Label text (required if no html)
 *     @type string $html          Label HTML (required if no text)
 *     @type string $for           ID of the form field this label is for
 *     @type string $classes       Additional CSS classes (e.g., 'govuk-label--l')
 *     @type bool   $isPageHeading Whether to wrap label in <h1> (default: false)
 *     @type array  $attributes    Additional HTML attributes
 * }
 *
 * Usage:
 * <?php civicone_govuk_label([
 *     'for' => 'email',
 *     'text' => 'Email address',
 *     'classes' => 'govuk-label--l',
 *     'isPageHeading' => true
 * ]); ?>
 */

function civicone_govuk_label(array $params): void
{
    // Get parameters with defaults
    $text = $params['text'] ?? '';
    $html = $params['html'] ?? '';
    $for = $params['for'] ?? '';
    $classes = $params['classes'] ?? '';
    $isPageHeading = $params['isPageHeading'] ?? false;
    $attributes = $params['attributes'] ?? [];

    // Build the content
    $content = $html ?: htmlspecialchars($text);

    if (empty($content)) {
        return; // Don't render empty labels
    }

    // Build CSS classes
    $cssClasses = 'govuk-label';
    if ($classes) {
        $cssClasses .= ' ' . $classes;
    }

    // Build additional attributes string
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }

    // Build the for attribute
    $forAttr = $for ? ' for="' . htmlspecialchars($for) . '"' : '';

    // Render the component
    if ($isPageHeading): ?>
<h1 class="govuk-label-wrapper">
    <label class="<?= htmlspecialchars($cssClasses) ?>"<?= $forAttr ?><?= $attrString ?>>
        <?= $content ?>
    </label>
</h1>
    <?php else: ?>
<label class="<?= htmlspecialchars($cssClasses) ?>"<?= $forAttr ?><?= $attrString ?>>
    <?= $content ?>
</label>
    <?php endif;
}
