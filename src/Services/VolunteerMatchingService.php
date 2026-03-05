<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VolunteerMatchingService - Skills-based volunteer-to-shift matching
 *
 * Extends the SmartMatchingEngine concept for volunteering:
 * - Matches volunteers to shifts based on required_skills
 * - Calculates match percentage per shift
 * - Provides "recommended shifts" for a user
 *
 * Scoring:
 *   50% - Skill match (user skills vs shift required_skills)
 *   25% - Proximity (if location available)
 *   15% - Availability (shift timing vs user's preferred times)
 *   10% - Quality signals (verified hours, reviews)
 */
class VolunteerMatchingService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Get recommended shifts for a user based on their skills
     *
     * @param int $userId User to find matches for
     * @param array $options [limit, min_match_score]
     * @return array Array of shifts with match scores
     */
    public static function getRecommendedShifts(int $userId, array $options = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $options['limit'] ?? 10;
        $minScore = $options['min_match_score'] ?? 20;

        // Get user's skills
        $userData = self::getUserVolunteerProfile($userId);
        if (!$userData) {
            return [];
        }

        $userSkills = SmartMatchingEngine::extractKeywords($userData['skills'] ?? '');

        // Also extract from their bio for broader matching
        $bioKeywords = SmartMatchingEngine::extractKeywords($userData['bio'] ?? '');
        $allUserSkills = array_unique(array_merge($userSkills, $bioKeywords));

        if (empty($allUserSkills)) {
            // No skills profile — return upcoming shifts sorted by date
            return self::getUpcomingShifts($tenantId, $userId, $limit);
        }

        // Get all upcoming shifts with required_skills
        $shifts = Database::query(
            "SELECT s.*, o.title as opp_title, o.description as opp_description,
                    o.location as opp_location, o.skills_needed as opp_skills,
                    org.name as org_name, org.logo_url as org_logo,
                    s.required_skills as shift_required_skills
             FROM vol_shifts s
             JOIN vol_opportunities o ON s.opportunity_id = o.id
             JOIN vol_organizations org ON o.organization_id = org.id
             WHERE org.tenant_id = ? AND o.tenant_id = ? AND s.tenant_id = ? AND o.is_active = 1 AND org.status = 'approved'
               AND s.start_time > NOW()
               AND s.id NOT IN (
                   SELECT shift_id FROM vol_applications WHERE user_id = ? AND shift_id IS NOT NULL AND tenant_id = ?
               )
             ORDER BY s.start_time ASC
             LIMIT 100",
            [$tenantId, $tenantId, $tenantId, $userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Score each shift
        $scoredShifts = [];
        foreach ($shifts as $shift) {
            $score = self::calculateMatchScore($allUserSkills, $userData, $shift);

            if ($score >= $minScore) {
                $scoredShifts[] = [
                    'shift' => [
                        'id' => (int)$shift['id'],
                        'start_time' => $shift['start_time'],
                        'end_time' => $shift['end_time'],
                        'capacity' => $shift['capacity'] ? (int)$shift['capacity'] : null,
                        'required_skills' => self::parseSkills($shift['shift_required_skills']),
                    ],
                    'opportunity' => [
                        'id' => (int)$shift['opportunity_id'],
                        'title' => $shift['opp_title'],
                        'location' => $shift['opp_location'],
                        'skills_needed' => $shift['opp_skills'],
                    ],
                    'organization' => [
                        'name' => $shift['org_name'],
                        'logo_url' => $shift['org_logo'],
                    ],
                    'match_score' => round($score),
                    'match_reasons' => self::getMatchReasons($allUserSkills, $shift),
                ];
            }
        }

        // Sort by score descending
        usort($scoredShifts, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return array_slice($scoredShifts, 0, $limit);
    }

    /**
     * Calculate match score for a shift
     *
     * @param array $userSkills User's extracted skill keywords
     * @param array $userData Full user data
     * @param array $shift Shift data with opportunity info
     * @return float Score 0-100
     */
    public static function calculateMatchScore(array $userSkills, array $userData, array $shift): float
    {
        $skillScore = self::calculateSkillMatchScore($userSkills, $shift);
        $proximityScore = self::calculateProximityScore($userData, $shift);
        $qualityScore = self::calculateQualityScore($userData);

        // Weighted combination
        $total = ($skillScore * 0.50) + ($proximityScore * 0.25) + ($qualityScore * 0.10);

        // Availability bonus: shifts happening soon get a small boost
        $timeBonus = self::calculateTimeBonus($shift);
        $total += $timeBonus * 0.15;

        return min(100, $total * 100);
    }

    /**
     * Calculate skill match score between user skills and shift requirements
     */
    private static function calculateSkillMatchScore(array $userSkills, array $shift): float
    {
        // Combine shift required_skills JSON with opportunity skills_needed text
        $shiftSkills = self::parseSkills($shift['shift_required_skills'] ?? null);
        $oppSkillKeywords = SmartMatchingEngine::extractKeywords($shift['opp_skills'] ?? '');
        $descKeywords = SmartMatchingEngine::extractKeywords($shift['opp_description'] ?? '');

        $requiredSkills = array_unique(array_merge($shiftSkills, $oppSkillKeywords, $descKeywords));

        if (empty($requiredSkills) || empty($userSkills)) {
            return 0.5; // Neutral when no skills to compare
        }

        // Count how many required skills the user has
        $matches = array_intersect(
            array_map('strtolower', $userSkills),
            array_map('strtolower', $requiredSkills)
        );

        $matchRatio = count($matches) / count($requiredSkills);

        return min(1.0, $matchRatio * 1.3); // Slight boost, cap at 1.0
    }

    /**
     * Calculate proximity score
     */
    private static function calculateProximityScore(array $userData, array $shift): float
    {
        $userLat = (float)($userData['latitude'] ?? 0);
        $userLon = (float)($userData['longitude'] ?? 0);

        if (!$userLat || !$userLon || empty($shift['opp_location'])) {
            return 0.5; // Neutral when no location data
        }

        // We don't have lat/lon for shifts, so partial matching on location text
        $userLocation = strtolower($userData['location'] ?? '');
        $shiftLocation = strtolower($shift['opp_location'] ?? '');

        if ($userLocation && $shiftLocation) {
            // Simple text similarity
            similar_text($userLocation, $shiftLocation, $percent);
            return $percent / 100;
        }

        return 0.5;
    }

    /**
     * Calculate quality score based on user's volunteering track record
     */
    private static function calculateQualityScore(array $userData): float
    {
        $score = 0.5; // Base

        // Verified hours boost
        $verifiedHours = (float)($userData['verified_hours'] ?? 0);
        if ($verifiedHours > 0) {
            $score += min(0.3, $verifiedHours / 100 * 0.3);
        }

        // Active in community
        if (!empty($userData['is_verified'])) {
            $score += 0.1;
        }

        // Good review average
        $avgRating = (float)($userData['avg_rating'] ?? 0);
        if ($avgRating >= 4.0) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate time bonus (shifts happening soon get priority)
     */
    private static function calculateTimeBonus(array $shift): float
    {
        $startTime = strtotime($shift['start_time'] ?? '');
        if (!$startTime) {
            return 0.5;
        }

        $hoursUntil = ($startTime - time()) / 3600;

        if ($hoursUntil < 24) {
            return 1.0; // Tomorrow or sooner
        }
        if ($hoursUntil < 72) {
            return 0.8; // Within 3 days
        }
        if ($hoursUntil < 168) {
            return 0.6; // Within a week
        }

        return 0.4; // Further out
    }

    /**
     * Get match reasons (human-readable explanation)
     */
    private static function getMatchReasons(array $userSkills, array $shift): array
    {
        $reasons = [];

        $shiftSkills = self::parseSkills($shift['shift_required_skills'] ?? null);
        $oppSkillKeywords = SmartMatchingEngine::extractKeywords($shift['opp_skills'] ?? '');
        $requiredSkills = array_unique(array_merge($shiftSkills, $oppSkillKeywords));

        if (!empty($requiredSkills)) {
            $matches = array_intersect(
                array_map('strtolower', $userSkills),
                array_map('strtolower', $requiredSkills)
            );
            if (!empty($matches)) {
                $reasons[] = 'Skills match: ' . implode(', ', array_slice(array_values($matches), 0, 3));
            }
        }

        $startTime = strtotime($shift['start_time'] ?? '');
        if ($startTime && ($startTime - time()) < 72 * 3600) {
            $reasons[] = 'Happening soon';
        }

        return $reasons;
    }

    /**
     * Get user's volunteer profile with skills and hours
     */
    private static function getUserVolunteerProfile(int $userId): ?array
    {
        $tenantId = TenantContext::getId();
        $result = Database::query(
            "SELECT u.id, u.name, u.skills, u.bio, u.location, u.latitude, u.longitude,
                    u.is_verified,
                    COALESCE((SELECT SUM(hours) FROM vol_logs WHERE user_id = u.id AND status = 'approved' AND tenant_id = ?), 0) as verified_hours,
                    COALESCE((SELECT AVG(rating) FROM vol_reviews WHERE target_type = 'user' AND target_id = u.id AND tenant_id = ?), 0) as avg_rating
             FROM users u
             WHERE u.id = ? AND u.tenant_id = ?",
            [$tenantId, $tenantId, $userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get upcoming shifts when user has no skills profile (fallback)
     */
    private static function getUpcomingShifts(int $tenantId, int $userId, int $limit): array
    {
        $shifts = Database::query(
            "SELECT s.*, o.title as opp_title, o.location as opp_location,
                    o.skills_needed as opp_skills,
                    org.name as org_name, org.logo_url as org_logo
             FROM vol_shifts s
             JOIN vol_opportunities o ON s.opportunity_id = o.id
             JOIN vol_organizations org ON o.organization_id = org.id
             WHERE org.tenant_id = ? AND o.tenant_id = ? AND s.tenant_id = ? AND o.is_active = 1 AND org.status = 'approved'
               AND s.start_time > NOW()
               AND s.id NOT IN (
                   SELECT shift_id FROM vol_applications WHERE user_id = ? AND shift_id IS NOT NULL AND tenant_id = ?
               )
             ORDER BY s.start_time ASC
             LIMIT ?",
            [$tenantId, $tenantId, $tenantId, $userId, $tenantId, (int)$limit]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($shift) {
            return [
                'shift' => [
                    'id' => (int)$shift['id'],
                    'start_time' => $shift['start_time'],
                    'end_time' => $shift['end_time'],
                    'capacity' => $shift['capacity'] ? (int)$shift['capacity'] : null,
                    'required_skills' => [],
                ],
                'opportunity' => [
                    'id' => (int)$shift['opportunity_id'],
                    'title' => $shift['opp_title'],
                    'location' => $shift['opp_location'],
                    'skills_needed' => $shift['opp_skills'],
                ],
                'organization' => [
                    'name' => $shift['org_name'],
                    'logo_url' => $shift['org_logo'],
                ],
                'match_score' => 50, // Neutral score
                'match_reasons' => ['Upcoming shift'],
            ];
        }, $shifts);
    }

    /**
     * Parse required_skills JSON column value
     *
     * @param string|null $json JSON string or null
     * @return array Array of skill strings
     */
    private static function parseSkills(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $skills = json_decode($json, true);
        if (!is_array($skills)) {
            return [];
        }

        return array_filter(array_map('trim', $skills));
    }
}
