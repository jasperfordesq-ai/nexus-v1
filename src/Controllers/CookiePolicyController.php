<?php

declare(strict_types=1);

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\SEO;
use Nexus\Services\CookieInventoryService;

/**
 * Cookie Policy Controller
 *
 * Displays the cookie policy page with detailed information
 * about all cookies used by the platform.
 */
class CookiePolicyController
{
    /**
     * GET /legal/cookies
     * Display cookie policy page
     *
     * @return void
     */
    public function index(): void
    {
        $tenantId = TenantContext::getId();
        $basePath = TenantContext::getBasePath();

        // Get all cookies for this tenant
        $cookies = CookieInventoryService::getAllCookies($tenantId);
        $counts = CookieInventoryService::getCookieCounts($tenantId);

        // Get tenant info
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'This Community';

        // Set SEO
        SEO::setTitle('Cookie Policy');
        SEO::setDescription('Learn about the cookies we use and how to manage your preferences.');

        // Render view (will use layout() to pick correct theme)
        View::render('pages/cookie-policy', [
            'pageTitle' => 'Cookie Policy',
            'cookies' => $cookies,
            'counts' => $counts,
            'tenantName' => $tenantName,
            'basePath' => $basePath,
            'lastUpdated' => date('F j, Y')
        ]);
    }
}
