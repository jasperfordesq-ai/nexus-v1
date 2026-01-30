<?php
/**
 * GOV.UK Hint Component
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/hint/template.njk
 * DOCS: https://design-system.service.gov.uk/components/text-input/#hint-text
 *
 * ACCESSIBILITY:
 * - Must be associated with form field via aria-describedby
 * - Uses <div> element (not <span>) for block-level content
 * - Grey color (#505a5f) meets WCAG 2.1 AA contrast requirements
 *
 * @param array $params {
 *     @type string $text       Hint text (required if no html)
 *     @type string $html       Hint HTML (required if no text)
 *     @type string $id         ID attribute for the hint (required for aria-describedby)
 *     @type string $classes    Additional CSS classes
 *     @type array  $attributes Additional HTML attributes
 * }
 *
 * Usage:
 * <?php civicone_govuk_hint([
 *     'id' => 'email-hint',
 *     'text' => 'We will only use this to send you a confirmation email'
 * ]); ?>
 */

function civicone_govuk_hint(array $params): void
{
    // Get parameters with defaults
    $text = $params['text'] ?? '';
    $html = $params['html'] ?? '';
    $id = $params['id'] ?? '';
    $classes = $params['classes'] ?? '';
    $attributes = $params['attributes'] ?? [];

    // Build the content
    $content = $html ?: htmlspecialchars($text);

    if (empty($content)) {
        return; // Don't render empty hints
    }

    // Build CSS classes
    $cssClasses = 'govuk-hint';
    if ($classes) {
        $cssClasses .= ' ' . $classes;
    }

    // Build additional attributes string
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }

    // Render the component
    ?>
<div<?= $id ? ' id="' . htmlspecialchars($id) . '"' : '' ?> class="<?= htmlspecialchars($cssClasses) ?>"<?= $attrString ?>>
    <?= $content ?>
</div>
    <?php
}
