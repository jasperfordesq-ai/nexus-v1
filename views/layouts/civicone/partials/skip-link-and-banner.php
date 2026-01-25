    <!-- Skip Link for Accessibility (WCAG 2.4.1) - GOV.UK Pattern -->
    <a href="#main-content" class="govuk-skip-link" data-module="govuk-skip-link">Skip to main content</a>

    <!-- Phase Banner - GOV.UK Pattern (with integrated layout switcher) -->
    <!-- Border extends full width, content is constrained by width-container inside -->
    <div class="govuk-phase-banner">
        <div class="govuk-width-container">
            <p class="govuk-phase-banner__content">
                <strong class="govuk-tag govuk-phase-banner__content__tag">Beta</strong>
                <span class="govuk-phase-banner__text">
                    This is a new service. <a class="govuk-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/contact">Give feedback</a> to help us improve it. <a class="govuk-link" href="#" data-layout-switcher="modern">Switch to classic view</a>
                </span>
            </p>
        </div>
    </div>
