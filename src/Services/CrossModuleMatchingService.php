<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * CrossModuleMatchingService - Unified matching across ALL modules
 *
 * Matches users across:
 * - Listings (skills offered vs needed)
 * - Jobs (qualifications)
 * - Volunteering (skills + availability)
 * - Groups (shared interests)
 *
 * Produces a unified match score and returns ranked results with source type.
 *
 * API: GET /api/v2/matches/all
 */
class CrossModuleMatchingService
{
    /**
     * Get all matches for a user across all modules
     *
     * @param int $userId
     * @param array $options ['limit', 'min_score', 'modules']
     * @return array Ranked matches from all modules with source type
     */
    public static function getAllMatches(int $userId, array $options = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min($options['limit'] ?? 20, 100);
        $minScore = $options['min_score'] ?? 30;
        $modules = $options['modules'] ?? ['listings', 'jobs', 'volunteering', 'groups'];
        $debugMode = !empty($options['debug']);

        $allMatches = [];

        // Get user profile for matching
        $userProfile = self::getUserProfile($userId, $tenantId);
        if (!$userProfile) {
            return ['matches' => [], 'total' => 0];
        }

        // Match across each enabled module
        if (in_array('listings', $modules)) {
            $listingMatches = self::matchListings($userId, $userProfile, $tenantId, $limit, $debugMode);
            $allMatches = array_merge($allMatches, $listingMatches);
        }

        if (in_array('jobs', $modules)) {
            $jobMatches = self::matchJobs($userId, $userProfile, $tenantId, $limit);
            $allMatches = array_merge($allMatches, $jobMatches);
        }

        if (in_array('volunteering', $modules)) {
            $volMatches = self::matchVolunteering($userId, $userProfile, $tenantId, $limit);
            $allMatches = array_merge($allMatches, $volMatches);
        }

        if (in_array('groups', $modules)) {
            $groupMatches = self::matchGroups($userId, $userProfile, $tenantId, $limit);
            $allMatches = array_merge($allMatches, $groupMatches);
        }

        // Filter by minimum score
        $allMatches = array_filter($allMatches, function ($m) use ($minScore) {
            return $m['score'] >= $minScore;
        });

        // Sort by score descending
        usort($allMatches, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Limit total results
        $allMatches = array_slice(array_values($allMatches), 0, $limit);

        return [
            'matches' => $allMatches,
            'total' => count($allMatches),
        ];
    }

    /**
     * Get user profile data for matching
     */
    private static function getUserProfile(int $userId, int $tenantId): ?array
    {
        try {
            $stmt = Database::query(
                "SELECT u.id, u.skills, u.bio, u.location, u.latitude, u.longitude,
                        u.availability, u.interests
                 FROM users u
                 WHERE u.id = ? AND u.tenant_id = ? AND u.status = 'active'",
                [$userId, $tenantId]
            );
            $profile = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$profile) {
                return null;
            }

            // Parse skills into array
            $profile['skills_array'] = !empty($profile['skills'])
                ? array_map('trim', array_map('strtolower', explode(',', $profile['skills'])))
                : [];

            // Parse interests into array
            $profile['interests_array'] = !empty($profile['interests'])
                ? array_map('trim', array_map('strtolower', explode(',', $profile['interests'])))
                : [];

            return $profile;
        } catch (\Exception $e) {
            error_log("CrossModuleMatchingService::getUserProfile error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Match against listings (skills offered vs needed)
     */
    private static function matchListings(int $userId, array $profile, int $tenantId, int $limit, bool $debugMode = false): array
    {
        $matches = [];
        $userSkills = $profile['skills_array'];

        if (empty($userSkills)) {
            return $matches;
        }

        try {
            // Find listings that need skills the user has (offers) and vice versa
            $limitInt = (int)$limit;
            $stmt = Database::query(
                "SELECT l.id, l.title, l.description, l.type, l.category_id,
                        l.location, l.latitude, l.longitude,
                        u.id as owner_id, u.name as owner_name, u.avatar_url as owner_avatar,
                        u.skills as owner_skills,
                        c.name as category_name
                 FROM listings l
                 JOIN users u ON l.user_id = u.id
                 LEFT JOIN categories c ON l.category_id = c.id
                 WHERE l.tenant_id = ? AND l.user_id != ? AND l.status = 'active'
                 ORDER BY l.created_at DESC
                 LIMIT {$limitInt}",
                [$tenantId, $userId]
            );

            $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($listings as $listing) {
                $score = self::calculateListingScore($profile, $listing);
                if ($score > 0) {
                    $matchItem = [
                        'id' => (int)$listing['id'],
                        'source_type' => 'listing',
                        'source_id' => (int)$listing['id'],
                        'title' => $listing['title'],
                        'description' => mb_substr($listing['description'] ?? '', 0, 200),
                        'type' => $listing['type'] ?? 'offer',
                        'category' => $listing['category_name'] ?? null,
                        'match_score' => $score,
                        'score' => $score,
                        'reasons' => self::getListingMatchReasons($profile, $listing),
                        'matched_user' => [
                            'id' => (int)$listing['owner_id'],
                            'name' => $listing['owner_name'],
                            'avatar_url' => $listing['owner_avatar'],
                        ],
                        'matched_at' => date('Y-m-d\TH:i:s\Z'),
                        'location' => $listing['location'] ?? null,
                        'distance_km' => self::calculateDistance(
                            $profile['latitude'] ?? null,
                            $profile['longitude'] ?? null,
                            $listing['latitude'] ?? null,
                            $listing['longitude'] ?? null
                        ),
                    ];
                    if ($debugMode) {
                        $matchItem['_debug_scores'] = self::getListingDebugScores($profile, $listing);
                    }
                    $matches[] = $matchItem;
                }
            }
        } catch (\Exception $e) {
            error_log("CrossModuleMatchingService::matchListings error: " . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Match against jobs (qualifications matching)
     */
    private static function matchJobs(int $userId, array $profile, int $tenantId, int $limit): array
    {
        $matches = [];
        $userSkills = $profile['skills_array'];

        try {
            $limitInt = (int)$limit;
            $stmt = Database::query(
                "SELECT j.id, j.title, j.description, j.location,
                        COALESCE(j.latitude, NULL) AS latitude,
                        COALESCE(j.longitude, NULL) AS longitude,
                        j.skills_required, j.organization_id, o.name as org_name
                 FROM job_vacancies j
                 LEFT JOIN organizations o ON j.organization_id = o.id
                 WHERE j.tenant_id = ? AND j.status = 'open'
                   AND (j.deadline IS NULL OR j.deadline >= CURDATE())
                 ORDER BY j.created_at DESC
                 LIMIT {$limitInt}",
                [$tenantId]
            );

            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($jobs as $job) {
                $requiredSkills = !empty($job['skills_required'])
                    ? array_map('trim', array_map('strtolower', explode(',', $job['skills_required'])))
                    : [];

                $score = self::calculateSkillOverlapScore($userSkills, $requiredSkills);

                // Boost for location match — prefer haversine over string comparison
                $profileLat = isset($profile['latitude']) ? (float)$profile['latitude'] : null;
                $profileLon = isset($profile['longitude']) ? (float)$profile['longitude'] : null;
                $itemLat    = isset($job['latitude']) ? (float)$job['latitude'] : null;
                $itemLon    = isset($job['longitude']) ? (float)$job['longitude'] : null;

                if ($profileLat && $profileLon && $itemLat && $itemLon) {
                    $earthR = 6371;
                    $dLat   = deg2rad($itemLat - $profileLat);
                    $dLon   = deg2rad($itemLon - $profileLon);
                    $a      = sin($dLat / 2) * sin($dLat / 2)
                            + cos(deg2rad($profileLat)) * cos(deg2rad($itemLat))
                            * sin($dLon / 2) * sin($dLon / 2);
                    $distKm = $earthR * 2 * atan2(sqrt($a), sqrt(1 - $a));
                    if ($distKm < 10) {
                        $score = min(100, $score + 20);
                    } elseif ($distKm < 25) {
                        $score = min(100, $score + 12);
                    } elseif ($distKm < 50) {
                        $score = min(100, $score + 6);
                    }
                } elseif (!empty($profile['location']) && !empty($job['location'])) {
                    // Fallback to string similarity if no coordinates
                    similar_text(strtolower($profile['location']), strtolower($job['location']), $pct);
                    if ($pct > 70) {
                        $score = min(100, $score + 15);
                    } elseif ($pct > 40) {
                        $score = min(100, $score + 7);
                    }
                }

                if ($score > 0) {
                    $matches[] = [
                        'id' => (int)$job['id'],
                        'source_type' => 'job',
                        'source_id' => (int)$job['id'],
                        'title' => $job['title'],
                        'description' => mb_substr($job['description'] ?? '', 0, 200),
                        'type' => 'job',
                        'category' => null,
                        'match_score' => $score,
                        'score' => $score,
                        'reasons' => self::getSkillMatchReasons($userSkills, $requiredSkills, 'qualification'),
                        'matched_user' => null,
                        'matched_at' => date('Y-m-d\TH:i:s\Z'),
                        'organization' => $job['org_name'] ?? null,
                        'location' => $job['location'] ?? null,
                        'distance_km' => ($profileLat && $profileLon && $itemLat && $itemLon)
                            ? self::calculateDistance($profileLat, $profileLon, $itemLat, $itemLon)
                            : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("CrossModuleMatchingService::matchJobs error: " . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Match against volunteering opportunities
     */
    private static function matchVolunteering(int $userId, array $profile, int $tenantId, int $limit): array
    {
        $matches = [];
        $userSkills = $profile['skills_array'];

        try {
            $limitInt = (int)$limit;
            $stmt = Database::query(
                "SELECT v.id, v.title, v.description, v.location,
                        COALESCE(v.latitude, NULL) AS latitude,
                        COALESCE(v.longitude, NULL) AS longitude,
                        v.skills_needed, v.start_date, v.end_date,
                        o.name as org_name
                 FROM vol_opportunities v
                 LEFT JOIN vol_organizations o ON v.organization_id = o.id
                 WHERE v.tenant_id = ? AND v.status = 'open' AND v.is_active = 1
                   AND (v.end_date IS NULL OR v.end_date >= CURDATE())
                 ORDER BY v.start_date ASC
                 LIMIT {$limitInt}",
                [$tenantId]
            );

            $opportunities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($opportunities as $vol) {
                $neededSkills = !empty($vol['skills_needed'])
                    ? array_map('trim', array_map('strtolower', explode(',', $vol['skills_needed'])))
                    : [];

                $score = self::calculateSkillOverlapScore($userSkills, $neededSkills);

                // Boost for location match — prefer haversine over string comparison
                $profileLat = isset($profile['latitude']) ? (float)$profile['latitude'] : null;
                $profileLon = isset($profile['longitude']) ? (float)$profile['longitude'] : null;
                $itemLat    = isset($vol['latitude']) ? (float)$vol['latitude'] : null;
                $itemLon    = isset($vol['longitude']) ? (float)$vol['longitude'] : null;

                if ($profileLat && $profileLon && $itemLat && $itemLon) {
                    $earthR = 6371;
                    $dLat   = deg2rad($itemLat - $profileLat);
                    $dLon   = deg2rad($itemLon - $profileLon);
                    $a      = sin($dLat / 2) * sin($dLat / 2)
                            + cos(deg2rad($profileLat)) * cos(deg2rad($itemLat))
                            * sin($dLon / 2) * sin($dLon / 2);
                    $distKm = $earthR * 2 * atan2(sqrt($a), sqrt(1 - $a));
                    if ($distKm < 10) {
                        $score = min(100, $score + 20);
                    } elseif ($distKm < 25) {
                        $score = min(100, $score + 12);
                    } elseif ($distKm < 50) {
                        $score = min(100, $score + 6);
                    }
                } elseif (!empty($profile['location']) && !empty($vol['location'])) {
                    // Fallback to string similarity if no coordinates
                    similar_text(strtolower($profile['location']), strtolower($vol['location']), $pct);
                    if ($pct > 70) {
                        $score = min(100, $score + 15);
                    } elseif ($pct > 40) {
                        $score = min(100, $score + 7);
                    }
                }

                if ($score > 0) {
                    $matches[] = [
                        'id' => (int)$vol['id'],
                        'source_type' => 'volunteering',
                        'source_id' => (int)$vol['id'],
                        'title' => $vol['title'],
                        'description' => mb_substr($vol['description'] ?? '', 0, 200),
                        'type' => 'volunteering',
                        'category' => null,
                        'match_score' => $score,
                        'score' => $score,
                        'reasons' => self::getSkillMatchReasons($userSkills, $neededSkills, 'skill'),
                        'matched_user' => null,
                        'matched_at' => date('Y-m-d\TH:i:s\Z'),
                        'organization' => $vol['org_name'] ?? null,
                        'location' => $vol['location'] ?? null,
                        'distance_km' => ($profileLat && $profileLon && $itemLat && $itemLon)
                            ? self::calculateDistance($profileLat, $profileLon, $itemLat, $itemLon)
                            : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("CrossModuleMatchingService::matchVolunteering error: " . $e->getMessage());
        }

        return $matches;
    }

    /**
     * Match against groups (interest overlap)
     */
    private static function matchGroups(int $userId, array $profile, int $tenantId, int $limit): array
    {
        $matches = [];
        $userInterests = array_merge($profile['interests_array'], $profile['skills_array']);

        if (empty($userInterests)) {
            return $matches;
        }

        try {
            $limitInt = (int)$limit;
            // Find groups user is NOT a member of
            $stmt = Database::query(
                "SELECT g.id, g.name, g.description, g.image_url, g.cached_member_count as member_count,
                        g.location, g.latitude, g.longitude, g.visibility,
                        u.name as owner_name
                 FROM `groups` g
                 JOIN users u ON g.owner_id = u.id
                 WHERE g.tenant_id = ?
                   AND g.id NOT IN (SELECT group_id FROM group_members WHERE user_id = ? AND status = 'active')
                   AND g.visibility = 'public'
                 ORDER BY g.cached_member_count DESC
                 LIMIT {$limitInt}",
                [$tenantId, $userId]
            );

            $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($groups as $group) {
                $groupKeywords = self::extractKeywords(($group['name'] ?? '') . ' ' . ($group['description'] ?? ''));
                $score = self::calculateKeywordOverlapScore($userInterests, $groupKeywords);

                // Boost for popular groups
                $memberCount = (int)($group['member_count'] ?? 0);
                if ($memberCount > 10) {
                    $score = min(100, $score + 5);
                }
                if ($memberCount > 50) {
                    $score = min(100, $score + 5);
                }

                // Boost for location match — prefer haversine over string comparison
                $profileLat = isset($profile['latitude']) ? (float)$profile['latitude'] : null;
                $profileLon = isset($profile['longitude']) ? (float)$profile['longitude'] : null;
                $itemLat    = isset($group['latitude']) ? (float)$group['latitude'] : null;
                $itemLon    = isset($group['longitude']) ? (float)$group['longitude'] : null;

                if ($profileLat && $profileLon && $itemLat && $itemLon) {
                    $earthR = 6371;
                    $dLat   = deg2rad($itemLat - $profileLat);
                    $dLon   = deg2rad($itemLon - $profileLon);
                    $a      = sin($dLat / 2) * sin($dLat / 2)
                            + cos(deg2rad($profileLat)) * cos(deg2rad($itemLat))
                            * sin($dLon / 2) * sin($dLon / 2);
                    $distKm = $earthR * 2 * atan2(sqrt($a), sqrt(1 - $a));
                    if ($distKm < 10) {
                        $score = min(100, $score + 20);
                    } elseif ($distKm < 25) {
                        $score = min(100, $score + 12);
                    } elseif ($distKm < 50) {
                        $score = min(100, $score + 6);
                    }
                } elseif (!empty($profile['location']) && !empty($group['location'])) {
                    // Fallback to string similarity if no coordinates
                    similar_text(strtolower($profile['location']), strtolower($group['location']), $pct);
                    if ($pct > 70) {
                        $score = min(100, $score + 15);
                    } elseif ($pct > 40) {
                        $score = min(100, $score + 7);
                    }
                }

                if ($score > 0) {
                    $matches[] = [
                        'id' => (int)$group['id'],
                        'source_type' => 'group',
                        'source_id' => (int)$group['id'],
                        'title' => $group['name'],
                        'description' => mb_substr($group['description'] ?? '', 0, 200),
                        'type' => 'group',
                        'category' => null,
                        'match_score' => $score,
                        'score' => $score,
                        'reasons' => ['Interest overlap with group topics'],
                        'matched_user' => null,
                        'matched_at' => date('Y-m-d\TH:i:s\Z'),
                        'member_count' => $memberCount,
                        'location' => $group['location'] ?? null,
                        'distance_km' => ($profileLat && $profileLon && $itemLat && $itemLon)
                            ? self::calculateDistance($profileLat, $profileLon, $itemLat, $itemLon)
                            : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("CrossModuleMatchingService::matchGroups error: " . $e->getMessage());
        }

        return $matches;
    }

    // =========================================================================
    // SCORING HELPERS
    // =========================================================================

    /**
     * Calculate listing match score
     */
    private static function calculateListingScore(array $profile, array $listing): int
    {
        $userSkills = $profile['skills_array'];
        $listingKeywords = self::extractKeywords(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''));

        // Skill/keyword overlap (60% weight)
        $skillScore = self::calculateKeywordOverlapScore($userSkills, $listingKeywords);

        // Proximity (30% weight)
        $proximityScore = 0;
        $distance = self::calculateDistance(
            $profile['latitude'] ?? null,
            $profile['longitude'] ?? null,
            $listing['latitude'] ?? null,
            $listing['longitude'] ?? null
        );
        if ($distance !== null) {
            if ($distance <= 5) {
                $proximityScore = 100;
            } elseif ($distance <= 15) {
                $proximityScore = 75;
            } elseif ($distance <= 30) {
                $proximityScore = 50;
            } elseif ($distance <= 50) {
                $proximityScore = 25;
            }
        }

        // Reciprocity potential (10% weight)
        $reciprocityScore = 0;
        $ownerSkills = !empty($listing['owner_skills'])
            ? array_map('trim', array_map('strtolower', explode(',', $listing['owner_skills'])))
            : [];
        if (!empty($ownerSkills) && !empty($userSkills)) {
            $overlap = count(array_intersect($userSkills, $ownerSkills));
            if ($overlap > 0) {
                $reciprocityScore = min(100, $overlap * 30);
            }
        }

        return (int)round($skillScore * 0.6 + $proximityScore * 0.3 + $reciprocityScore * 0.1);
    }

    /**
     * Return per-component debug scores for a listing match (for ?debug=true).
     *
     * @return array{category: int, skill: int, proximity: int, freshness: int, reciprocity: int, quality: int}
     */
    private static function getListingDebugScores(array $profile, array $listing): array
    {
        $userSkills = $profile['skills_array'];
        $listingKeywords = self::extractKeywords(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''));

        $skillScore = self::calculateKeywordOverlapScore($userSkills, $listingKeywords);

        $proximityScore = 0;
        $distance = self::calculateDistance(
            $profile['latitude'] ?? null,
            $profile['longitude'] ?? null,
            $listing['latitude'] ?? null,
            $listing['longitude'] ?? null
        );
        if ($distance !== null) {
            if ($distance <= 5) {
                $proximityScore = 100;
            } elseif ($distance <= 15) {
                $proximityScore = 75;
            } elseif ($distance <= 30) {
                $proximityScore = 50;
            } elseif ($distance <= 50) {
                $proximityScore = 25;
            }
        }

        $reciprocityScore = 0;
        $ownerSkills = !empty($listing['owner_skills'])
            ? array_map('trim', array_map('strtolower', explode(',', $listing['owner_skills'])))
            : [];
        if (!empty($ownerSkills) && !empty($userSkills)) {
            $overlap = count(array_intersect($userSkills, $ownerSkills));
            if ($overlap > 0) {
                $reciprocityScore = min(100, $overlap * 30);
            }
        }

        return [
            'category' => 0,
            'skill' => $skillScore,
            'proximity' => $proximityScore,
            'freshness' => 0,
            'reciprocity' => $reciprocityScore,
            'quality' => 0,
        ];
    }

    /**
     * Calculate skill overlap score between two skill arrays using Jaccard similarity.
     *
     * Jaccard = |intersection| / |union|
     *
     * Unlike the old precision-only formula (overlap / required), Jaccard penalises
     * large mismatches in either direction, giving a fairer similarity measure.
     */
    private static function calculateSkillOverlapScore(array $userSkills, array $requiredSkills): int
    {
        if (empty($userSkills) || empty($requiredSkills)) {
            return 0;
        }

        $overlap = count(array_intersect($userSkills, $requiredSkills));
        $union   = count($userSkills) + count($requiredSkills) - $overlap;

        if ($union === 0) {
            return 0;
        }

        $jaccard = $overlap / $union;
        return (int)round($jaccard * 100);
    }

    /**
     * Calculate keyword overlap score
     */
    private static function calculateKeywordOverlapScore(array $userKeywords, array $targetKeywords): int
    {
        if (empty($userKeywords) || empty($targetKeywords)) {
            return 0;
        }

        $matchCount = 0;
        foreach ($userKeywords as $keyword) {
            foreach ($targetKeywords as $target) {
                if ($keyword === $target || stripos($target, $keyword) !== false || stripos($keyword, $target) !== false) {
                    $matchCount++;
                    break;
                }
            }
        }

        $maxPossible = max(count($userKeywords), count($targetKeywords));
        return (int)round(($matchCount / $maxPossible) * 100);
    }

    /**
     * Extract keywords from text
     */
    private static function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
                       'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
                       'have', 'has', 'had', 'do', 'does', 'did', 'will', 'can', 'may',
                       'this', 'that', 'these', 'those', 'it', 'its', 'we', 'our', 'you', 'your',
                       'i', 'my', 'me', 'he', 'she', 'they', 'them', 'their'];

        $words = array_filter($words, function ($w) use ($stopWords) {
            return strlen($w) > 2 && !in_array($w, $stopWords);
        });

        return array_unique(array_values($words));
    }

    /**
     * Get listing match reasons
     */
    private static function getListingMatchReasons(array $profile, array $listing): array
    {
        $reasons = [];
        $userSkills = $profile['skills_array'];
        $listingKeywords = self::extractKeywords(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''));

        $overlap = array_intersect($userSkills, $listingKeywords);
        if (!empty($overlap)) {
            $reasons[] = 'Skills match: ' . implode(', ', array_slice($overlap, 0, 3));
        }

        $distance = self::calculateDistance(
            $profile['latitude'] ?? null,
            $profile['longitude'] ?? null,
            $listing['latitude'] ?? null,
            $listing['longitude'] ?? null
        );
        if ($distance !== null && $distance < 15) {
            $reasons[] = 'Nearby (' . round($distance, 1) . ' km)';
        }

        if (empty($reasons)) {
            $reasons[] = 'Content relevance';
        }

        return $reasons;
    }

    /**
     * Get skill match reasons
     */
    private static function getSkillMatchReasons(array $userSkills, array $requiredSkills, string $label): array
    {
        $overlap = array_intersect($userSkills, $requiredSkills);
        if (!empty($overlap)) {
            return [ucfirst($label) . ' match: ' . implode(', ', array_slice($overlap, 0, 3))];
        }
        return ['Partial ' . $label . ' relevance'];
    }

    /**
     * Calculate Haversine distance between two coordinates
     *
     * @return float|null Distance in km or null if coordinates missing
     */
    private static function calculateDistance(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }
}
