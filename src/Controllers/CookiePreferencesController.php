<?php

declare(strict_types=1);

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\SEO;
use Nexus\Services\CookieConsentService;
use Nexus\Services\CookieInventoryService;

/**
 * Cookie Preferences Controller
 *
 * Manages the cookie preferences page where users can
 * view and update their consent choices.
 */
class CookiePreferencesController
{
    /**
     * GET /cookie-preferences
     * Display cookie preferences management page
     *
     * @return void
     */
    public function index(): void
    {
        $tenantId = TenantContext::getId();
        $basePath = TenantContext::getBasePath();

        // Get current consent
        $currentConsent = CookieConsentService::getCurrentConsent();

        // Get cookie inventory
        $cookies = CookieInventoryService::getAllCookies($tenantId);
        $counts = CookieInventoryService::getCookieCounts($tenantId);

        // Get tenant settings
        $tenantSettings = CookieConsentService::getTenantSettings($tenantId);

        // Set SEO
        SEO::setTitle('Cookie Preferences');
        SEO::setDescription('Manage your cookie consent preferences.');

        // Render view
        View::render('pages/cookie-preferences', [
            'pageTitle' => 'Cookie Preferences',
            'currentConsent' => $currentConsent,
            'cookies' => $cookies,
            'counts' => $counts,
            'tenantSettings' => $tenantSettings,
            'basePath' => $basePath
        ]);
    }
}
