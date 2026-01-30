<?php
/**
 * GOV.UK Button Component
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/button/template.njk
 * DOCS: https://design-system.service.gov.uk/components/button/
 *
 * @param array $args {
 *     @type string $text              Button text (required)
 *     @type string $html              Button HTML content (alternative to text)
 *     @type string $type              HTML button type: 'submit' (default), 'button', 'reset'
 *     @type string $href              Link URL (creates <a> instead of <button>)
 *     @type string $classes           Additional CSS classes (e.g., 'govuk-button--secondary', 'govuk-button--warning')
 *     @type string $id                Element ID
 *     @type string $name              Form field name
 *     @type string $value             Form field value
 *     @type bool   $disabled          Whether button is disabled
 *     @type bool   $isStartButton     Whether to show as green start button with arrow
 *     @type bool   $preventDoubleClick Enable double-click prevention
 *     @type string $ariaLabel         Accessible label (optional)
 *     @type string $onclick           JavaScript onclick handler (optional, prefer unobtrusive JS)
 *     @type array  $attributes        Additional HTML attributes
 * }
 *
 * Usage:
 * <?php
 * require_once 'button.php';
 * echo civicone_govuk_button(['text' => 'Continue', 'isStartButton' => true]);
 * echo civicone_govuk_button(['text' => 'Save and continue']);
 * echo civicone_govuk_button(['text' => 'Cancel', 'classes' => 'govuk-button--secondary']);
 * echo civicone_govuk_button(['text' => 'Delete', 'classes' => 'govuk-button--warning']);
 * ?>
 */

function civicone_govuk_button(array $args = []): string
{
    // Get parameters with defaults
    $text = $args['text'] ?? 'Button';
    $html = $args['html'] ?? '';
    $type = $args['type'] ?? 'submit';
    $href = $args['href'] ?? null;
    $classes = $args['classes'] ?? ($args['class'] ?? ''); // Support both 'classes' and legacy 'class'
    $id = $args['id'] ?? '';
    $name = $args['name'] ?? '';
    $value = $args['value'] ?? '';
    $disabled = $args['disabled'] ?? false;
    $isStartButton = $args['isStartButton'] ?? false;
    $preventDoubleClick = $args['preventDoubleClick'] ?? null;
    $ariaLabel = $args['ariaLabel'] ?? null;
    $onclick = $args['onclick'] ?? null;
    $attributes = $args['attributes'] ?? [];

    // Build CSS classes
    $cssClasses = 'govuk-button';
    if ($isStartButton) {
        $cssClasses .= ' govuk-button--start';
    }
    if ($classes) {
        $cssClasses .= ' ' . $classes;
    }

    // Build content
    $content = $html ?: htmlspecialchars($text);

    // Add start button arrow icon (GOV.UK pattern)
    if ($isStartButton) {
        $content .= '
  <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
    <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
  </svg>';
    }

    // Build additional attributes string
    $attrString = '';
    foreach ($attributes as $key => $attrValue) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($attrValue) . '"';
    }

    // Common attributes for both button and link
    $commonAttrs = ' class="' . htmlspecialchars($cssClasses) . '"';
    $commonAttrs .= ' data-module="govuk-button"';
    if ($id) {
        $commonAttrs .= ' id="' . htmlspecialchars($id) . '"';
    }
    if ($ariaLabel) {
        $commonAttrs .= ' aria-label="' . htmlspecialchars($ariaLabel) . '"';
    }
    if ($onclick) {
        $commonAttrs .= ' onclick="' . htmlspecialchars($onclick) . '"';
    }
    $commonAttrs .= $attrString;

    // Render as link or button
    if ($href && !$disabled) {
        // Link styled as button
        return '<a href="' . htmlspecialchars($href) . '" role="button" draggable="false"' . $commonAttrs . '>' .
               $content .
               '</a>';
    } else {
        // Regular button
        $buttonAttrs = ' type="' . htmlspecialchars($type) . '"';
        if ($name) {
            $buttonAttrs .= ' name="' . htmlspecialchars($name) . '"';
        }
        if ($value) {
            $buttonAttrs .= ' value="' . htmlspecialchars($value) . '"';
        }
        if ($disabled) {
            $buttonAttrs .= ' disabled aria-disabled="true"';
        }
        if ($preventDoubleClick !== null) {
            $buttonAttrs .= ' data-prevent-double-click="' . ($preventDoubleClick ? 'true' : 'false') . '"';
        }

        return '<button' . $buttonAttrs . $commonAttrs . '>' .
               $content .
               '</button>';
    }
}
