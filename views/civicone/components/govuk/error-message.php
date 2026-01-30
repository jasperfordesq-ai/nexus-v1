<?php
/**
 * GOV.UK Error Message Component
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/error-message/template.njk
 * DOCS: https://design-system.service.gov.uk/components/error-message/
 *
 * ACCESSIBILITY:
 * - Uses <span class="govuk-visually-hidden"> for screen reader prefix (NOT CSS ::before)
 * - The visually hidden text ensures screen readers announce "Error: [message]"
 * - Must be associated with form field via aria-describedby
 *
 * @param array $params {
 *     @type string $text       Error message text (required if no html)
 *     @type string $html       Error message HTML (required if no text)
 *     @type string $id         ID attribute for the error message
 *     @type string $classes    Additional CSS classes
 *     @type string $visuallyHiddenText  Text for screen readers (default: "Error")
 *     @type array  $attributes Additional HTML attributes
 * }
 *
 * Usage:
 * <?php civicone_govuk_error_message([
 *     'id' => 'email-error',
 *     'text' => 'Enter your email address'
 * ]); ?>
 */

function civicone_govuk_error_message(array $params): void
{
    // Get parameters with defaults
    $text = $params['text'] ?? '';
    $html = $params['html'] ?? '';
    $id = $params['id'] ?? '';
    $classes = $params['classes'] ?? '';
    $visuallyHiddenText = $params['visuallyHiddenText'] ?? 'Error';
    $attributes = $params['attributes'] ?? [];

    // Build the message content
    $content = $html ?: htmlspecialchars($text);

    if (empty($content)) {
        return; // Don't render empty error messages
    }

    // Build CSS classes
    $cssClasses = 'govuk-error-message';
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
<p<?= $id ? ' id="' . htmlspecialchars($id) . '"' : '' ?> class="<?= htmlspecialchars($cssClasses) ?>"<?= $attrString ?>>
    <?php if ($visuallyHiddenText): ?>
    <span class="govuk-visually-hidden"><?= htmlspecialchars($visuallyHiddenText) ?>:</span>
    <?php endif; ?>
    <?= $content ?>
</p>
    <?php
}
