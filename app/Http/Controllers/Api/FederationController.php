<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\Database;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationAuditService;
use Nexus\Services\FederationActivityService;

/**
 * FederationController -- Federation cross-tenant features.
 *
 * V2 endpoints (status, opt-in/out, partners, activity) are fully migrated.
 * V1 federation API endpoints (timebanks, members, listings, etc.) delegate
 * to the legacy FederationApiController which uses FederationApiMiddleware
 * for API key authentication.
 * sendMessage and createTransaction are kept as delegation because they
 * involve email sending and Pusher realtime notifications.
 */
class FederationController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const LEVEL_NAMES = [
        1 => 'Discovery',
        2 => 'Social',
        3 => 'Economic',
        4 => 'Integrated',
    ];

    // =====================================================================
    // V2 STATUS & OPT-IN/OUT (migrated)
    // =====================================================================

    /** GET federation/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $tenantFederationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        $userSettings = FederationUserService::getUserSettings($userId);
        $userOptedIn = (bool) ($userSettings['federation_optin'] ?? false);

        $partnershipsCount = 0;
        try {
            $result = Database::query(
                "SELECT COUNT(*) as cnt FROM federation_partnerships
                 WHERE (tenant_id = ? OR partner_tenant_id = ?) AND status = 'active'",
                [$tenantId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);
            $partnershipsCount = (int) ($result['cnt'] ?? 0);
        } catch (\Exception $e) {
            error_log("FederationV2Api::status partnerships count error: " . $e->getMessage());
        }

        return $this->respondWithData([
            'enabled' => $tenantFederationEnabled && $userOptedIn,
            'tenant_federation_enabled' => $tenantFederationEnabled,
            'partnerships_count' => $partnershipsCount,
            'federation_optin' => $userOptedIn,
        ]);
    }

    /** POST federation/opt-in */
    public function optIn(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $tenantEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$tenantEnabled) {
            return $this->respondWithError('FEDERATION_NOT_AVAILABLE', 'Federation is not enabled for your community.', null, 403);
        }

        $current = FederationUserService::getUserSettings($userId);

        if ($current['federation_optin']) {
            return $this->respondWithData(['success' => true, 'message' => 'Already opted in to federation.']);
        }

        $settings = array_merge($current, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
            'appear_in_federated_search' => true,
            'show_skills_federated' => true,
            'messaging_enabled_federated' => true,
            'transactions_enabled_federated' => true,
        ]);

        $success = FederationUserService::updateSettings($userId, $settings);

        if ($success) {
            FederationAuditService::log('user_federation_optin', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => 'Federation enabled successfully.']);
        } else {
            return $this->respondWithError('OPT_IN_FAILED', 'Failed to enable federation. Please try again.', null, 500);
        }
    }

    /** POST federation/setup */
    public function setup(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $tenantEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$tenantEnabled) {
            return $this->respondWithError('FEDERATION_NOT_AVAILABLE', 'Federation is not enabled for your community.', null, 403);
        }

        $data = $this->getAllInput();

        $settings = [
            'federation_optin' => true,
            'profile_visible_federated' => $data['profile_visible_federated'] ?? true,
            'appear_in_federated_search' => $data['appear_in_federated_search'] ?? true,
            'show_skills_federated' => $data['show_skills_federated'] ?? true,
            'show_location_federated' => $data['show_location_federated'] ?? false,
            'show_reviews_federated' => $data['show_reviews_federated'] ?? true,
            'messaging_enabled_federated' => $data['messaging_enabled_federated'] ?? true,
            'transactions_enabled_federated' => $data['transactions_enabled_federated'] ?? true,
            'email_notifications' => $data['email_notifications'] ?? true,
            'service_reach' => $data['service_reach'] ?? 'local_only',
            'travel_radius_km' => array_key_exists('travel_radius_km', $data) ? (int) $data['travel_radius_km'] : 25,
        ];

        $success = FederationUserService::updateSettings($userId, $settings);

        if ($success) {
            FederationAuditService::log('user_federation_optin', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => 'Federation enabled successfully.']);
        } else {
            return $this->respondWithError('SETUP_FAILED', 'Failed to enable federation. Please try again.', null, 500);
        }
    }

    /** POST federation/opt-out */
    public function optOut(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $success = FederationUserService::optOut($userId);

        if ($success) {
            FederationAuditService::log('user_federation_optout', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => 'Federation disabled successfully.']);
        } else {
            return $this->respondWithError('OPT_OUT_FAILED', 'Failed to disable federation. Please try again.', null, 500);
        }
    }

    /** GET federation/partners */
    public function partners(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        try {
            $partnerships = Database::query("
                SELECT
                    fp.id as partnership_id,
                    fp.federation_level,
                    fp.created_at as partnership_since,
                    fp.profiles_enabled, fp.messaging_enabled, fp.transactions_enabled,
                    fp.listings_enabled, fp.events_enabled, fp.groups_enabled,
                    CASE WHEN fp.tenant_id = ? THEN fp.partner_tenant_id ELSE fp.tenant_id END as partner_tenant_id,
                    CASE WHEN fp.tenant_id = ? THEN t2.name ELSE t1.name END as partner_name,
                    CASE WHEN fp.tenant_id = ? THEN t2.tagline ELSE t1.tagline END as partner_tagline,
                    CASE WHEN fp.tenant_id = ? THEN t2.location_name ELSE t1.location_name END as partner_location,
                    CASE WHEN fp.tenant_id = ? THEN t2.country_code ELSE t1.country_code END as partner_country,
                    CASE WHEN fp.tenant_id = ? THEN dp2.logo_url ELSE dp1.logo_url END as partner_logo,
                    CASE WHEN fp.tenant_id = ? THEN dp2.member_count ELSE dp1.member_count END as partner_member_count
                FROM federation_partnerships fp
                LEFT JOIN tenants t1 ON fp.tenant_id = t1.id
                LEFT JOIN tenants t2 ON fp.partner_tenant_id = t2.id
                LEFT JOIN federation_directory_profiles dp1 ON dp1.tenant_id = fp.tenant_id
                LEFT JOIN federation_directory_profiles dp2 ON dp2.tenant_id = fp.partner_tenant_id
                WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?)
                AND fp.status = 'active'
                ORDER BY partner_name ASC
            ", [
                $tenantId, $tenantId, $tenantId, $tenantId,
                $tenantId, $tenantId, $tenantId,
                $tenantId, $tenantId,
            ])->fetchAll(\PDO::FETCH_ASSOC);

            $formatted = array_map(function ($p) {
                $level = (int) ($p['federation_level'] ?? 1);
                $permissions = [];
                if ($p['profiles_enabled']) $permissions[] = 'profiles';
                if ($p['messaging_enabled']) $permissions[] = 'messaging';
                if ($p['transactions_enabled']) $permissions[] = 'transactions';
                if ($p['listings_enabled']) $permissions[] = 'listings';
                if ($p['events_enabled']) $permissions[] = 'events';
                if ($p['groups_enabled']) $permissions[] = 'groups';

                return [
                    'id' => (int) $p['partner_tenant_id'],
                    'name' => $p['partner_name'] ?? 'Unknown',
                    'logo' => $p['partner_logo'] ?: null,
                    'tagline' => $p['partner_tagline'] ?? '',
                    'location' => $p['partner_location'] ?? '',
                    'country' => $p['partner_country'] ?? '',
                    'member_count' => (int) ($p['partner_member_count'] ?? 0),
                    'federation_level' => $level,
                    'federation_level_name' => self::LEVEL_NAMES[$level] ?? 'Discovery',
                    'permissions' => $permissions,
                    'partnership_since' => $p['partnership_since'] ?? null,
                ];
            }, $partnerships);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            error_log("FederationV2Api::partners error: " . $e->getMessage());
            return $this->respondWithData([]);
        }
    }

    /** GET federation/activity */
    public function activity(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $rawActivity = FederationActivityService::getActivityFeed($userId, 20);

            $formatted = [];
            $id = 1;
            foreach ($rawActivity as $item) {
                $type = $this->mapActivityType($item['type'] ?? 'message');
                $formatted[] = [
                    'id' => $id++,
                    'type' => $type,
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? ($item['preview'] ?? ''),
                    'created_at' => $item['timestamp'] ?? date('Y-m-d H:i:s'),
                    'actor' => [
                        'name' => $item['subtitle'] ?? 'Federation Network',
                        'avatar' => null,
                        'tenant_name' => $item['subtitle'] ?? null,
                    ],
                ];
            }

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            error_log("FederationV2Api::activity error: " . $e->getMessage());
            return $this->respondWithData([]);
        }
    }

    // =====================================================================
    // V1 FEDERATION API — Delegation (uses FederationApiMiddleware auth)
    // =====================================================================

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'index');
    }

    public function timebanks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'timebanks');
    }

    public function members(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'members');
    }

    public function member($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'member', [$id]);
    }

    public function listings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'listings');
    }

    public function listing($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'listing', [$id]);
    }

    /** Delegation — involves email sending */
    public function sendMessage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'sendMessage');
    }

    /** Delegation — involves cross-tenant transaction processing */
    public function createTransaction(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'createTransaction');
    }

    public function oauthToken(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'oauthToken');
    }

    public function testWebhook(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationApiController::class, 'testWebhook');
    }

    // =====================================================================
    // PRIVATE HELPERS
    // =====================================================================

    private function mapActivityType(string $rawType): string
    {
        $map = [
            'message' => 'message_received',
            'transaction' => 'transaction_received',
            'new_partner' => 'partnership_approved',
        ];
        return $map[$rawType] ?? 'member_joined';
    }
}
