<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;

/**
 * Federation Settings Controller
 *
 * User-facing settings page to manage federation preferences
 */
class FederationSettingsController
{
    /**
     * Display user's federation settings page
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

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get user's federation settings
        $userSettings = Database::query(
            "SELECT * FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // If no settings exist, redirect to onboarding
        if (!$userSettings) {
            header('Location: ' . $basePath . '/federation/onboarding');
            exit;
        }

        // Get user profile
        $userProfile = Database::query(
            "SELECT id, name, first_name, last_name, avatar_url, bio, skills, location
             FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Get partner count
        $partnerships = Database::query(
            "SELECT COUNT(*) as count FROM federation_partnerships
             WHERE (tenant_id = ? OR partner_tenant_id = ?) AND status = 'active'",
            [$tenantId, $tenantId]
        )->fetch();
        $partnerCount = $partnerships['count'] ?? 0;

        // Get user's federation statistics
        $stats = $this->getUserStats($userId);

        \Nexus\Core\SEO::setTitle('Federation Settings');
        \Nexus\Core\SEO::setDescription('Manage your federation privacy settings and preferences.');

        View::render('federation/settings', [
            'pageTitle' => 'Federation Settings',
            'userSettings' => $userSettings,
            'userProfile' => $userProfile,
            'partnerCount' => $partnerCount,
            'stats' => $stats,
            'basePath' => $basePath
        ]);
    }

    /**
     * Save settings via AJAX
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
        $appearInSearch = !empty($input['appear_in_search']);
        $profileVisible = !empty($input['profile_visible']);

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
                        $appearInSearch ? 1 : 0,
                        $profileVisible ? 1 : 0,
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
                        $appearInSearch ? 1 : 0,
                        $profileVisible ? 1 : 0
                    ]
                );
            }

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                'user_settings_updated',
                $tenantId,
                null,
                $userId,
                ['privacy_level' => $privacyLevel, 'opted_in' => $federationOptin]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Settings saved successfully!'
            ]);

        } catch (\Exception $e) {
            error_log("Federation settings save error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
        }
        exit;
    }

    /**
     * Disable federation (opt-out) via AJAX
     */
    public function disable()
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

        try {
            Database::query(
                "UPDATE federation_user_settings SET
                    federation_optin = 0,
                    appear_in_federated_search = 0,
                    profile_visible_federated = 0,
                    updated_at = NOW()
                 WHERE user_id = ?",
                [$userId]
            );

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                'user_opted_out',
                $tenantId,
                null,
                $userId,
                []
            );

            echo json_encode([
                'success' => true,
                'message' => 'Federation disabled. Your profile is no longer visible to partner timebanks.',
                'redirect' => TenantContext::getBasePath() . '/federation'
            ]);

        } catch (\Exception $e) {
            error_log("Federation disable error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to disable federation']);
        }
        exit;
    }

    /**
     * Re-enable federation via AJAX
     */
    public function enable()
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

        try {
            Database::query(
                "UPDATE federation_user_settings SET
                    federation_optin = 1,
                    appear_in_federated_search = 1,
                    profile_visible_federated = 1,
                    updated_at = NOW()
                 WHERE user_id = ?",
                [$userId]
            );

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                'user_opted_in',
                $tenantId,
                null,
                $userId,
                []
            );

            echo json_encode([
                'success' => true,
                'message' => 'Federation enabled! Your profile is now visible to partner timebanks.'
            ]);

        } catch (\Exception $e) {
            error_log("Federation enable error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to enable federation']);
        }
        exit;
    }

    /**
     * Get user's federation statistics
     */
    private function getUserStats(int $userId): array
    {
        $stats = [
            'messages_sent' => 0,
            'messages_received' => 0,
            'transactions_count' => 0,
            'hours_exchanged' => 0,
        ];

        try {
            // Messages sent
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages
                 WHERE sender_id = ? AND is_federated = 1",
                [$userId]
            )->fetch();
            $stats['messages_sent'] = $result['count'] ?? 0;

            // Messages received
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages
                 WHERE receiver_id = ? AND is_federated = 1",
                [$userId]
            )->fetch();
            $stats['messages_received'] = $result['count'] ?? 0;

            // Transactions
            $result = Database::query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE (sender_id = ? OR receiver_id = ?) AND is_federated = 1 AND status = 'completed'",
                [$userId, $userId]
            )->fetch();
            $stats['transactions_count'] = $result['count'] ?? 0;
            $stats['hours_exchanged'] = (float)($result['total'] ?? 0);

        } catch (\Exception $e) {
            error_log("Federation settings stats error: " . $e->getMessage());
        }

        return $stats;
    }
}
