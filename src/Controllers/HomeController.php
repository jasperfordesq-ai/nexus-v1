<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Models\Listing;
use Nexus\Models\User;
use Nexus\Models\Group;
use Nexus\Services\LayoutValidator;
use Nexus\Helpers\UrlHelper;

// V12: Removed FDS Mobile Detection (2026-01-17)
// The abandoned standalone mobile app has been removed.
// All devices now use responsive layouts with mobile-nav-v2.

class HomeController
{
    public function index()
    {
        // 1. DATA FETCHING (Valid Methods for Live Models)
        try {
            $tenantId = class_exists('Nexus\Core\TenantContext') ? TenantContext::getId() : 1;
        } catch (\Throwable $e) {
            $tenantId = 1; // Fallback to Master Tenant
        }

        try {
            // FEED: Load ALL items (Modern Feed Style).
            // Using Listing::all() to bypass strict type check bug in getRecent().
            $listings = class_exists('Nexus\Models\Listing') ? Listing::all() : [];
        } catch (\Throwable $e) {
            $listings = [];
        }

        try {
            // MEMBERS: Use getPaginated (Valid Method)
            $members = class_exists('Nexus\Models\User') ? User::getPaginated(5) : [];
        } catch (\Throwable $e) {
            $members = [];
        }

        try {
            // HUBS: Use getFeatured (Valid Method)
            $hubs = class_exists('Nexus\Models\Group') ? Group::getFeatured(3) : [];
        } catch (\Throwable $e) {
            $hubs = [];
        }

        $featuredGroups = $hubs;

        try {
            $tenantName = class_exists('Nexus\Core\TenantContext') ? (TenantContext::get()['name'] ?? 'Nexus') : 'Nexus';
        } catch (\Throwable $e) {
            $tenantName = 'Nexus';
        }

        $pageTitle = $tenantName . ' - Home';

        // Add Organization + WebSite schemas for homepage
        \Nexus\Core\SEO::addSiteSchemas();

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) session_start();

        // 2. MOBILE DETECTION - DISABLED (2026-01-17)
        // The abandoned standalone mobile app has been removed.
        // Mobile devices now use the responsive desktop views with mobile-nav-v2.
        // if ($this->isMobileDevice()) { ... }

        // 3. LAYOUT RESOLUTION (Switcher Logic Restored)
        // REMOVED: Duplicate ?layout= processing - now handled exclusively in index.php
        // This prevents race conditions from multiple processing points

        // Use LayoutHelper for consistent layout detection
        $layout = \Nexus\Services\LayoutHelper::get();

        // 4. RENDER DESKTOP LAYOUTS - Let View class handle layout switching
        View::render('home', compact('listings', 'members', 'hubs', 'featuredGroups', 'pageTitle'));
    }

    /**
     * Dashboard Action
     * Redirects to modern home feed - dashboard functionality disabled.
     */
    public function dashboard()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $basePath = \Nexus\Core\TenantContext::getBasePath();

        // Redirect all dashboard requests to modern home feed
        header('Location: ' . $basePath . '/home');
        exit;
    }

    /**
     * Switch Layout Action
     * Handle explicit layout switching via POST or GET
     * Now uses LayoutValidator service for proper access control
     */
    public function switchLayout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $layout = $_REQUEST['layout'] ?? null;

        if (!$layout) {
            $_SESSION['layout_switch_error'] = 'No layout specified';
            $this->redirectBack();
        }

        // Use LayoutValidator service for proper validation and access control
        $result = LayoutValidator::handleSwitchRequest($layout);

        if ($result['success']) {
            // Success - layout was switched
            // LayoutValidator already handled session, cookie, and user preference
            $this->redirectBack();
        } else {
            // Failed - error message already set in session by LayoutValidator
            // Fallback layout already set if applicable
            $this->redirectBack();
        }
    }

    /**
     * Redirect back to referer or dashboard
     */
    private function redirectBack()
    {
        $redirect = UrlHelper::safeReferer('/dashboard');

        // Prevent infinite loop if referer is the switch endpoint itself
        if (strpos($redirect, 'switch_layout') !== false) {
            $redirect = '/dashboard';
        }

        header("Location: $redirect");
        exit;
    }
}
