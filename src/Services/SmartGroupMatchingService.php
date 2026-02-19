<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Models\Group;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Smart Group Matching Service
 *
 * Intelligently assigns users to groups using multiple strategies:
 * 1. Geographic distance (most accurate - uses lat/lng coordinates)
 * 2. Text matching (fallback - uses fuzzy string matching)
 * 3. Parent group cascade (automatically adds to parent groups)
 */
class SmartGroupMatchingService
{
    private const DISTANCE_THRESHOLD_KM = 50; // Maximum distance for auto-assignment
    private const TEXT_CONFIDENCE_THRESHOLD = 90.0; // Minimum similarity percentage for text matching

    /**
     * Assign a single user to appropriate groups
     *
     * @param array $user User record with id, location, latitude, longitude
     * @return array ['success' => bool, 'message' => string, 'groups' => array]
     */
    public function assignUser($user)
    {
        $result = [
            'success' => false,
            'message' => '',
            'groups' => [],
            'method' => null
        ];

        if (empty($user['location']) && (empty($user['latitude']) || empty($user['longitude']))) {
            $result['message'] = "User {$user['id']} has no location data";
            return $result;
        }

        // Strategy 1: Geographic distance (preferred)
        if (!empty($user['latitude']) && !empty($user['longitude'])) {
            $geoResult = $this->assignByDistance($user);
            if ($geoResult['success']) {
                return $geoResult;
            }
        }

        // Strategy 2: Text matching (fallback)
        if (!empty($user['location'])) {
            return $this->assignByTextMatch($user);
        }

        $result['message'] = "Could not assign user {$user['id']} - insufficient location data";
        return $result;
    }

    /**
     * Assign user based on geographic distance
     */
    private function assignByDistance($user)
    {
        $result = [
            'success' => false,
            'message' => '',
            'groups' => [],
            'method' => 'geographic'
        ];

        $tenantId = TenantContext::getId();

        // Find nearest bottom-level group with coordinates
        $nearest = Database::query("
            SELECT
                g.id,
                g.name,
                g.latitude,
                g.longitude,
                (6371 * acos(
                    cos(radians(?)) *
                    cos(radians(g.latitude)) *
                    cos(radians(g.longitude) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(g.latitude))
                )) AS distance_km
            FROM `groups` g
            WHERE g.tenant_id = ?
            AND g.type_id = (SELECT id FROM group_types WHERE tenant_id = ? AND name LIKE '%hub%' LIMIT 1)
            AND (g.visibility IS NULL OR g.visibility = 'public')
            AND g.latitude IS NOT NULL
            AND g.longitude IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
            ORDER BY distance_km ASC
            LIMIT 1
        ", [
            $user['latitude'],
            $user['longitude'],
            $user['latitude'],
            $tenantId,
            $tenantId
        ])->fetch();

        if (!$nearest) {
            $result['message'] = "No groups with coordinates found";
            return $result;
        }

        $distance = round($nearest['distance_km'], 2);

        // Check distance threshold
        if ($distance > self::DISTANCE_THRESHOLD_KM) {
            $result['message'] = "Nearest group '{$nearest['name']}' is {$distance}km away (threshold: " . self::DISTANCE_THRESHOLD_KM . "km)";
            return $result;
        }

