<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Models\Group;
use Nexus\Core\Database;

class GroupAssignmentService
{
    private const CONFIDENCE_THRESHOLD = 98.0;

    /**
     * Attempts to assign a user to a Leaf Group based on their location.
     * Returns the result string for logging.
     */
    public function assignUser($user)
    {
        if (empty($user['location'])) {
            return "[SKIPPED] No location set for user {$user['id']}";
        }

        // 1. Get Leaf Groups
        $leafGroups = $this->getLeafGroups();
        if (empty($leafGroups)) {
            return "[SKIPPED] No leaf groups found in tenant";
        }

        // 2. Sanitize and parts
        $userLoc = $this->sanitize($user['location']);
        if (empty($userLoc)) {
            return "[SKIPPED] User location is empty after sanitization";
        }

        // Handle comma-separated locations (e.g. Town, County, Country)
        // We will try to find a match for ANY part of the address.
        // Original full string is also a candidate.
        $candidates = array_filter(explode(',', $user['location']));
        // Map candidates to sanitized versions
        $candidates = array_map([$this, 'sanitize'], $candidates);
        $candidates = array_filter($candidates); // remove empty

        $bestMatch = null;
        $bestPercent = 0.0;
        $matchedCandidate = '';

        // 3. Find Match
        foreach ($leafGroups as $group) {
            $groupName = $this->sanitize($group['name']);
            if (empty($groupName)) continue;

            // Compare against each part of the user's location
            foreach ($candidates as $part) {
                // Calculate similarity
                // similar_text calculates similarity in percentage
                $percent = 0.0;
                similar_text($part, $groupName, $percent);

                if ($percent > $bestPercent) {
                    $bestPercent = $percent;
                    $bestMatch = $group;
                    $matchedCandidate = $part;
                }
            }
        }

        // 4. Validate Threshold
        if ($bestPercent >= self::CONFIDENCE_THRESHOLD) {
            // Assign!
            try {
                // Check if already member
                if (!Group::isMember($bestMatch['id'], $user['id'])) {
                    Group::join($bestMatch['id'], $user['id']);
                    // Set role to member explicitly just in case
                    Group::updateMemberRole($bestMatch['id'], $user['id'], 'member');

                    return "[ADDED] User {$user['id']} (Match: '{$matchedCandidate}' vs '{$bestMatch['name']}' " . number_format($bestPercent, 2) . "%) -> Group '{$bestMatch['name']}'";
                } else {
                    return "[SKIPPED] User {$user['id']} is already in '{$bestMatch['name']}'";
                }
            } catch (\Exception $e) {
                return "[ERROR] Failed to join group: " . $e->getMessage();
            }
        }

        return "[SKIPPED - LOW CONFIDENCE] User location '{$user['location']}' vs Best Match '{$bestMatch['name']}' (" . number_format($bestPercent, 2) . "%)";
    }

    private function getLeafGroups()
    {
        // A Leaf Group is defined as a hub-type group that has NO children.
        // Only auto-join users to geographic hubs, not regular community groups.
        $hubType = \Nexus\Models\GroupType::getHubType();
        if (!$hubType) {
            return []; // No hub type configured
        }

        $tenantId = \Nexus\Core\TenantContext::getId();
        $sql = "SELECT * FROM `groups`
                WHERE tenant_id = ?
                AND type_id = ?
                AND id NOT IN (
                    SELECT parent_id FROM `groups` WHERE tenant_id = ? AND parent_id IS NOT NULL
                )";
        return Database::query($sql, [$tenantId, $hubType['id'], $tenantId])->fetchAll();
    }

    private function sanitize($text)
    {
        $text = strtolower($text);
        // Remove common administrative noise
        $noise = ['county', 'co.', 'ireland', 'eire', 'group', 'the', 'town', 'city'];
        $text = str_replace($noise, '', $text);
        // Remove non-alphanumeric (except spaces)
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        return trim($text);
    }
}
