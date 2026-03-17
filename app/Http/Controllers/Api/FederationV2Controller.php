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
use Nexus\Services\FederatedConnectionService;
use Nexus\Services\FederationEmailService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationRealtimeService;
use Nexus\Models\Notification;

/**
 * FederationV2Controller -- Federation v2: cross-tenant discovery, messaging, connections.
 *
 * All methods migrated from delegation to direct service/DB calls.
 */
class FederationV2Controller extends BaseApiController
{
    protected bool $isV2Api = true;

    private const LEVEL_NAMES = [
        1 => 'Discovery',
        2 => 'Social',
        3 => 'Economic',
        4 => 'Integrated',
    ];

    public function __construct() {}

    // =====================================================================
    // STATUS & OPT-IN/OUT
    // =====================================================================

    /** GET /api/v2/federation/status */
    public function status(): JsonResponse
    {
        $userId = $this->getUserId();
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

    /** POST /api/v2/federation/opt-in */
    public function optIn(): JsonResponse
    {
        $userId = $this->getUserId();
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
        }

        return $this->respondWithError('OPT_IN_FAILED', 'Failed to enable federation. Please try again.', null, 500);
    }

    /** POST /api/v2/federation/setup */
    public function setup(): JsonResponse
    {
        $userId = $this->getUserId();
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
        }

