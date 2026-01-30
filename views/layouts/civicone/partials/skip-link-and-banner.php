<?php
/**
 * Skip Link and Phase Banner - GOV.UK Pattern
 *
 * Skip Link: WCAG 2.4.1 Bypass Blocks - MUST be first focusable element
 * Phase Banner: Indicates service status (alpha/beta/live)
 *
 * SOURCE:
 * - Skip Link: https://design-system.service.gov.uk/components/skip-link/
 * - Phase Banner: https://design-system.service.gov.uk/components/phase-banner/
 */

// Load the skip-link component
require_once __DIR__ . '/../../../civicone/components/govuk/skip-link.php';
?>
    <!-- Skip Link for Accessibility (WCAG 2.4.1) - MUST be first focusable element -->
    <?= civicone_govuk_skip_link() ?>

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
