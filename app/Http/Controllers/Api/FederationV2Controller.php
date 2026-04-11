<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FederationActivityService;
use App\Services\FederationEmailService;
use App\Services\FederationRealtimeService;
use App\Services\FederationUserService;
use App\Services\TranscriptionService;
use App\Services\TranslationConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Services\FederatedConnectionService;
use App\Services\FederationAuditService;
use App\Services\FederationFeatureService;
use App\Services\FederationPartnershipService;
use App\Services\FederationSearchService;

/**
 * FederationV2Controller -- Federation v2: cross-tenant discovery, messaging, connections.
 *
 * All methods migrated from delegation to direct service/DB calls.
 */
class FederationV2Controller extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FederatedConnectionService $federatedConnectionService,
        private readonly FederationActivityService $federationActivityService,
        private readonly FederationAuditService $federationAuditService,
        private readonly FederationEmailService $federationEmailService,
        private readonly FederationFeatureService $federationFeatureService,
        private readonly FederationPartnershipService $federationPartnershipService,
        private readonly FederationRealtimeService $federationRealtimeService,
        private readonly FederationUserService $federationUserService,
    ) {}

    private const LEVEL_NAMES = [
        1 => 'Discovery',
        2 => 'Social',
        3 => 'Economic',
        4 => 'Integrated',
    ];

    /**
     * Parse the partner_id query parameter.
     *
     * Returns ['type' => 'external'|'internal', 'id' => int] or null if no filter.
     * External partners use composite IDs like "ext-7"; internal use plain integers.
     */
    private function parsePartnerFilter(): ?array
    {
        $raw = $this->query('partner_id', '');
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (str_starts_with($raw, 'ext-')) {
            $id = (int) substr($raw, 4);
            return $id > 0 ? ['type' => 'external', 'id' => $id] : null;
        }
        $id = (int) $raw;
        return $id > 0 ? ['type' => 'internal', 'id' => $id] : null;
    }

    /**
     * Resolve a relative avatar/image URL from an external partner against their base_url.
     * Returns the URL unchanged if it's already absolute, or null if empty.
     */
    private static function resolveExternalUrl(?string $url, string $partnerBaseUrl): ?string
    {
        if (!$url || $url === '') return null;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
        return $partnerBaseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Get the base_url for an external partner (cached per request).
     */
    private function getPartnerBaseUrl(int $externalPartnerId): string
    {
        static $cache = [];
        if (!isset($cache[$externalPartnerId])) {
            $partner = \App\Services\FederationExternalPartnerService::getById($externalPartnerId, $this->getTenantId());
            $cache[$externalPartnerId] = rtrim($partner['base_url'] ?? '', '/');
        }
        return $cache[$externalPartnerId];
    }

    // =====================================================================
    // STATUS & OPT-IN/OUT
    // =====================================================================

    /** GET /api/v2/federation/status */
    public function status(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $tenantFederationEnabled = $this->federationFeatureService->isGloballyEnabled()
            && $this->federationFeatureService->isTenantFederationEnabled($tenantId);

        $userSettings = $this->federationUserService->getUserSettings($userId);
        $userOptedIn = (bool) ($userSettings['federation_optin'] ?? false);

        $partnershipsCount = 0;
        try {
            $result = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_partnerships
                 WHERE (tenant_id = ? OR partner_tenant_id = ?) AND status = 'active'",
                [$tenantId, $tenantId]
            );
            $partnershipsCount = (int) ($result->cnt ?? 0);
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

    /** POST /api/v2/federation/opt-in */
    public function optIn(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $tenantEnabled = $this->federationFeatureService->isGloballyEnabled()
            && $this->federationFeatureService->isTenantFederationEnabled($tenantId);

        if (!$tenantEnabled) {
            return $this->respondWithError('FEDERATION_NOT_AVAILABLE', __('api.fed_not_available'), null, 403);
        }

        $current = $this->federationUserService->getUserSettings($userId);

        if ($current['federation_optin']) {
            return $this->respondWithData(['success' => true, 'message' => __('api_controllers_1.federation.already_opted_in')]);
        }

        $settings = array_merge($current, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
            'appear_in_federated_search' => true,
            'show_skills_federated' => true,
            'show_location_federated' => true,
            'show_reviews_federated' => true,
            'messaging_enabled_federated' => true,
            'transactions_enabled_federated' => true,
        ]);

        $success = $this->federationUserService->updateSettings($userId, $settings);

        if ($success) {
            $this->federationAuditService->log('user_federation_optin', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => __('api_controllers_1.federation.enabled_successfully')]);
        }

        return $this->respondWithError('OPT_IN_FAILED', __('api.fed_opt_in_failed'), null, 500);
    }

    /** POST /api/v2/federation/setup */
    public function setup(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $tenantEnabled = $this->federationFeatureService->isGloballyEnabled()
            && $this->federationFeatureService->isTenantFederationEnabled($tenantId);

        if (!$tenantEnabled) {
            return $this->respondWithError('FEDERATION_NOT_AVAILABLE', __('api.fed_not_available'), null, 403);
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

        $success = $this->federationUserService->updateSettings($userId, $settings);

        if ($success) {
            $this->federationAuditService->log('user_federation_optin', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => __('api_controllers_1.federation.enabled_successfully')]);
        }

        return $this->respondWithError('SETUP_FAILED', __('api.fed_setup_failed'), null, 500);
    }

    /** POST /api/v2/federation/opt-out */
    public function optOut(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $success = $this->federationUserService->optOut($userId);

        if ($success) {
            $this->federationAuditService->log('user_federation_optout', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => __('api_controllers_1.federation.disabled_successfully')]);
        }

        return $this->respondWithError('OPT_OUT_FAILED', __('api.fed_opt_out_failed'), null, 500);
    }

    // =====================================================================
    // PARTNERS
    // =====================================================================

    /** GET /api/v2/federation/partners */
    public function partners(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();

        try {
            $partnershipResults = DB::select("
                SELECT
                    fp.id as partnership_id, fp.federation_level,
                    fp.created_at as partnership_since,
                    fp.profiles_enabled, fp.messaging_enabled, fp.transactions_enabled,
                    fp.listings_enabled, fp.events_enabled, fp.groups_enabled,
                    CASE WHEN fp.tenant_id = ? THEN fp.partner_tenant_id ELSE fp.tenant_id END as partner_tenant_id,
                    CASE WHEN fp.tenant_id = ? THEN t2.name ELSE t1.name END as partner_name,
                    CASE WHEN fp.tenant_id = ? THEN t2.tagline ELSE t1.tagline END as partner_tagline,
                    CASE WHEN fp.tenant_id = ? THEN t2.location_name ELSE t1.location_name END as partner_location,
                    CASE WHEN fp.tenant_id = ? THEN t2.country_code ELSE t1.country_code END as partner_country,
                    CASE WHEN fp.tenant_id = ? THEN dp2.logo_url ELSE dp1.logo_url END as partner_logo,
                    (SELECT COUNT(*) FROM users u
                     WHERE u.tenant_id = CASE WHEN fp.tenant_id = ? THEN fp.partner_tenant_id ELSE fp.tenant_id END
                       AND u.status = 'active') as partner_member_count
                FROM federation_partnerships fp
                LEFT JOIN tenants t1 ON fp.tenant_id = t1.id
                LEFT JOIN tenants t2 ON fp.partner_tenant_id = t2.id
                LEFT JOIN federation_directory_profiles dp1 ON dp1.tenant_id = fp.tenant_id
                LEFT JOIN federation_directory_profiles dp2 ON dp2.tenant_id = fp.partner_tenant_id
                WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?) AND fp.status = 'active'
                ORDER BY partner_name ASC
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId]);
            $partnerships = array_map(fn($r) => (array)$r, $partnershipResults);

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

            // Also include active external partners
            $externalPartners = $this->getExternalPartnersForDisplay($tenantId);
            $formatted = array_merge($formatted, $externalPartners);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            error_log("FederationV2Api::partners error: " . $e->getMessage());
            return $this->respondWithData([]);
        }
    }

    /**
     * Fetch active external partners formatted for the Partner Communities page.
     */
    private function getExternalPartnersForDisplay(int $tenantId): array
    {
        try {
            $externalPartners = \App\Services\FederationExternalPartnerService::getActivePartners($tenantId);

            return array_map(function ($ep) {
                $permissions = [];
                if ($ep['allow_member_search'] ?? false) $permissions[] = 'profiles';
                if ($ep['allow_messaging'] ?? false) $permissions[] = 'messaging';
                if ($ep['allow_transactions'] ?? false) $permissions[] = 'transactions';
                if ($ep['allow_listing_search'] ?? false) $permissions[] = 'listings';
                if ($ep['allow_events'] ?? false) $permissions[] = 'events';
                if ($ep['allow_groups'] ?? false) $permissions[] = 'groups';

                return [
                    'id' => 'ext-' . $ep['id'],
                    'name' => $ep['name'],
                    'logo' => null,
                    'tagline' => $ep['description'] ?? '',
                    'location' => '',
                    'country' => '',
                    'member_count' => (int) ($ep['partner_member_count'] ?? 0),
                    'federation_level' => 1,
                    'federation_level_name' => 'External',
                    'permissions' => $permissions,
                    'partnership_since' => $ep['created_at'] ?? null,
                    'is_external' => true,
                    'base_url' => $ep['base_url'] ?? null,
                    'status' => $ep['status'] ?? 'active',
                ];
            }, $externalPartners);
        } catch (\Throwable $e) {
            error_log("FederationV2Api::getExternalPartnersForDisplay error: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================================
    // ACTIVITY
    // =====================================================================

    /** GET /api/v2/federation/activity */
    public function activity(): JsonResponse
    {
        $userId = $this->getUserId();

        try {
            $rawActivity = $this->federationActivityService->getActivityFeed($userId, 20);

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
    // FEDERATED EVENTS
    // =====================================================================

    /** GET /api/v2/federation/events */
    public function events(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();

        $q = $this->query('q', '');
        $partnerFilter = $this->parsePartnerFilter();
        $upcoming = $this->queryBool('upcoming', false);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

        // External partners don't have a federation events API — return empty
        if ($partnerFilter && $partnerFilter['type'] === 'external') {
            return $this->respondWithCollection([], null, $perPage, false);
        }

        $partnerId = $partnerFilter ? $partnerFilter['id'] : 0;

        try {
            $sql = "
                SELECT e.id, e.title, e.description, e.start_time as start_date, e.end_time as end_date,
                    e.location, e.allow_remote_attendance as is_online, e.cover_image, e.max_attendees,
                    e.user_id, e.tenant_id, u.first_name, u.last_name, u.avatar_url, t.name as tenant_name,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id = e.id AND er.status = 'going') as attendees_count
                FROM events e
                JOIN users u ON u.id = e.user_id
                JOIN tenants t ON t.id = e.tenant_id
                JOIN federation_partnerships fp ON (
                    (fp.tenant_id = :tid1 AND fp.partner_tenant_id = e.tenant_id)
                    OR (fp.partner_tenant_id = :tid2 AND fp.tenant_id = e.tenant_id)
                )
                WHERE fp.status = 'active' AND fp.events_enabled = 1
                AND e.tenant_id != :tid3 AND e.status = 'published'
            ";
            $params = [':tid1' => $tenantId, ':tid2' => $tenantId, ':tid3' => $tenantId];

            if ($upcoming) {
                $sql .= " AND e.start_time >= NOW()";
            }
            if (!empty($q)) {
                $sql .= " AND (e.title LIKE :q1 OR e.description LIKE :q2)";
                $params[':q1'] = "%{$q}%";
                $params[':q2'] = "%{$q}%";
            }
            if ($partnerId) {
                $sql .= " AND e.tenant_id = :partner_id";
                $params[':partner_id'] = $partnerId;
            }
            if ($cursorId) {
                $sql .= " AND e.id < :cursor_id";
                $params[':cursor_id'] = (int) $cursorId;
            }

            $sql .= " ORDER BY e.id DESC LIMIT :limit";
            $params[':limit'] = $perPage + 1;

            $stmt = DB::getPdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $perPage);
            }

            $formatted = array_map(function ($e) {
                return [
                    'id' => (int) $e['id'],
                    'title' => $e['title'],
                    'description' => $e['description'] ?? '',
                    'start_date' => $e['start_date'],
                    'end_date' => $e['end_date'],
                    'location' => $e['location'],
                    'is_online' => (bool) ($e['is_online'] ?? false),
                    'cover_image' => $e['cover_image'] ?: null,
                    'attendees_count' => (int) ($e['attendees_count'] ?? 0),
                    'max_attendees' => $e['max_attendees'] ? (int) $e['max_attendees'] : null,
                    'organizer' => [
                        'id' => (int) $e['user_id'],
                        'name' => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')),
                        'avatar' => $e['avatar_url'] ?: null,
                    ],
                    'timebank' => [
                        'id' => (int) $e['tenant_id'],
                        'name' => $e['tenant_name'],
                    ],
                    'created_at' => $e['start_date'],
                ];
            }, $rows);

            $nextCursor = null;
            if ($hasMore && !empty($rows)) {
                $lastRow = end($rows);
                $nextCursor = $this->encodeCursor($lastRow['id']);
            }

            return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            error_log("FederationV2Api::events error: " . $e->getMessage());
            return $this->respondWithCollection([], null, $perPage, false);
        }
    }

    // =====================================================================
    // FEDERATED LISTINGS
    // =====================================================================

    /** GET /api/v2/federation/listings */
    public function listings(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();

        $q = $this->query('q', '');
        $type = $this->query('type', '');
        $partnerFilter = $this->parsePartnerFilter();
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

        // If filtering by a specific external partner, return only that partner's data
        if ($partnerFilter && $partnerFilter['type'] === 'external') {
            $external = $this->fetchExternalListingsFromPartner($partnerFilter['id'], $q, $type);
            return $this->respondWithCollection($external, null, $perPage, false);
        }

        $partnerId = $partnerFilter ? $partnerFilter['id'] : 0;

        try {
            $sql = "
                SELECT l.id, l.title, l.description, l.type, l.category as category_name,
                    l.image_url, l.price as estimated_hours, l.location,
                    l.user_id, l.tenant_id, l.created_at,
                    u.first_name, u.last_name, u.avatar_url, t.name as tenant_name
                FROM listings l
                JOIN users u ON u.id = l.user_id
                JOIN tenants t ON t.id = l.tenant_id
                JOIN federation_partnerships fp ON (
                    (fp.tenant_id = :tid1 AND fp.partner_tenant_id = l.tenant_id)
                    OR (fp.partner_tenant_id = :tid2 AND fp.tenant_id = l.tenant_id)
                )
                JOIN federation_user_settings fus ON fus.user_id = l.user_id
                WHERE fp.status = 'active' AND fp.listings_enabled = 1
                AND l.status = 'active' AND l.tenant_id != :tid3
                AND fus.federation_optin = 1
            ";
            $params = [':tid1' => $tenantId, ':tid2' => $tenantId, ':tid3' => $tenantId];

            if (!empty($q)) {
                $sql .= " AND (l.title LIKE :q1 OR l.description LIKE :q2)";
                $params[':q1'] = "%{$q}%";
                $params[':q2'] = "%{$q}%";
            }
            if (!empty($type) && in_array($type, ['offer', 'request'])) {
                $sql .= " AND l.type = :type";
                $params[':type'] = $type;
            }
            if ($partnerId) {
                $sql .= " AND l.tenant_id = :partner_id";
                $params[':partner_id'] = $partnerId;
            }
            if ($cursorId) {
                $sql .= " AND l.id < :cursor_id";
                $params[':cursor_id'] = (int) $cursorId;
            }

            $sql .= " ORDER BY l.id DESC LIMIT :limit";
            $params[':limit'] = $perPage + 1;

            $stmt = DB::getPdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $perPage);
            }

            $formatted = array_map(function ($l) {
                return [
                    'id' => (int) $l['id'],
                    'title' => $l['title'],
                    'description' => $l['description'] ?? '',
                    'type' => $l['type'],
                    'category_name' => $l['category_name'] ?? null,
                    'image_url' => $l['image_url'] ?: null,
                    'estimated_hours' => $l['estimated_hours'] ? (float) $l['estimated_hours'] : null,
                    'location' => $l['location'] ?? null,
                    'author' => [
                        'id' => (int) $l['user_id'],
                        'name' => trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                        'avatar' => $l['avatar_url'] ?: null,
                    ],
                    'timebank' => [
                        'id' => (int) $l['tenant_id'],
                        'name' => $l['tenant_name'],
                    ],
                    'created_at' => $l['created_at'],
                ];
            }, $rows);

            $nextCursor = null;
            if ($hasMore && !empty($rows)) {
                $lastRow = end($rows);
                $nextCursor = $this->encodeCursor($lastRow['id']);
            }

            // Merge external partner listings (only on first page, and only when NOT filtering by internal partner)
            if (!$cursorId && !$partnerId) {
                $external = $this->fetchExternalListings($tenantId, $q, $type);
                if (!empty($external)) {
                    $formatted = array_merge($formatted, $external);
                }
            }

            return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            error_log("FederationV2Api::listings error: " . $e->getMessage());
            return $this->respondWithCollection([], null, $perPage, false);
        }
    }

    /**
     * Fetch listings from a SINGLE external partner by ID.
     */
    private function fetchExternalListingsFromPartner(int $externalPartnerId, string $q, string $type): array
    {
        try {
            $filters = [];
            if (!empty($q)) $filters['q'] = $q;
            if (!empty($type)) $filters['type'] = $type;

            $result = \App\Services\FederationExternalApiClient::fetchListings($externalPartnerId, $filters);

            if (!($result['success'] ?? false) || empty($result['data'])) {
                return [];
            }

            $partner = \App\Services\FederationExternalPartnerService::getById(
                $externalPartnerId,
                $this->getTenantId()
            );
            $partnerName = $partner['name'] ?? 'External Partner';
            $partnerBaseUrl = rtrim($partner['base_url'] ?? '', '/');

            return array_map(function ($l) use ($externalPartnerId, $partnerName, $partnerBaseUrl) {
                // v1 API returns 'owner', not 'author'
                $owner = $l['owner'] ?? $l['author'] ?? [];
                $avatar = self::resolveExternalUrl($owner['avatar'] ?? null, $partnerBaseUrl);

                return [
                    'id' => 'ext-' . $externalPartnerId . '-' . ($l['id'] ?? 0),
                    'title' => $l['title'] ?? '',
                    'description' => $l['description'] ?? '',
                    'type' => $l['type'] ?? 'offer',
                    'category_name' => $l['category_name'] ?? $l['category'] ?? null,
                    'image_url' => $l['image_url'] ?? null,
                    'estimated_hours' => isset($l['rate']) ? (float) $l['rate'] : (isset($l['estimated_hours']) ? (float) $l['estimated_hours'] : null),
                    'location' => $l['location'] ?? null,
                    'author' => [
                        'id' => (int) ($owner['id'] ?? 0),
                        'name' => $owner['name'] ?? trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                        'avatar' => $avatar,
                    ],
                    'timebank' => [
                        'id' => 'ext-' . $externalPartnerId,
                        'name' => $l['timebank']['name'] ?? $partnerName,
                    ],
                    'created_at' => $l['created_at'] ?? null,
                    'is_external' => true,
                    'external_partner_id' => $externalPartnerId,
                    'partner_name' => $partnerName,
                ];
            }, $result['data']);
        } catch (\Throwable $e) {
            error_log("FederationV2Api::fetchExternalListingsFromPartner error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch listings from ALL external federation partners and normalize to the same shape.
     */
    private function fetchExternalListings(int $tenantId, string $q, string $type): array
    {
        try {
            $searchService = app(FederationSearchService::class);
            $filters = [];
            if (!empty($q)) $filters['q'] = $q;
            if (!empty($type)) $filters['type'] = $type;

            $result = $searchService->searchExternalListings($tenantId, $filters);

            return array_map(function ($l) {
                // v1 API returns 'owner', not 'author'
                $owner = $l['owner'] ?? $l['author'] ?? [];
                $partnerId = $l['partner_id'] ?? 0;
                $baseUrl = $partnerId ? $this->getPartnerBaseUrl((int) $partnerId) : '';
                return [
                    'id' => 'ext-' . $partnerId . '-' . ($l['id'] ?? 0),
                    'title' => $l['title'] ?? '',
                    'description' => $l['description'] ?? '',
                    'type' => $l['type'] ?? 'offer',
                    'category_name' => $l['category_name'] ?? $l['category'] ?? null,
                    'image_url' => $l['image_url'] ?? null,
                    'estimated_hours' => isset($l['rate']) ? (float) $l['rate'] : (isset($l['estimated_hours']) ? (float) $l['estimated_hours'] : null),
                    'location' => $l['location'] ?? null,
                    'author' => [
                        'id' => (int) ($owner['id'] ?? 0),
                        'name' => $owner['name'] ?? trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                        'avatar' => self::resolveExternalUrl($owner['avatar'] ?? null, $baseUrl),
                    ],
                    'timebank' => [
                        'id' => (int) ($l['timebank']['id'] ?? $l['tenant_id'] ?? 0),
                        'name' => $l['timebank']['name'] ?? $l['partner_name'] ?? 'External Partner',
                    ],
                    'created_at' => $l['created_at'] ?? null,
                    'is_external' => true,
                    'partner_name' => $l['partner_name'] ?? null,
                ];
            }, $result['listings'] ?? []);
        } catch (\Throwable $e) {
            error_log("FederationV2Api::fetchExternalListings error: " . $e->getMessage());
            return [];
        }
    }

    // =====================================================================
    // FEDERATED MEMBERS
    // =====================================================================

    /** GET /api/v2/federation/members */
    public function members(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();

        $q = $this->query('q', '');
        $partnerFilter = $this->parsePartnerFilter();
        $serviceReach = $this->query('service_reach', '');
        $skills = $this->query('skills', '');
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $limit = $this->queryInt('limit');
        if ($limit && $limit < $perPage) {
            $perPage = $limit;
        }
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

        // If filtering by a specific external partner, return only that partner's data
        if ($partnerFilter && $partnerFilter['type'] === 'external') {
            $external = $this->fetchExternalMembersFromPartner($partnerFilter['id'], $q, $skills);
            return $this->respondWithCollection($external, null, $perPage, false, ['total_items' => count($external)]);
        }

        $partnerId = $partnerFilter ? $partnerFilter['id'] : 0;

        try {
            $fromWhere = "
                FROM users u
                JOIN federation_user_settings fus ON fus.user_id = u.id
                JOIN tenants t ON t.id = u.tenant_id
                JOIN federation_partnerships fp ON (
                    (fp.tenant_id = :tid1 AND fp.partner_tenant_id = u.tenant_id)
                    OR (fp.partner_tenant_id = :tid2 AND fp.tenant_id = u.tenant_id)
                )
                WHERE fp.status = 'active' AND fp.profiles_enabled = 1
                AND fus.federation_optin = 1 AND fus.appear_in_federated_search = 1
                AND u.status = 'active' AND u.tenant_id != :tid3
            ";
            $filterParams = [':tid1' => $tenantId, ':tid2' => $tenantId, ':tid3' => $tenantId];

            if (!empty($q)) {
                $fromWhere .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR (fus.show_skills_federated = 1 AND u.skills LIKE :q3) OR u.bio LIKE :q4)";
                $searchTerm = "%{$q}%";
                $filterParams[':q1'] = $searchTerm;
                $filterParams[':q2'] = $searchTerm;
                $filterParams[':q3'] = $searchTerm;
                $filterParams[':q4'] = $searchTerm;
            }
            if ($partnerId) {
                $fromWhere .= " AND u.tenant_id = :partner_id";
                $filterParams[':partner_id'] = $partnerId;
            }
            if (!empty($serviceReach) && in_array($serviceReach, ['local_only', 'remote_ok', 'travel_ok'])) {
                if ($serviceReach === 'remote_ok') {
                    $fromWhere .= " AND fus.service_reach IN ('remote_ok', 'travel_ok')";
                } else {
                    $fromWhere .= " AND fus.service_reach = :service_reach";
                    $filterParams[':service_reach'] = $serviceReach;
                }
            }
            if (!empty($skills)) {
                $fromWhere .= " AND fus.show_skills_federated = 1";
                $skillList = array_map('trim', explode(',', $skills));
                $skillIdx = 0;
                foreach ($skillList as $skill) {
                    if (!empty($skill)) {
                        $paramName = ":skill{$skillIdx}";
                        $fromWhere .= " AND u.skills LIKE {$paramName}";
                        $filterParams[$paramName] = "%{$skill}%";
                        $skillIdx++;
                    }
                }
            }

            // Count query (without cursor) for total_items
            $countStmt = DB::getPdo()->prepare("SELECT COUNT(*) " . $fromWhere);
            foreach ($filterParams as $key => $value) {
                $countStmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

            // Data query with cursor and limit
            $params = $filterParams;
            $sql = "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.bio, u.skills,
                    u.location, u.tenant_id, t.name as tenant_name,
                    fus.service_reach, fus.messaging_enabled_federated,
                    fus.show_skills_federated, fus.show_location_federated " . $fromWhere;
            if ($cursorId) {
                $sql .= " AND u.id < :cursor_id";
                $params[':cursor_id'] = (int) $cursorId;
            }
            $sql .= " ORDER BY u.id DESC LIMIT :limit";
            $params[':limit'] = $perPage + 1;

            $stmt = DB::getPdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $perPage);
            }

            $formatted = array_map(function ($m) {
                return [
                    'id' => (int) $m['id'],
                    'name' => trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
                    'first_name' => $m['first_name'] ?? '',
                    'last_name' => $m['last_name'] ?? '',
                    'avatar' => $m['avatar_url'] ?: null,
                    'bio' => $m['bio'] ?? '',
                    'skills' => ($m['show_skills_federated'] && !empty($m['skills'])) ? array_map('trim', explode(',', $m['skills'])) : [],
                    'location' => $m['show_location_federated'] ? ($m['location'] ?? null) : null,
                    'service_reach' => $m['service_reach'] ?? 'local_only',
                    'messaging_enabled' => (bool) ($m['messaging_enabled_federated'] ?? false),
                    'tenant_id' => (int) $m['tenant_id'],
                    'tenant_name' => $m['tenant_name'] ?? '',
                    'timebank' => [
                        'id' => (int) $m['tenant_id'],
                        'name' => $m['tenant_name'],
                    ],
                ];
            }, $rows);

            $nextCursor = null;
            if ($hasMore && !empty($rows)) {
                $lastRow = end($rows);
                $nextCursor = $this->encodeCursor($lastRow['id']);
            }

            // Merge external partner members (only on first page, and only when NOT filtering by internal partner)
            if (!$cursorId && !$partnerId) {
                $external = $this->fetchExternalMembers($tenantId, $q, $skills);
                if (!empty($external)) {
                    $totalItems += count($external);
                    $formatted = array_merge($formatted, $external);
                }
            }

            return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore, ['total_items' => $totalItems]);
        } catch (\Exception $e) {
            error_log("FederationV2Api::members error: " . $e->getMessage());
            return $this->respondWithCollection([], null, $perPage, false, ['total_items' => 0]);
        }
    }

    /**
     * Fetch members from a SINGLE external partner by ID.
     */
    private function fetchExternalMembersFromPartner(int $externalPartnerId, string $q, string $skills): array
    {
        try {
            $filters = [];
            if (!empty($q)) $filters['q'] = $q;
            if (!empty($skills)) $filters['skills'] = $skills;

            $result = \App\Services\FederationExternalApiClient::fetchMembers($externalPartnerId, $filters);

            if (!($result['success'] ?? false) || empty($result['data'])) {
                return [];
            }

            $partner = \App\Services\FederationExternalPartnerService::getById(
                $externalPartnerId,
                $this->getTenantId()
            );
            $partnerName = $partner['name'] ?? 'External Partner';
            $partnerBaseUrl = rtrim($partner['base_url'] ?? '', '/');

            return array_map(function ($m) use ($externalPartnerId, $partnerName, $partnerBaseUrl) {
                return [
                    'id' => 'ext-' . $externalPartnerId . '-' . ($m['id'] ?? 0),
                    'name' => $m['name'] ?? trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
                    'first_name' => $m['first_name'] ?? '',
                    'last_name' => $m['last_name'] ?? '',
                    'avatar' => self::resolveExternalUrl($m['avatar'] ?? null, $partnerBaseUrl),
                    'bio' => $m['bio'] ?? '',
                    'skills' => is_array($m['skills'] ?? null) ? $m['skills'] : (is_string($m['skills'] ?? null) ? array_map('trim', explode(',', $m['skills'])) : []),
                    'location' => $m['location'] ?? null,
                    'service_reach' => $m['service_reach'] ?? 'local_only',
                    'messaging_enabled' => (bool) ($m['accepts_messages'] ?? $m['messaging_enabled'] ?? false),
                    'tenant_id' => 'ext-' . $externalPartnerId,
                    'tenant_name' => $m['timebank']['name'] ?? $partnerName,
                    'timebank' => [
                        'id' => 'ext-' . $externalPartnerId,
                        'name' => $m['timebank']['name'] ?? $partnerName,
                    ],
                    'is_external' => true,
                    'partner_name' => $partnerName,
                ];
            }, $result['data']);
        } catch (\Throwable $e) {
            error_log("FederationV2Api::fetchExternalMembersFromPartner error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch members from ALL external federation partners and normalize to the same shape.
     */
    private function fetchExternalMembers(int $tenantId, string $q, string $skills): array
    {
        try {
            $searchService = app(FederationSearchService::class);
            $filters = [];
            if (!empty($q)) $filters['q'] = $q;
            if (!empty($skills)) $filters['skills'] = $skills;

            $result = $searchService->searchExternalMembers($tenantId, $filters);

            return array_map(function ($m) {
                $partnerId = $m['partner_id'] ?? 0;
                $baseUrl = $partnerId ? $this->getPartnerBaseUrl((int) $partnerId) : '';
                return [
                    'id' => 'ext-' . $partnerId . '-' . ($m['id'] ?? 0),
                    'name' => $m['name'] ?? trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
                    'first_name' => $m['first_name'] ?? '',
                    'last_name' => $m['last_name'] ?? '',
                    'avatar' => self::resolveExternalUrl($m['avatar'] ?? null, $baseUrl),
                    'bio' => $m['bio'] ?? '',
                    'skills' => is_array($m['skills'] ?? null) ? $m['skills'] : (is_string($m['skills'] ?? null) ? array_map('trim', explode(',', $m['skills'])) : []),
                    'location' => $m['location'] ?? null,
                    'service_reach' => $m['service_reach'] ?? 'local_only',
                    'messaging_enabled' => (bool) ($m['accepts_messages'] ?? $m['messaging_enabled'] ?? false),
                    'tenant_id' => (int) ($m['timebank']['id'] ?? $m['tenant_id'] ?? 0),
                    'tenant_name' => $m['timebank']['name'] ?? $m['partner_name'] ?? 'External Partner',
                    'timebank' => [
                        'id' => (int) ($m['timebank']['id'] ?? $m['tenant_id'] ?? 0),
                        'name' => $m['timebank']['name'] ?? $m['partner_name'] ?? 'External Partner',
                    ],
                    'is_external' => true,
                    'partner_name' => $m['partner_name'] ?? null,
                ];
            }, $result['members'] ?? []);
        } catch (\Throwable $e) {
            error_log("FederationV2Api::fetchExternalMembers error: " . $e->getMessage());
            return [];
        }
    }

    /** GET /api/v2/federation/members/{id} */
    public function member(int $id): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();
        $memberTenantId = $this->queryInt('tenant_id');

        try {
            $sql = "
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.bio, u.skills,
                    u.location, u.tenant_id, t.name as tenant_name,
                    fus.service_reach, fus.messaging_enabled_federated,
                    fus.transactions_enabled_federated, fus.show_skills_federated,
                    fus.show_location_federated, fus.show_reviews_federated
                FROM users u
                JOIN federation_user_settings fus ON fus.user_id = u.id
                JOIN tenants t ON t.id = u.tenant_id
                JOIN federation_partnerships fp ON (
                    (fp.tenant_id = :tid1 AND fp.partner_tenant_id = u.tenant_id)
                    OR (fp.partner_tenant_id = :tid2 AND fp.tenant_id = u.tenant_id)
                )
                WHERE u.id = :user_id AND fp.status = 'active' AND fp.profiles_enabled = 1
                AND fus.federation_optin = 1 AND fus.profile_visible_federated = 1
                AND u.status = 'active'
            ";
            $params = [':tid1' => $tenantId, ':tid2' => $tenantId, ':user_id' => $id];

            if ($memberTenantId) {
                $sql .= " AND u.tenant_id = :member_tenant_id";
                $params[':member_tenant_id'] = $memberTenantId;
            }

            $sql .= " LIMIT 1";

            $stmt = DB::getPdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $m = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$m) {
                return $this->respondWithError('MEMBER_NOT_FOUND', __('api.fed_member_not_found'), null, 404);
            }

            $member = [
                'id' => (int) $m['id'],
                'name' => trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
                'first_name' => $m['first_name'] ?? '',
                'last_name' => $m['last_name'] ?? '',
                'avatar' => $m['avatar_url'] ?: null,
                'bio' => $m['bio'] ?? '',
                'skills' => ($m['show_skills_federated'] && !empty($m['skills']))
                    ? array_map('trim', explode(',', $m['skills']))
                    : [],
                'location' => $m['show_location_federated'] ? ($m['location'] ?? null) : null,
                'service_reach' => $m['service_reach'] ?? 'local_only',
                'messaging_enabled' => (bool) ($m['messaging_enabled_federated'] ?? false),
                'tenant_id' => (int) $m['tenant_id'],
                'tenant_name' => $m['tenant_name'] ?? '',
                'timebank' => [
                    'id' => (int) $m['tenant_id'],
                    'name' => $m['tenant_name'],
                ],
            ];

            return $this->respondWithData($member);
        } catch (\Exception $e) {
            error_log("FederationV2Api::member error: " . $e->getMessage());
            return $this->respondWithError('INTERNAL_ERROR', __('api.fed_member_profile_failed'), null, 500);
        }
    }

    // =====================================================================
    // FEDERATED MESSAGES
    // =====================================================================

    /** GET /api/v2/federation/messages */
    public function messages(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        try {
            $rowResults = DB::select("
                SELECT fm.id, fm.subject, fm.body, fm.direction, fm.status, fm.read_at,
                    fm.created_at, fm.sender_tenant_id, fm.sender_user_id,
                    fm.receiver_tenant_id, fm.receiver_user_id, fm.reference_message_id,
                    fm.external_partner_id, fm.external_receiver_name, fm.external_message_id,
                    COALESCE(su.first_name, '') as sender_first_name,
                    COALESCE(su.last_name, '') as sender_last_name,
                    su.avatar_url as sender_avatar, st.name as sender_tenant_name,
                    COALESCE(ru.first_name, '') as receiver_first_name,
                    COALESCE(ru.last_name, '') as receiver_last_name,
                    ru.avatar_url as receiver_avatar, rt.name as receiver_tenant_name,
                    ep.name as external_partner_name
                FROM federation_messages fm
                LEFT JOIN users su ON su.id = fm.sender_user_id
                LEFT JOIN tenants st ON st.id = fm.sender_tenant_id
                LEFT JOIN users ru ON ru.id = fm.receiver_user_id
                LEFT JOIN tenants rt ON rt.id = fm.receiver_tenant_id
                LEFT JOIN federation_external_partners ep ON ep.id = fm.external_partner_id
                WHERE (
                    (fm.sender_tenant_id = ? AND fm.sender_user_id = ?)
                    OR (fm.receiver_tenant_id = ? AND fm.receiver_user_id = ?)
                    OR (fm.external_partner_id IS NOT NULL AND fm.direction = 'outbound' AND fm.sender_user_id = ? AND fm.sender_tenant_id = ?)
                    OR (fm.external_partner_id IS NOT NULL AND fm.direction = 'inbound' AND fm.receiver_user_id = ? AND fm.receiver_tenant_id = ?)
                )
                ORDER BY fm.created_at DESC LIMIT 200
            ", [$tenantId, $userId, $tenantId, $userId, $userId, $tenantId, $userId, $tenantId]);
            $rows = array_map(fn($r) => (array)$r, $rowResults);

            $formatted = array_map(function ($msg) {
                $isExternal = !empty($msg['external_partner_id']);
                $extPartnerId = $isExternal ? (int) $msg['external_partner_id'] : null;
                $direction = $msg['direction'] === 'outbound' ? 'outbound' : 'inbound';
                $externalName = !empty($msg['external_receiver_name']) ? $msg['external_receiver_name'] : __('api.external_user_fallback');
                $partnerName = !empty($msg['external_partner_name']) ? $msg['external_partner_name'] : __('api.external_partner_fallback');

                if ($isExternal && $direction === 'outbound') {
                    // Outbound to external: sender is local, receiver is external
                    $senderInfo = [
                        'id' => (int) $msg['sender_user_id'],
                        'name' => trim($msg['sender_first_name'] . ' ' . $msg['sender_last_name']),
                        'avatar' => $msg['sender_avatar'] ?: null,
                        'tenant_id' => (int) $msg['sender_tenant_id'],
                        'tenant_name' => $msg['sender_tenant_name'] ?? '',
                    ];
                    $receiverInfo = [
                        'id' => (int) $msg['receiver_user_id'],
                        'name' => $externalName,
                        'avatar' => null,
                        'tenant_id' => 'ext-' . $extPartnerId,
                        'tenant_name' => $partnerName,
                    ];
                } elseif ($isExternal && $direction === 'inbound') {
                    // Inbound from external: sender is external, receiver is local
                    $senderInfo = [
                        'id' => (int) $msg['sender_user_id'],
                        'name' => $externalName,
                        'avatar' => null,
                        'tenant_id' => 'ext-' . $extPartnerId,
                        'tenant_name' => $partnerName,
                    ];
                    $receiverInfo = [
                        'id' => (int) $msg['receiver_user_id'],
                        'name' => trim($msg['receiver_first_name'] . ' ' . $msg['receiver_last_name']),
                        'avatar' => $msg['receiver_avatar'] ?: null,
                        'tenant_id' => (int) $msg['receiver_tenant_id'],
                        'tenant_name' => $msg['receiver_tenant_name'] ?? '',
                    ];
                } else {
                    // Internal message
                    $senderInfo = [
                        'id' => (int) $msg['sender_user_id'],
                        'name' => trim($msg['sender_first_name'] . ' ' . $msg['sender_last_name']),
                        'avatar' => $msg['sender_avatar'] ?: null,
                        'tenant_id' => (int) $msg['sender_tenant_id'],
                        'tenant_name' => $msg['sender_tenant_name'] ?? '',
                    ];
                    $receiverInfo = [
                        'id' => (int) $msg['receiver_user_id'],
                        'name' => trim($msg['receiver_first_name'] . ' ' . $msg['receiver_last_name']),
                        'avatar' => $msg['receiver_avatar'] ?: null,
                        'tenant_id' => (int) $msg['receiver_tenant_id'],
                        'tenant_name' => $msg['receiver_tenant_name'] ?? '',
                    ];
                }

                $formatted = [
                    'id' => (int) $msg['id'],
                    'subject' => $msg['subject'] ?? '',
                    'body' => $msg['body'],
                    'direction' => $direction,
                    'status' => $this->mapMessageStatus($msg['status'] ?? 'delivered'),
                    'read_at' => $msg['read_at'] ?: null,
                    'created_at' => $msg['created_at'],
                    'sender' => $senderInfo,
                    'receiver' => $receiverInfo,
                    'reference_message_id' => $msg['reference_message_id'] ? (int) $msg['reference_message_id'] : null,
                ];

                if ($isExternal) {
                    $formatted['is_external'] = true;
                    $formatted['external_partner_id'] = $extPartnerId;
                }

                return $formatted;
            }, $rows);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            error_log("FederationV2Api::messages error: " . $e->getMessage());
            return $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/federation/messages
     *
     * Send a cross-tenant federated message. Inserts outbound + inbound copies,
     * then dispatches email, realtime, and in-app notifications (non-blocking).
     */
    public function sendMessage(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $input = request()->all();
        $receiverId = $input['receiver_id'] ?? null;
        $receiverTenantId = $input['receiver_tenant_id'] ?? null;
        $subject = $input['subject'] ?? '';
        $body = $input['body'] ?? '';
        $referenceMessageId = $input['reference_message_id'] ?? null;

        // Verify sender is opted in and has messaging enabled
        $senderSettings = $this->federationUserService->getUserSettings($userId);
        if (!($senderSettings['federation_optin'] ?? false)) {
            return $this->respondWithError('SENDER_NOT_OPTED_IN', __('api.fed_sender_not_opted_in'), null, 403);
        }
        if (!($senderSettings['messaging_enabled_federated'] ?? false)) {
            return $this->respondWithError('SENDER_MESSAGING_DISABLED', __('api.fed_sender_messaging_disabled'), null, 403);
        }

        // Validate required fields
        $errors = [];
        if (empty($receiverId)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_1.federation_v2.receiver_id_required'), 'field' => 'receiver_id'];
        }
        if (empty($receiverTenantId)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_1.federation_v2.receiver_tenant_id_required'), 'field' => 'receiver_tenant_id'];
        }
        if (empty($body)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_1.federation_v2.message_body_required'), 'field' => 'body'];
        }
        if (mb_strlen($subject) > 255) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_1.federation_v2.subject_max_length'), 'field' => 'subject'];
        }
        if (mb_strlen($body) > 10000) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_1.federation_v2.body_max_length'), 'field' => 'body'];
        }
        if (!empty($errors)) {
            return $this->respondWithErrors($errors);
        }

        // Sanitize subject and body to prevent stored XSS
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

        // ── External partner message routing ──
        // If receiver_tenant_id starts with "ext-", route via FederationExternalApiClient
        $receiverTenantStr = (string) $receiverTenantId;
        if (str_starts_with($receiverTenantStr, 'ext-')) {
            return $this->sendExternalMessage(
                $userId, $tenantId, $receiverTenantStr, (string) $receiverId,
                $subject, $body, $referenceMessageId
            );
        }

        try {
            // Verify the receiver exists and accepts federated messages
            $receiverRow = DB::selectOne("
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.tenant_id,
                       fus.messaging_enabled_federated, fus.federation_optin,
                       t.name as tenant_name
                FROM users u
                JOIN federation_user_settings fus ON fus.user_id = u.id
                JOIN tenants t ON t.id = u.tenant_id
                WHERE u.id = ? AND u.tenant_id = ? AND u.status = 'active'
            ", [(int)$receiverId, (int)$receiverTenantId]);
            $receiver = $receiverRow ? (array)$receiverRow : null;

            if (!$receiver) {
                return $this->respondWithError('RECIPIENT_NOT_FOUND', __('api.fed_recipient_not_found'), null, 404);
            }

            if (!$receiver['federation_optin'] || !$receiver['messaging_enabled_federated']) {
                return $this->respondWithError('MESSAGING_DISABLED', __('api.fed_messaging_disabled'), null, 403);
            }

            // Verify an active partnership exists between the two tenants
            $partnership = $this->federationPartnershipService->getPartnership($tenantId, (int)$receiverTenantId);
            if (!$partnership || $partnership['status'] !== 'active') {
                return $this->respondWithError('NO_PARTNERSHIP', __('api.fed_no_partnership'), null, 403);
            }

            if (!($partnership['messaging_enabled'] ?? false)) {
                return $this->respondWithError('MESSAGING_NOT_ALLOWED', __('api.fed_messaging_not_allowed'), null, 403);
            }

            // Get sender info
            $senderRow = DB::selectOne("
                SELECT u.first_name, u.last_name, u.avatar_url, t.name as tenant_name
                FROM users u
                JOIN tenants t ON t.id = u.tenant_id
                WHERE u.id = ?
            ", [$userId]);
            $sender = $senderRow ? (array)$senderRow : [];

            $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

            // Insert outbound message (sender's copy)
            DB::insert("
                INSERT INTO federation_messages
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 subject, body, direction, status, reference_message_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'outbound', 'delivered', ?, NOW())
            ", [
                $tenantId, $userId,
                (int)$receiverTenantId, (int)$receiverId,
                $subject, $body,
                $referenceMessageId ? (int)$referenceMessageId : null,
            ]);
            $outboundId = (int)DB::getPdo()->lastInsertId();

            // Insert inbound message (receiver's copy)
            DB::insert("
                INSERT INTO federation_messages
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 subject, body, direction, status, reference_message_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'inbound', 'unread', ?, NOW())
            ", [
                $tenantId, $userId,
                (int)$receiverTenantId, (int)$receiverId,
                $subject, $body,
                $referenceMessageId ? (int)$referenceMessageId : null,
            ]);

            // Audit log
            $this->federationAuditService->log(
                'cross_tenant_message',
                $tenantId,
                (int)$receiverTenantId,
                $userId,
                ['message_id' => $outboundId, 'receiver_id' => (int)$receiverId],
                FederationAuditService::LEVEL_INFO
            );

            // ── Notification dispatch (email + realtime + in-app + push) ──
            // These are async/non-blocking: failures are logged but don't affect the response.

            $senderTenantName = $sender['tenant_name'] ?? '';

            // 1. Email notification to recipient
            try {
                $this->federationEmailService->sendNewMessageNotification(
                    (int)$receiverId,
                    $userId,
                    $tenantId,
                    substr($body, 0, 200)
                );
            } catch (\Exception $e) {
                error_log("FederationV2: Failed to send federation message email: " . $e->getMessage());
            }

            // 2. Real-time notification via Pusher
            try {
                $this->federationRealtimeService->broadcastNewMessage(
                    $userId,
                    $tenantId,
                    (int)$receiverId,
                    (int)$receiverTenantId,
                    [
                        'message_id' => $outboundId,
                        'sender_name' => $senderName,
                        'sender_tenant_name' => $senderTenantName,
                        'subject' => $subject,
                        'body' => $body,
                    ]
                );
            } catch (\Exception $e) {
                error_log("FederationV2: Failed to send federation message realtime: " . $e->getMessage());
            }

            // 3. In-app notification + push notification
            try {
                $subjectPreview = mb_substr($subject ?: __('api.federation_no_subject'), 0, 50);
                if (mb_strlen($subject) > 50) {
                    $subjectPreview .= '...';
                }
                $notifMessage = __('api.federation_new_message_notif', [
                    'sender' => $senderName,
                    'community' => $senderTenantName,
                    'subject' => $subjectPreview,
                ]);

                Notification::createNotification(
                    (int)$receiverId,
                    $notifMessage,
                    '/federation/messages',
                    'federation_message',
                    true,
                    (int)$receiverTenantId  // Receiver's tenant, not sender's
                );
            } catch (\Exception $e) {
                error_log("FederationV2: Failed to send federation message in-app notification: " . $e->getMessage());
            }

            // Return the outbound message in the expected format
            return $this->respondWithData([
                'id' => $outboundId,
                'subject' => $subject,
                'body' => $body,
                'direction' => 'outbound',
                'status' => 'delivered',
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'sender' => [
                    'id' => $userId,
                    'name' => $senderName,
                    'avatar' => $sender['avatar_url'] ?? null,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $sender['tenant_name'] ?? '',
                ],
                'receiver' => [
                    'id' => (int)$receiverId,
                    'name' => trim($receiver['first_name'] . ' ' . $receiver['last_name']),
                    'avatar' => $receiver['avatar_url'] ?: null,
                    'tenant_id' => (int)$receiverTenantId,
                    'tenant_name' => $receiver['tenant_name'] ?? '',
                ],
                'reference_message_id' => $referenceMessageId ? (int)$referenceMessageId : null,
            ], null, 201);
        } catch (\Exception $e) {
            error_log("FederationV2Api::sendMessage error: " . $e->getMessage());
            return $this->respondWithError('SEND_FAILED', __('api.fed_send_failed'), null, 500);
        }
    }

    /**
     * Send a message to an external partner via their federation API.
     *
     * Called when receiver_tenant_id starts with "ext-". Routes through
     * FederationExternalApiClient and stores a local outbound copy.
     */
    private function sendExternalMessage(
        int $userId, int $tenantId, string $receiverTenantStr, string $receiverIdStr,
        string $subject, string $body, $referenceMessageId
    ): JsonResponse {
        // Parse partner ID from "ext-3"
        $externalPartnerId = (int) substr($receiverTenantStr, 4);
        if ($externalPartnerId <= 0) {
            return $this->respondWithError('INVALID_PARTNER', __('api.invalid_external_partner_id'), null, 400);
        }

        // Parse real member ID from "ext-{partnerId}-{userId}" or plain "{userId}"
        $realReceiverId = $receiverIdStr;
        if (str_starts_with($receiverIdStr, 'ext-')) {
            $parts = explode('-', $receiverIdStr, 3); // ext, partnerId, userId
            $realReceiverId = $parts[2] ?? $receiverIdStr;
        }
        $realReceiverId = (int) $realReceiverId;
        if ($realReceiverId <= 0) {
            return $this->respondWithError('INVALID_RECEIVER', __('api.invalid_external_receiver_id'), null, 400);
        }

        // Load and validate external partner
        $partner = \App\Services\FederationExternalPartnerService::getById($externalPartnerId, $tenantId);
        if (!$partner || $partner['status'] !== 'active') {
            return $this->respondWithError('PARTNER_NOT_FOUND', __('api.external_partner_not_found'), null, 404);
        }
        if (!($partner['allow_messaging'] ?? false)) {
            return $this->respondWithError('MESSAGING_NOT_ALLOWED', __('api.external_partner_messaging_disabled'), null, 403);
        }

        // Get sender info for the outbound call and local record
        $senderRow = DB::selectOne(
            "SELECT u.first_name, u.last_name, u.avatar_url, t.name as tenant_name FROM users u JOIN tenants t ON t.id = u.tenant_id WHERE u.id = ?",
            [$userId]
        );
        $sender = $senderRow ? (array) $senderRow : [];
        $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

        // Send via external API
        try {
            $result = \App\Services\FederationExternalApiClient::sendMessage($externalPartnerId, [
                'sender_id' => $userId,
                'sender_name' => $senderName,
                'recipient_id' => $realReceiverId,
                'subject' => $subject,
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            error_log("FederationV2::sendExternalMessage API error: " . $e->getMessage());
            return $this->respondWithError('EXTERNAL_API_FAILED', __('api.external_partner_api_failed'), null, 502);
        }

        if (!($result['success'] ?? false)) {
            $errorMsg = $result['error'] ?? 'External partner rejected the message';
            return $this->respondWithError('EXTERNAL_SEND_FAILED', $errorMsg, null, 422);
        }

        $externalMessageId = $result['data']['message_id'] ?? null;
        $rawReceiverName = $result['data']['receiver_name'] ?? '';
        $receiverName = htmlspecialchars(
            !empty($rawReceiverName) ? $rawReceiverName : __('api.external_user_fallback') . ' #' . $realReceiverId,
            ENT_QUOTES, 'UTF-8'
        );

        // Store local outbound copy so it appears in sender's thread list.
        // receiver_user_id stores the REMOTE user ID (for unique thread keys).
        // receiver_tenant_id = 0 signals this is external (external_partner_id is authoritative).
        DB::insert("
            INSERT INTO federation_messages
            (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
             subject, body, direction, status, reference_message_id,
             external_partner_id, external_receiver_name, external_message_id, created_at)
            VALUES (?, ?, 0, ?, ?, ?, 'outbound', 'delivered', ?, ?, ?, ?, NOW())
        ", [
            $tenantId, $userId,
            $realReceiverId,
            $subject, $body,
            $referenceMessageId ? (int) $referenceMessageId : null,
            $externalPartnerId,
            $receiverName,
            $externalMessageId,
        ]);
        $outboundId = (int) DB::getPdo()->lastInsertId();

        // Audit log
        $this->federationAuditService->log(
            'external_message_sent',
            $tenantId, null, $userId,
            ['message_id' => $outboundId, 'partner_id' => $externalPartnerId, 'receiver_id' => $realReceiverId],
            FederationAuditService::LEVEL_INFO
        );

        return $this->respondWithData([
            'id' => $outboundId,
            'subject' => $subject,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'delivered',
            'read_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'sender' => [
                'id' => $userId,
                'name' => $senderName,
                'avatar' => $sender['avatar_url'] ?? null,
                'tenant_id' => $tenantId,
                'tenant_name' => $sender['tenant_name'] ?? '',
            ],
            'receiver' => [
                'id' => $realReceiverId,
                'name' => $receiverName,
                'avatar' => null,
                'tenant_id' => $receiverTenantStr,
                'tenant_name' => $partner['name'] ?? 'External Partner',
            ],
            'reference_message_id' => $referenceMessageId ? (int) $referenceMessageId : null,
            'is_external' => true,
            'external_partner_id' => $externalPartnerId,
        ], null, 201);
    }

    /** POST /api/v2/federation/messages/{id}/read */
    public function markMessageRead(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        try {
            $messageRow = DB::selectOne("
                SELECT id, status FROM federation_messages
                WHERE id = ? AND receiver_tenant_id = ? AND receiver_user_id = ?
                AND direction = 'inbound'
            ", [$id, $tenantId, $userId]);
            $message = $messageRow ? (array)$messageRow : null;

            if (!$message) {
                return $this->respondWithError('MESSAGE_NOT_FOUND', __('api.fed_message_not_found'), null, 404);
            }

            if ($message['status'] !== 'read') {
                DB::update(
                    "UPDATE federation_messages SET status = 'read', read_at = NOW() WHERE id = ? AND receiver_tenant_id = ? AND receiver_user_id = ?",
                    [$id, $tenantId, $userId]
                );
            }

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            error_log("FederationV2Api::markMessageRead error: " . $e->getMessage());
            return $this->respondWithError('INTERNAL_ERROR', __('api.fed_mark_read_failed'), null, 500);
        }
    }

    /** POST /api/v2/federation/messages/{id}/translate */
    public function translateMessage(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        if (!\App\Core\TenantContext::hasFeature('message_translation')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.feature_disabled'), null, 403);
        }

        $this->rateLimit('federation_messages_translate', 20, 60);

        $targetLanguage = trim(request()->input('target_language', ''));
        if (empty($targetLanguage)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.message_target_language_required'), 'target_language', 400);
        }
        if (strlen($targetLanguage) > 10 || !preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $targetLanguage)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.message_target_language_required'), 'target_language', 400);
        }

        // Fetch the message — user must be sender or receiver
        // Handles both internal federation and external partner messages
        $message = DB::selectOne("
            SELECT id, body, sender_tenant_id, sender_user_id,
                   receiver_tenant_id, receiver_user_id,
                   external_partner_id, direction
            FROM federation_messages
            WHERE id = ?
              AND (
                (sender_tenant_id = ? AND sender_user_id = ?)
                OR (receiver_tenant_id = ? AND receiver_user_id = ?)
                OR (external_partner_id IS NOT NULL AND direction = 'outbound' AND sender_user_id = ?)
                OR (external_partner_id IS NOT NULL AND direction = 'inbound' AND receiver_user_id = ?)
              )
        ", [$id, $tenantId, $userId, $tenantId, $userId, $userId, $userId]);

        if (!$message) {
            return $this->respondWithError('NOT_FOUND', __('api.fed_message_not_found'), null, 404);
        }

        $sourceText = $message->body ?? '';
        if (empty(trim($sourceText))) {
            return $this->respondWithError('NO_CONTENT', __('api.message_no_translatable_content'), null, 422);
        }

        // INT7: Fetch conversation context for better disambiguation
        $conversationContext = [];
        $translationConfig = TranslationConfigurationService::getAll();
        if (!empty($translationConfig['translation.context_aware'])) {
            $contextLimit = (int) ($translationConfig['translation.context_messages'] ?? 5);

            // Get the two participants of this thread
            $sTenant = (int) $message->sender_tenant_id;
            $sUser   = (int) $message->sender_user_id;
            $rTenant = (int) $message->receiver_tenant_id;
            $rUser   = (int) $message->receiver_user_id;

            $contextRows = DB::select("
                SELECT body FROM federation_messages
                WHERE id < ?
                  AND (
                    (sender_tenant_id = ? AND sender_user_id = ? AND receiver_tenant_id = ? AND receiver_user_id = ?)
                    OR (sender_tenant_id = ? AND sender_user_id = ? AND receiver_tenant_id = ? AND receiver_user_id = ?)
                  )
                ORDER BY id DESC LIMIT ?
            ", [$id, $sTenant, $sUser, $rTenant, $rUser, $rTenant, $rUser, $sTenant, $sUser, $contextLimit]);

            $conversationContext = array_filter(
                array_reverse(array_map(fn ($r) => $r->body, $contextRows)),
                fn ($b) => !empty($b)
            );
            $conversationContext = array_values($conversationContext);
        }

        // INT10: Load glossary terms for the target language
        $glossary = [];
        if (!empty($translationConfig['translation.glossary_enabled'])
            && DB::getSchemaBuilder()->hasTable('translation_glossaries')
        ) {
            $glossaryRows = DB::table('translation_glossaries')
                ->where('tenant_id', $tenantId)
                ->where('target_language', $targetLanguage)
                ->where('is_active', true)
                ->limit(50)
                ->get(['source_term', 'target_term']);
            foreach ($glossaryRows as $row) {
                $glossary[$row->source_term] = $row->target_term;
            }
        }

        $translatedText = TranscriptionService::translate($sourceText, 'auto', $targetLanguage, $conversationContext, $glossary);

        if ($translatedText === null) {
            return $this->respondWithError('TRANSLATION_FAILED', __('api.message_translation_failed'), null, 500);
        }

        return $this->respondWithData([
            'translated_text' => $translatedText,
            'source_type'     => 'body',
            'context_used'    => !empty($conversationContext),
        ]);
    }

    // =====================================================================
    // SETTINGS
    // =====================================================================

    /** GET /api/v2/federation/settings */
    public function getSettings(): JsonResponse
    {
        $userId = $this->getUserId();

        $userSettings = $this->federationUserService->getUserSettings($userId);

        return $this->respondWithData([
            'enabled' => (bool) ($userSettings['federation_optin'] ?? false),
            'settings' => [
                'profile_visible_federated' => (bool) ($userSettings['profile_visible_federated'] ?? false),
                'appear_in_federated_search' => (bool) ($userSettings['appear_in_federated_search'] ?? false),
                'show_skills_federated' => (bool) ($userSettings['show_skills_federated'] ?? false),
                'show_location_federated' => (bool) ($userSettings['show_location_federated'] ?? false),
                'show_reviews_federated' => (bool) ($userSettings['show_reviews_federated'] ?? false),
                'messaging_enabled_federated' => (bool) ($userSettings['messaging_enabled_federated'] ?? false),
                'transactions_enabled_federated' => (bool) ($userSettings['transactions_enabled_federated'] ?? false),
                'email_notifications' => (bool) ($userSettings['email_notifications'] ?? true),
                'service_reach' => $userSettings['service_reach'] ?? 'local_only',
                'travel_radius_km' => $userSettings['travel_radius_km'] ? (int) $userSettings['travel_radius_km'] : null,
                'federation_optin' => (bool) ($userSettings['federation_optin'] ?? false),
            ],
        ]);
    }

    /** PUT /api/v2/federation/settings */
    public function updateSettings(): JsonResponse
    {
        $userId = $this->getUserId();

        $data = $this->getAllInput();

        $current = $this->federationUserService->getUserSettings($userId);
        $settings = [
            'federation_optin' => $current['federation_optin'],
            'profile_visible_federated' => $data['profile_visible_federated'] ?? $current['profile_visible_federated'],
            'appear_in_federated_search' => $data['appear_in_federated_search'] ?? $current['appear_in_federated_search'],
            'show_skills_federated' => $data['show_skills_federated'] ?? $current['show_skills_federated'],
            'show_location_federated' => $data['show_location_federated'] ?? $current['show_location_federated'],
            'show_reviews_federated' => $data['show_reviews_federated'] ?? $current['show_reviews_federated'],
            'messaging_enabled_federated' => $data['messaging_enabled_federated'] ?? $current['messaging_enabled_federated'],
            'transactions_enabled_federated' => $data['transactions_enabled_federated'] ?? $current['transactions_enabled_federated'],
            'email_notifications' => $data['email_notifications'] ?? ($current['email_notifications'] ?? true),
            'service_reach' => $data['service_reach'] ?? $current['service_reach'],
            'travel_radius_km' => array_key_exists('travel_radius_km', $data) ? $data['travel_radius_km'] : $current['travel_radius_km'],
        ];

        $success = $this->federationUserService->updateSettings($userId, $settings);

        if ($success) {
            return $this->respondWithData(['success' => true, 'message' => __('api_controllers_1.federation.settings_updated')]);
        }

        return $this->respondWithError('UPDATE_FAILED', __('api.fed_settings_update_failed'), null, 500);
    }

    // =====================================================================
    // CONNECTIONS
    // =====================================================================

    /** GET /api/v2/federation/connections */
    public function connections(): JsonResponse
    {
        $userId = $this->getUserId();
        $status = $this->input('status', 'accepted');
        $limit = min($this->queryInt('limit', 50, 1, 100), 100);
        $offset = max($this->queryInt('offset', 0, 0), 0);

        $connections = $this->federatedConnectionService->getConnections($userId, $status, $limit, $offset);
        $pendingCount = $this->federatedConnectionService->getPendingCount($userId);

        return $this->respondWithData([
            'connections' => $connections,
            'pending_count' => $pendingCount,
        ]);
    }

    /** POST /api/v2/federation/connections */
    public function sendConnectionRequest(): JsonResponse
    {
        $userId = $this->getUserId();
        $receiverId = (int) $this->input('receiver_id');
        $receiverTenantId = (int) $this->input('receiver_tenant_id');
        $message = $this->input('message');

        if (!$receiverId || !$receiverTenantId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.fed_receiver_ids_required'), null, 400);
        }

        $result = $this->federatedConnectionService->sendRequest($userId, $receiverId, $receiverTenantId, $message);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result, null, 201);
    }

    /** POST /api/v2/federation/connections/{id}/accept */
    public function acceptConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = $this->federatedConnectionService->acceptRequest($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/federation/connections/{id}/reject */
    public function rejectConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = $this->federatedConnectionService->rejectRequest($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result);
    }

    /** DELETE /api/v2/federation/connections/{id} */
    public function removeConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = $this->federatedConnectionService->removeConnection($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 404);
        }

        return $this->respondWithData($result);
    }

    /** GET /api/v2/federation/connections/status/{userId}/{tenantId} */
    public function connectionStatus($userId, $tenantId): JsonResponse
    {
        $currentUserId = $this->getUserId();
        $status = $this->federatedConnectionService->getStatus($currentUserId, $userId, $tenantId);
        return $this->respondWithData($status);
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

    private function mapMessageStatus(string $dbStatus): string
    {
        $map = [
            'pending' => 'delivered',
            'delivered' => 'delivered',
            'unread' => 'unread',
            'read' => 'read',
            'failed' => 'failed',
        ];
        return $map[$dbStatus] ?? 'delivered';
    }

    // =====================================================================
    // FEDERATED TRANSACTIONS
    // =====================================================================

    /** POST /api/v2/federation/transactions */
    public function sendTransaction(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $input = request()->all();
        $receiverId = $input['receiver_id'] ?? null;
        $receiverTenantId = $input['receiver_tenant_id'] ?? null;
        $amount = $input['amount'] ?? null;
        $description = $input['description'] ?? '';

        // Validate sender settings
        $senderSettings = $this->federationUserService->getUserSettings($userId);
        if (!($senderSettings['federation_optin'] ?? false)) {
            return $this->respondWithError('SENDER_NOT_OPTED_IN', 'You must opt in to federation first', null, 403);
        }
        if (!($senderSettings['transactions_enabled_federated'] ?? false)) {
            return $this->respondWithError('SENDER_TRANSACTIONS_DISABLED', 'You have not enabled federated transactions', null, 403);
        }

        // Validate required fields
        $errors = [];
        if (empty($receiverId)) $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Receiver ID is required', 'field' => 'receiver_id'];
        if (empty($receiverTenantId)) $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Receiver tenant ID is required', 'field' => 'receiver_tenant_id'];
        if ($amount === null || $amount === '') $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Amount is required', 'field' => 'amount'];
        if (empty($description)) $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description is required', 'field' => 'description'];
        if (!empty($errors)) return $this->respondWithErrors($errors);

        $amount = (int) $amount;
        if ($amount < 1 || $amount > 100) {
            return $this->respondWithError('INVALID_AMOUNT', 'Amount must be between 1 and 100 whole hours', null, 400);
        }

        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        $receiverTenantStr = (string) $receiverTenantId;
        $isExternal = str_starts_with($receiverTenantStr, 'ext-');

        if ($isExternal) {
            return $this->sendExternalTransaction($userId, $tenantId, $receiverTenantStr, (string) $receiverId, $amount, $description);
        }

        // ── Internal transaction ──
        try {
            $receiverTenantIdInt = (int) $receiverTenantId;
            $receiverIdInt = (int) $receiverId;

            // Prevent self-transactions
            if ($receiverIdInt === $userId && $receiverTenantIdInt === $tenantId) {
                return $this->respondWithError('SELF_TRANSACTION', 'Cannot send a transaction to yourself', null, 400);
            }

            // Verify receiver exists, opted in, and accepts federated transactions
            $receiver = DB::selectOne(
                "SELECT u.id, u.tenant_id, t.name as tenant_name, fus.transactions_enabled_federated
                 FROM users u JOIN tenants t ON t.id = u.tenant_id
                 JOIN federation_user_settings fus ON fus.user_id = u.id
                 WHERE u.id = ? AND u.tenant_id = ? AND u.status = 'active' AND fus.federation_optin = 1",
                [$receiverIdInt, $receiverTenantIdInt]
            );
            if (!$receiver) return $this->respondWithError('RECIPIENT_NOT_FOUND', 'Recipient not found', null, 404);
            if (!$receiver->transactions_enabled_federated) {
                return $this->respondWithError('RECIPIENT_TRANSACTIONS_DISABLED', 'Recipient has not enabled federated transactions', null, 403);
            }

            // Partnership check
            $partnership = $this->federationPartnershipService->getPartnership($tenantId, $receiverTenantIdInt);
            if (!$partnership || $partnership['status'] !== 'active' || !($partnership['transactions_enabled'] ?? false)) {
                return $this->respondWithError('TRANSACTIONS_NOT_ALLOWED', 'Partnership does not allow transactions', null, 403);
            }

            DB::beginTransaction();
            $deducted = DB::update("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?", [$amount, $userId, $amount]);
            if ($deducted === 0) {
                DB::rollBack();
                return $this->respondWithError('INSUFFICIENT_BALANCE', 'Insufficient balance', null, 400);
            }

            DB::update("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $receiverIdInt]);

            DB::insert(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, is_federated, sender_tenant_id, receiver_tenant_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', 1, ?, ?, NOW())",
                [$receiverTenantIdInt, $userId, $receiverIdInt, $amount, $description, $tenantId, $receiverTenantIdInt]
            );
            $txId = (int) DB::getPdo()->lastInsertId();
            DB::commit();

            $this->federationAuditService->log('federation_transaction', $tenantId, $receiverTenantIdInt, $userId,
                ['transaction_id' => $txId, 'amount' => $amount, 'receiver_id' => $receiverIdInt]);

            return $this->respondWithData(['transaction_id' => $txId, 'status' => 'completed', 'amount' => $amount], null, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            error_log("FederationV2::sendTransaction internal error: " . $e->getMessage());
            return $this->respondWithError('TRANSACTION_FAILED', 'Transaction failed', null, 500);
        }
    }

    /**
     * Send a transaction to an external partner via their federation API.
     */
    private function sendExternalTransaction(
        int $userId, int $tenantId, string $receiverTenantStr, string $receiverIdStr,
        int $amount, string $description
    ): JsonResponse {
        $externalPartnerId = (int) substr($receiverTenantStr, 4);
        if ($externalPartnerId <= 0) {
            return $this->respondWithError('INVALID_PARTNER', __('api.invalid_external_partner_id'), null, 400);
        }

        $realReceiverId = $receiverIdStr;
        if (str_starts_with($receiverIdStr, 'ext-')) {
            $parts = explode('-', $receiverIdStr, 3);
            $realReceiverId = $parts[2] ?? $receiverIdStr;
        }
        $realReceiverId = (int) $realReceiverId;
        if ($realReceiverId <= 0) {
            return $this->respondWithError('INVALID_RECEIVER', __('api.invalid_external_receiver_id'), null, 400);
        }

        $partner = \App\Services\FederationExternalPartnerService::getById($externalPartnerId, $tenantId);
        if (!$partner || $partner['status'] !== 'active') {
            return $this->respondWithError('PARTNER_NOT_FOUND', __('api.external_partner_not_found'), null, 404);
        }
        if (!($partner['allow_transactions'] ?? false)) {
            return $this->respondWithError('TRANSACTIONS_NOT_ALLOWED', 'This partner does not allow transactions', null, 403);
        }

        DB::beginTransaction();
        try {
            // Lock sender row and check balance BEFORE calling external API
            $senderBalance = DB::selectOne("SELECT balance FROM users WHERE id = ? FOR UPDATE", [$userId]);
            if (!$senderBalance || $senderBalance->balance < $amount) {
                DB::rollBack();
                return $this->respondWithError('INSUFFICIENT_BALANCE', 'Insufficient balance', null, 400);
            }

            // Call external API BEFORE deducting — if it fails, nothing to rollback
            $result = \App\Services\FederationExternalApiClient::createTransaction($externalPartnerId, [
                'sender_id' => $userId,
                'recipient_id' => $realReceiverId,
                'amount' => $amount,
                'description' => $description,
            ]);

            if (!($result['success'] ?? false)) {
                DB::rollBack();
                $errorMsg = $result['error'] ?? 'External partner rejected the transaction';
                return $this->respondWithError('EXTERNAL_TX_FAILED', $errorMsg, null, 422);
            }

            // External API succeeded — now deduct balance and record locally
            DB::update("UPDATE users SET balance = balance - ? WHERE id = ?", [$amount, $userId]);

            $externalTxId = $result['data']['transaction_id'] ?? null;

            // Store the remote receiver's real ID (not 0) for data integrity.
            // FK constraint dropped for federation support.
            DB::insert(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, is_federated, sender_tenant_id, receiver_tenant_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', 1, ?, ?, NOW())",
                [$tenantId, $userId, $realReceiverId, $amount, $description, $tenantId, $externalPartnerId]
            );
            $txId = (int) DB::getPdo()->lastInsertId();
            DB::commit();

            $this->federationAuditService->log('external_transaction_sent', $tenantId, null, $userId,
                ['transaction_id' => $txId, 'external_tx_id' => $externalTxId, 'partner_id' => $externalPartnerId, 'amount' => $amount]);

            return $this->respondWithData([
                'transaction_id' => $txId,
                'status' => 'completed',
                'amount' => $amount,
                'is_external' => true,
                'external_partner' => $partner['name'] ?? 'External Partner',
            ], null, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            error_log("FederationV2::sendExternalTransaction error: " . $e->getMessage());
            return $this->respondWithError('TRANSACTION_FAILED', 'Transaction failed: ' . $e->getMessage(), null, 500);
        }
    }
}
