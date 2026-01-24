    <!-- Skip Link for Accessibility (WCAG 2.4.1) - GOV.UK Pattern -->
    <a href="#main-content" class="govuk-skip-link" data-module="govuk-skip-link">Skip to main content</a>

<?php
// Tenant feature check for layout banner
$showLayoutBanner = true; // Default: show banner
try {
    $result = \Nexus\Core\Database::query(
        "SELECT setting_value FROM tenant_settings
         WHERE tenant_id = ? AND setting_key = 'feature.layout_banner'",
        [\Nexus\Core\TenantContext::getId()]
    )->fetch();
    if ($result && ($result['setting_value'] === '0' || $result['setting_value'] === 'false')) {
        $showLayoutBanner = false;
    }
} catch (\Exception $e) {
    // If query fails, keep default (show banner)
}
?>

<?php if ($showLayoutBanner): ?>
    <!-- Phase Banner - GOV.UK Pattern -->
    <div class="govuk-phase-banner govuk-width-container">
        <p class="govuk-phase-banner__content">
            <strong class="govuk-tag govuk-phase-banner__content__tag">Beta</strong>
            <span class="govuk-phase-banner__text">
                This is a new accessible layout — <a class="govuk-link" href="#" data-layout-switcher="modern">switch to classic view</a> or <a class="govuk-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help/feedback">give feedback</a>.
            </span>
        </p>
    </div>
<?php endif; ?>
