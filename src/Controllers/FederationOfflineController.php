<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;

/**
 * Federation Offline Controller
 *
 * Displays offline page for federation features when user is not connected
 */
class FederationOfflineController
{
    /**
     * Display the federation offline page
     */
    public function index()
    {
        $basePath = TenantContext::getBasePath();

        \Nexus\Core\SEO::setTitle('Offline - Federation');

        View::render('federation/offline', [
            'pageTitle' => 'You\'re Offline',
            'basePath' => $basePath
        ]);
    }
}