        return $this->respondWithError('SETUP_FAILED', 'Failed to enable federation. Please try again.', null, 500);
    }

    /** POST /api/v2/federation/opt-out */
    public function optOut(): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $success = FederationUserService::optOut($userId);

        if ($success) {
            FederationAuditService::log('user_federation_optout', $tenantId, null, $userId, [], FederationAuditService::LEVEL_INFO);
            return $this->respondWithData(['success' => true, 'message' => 'Federation disabled successfully.']);
        }

        return $this->respondWithError('OPT_OUT_FAILED', 'Failed to disable federation. Please try again.', null, 500);
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
            $partnerships = Database::query("
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
                    CASE WHEN fp.tenant_id = ? THEN dp2.member_count ELSE dp1.member_count END as partner_member_count
                FROM federation_partnerships fp
                LEFT JOIN tenants t1 ON fp.tenant_id = t1.id
                LEFT JOIN tenants t2 ON fp.partner_tenant_id = t2.id
                LEFT JOIN federation_directory_profiles dp1 ON dp1.tenant_id = fp.tenant_id
                LEFT JOIN federation_directory_profiles dp2 ON dp2.tenant_id = fp.partner_tenant_id
                WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?) AND fp.status = 'active'
                ORDER BY partner_name ASC
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

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

    // =====================================================================
    // ACTIVITY
    // =====================================================================

    /** GET /api/v2/federation/activity */
    public function activity(): JsonResponse
    {
        $userId = $this->getUserId();

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
    // FEDERATED EVENTS
    // =====================================================================

    /** GET /api/v2/federation/events */
    public function events(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();

        $q = $this->query('q', '');
        $partnerId = $this->queryInt('partner_id');
        $upcoming = $this->queryBool('upcoming', false);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

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

            $stmt = Database::getInstance()->prepare($sql);
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
        $partnerId = $this->queryInt('partner_id');
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

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

            $stmt = Database::getInstance()->prepare($sql);
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

            return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            error_log("FederationV2Api::listings error: " . $e->getMessage());
            return $this->respondWithCollection([], null, $perPage, false);
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
        $partnerId = $this->queryInt('partner_id');
        $serviceReach = $this->query('service_reach', '');
        $skills = $this->query('skills', '');
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $limit = $this->queryInt('limit');
        if ($limit && $limit < $perPage) {
            $perPage = $limit;
        }
        $cursorParam = $this->query('cursor');
        $cursorId = $cursorParam ? $this->decodeCursor($cursorParam) : null;

        try {
            $sql = "
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.bio, u.skills,
                    u.location, u.tenant_id, t.name as tenant_name,
                    fus.service_reach, fus.messaging_enabled_federated
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
            $params = [':tid1' => $tenantId, ':tid2' => $tenantId, ':tid3' => $tenantId];

            if (!empty($q)) {
                $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR u.skills LIKE :q3 OR u.bio LIKE :q4)";
                $searchTerm = "%{$q}%";
                $params[':q1'] = $searchTerm;
                $params[':q2'] = $searchTerm;
                $params[':q3'] = $searchTerm;
                $params[':q4'] = $searchTerm;
            }
            if ($partnerId) {
                $sql .= " AND u.tenant_id = :partner_id";
                $params[':partner_id'] = $partnerId;
            }
            if (!empty($serviceReach) && in_array($serviceReach, ['local_only', 'remote_ok', 'travel_ok'])) {
                if ($serviceReach === 'remote_ok') {
                    $sql .= " AND fus.service_reach IN ('remote_ok', 'travel_ok')";
                } else {
                    $sql .= " AND fus.service_reach = :service_reach";
                    $params[':service_reach'] = $serviceReach;
                }
            }
            if (!empty($skills)) {
                $skillList = array_map('trim', explode(',', $skills));
                $skillIdx = 0;
                foreach ($skillList as $skill) {
                    if (!empty($skill)) {
                        $paramName = ":skill{$skillIdx}";
                        $sql .= " AND u.skills LIKE {$paramName}";
                        $params[$paramName] = "%{$skill}%";
                        $skillIdx++;
                    }
                }
            }
            if ($cursorId) {
                $sql .= " AND u.id < :cursor_id";
                $params[':cursor_id'] = (int) $cursorId;
            }

            $sql .= " ORDER BY u.id DESC LIMIT :limit";
            $params[':limit'] = $perPage + 1;

            $stmt = Database::getInstance()->prepare($sql);
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
                    'skills' => !empty($m['skills']) ? array_map('trim', explode(',', $m['skills'])) : [],
                    'location' => $m['location'] ?? null,
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

            return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            error_log("FederationV2Api::members error: " . $e->getMessage());
            return $this->respondWithCollection([], null, $perPage, false);
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

            $stmt = Database::getInstance()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $m = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$m) {
                return $this->respondWithError('MEMBER_NOT_FOUND', 'Federated member not found or not accessible.', null, 404);
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
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to load member profile.', null, 500);
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
            $rows = Database::query("
                SELECT fm.id, fm.subject, fm.body, fm.direction, fm.status, fm.read_at,
                    fm.created_at, fm.sender_tenant_id, fm.sender_user_id,
                    fm.receiver_tenant_id, fm.receiver_user_id, fm.reference_message_id,
                    COALESCE(su.first_name, '') as sender_first_name,
                    COALESCE(su.last_name, '') as sender_last_name,
                    su.avatar_url as sender_avatar, st.name as sender_tenant_name,
                    COALESCE(ru.first_name, '') as receiver_first_name,
                    COALESCE(ru.last_name, '') as receiver_last_name,
                    ru.avatar_url as receiver_avatar, rt.name as receiver_tenant_name
                FROM federation_messages fm
                LEFT JOIN users su ON su.id = fm.sender_user_id
                LEFT JOIN tenants st ON st.id = fm.sender_tenant_id
                LEFT JOIN users ru ON ru.id = fm.receiver_user_id
                LEFT JOIN tenants rt ON rt.id = fm.receiver_tenant_id
                WHERE (
                    (fm.sender_tenant_id = ? AND fm.sender_user_id = ?)
                    OR (fm.receiver_tenant_id = ? AND fm.receiver_user_id = ?)
                )
                ORDER BY fm.created_at DESC LIMIT 200
            ", [$tenantId, $userId, $tenantId, $userId])->fetchAll(\PDO::FETCH_ASSOC);

            $formatted = array_map(function ($msg) {
                return [
                    'id' => (int) $msg['id'],
                    'subject' => $msg['subject'] ?? '',
                    'body' => $msg['body'],
                    'direction' => $msg['direction'] === 'outbound' ? 'outbound' : 'inbound',
                    'status' => $this->mapMessageStatus($msg['status'] ?? 'delivered'),
                    'read_at' => $msg['read_at'] ?: null,
                    'created_at' => $msg['created_at'],
                    'sender' => [
                        'id' => (int) $msg['sender_user_id'],
                        'name' => trim($msg['sender_first_name'] . ' ' . $msg['sender_last_name']),
                        'avatar' => $msg['sender_avatar'] ?: null,
                        'tenant_id' => (int) $msg['sender_tenant_id'],
                        'tenant_name' => $msg['sender_tenant_name'] ?? '',
                    ],
                    'receiver' => [
                        'id' => (int) $msg['receiver_user_id'],
                        'name' => trim($msg['receiver_first_name'] . ' ' . $msg['receiver_last_name']),
                        'avatar' => $msg['receiver_avatar'] ?: null,
                        'tenant_id' => (int) $msg['receiver_tenant_id'],
                        'tenant_name' => $msg['receiver_tenant_name'] ?? '',
                    ],
                    'reference_message_id' => $msg['reference_message_id'] ? (int) $msg['reference_message_id'] : null,
                ];
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

        // Validate required fields
        $errors = [];
        if (empty($receiverId)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'receiver_id is required.', 'field' => 'receiver_id'];
        }
        if (empty($receiverTenantId)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'receiver_tenant_id is required.', 'field' => 'receiver_tenant_id'];
        }
        if (empty($body)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message body is required.', 'field' => 'body'];
        }
        if (!empty($errors)) {
            return $this->respondWithErrors($errors);
        }

        try {
            // Verify the receiver exists and accepts federated messages
            $receiver = Database::query("
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.tenant_id,
                       fus.messaging_enabled_federated, fus.federation_optin,
                       t.name as tenant_name
                FROM users u
                JOIN federation_user_settings fus ON fus.user_id = u.id
                JOIN tenants t ON t.id = u.tenant_id
                WHERE u.id = ? AND u.tenant_id = ? AND u.status = 'active'
            ", [(int)$receiverId, (int)$receiverTenantId])->fetch(\PDO::FETCH_ASSOC);

            if (!$receiver) {
                return $this->respondWithError('RECIPIENT_NOT_FOUND', 'Recipient not found.', null, 404);
            }

            if (!$receiver['federation_optin'] || !$receiver['messaging_enabled_federated']) {
                return $this->respondWithError('MESSAGING_DISABLED', 'This member does not accept federated messages.', null, 403);
            }

            // Verify an active partnership exists between the two tenants
            $partnership = FederationPartnershipService::getPartnership($tenantId, (int)$receiverTenantId);
            if (!$partnership || $partnership['status'] !== 'active') {
                return $this->respondWithError('NO_PARTNERSHIP', 'No active partnership with the recipient\'s community.', null, 403);
            }

            if (!($partnership['messaging_enabled'] ?? false)) {
                return $this->respondWithError('MESSAGING_NOT_ALLOWED', 'Messaging is not enabled for this partnership.', null, 403);
            }

            // Get sender info
            $sender = Database::query("
                SELECT u.first_name, u.last_name, u.avatar_url, t.name as tenant_name
                FROM users u
                JOIN tenants t ON t.id = u.tenant_id
                WHERE u.id = ?
            ", [$userId])->fetch(\PDO::FETCH_ASSOC);

            $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

            // Insert outbound message (sender's copy)
            Database::query("
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
            $outboundId = (int)Database::lastInsertId();

            // Insert inbound message (receiver's copy)
            Database::query("
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
            FederationAuditService::log(
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
                FederationEmailService::sendNewMessageNotification(
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
                FederationRealtimeService::broadcastNewMessage(
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
                $notifMessage = "New federated message from {$senderName} ({$senderTenantName}): " . substr($subject ?: '(no subject)', 0, 50);
                if (strlen($subject) > 50) {
                    $notifMessage .= '...';
                }

                Notification::create(
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
            return $this->respondWithError('SEND_FAILED', 'Failed to send message. Please try again.', null, 500);
        }
    }

    /** POST /api/v2/federation/messages/{id}/read */
    public function markMessageRead(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        try {
            $message = Database::query("
                SELECT id, status FROM federation_messages
                WHERE id = ? AND receiver_tenant_id = ? AND receiver_user_id = ?
                AND direction = 'inbound'
            ", [$id, $tenantId, $userId])->fetch(\PDO::FETCH_ASSOC);

            if (!$message) {
                return $this->respondWithError('MESSAGE_NOT_FOUND', 'Message not found.', null, 404);
            }

            if ($message['status'] !== 'read') {
                Database::query(
                    "UPDATE federation_messages SET status = 'read', read_at = NOW() WHERE id = ?",
                    [$id]
                );
            }

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            error_log("FederationV2Api::markMessageRead error: " . $e->getMessage());
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to mark message as read.', null, 500);
        }
    }

    // =====================================================================
    // SETTINGS
    // =====================================================================

    /** GET /api/v2/federation/settings */
    public function getSettings(): JsonResponse
    {
        $userId = $this->getUserId();

        $userSettings = FederationUserService::getUserSettings($userId);

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

        $current = FederationUserService::getUserSettings($userId);
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

        $success = FederationUserService::updateSettings($userId, $settings);

        if ($success) {
            return $this->respondWithData(['success' => true, 'message' => 'Settings updated successfully.']);
        }

        return $this->respondWithError('UPDATE_FAILED', 'Failed to update settings. Please try again.', null, 500);
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

        $connections = FederatedConnectionService::getConnections($userId, $status, $limit, $offset);
        $pendingCount = FederatedConnectionService::getPendingCount($userId);

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
            return $this->respondWithError('VALIDATION_ERROR', 'receiver_id and receiver_tenant_id are required.', null, 400);
        }

        $result = FederatedConnectionService::sendRequest($userId, $receiverId, $receiverTenantId, $message);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result, null, 201);
    }

    /** POST /api/v2/federation/connections/{id}/accept */
    public function acceptConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = FederatedConnectionService::acceptRequest($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/federation/connections/{id}/reject */
    public function rejectConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = FederatedConnectionService::rejectRequest($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData($result);
    }

    /** DELETE /api/v2/federation/connections/{id} */
    public function removeConnection(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $result = FederatedConnectionService::removeConnection($id, $userId);

        if (!$result['success']) {
            return $this->respondWithError('CONNECTION_ERROR', $result['error'], null, 404);
        }

        return $this->respondWithData($result);
    }

    /** GET /api/v2/federation/connections/status/{userId}/{tenantId} */
    public function connectionStatus($userId, $tenantId): JsonResponse
    {
        $currentUserId = $this->getUserId();
        $status = FederatedConnectionService::getStatus($currentUserId, $userId, $tenantId);
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
            'failed' => 'delivered',
        ];
        return $map[$dbStatus] ?? 'delivered';
    }
}
