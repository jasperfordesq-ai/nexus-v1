<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;

/**
 * Federation Onboarding Controller
 *
 * Step-by-step wizard to help users enable and configure federation
 */
class FederationOnboardingController
{
    /**
     * Display the onboarding wizard
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled for this tenant
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get current user's federation settings
        $userSettings = Database::query(
            "SELECT * FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Get user profile info for preview
        $userProfile = Database::query(
            "SELECT id, name, first_name, last_name, avatar_url, bio, skills, location
             FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Count active partnerships
        $partnerships = Database::query(
            "SELECT COUNT(*) as count FROM federation_partnerships
             WHERE (tenant_id = ? OR partner_tenant_id = ?) AND status = 'active'",
            [$tenantId, $tenantId]
        )->fetch();
        $partnerCount = $partnerships['count'] ?? 0;

        // Get user's existing GDPR consents to sync checkbox state
        $consentStatus = [];
        try {
            $gdprService = new \Nexus\Services\Enterprise\GdprService();
            $userConsents = $gdprService->getUserConsents($userId);
            foreach ($userConsents as $consent) {
                $consentStatus[$consent['consent_type_slug']] = (bool) $consent['consent_given'];
            }
        } catch (\Throwable $e) {
            error_log("Federation onboarding consent fetch error: " . $e->getMessage());
        }

        \Nexus\Core\SEO::setTitle('Get Started with Federation');
        \Nexus\Core\SEO::setDescription('Set up your federation profile and connect with members from partner timebanks.');

        View::render('federation/onboarding', [
            'pageTitle' => 'Get Started with Federation',
            'userSettings' => $userSettings,
            'userProfile' => $userProfile,
            'partnerCount' => $partnerCount,
            'basePath' => $basePath,
            'consentStatus' => $consentStatus
        ]);
    }

    /**
     * Save onboarding settings via AJAX
     */
    public function save()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Parse JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        $federationOptin = !empty($input['federation_optin']);
        $privacyLevel = $input['privacy_level'] ?? 'discovery';
        $serviceReach = $input['service_reach'] ?? 'local_only';
        $showLocation = !empty($input['show_location']);
        $showSkills = !empty($input['show_skills']);
        $messagingEnabled = !empty($input['messaging_enabled']);
        $transactionsEnabled = !empty($input['transactions_enabled']);

        try {
            // Check if settings exist
            $existing = Database::query(
                "SELECT id FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch();

            if ($existing) {
                // Update existing settings
                Database::query(
                    "UPDATE federation_user_settings SET
                        federation_optin = ?,
                        privacy_level = ?,
                        service_reach = ?,
                        show_location_federated = ?,
                        show_skills_federated = ?,
                        messaging_enabled_federated = ?,
                        transactions_enabled_federated = ?,
                        appear_in_federated_search = ?,
                        profile_visible_federated = ?,
                        updated_at = NOW()
                     WHERE user_id = ?",
                    [
                        $federationOptin ? 1 : 0,
                        $privacyLevel,
                        $serviceReach,
                        $showLocation ? 1 : 0,
                        $showSkills ? 1 : 0,
                        $messagingEnabled ? 1 : 0,
                        $transactionsEnabled ? 1 : 0,
                        $federationOptin ? 1 : 0,
                        $federationOptin ? 1 : 0,
                        $userId
                    ]
                );
            } else {
                // Insert new settings
                Database::query(
                    "INSERT INTO federation_user_settings
                        (user_id, tenant_id, federation_optin, privacy_level, service_reach,
                         show_location_federated, show_skills_federated,
                         messaging_enabled_federated, transactions_enabled_federated,
                         appear_in_federated_search, profile_visible_federated, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $userId,
                        $tenantId,
                        $federationOptin ? 1 : 0,
                        $privacyLevel,
                        $serviceReach,
                        $showLocation ? 1 : 0,
                        $showSkills ? 1 : 0,
                        $messagingEnabled ? 1 : 0,
                        $transactionsEnabled ? 1 : 0,
                        $federationOptin ? 1 : 0,
                        $federationOptin ? 1 : 0
                    ]
                );
            }

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                'user_onboarding_complete',
                $tenantId,
                null,
                $userId,
                ['privacy_level' => $privacyLevel, 'opted_in' => $federationOptin]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Federation settings saved successfully!',
                'redirect' => TenantContext::getBasePath() . '/federation'
            ]);

        } catch (\Exception $e) {
            error_log("Federation onboarding save error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
        }
        exit;
    }
}
