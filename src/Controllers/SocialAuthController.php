<?php

namespace Nexus\Controllers;

use Nexus\Core\SimpleOAuth;
use Nexus\Services\SocialAuthService;
use Nexus\Core\TenantContext;

class SocialAuthController
{
    private function getConfig($provider)
    {
        $tenant = TenantContext::get();
        $config = !empty($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];

        if (empty($config['social_login']['enabled']) || !$config['social_login']['enabled']) {
            return null; // Feature Disabled
        }

        if (empty($config['social_login']['providers'][$provider])) {
            return null; // Provider not configured
        }

        $creds = $config['social_login']['providers'][$provider];
        $creds['redirect_uri'] = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . TenantContext::getBasePath() . "/login/oauth/callback/" . $provider;

        return $creds;
    }

    public function redirect($provider)
    {
        $config = $this->getConfig($provider);
        if (!$config) {
            die("Social Login disabled or misconfigured for this tenant.");
        }

        $client = new SimpleOAuth($provider, $config);
        header('Location: ' . $client->getAuthUrl());
        exit;
    }

    public function callback($provider)
    {
        $config = $this->getConfig($provider);
        if (!$config) {
            die("Social Login error.");
        }

        if (empty($_GET['code'])) {
            die("Invalid Login Request.");
        }

        // Verify State
        if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
            die("Security Error: Invalid State.");
        }

        $client = new SimpleOAuth($provider, $config);
        $token = $client->getAccessToken($_GET['code']);

        if (empty($token['access_token'])) {
            die("Failed to authenticate with provider.");
        }

        $userInfo = $client->getUserInfo($token['access_token']);

        if (!$userInfo || empty($userInfo['email'])) {
            die("Could not retrieve user profile.");
        }

        // Handle User Logic
        $service = new SocialAuthService();
        $user = $service->handleUser($provider, $userInfo);

        if ($user) {
            // FIXED: Preserve layout preference before session regeneration
            $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;

            // SECURITY: Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // FIXED: Restore layout preference after regeneration
            if ($preservedLayout) {
                $_SESSION['nexus_active_layout'] = $preservedLayout;
                $_SESSION['nexus_layout'] = $preservedLayout;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email']; // For biometric login
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;

            // Log Activity
            \Nexus\Models\ActivityLog::log($user['id'], 'login', 'User logged in via ' . ucfirst($provider));

            header('Location: ' . TenantContext::getBasePath() . '/dashboard');
            exit;
        } else {
            die("Login failed.");
        }
    }
}
