<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;

/**
 * Federation Help Controller
 *
 * Help and FAQ page for federation features
 */
class FederationHelpController
{
    /**
     * Display federation help/FAQ page
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $basePath = TenantContext::getBasePath();
        $userId = $_SESSION['user_id'];

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        // Check if user is opted into federation
        $userOptedIn = false;
        if ($federationEnabled) {
            $result = \Nexus\Core\Database::query(
                "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch();
            $userOptedIn = !empty($result['federation_optin']);
        }

        \Nexus\Core\SEO::setTitle('Federation Help & FAQ');
        \Nexus\Core\SEO::setDescription('Learn about partner timebanks, federation features, and how to connect with members from other communities.');

        View::render('federation/help', [
            'pageTitle' => 'Federation Help',
            'federationEnabled' => $federationEnabled,
            'userOptedIn' => $userOptedIn,
            'basePath' => $basePath
        ]);
    }
}
