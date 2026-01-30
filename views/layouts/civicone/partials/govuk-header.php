<?php
/**
 * CivicOne Header - Dark Blue Bar (GOV.UK Pattern)
 *
 * This follows the GOV.UK Header STRUCTURE but with CivicOne branding.
 * We use the GOV.UK Frontend CSS patterns but NOT their logo or branding.
 *
 * The GOV.UK crown logo and "GOV.UK" text are protected Crown Copyright
 * and can only be used by official UK government services.
 *
 * CSS: /assets/css/civicone-govuk-header.css
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$homepageUrl = $basePath . '/';
$siteName = TenantContext::getSetting('site_name') ?? 'CivicOne';
?>
<div class="govuk-header" data-module="govuk-header">
    <div class="govuk-header__container govuk-width-container">
        <div class="govuk-header__logo">
            <a href="<?= htmlspecialchars($homepageUrl) ?>" class="govuk-header__homepage-link">
                <span class="govuk-header__logotype">
                    <?= htmlspecialchars($siteName) ?>
                </span>
            </a>
        </div>
    </div>
</div>