        // Assign to the group
        try {
            if (!Group::isMember($nearest['id'], $user['id'])) {
                Group::join($nearest['id'], $user['id']);
                $result['groups'][] = [
                    'id' => $nearest['id'],
                    'name' => $nearest['name'],
                    'distance_km' => $distance
                ];

                // Cascade to parent groups
                $cascaded = $this->cascadeToParents($user['id'], $nearest['id']);
                $result['groups'] = array_merge($result['groups'], $cascaded);

                $result['success'] = true;
                $result['message'] = "Assigned to '{$nearest['name']}' ({$distance}km away) + " . count($cascaded) . " parent groups";
            } else {
                $result['message'] = "User already in '{$nearest['name']}'";
            }
        } catch (\Exception $e) {
            $result['message'] = "Error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Assign user based on text matching (fallback)
     */
    private function assignByTextMatch($user)
    {
        $result = [
            'success' => false,
            'message' => '',
            'groups' => [],
            'method' => 'text_matching'
        ];

        $leafGroups = $this->getLeafGroups();
        if (empty($leafGroups)) {
            $result['message'] = "No leaf groups found";
            return $result;
        }

        $userLoc = $this->sanitize($user['location']);
        $candidates = array_filter(array_map([$this, 'sanitize'], explode(',', $user['location'])));

        $bestMatch = null;
        $bestPercent = 0.0;
        $matchedCandidate = '';

        foreach ($leafGroups as $group) {
            $groupName = $this->sanitize($group['name']);
            if (empty($groupName)) continue;

            foreach ($candidates as $part) {
                $percent = 0.0;
                similar_text($part, $groupName, $percent);

                if ($percent > $bestPercent) {
                    $bestPercent = $percent;
                    $bestMatch = $group;
                    $matchedCandidate = $part;
                }
            }
        }

        if ($bestPercent >= self::TEXT_CONFIDENCE_THRESHOLD && $bestMatch) {
            try {
                if (!Group::isMember($bestMatch['id'], $user['id'])) {
                    Group::join($bestMatch['id'], $user['id']);
                    $result['groups'][] = [
                        'id' => $bestMatch['id'],
                        'name' => $bestMatch['name'],
                        'confidence' => round($bestPercent, 2)
                    ];

                    // Cascade to parent groups
                    $cascaded = $this->cascadeToParents($user['id'], $bestMatch['id']);
                    $result['groups'] = array_merge($result['groups'], $cascaded);

                    $result['success'] = true;
                    $result['message'] = "Matched '{$matchedCandidate}' to '{$bestMatch['name']}' (" . round($bestPercent, 2) . "% confidence) + " . count($cascaded) . " parent groups";
                } else {
                    $result['message'] = "User already in '{$bestMatch['name']}'";
                }
            } catch (\Exception $e) {
                $result['message'] = "Error: " . $e->getMessage();
            }
        } else {
            $result['message'] = "No confident match (best: " . round($bestPercent, 2) . "%)";
        }

        return $result;
    }

    /**
     * Cascade membership to all parent groups
     */
    private function cascadeToParents($userId, $groupId)
    {
        $cascaded = [];
        $tenantId = TenantContext::getId();

        // Get parent
        $parent = Database::query("
            SELECT parent_id, (SELECT name FROM `groups` WHERE id = parent_id) as parent_name
            FROM `groups`
            WHERE id = ?
            AND tenant_id = ?
            AND parent_id IS NOT NULL
        ", [$groupId, $tenantId])->fetch();

        while ($parent && $parent['parent_id']) {
            try {
                if (!Group::isMember($parent['parent_id'], $userId)) {
                    Group::join($parent['parent_id'], $userId);
                    $cascaded[] = [
                        'id' => $parent['parent_id'],
                        'name' => $parent['parent_name'],
                        'type' => 'parent'
                    ];
                }

                // Get next parent
                $parent = Database::query("
                    SELECT parent_id, (SELECT name FROM `groups` WHERE id = parent_id) as parent_name
                    FROM `groups`
                    WHERE id = ?
                    AND tenant_id = ?
                    AND parent_id IS NOT NULL
                ", [$parent['parent_id'], $tenantId])->fetch();
            } catch (\Exception $e) {
                error_log("Cascade error: " . $e->getMessage());
                break;
            }
        }

        return $cascaded;
    }

    /**
     * Get all leaf (bottom-level) groups
     */
    private function getLeafGroups()
    {
        $tenantId = TenantContext::getId();
        return Database::query("
            SELECT g.*
            FROM `groups` g
            WHERE g.tenant_id = ?
            AND g.type_id = (SELECT id FROM group_types WHERE tenant_id = ? AND name LIKE '%hub%' LIMIT 1)
            AND (g.visibility IS NULL OR g.visibility = 'public')
            AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
            ORDER BY g.name
        ", [$tenantId, $tenantId])->fetchAll();
    }

    /**
     * Sanitize text for comparison
     */
    private function sanitize($text)
    {
        $text = strtolower($text);
        $noise = ['county', 'co.', 'ireland', 'eire', 'group', 'the', 'town', 'city'];
        $text = str_replace($noise, '', $text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        return trim($text);
    }
}
