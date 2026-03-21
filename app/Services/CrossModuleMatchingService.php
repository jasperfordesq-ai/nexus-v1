<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CrossModuleMatchingService — Aggregates match results across listings, groups,
 * volunteering, and events for a given user.
 *
 * Uses SmartMatchingEngine for listing-based matching and supplements with
 * group/volunteering/event recommendations based on skills, categories, and proximity.
 */
class CrossModuleMatchingService
{
    public function __construct(
        private readonly SmartMatchingEngine $smartMatchingEngine,
        private readonly MatchLearningService $matchLearningService,
    ) {}

    /**
     * Get all matches for a user across multiple modules.
     *
     * Options:
     * - limit: int (default 20)
     * - min_score: int (default 30)
     * - modules: string[] (default all: listings, groups, volunteering, events)
     * - debug: bool (include breakdown data)
     *
     * @return array{matches: array, meta: array}
     */
    public function getAllMatches(int $userId, array $options = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $options['limit'] ?? 20;
        $minScore = $options['min_score'] ?? 30;
        $debug = $options['debug'] ?? false;
        $modules = $options['modules'] ?? ['listings', 'groups', 'volunteering', 'events'];

        $allMatches = [];

        try {
            if (in_array('listings', $modules, true)) {
                $listingMatches = $this->getListingMatches($userId, $tenantId, $minScore, $limit, $debug);
                $allMatches = array_merge($allMatches, $listingMatches);
            }

            if (in_array('groups', $modules, true)) {
                $groupMatches = $this->getGroupMatches($userId, $tenantId, $minScore, $limit);
                $allMatches = array_merge($allMatches, $groupMatches);
            }

            if (in_array('volunteering', $modules, true)) {
                $volMatches = $this->getVolunteeringMatches($userId, $tenantId, $minScore, $limit);
                $allMatches = array_merge($allMatches, $volMatches);
            }

            if (in_array('events', $modules, true)) {
                $eventMatches = $this->getEventMatches($userId, $tenantId, $minScore, $limit);
                $allMatches = array_merge($allMatches, $eventMatches);
            }
        } catch (\Throwable $e) {
            Log::warning('[CrossModuleMatchingService] Error aggregating matches', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Apply learning boosts/penalties from historical interactions
        foreach ($allMatches as &$match) {
            if (($match['module'] ?? '') === 'listing' && isset($match['listing_id'])) {
                $boost = $this->matchLearningService->getHistoricalBoost($userId, $match);
                $match['match_score'] = max(0, min(100, $match['match_score'] + $boost));
            }
        }
        unset($match);

        // Filter dismissed matches
        $dismissedIds = $this->getDismissedListingIds($userId, $tenantId);
        $allMatches = array_filter($allMatches, function ($m) use ($dismissedIds) {
            if (($m['module'] ?? '') === 'listing' && isset($m['listing_id'])) {
                return !in_array((int) $m['listing_id'], $dismissedIds, true);
            }
            return true;
        });

        // Sort by score descending
        usort($allMatches, fn($a, $b) => ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0));

        // Apply final limit
        $allMatches = array_values(array_slice($allMatches, 0, $limit));

        // Strip debug data if not requested
        if (!$debug) {
            $allMatches = array_map(function ($m) {
                unset($m['match_breakdown']);
                return $m;
            }, $allMatches);
        }

        return [
            'matches' => $allMatches,
            'meta' => [
                'total' => count($allMatches),
                'modules' => $modules,
                'min_score' => $minScore,
            ],
        ];
    }

    /**
     * Get listing-based matches from SmartMatchingEngine.
     */
    private function getListingMatches(int $userId, int $tenantId, int $minScore, int $limit, bool $debug): array
    {
        try {
            $raw = $this->smartMatchingEngine->findMatchesForUser($userId, [
                'min_score' => $minScore,
                'limit' => $limit,
            ]);

            return array_map(function ($match) use ($debug) {
                $result = [
                    'module' => 'listing',
                    'listing_id' => (int) ($match['id'] ?? 0),
                    'title' => $match['title'] ?? '',
                    'description' => mb_substr($match['description'] ?? '', 0, 200),
                    'type' => $match['type'] ?? 'offer',
                    'category_name' => $match['category_name'] ?? null,
                    'user_id' => (int) ($match['user_id'] ?? 0),
                    'user_name' => trim(($match['first_name'] ?? '') . ' ' . ($match['last_name'] ?? '')),
                    'avatar_url' => $match['avatar_url'] ?? null,
                    'match_score' => (float) ($match['match_score'] ?? 0),
                    'match_type' => $match['match_type'] ?? 'one_way',
                    'match_reasons' => $match['match_reasons'] ?? [],
                    'distance_km' => isset($match['distance_km']) ? (float) $match['distance_km'] : null,
                    'matched_listing' => $match['matched_listing'] ?? null,
                    'created_at' => $match['created_at'] ?? null,
                ];

                if ($debug && isset($match['match_breakdown'])) {
                    $result['match_breakdown'] = $match['match_breakdown'];
                }

                return $result;
            }, $raw);
        } catch (\Throwable $e) {
            Log::warning('[CrossModuleMatchingService] Listing matches failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get group recommendations based on user's skills and categories.
     */
    private function getGroupMatches(int $userId, int $tenantId, int $minScore, int $limit): array
    {
        try {
            // Get user's skills and category interests from their listings
            $userSkills = $this->getUserSkillKeywords($userId, $tenantId);
            $userCategoryIds = $this->getUserCategoryIds($userId, $tenantId);

            // Get groups the user is NOT already a member of
            $groups = DB::select(
                "SELECT g.id, g.name, g.description, g.image_url, g.visibility,
                        g.location, g.latitude, g.longitude,
                        g.created_at,
                        (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count
                 FROM `groups` g
                 WHERE g.tenant_id = ?
                   AND g.id NOT IN (
                       SELECT gm2.group_id FROM group_members gm2 WHERE gm2.user_id = ?
                   )
                 ORDER BY g.created_at DESC
                 LIMIT ?",
                [$tenantId, $userId, $limit * 2]
            );

            $matches = [];
            foreach ($groups as $group) {
                $score = $this->scoreGroupMatch($group, $userSkills, $userCategoryIds);
                if ($score >= $minScore) {
                    $matches[] = [
                        'module' => 'group',
                        'group_id' => (int) $group->id,
                        'title' => $group->name,
                        'description' => mb_substr($group->description ?? '', 0, 200),
                        'image_url' => $group->image_url ?? null,
                        'member_count' => (int) $group->member_count,
                        'visibility' => $group->visibility ?? 'public',
                        'match_score' => $score,
                        'match_type' => 'group_recommendation',
                        'match_reasons' => ['Group matches your interests'],
                        'distance_km' => null,
                        'created_at' => $group->created_at ?? null,
                    ];
                }
            }

            return array_slice($matches, 0, $limit);
        } catch (\Throwable $e) {
            Log::warning('[CrossModuleMatchingService] Group matches failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get volunteering opportunity matches.
     */
    private function getVolunteeringMatches(int $userId, int $tenantId, int $minScore, int $limit): array
    {
        try {
            // Check if volunteering_organizations table exists and has data
            $orgs = DB::select(
                "SELECT vo.id, vo.name, vo.description, vo.contact_email, vo.website,
                        vo.address, vo.status, vo.created_at
                 FROM volunteering_organizations vo
                 WHERE vo.tenant_id = ? AND vo.status = 'approved'
                 ORDER BY vo.created_at DESC
                 LIMIT ?",
                [$tenantId, $limit * 2]
            );

            $userSkills = $this->getUserSkillKeywords($userId, $tenantId);

            $matches = [];
            foreach ($orgs as $org) {
                $score = $this->scoreTextRelevance(
                    $userSkills,
                    ($org->name ?? '') . ' ' . ($org->description ?? '')
                );

                // Base score for approved volunteering orgs
                $score = max($score, 35);

                if ($score >= $minScore) {
                    $matches[] = [
                        'module' => 'volunteering',
                        'organization_id' => (int) $org->id,
                        'title' => $org->name,
                        'description' => mb_substr($org->description ?? '', 0, 200),
                        'match_score' => $score,
                        'match_type' => 'volunteering_recommendation',
                        'match_reasons' => ['Volunteering opportunity that matches your profile'],
                        'distance_km' => null,
                        'created_at' => $org->created_at ?? null,
                    ];
                }
            }

            return array_slice($matches, 0, $limit);
        } catch (\Throwable $e) {
            // Table may not exist — degrade gracefully
            return [];
        }
    }

    /**
     * Get upcoming event matches based on user interests.
     */
    private function getEventMatches(int $userId, int $tenantId, int $minScore, int $limit): array
    {
        try {
            $userCategoryIds = $this->getUserCategoryIds($userId, $tenantId);
            $userSkills = $this->getUserSkillKeywords($userId, $tenantId);

            // Get future events the user has NOT RSVP'd to
            $events = DB::select(
                "SELECT e.id, e.title, e.description, e.event_date, e.location,
                        e.latitude, e.longitude, e.category_id, e.image_url,
                        e.created_at,
                        (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id) as attendee_count
                 FROM events e
                 WHERE e.tenant_id = ?
                   AND e.event_date >= NOW()
                   AND e.id NOT IN (
                       SELECT ea2.event_id FROM event_attendees ea2 WHERE ea2.user_id = ?
                   )
                 ORDER BY e.event_date ASC
                 LIMIT ?",
                [$tenantId, $userId, $limit * 2]
            );

            $matches = [];
            foreach ($events as $event) {
                $score = 30; // Base

                // Category match boost
                if ($event->category_id && in_array((int) $event->category_id, $userCategoryIds, true)) {
                    $score += 30;
                }

                // Text relevance boost
                $textScore = $this->scoreTextRelevance(
                    $userSkills,
                    ($event->title ?? '') . ' ' . ($event->description ?? '')
                );
                $score += (int) ($textScore * 0.3);

                // Freshness boost for imminent events
                if ($event->event_date) {
                    $daysUntil = max(0, (strtotime($event->event_date) - time()) / 86400);
                    if ($daysUntil <= 7) {
                        $score += 10;
                    }
                }

                $score = min(95, $score);

                if ($score >= $minScore) {
                    $matches[] = [
                        'module' => 'event',
                        'event_id' => (int) $event->id,
                        'title' => $event->title,
                        'description' => mb_substr($event->description ?? '', 0, 200),
                        'event_date' => $event->event_date,
                        'location' => $event->location ?? null,
                        'image_url' => $event->image_url ?? null,
                        'attendee_count' => (int) ($event->attendee_count ?? 0),
                        'match_score' => (float) $score,
                        'match_type' => 'event_recommendation',
                        'match_reasons' => ['Upcoming event that matches your interests'],
                        'distance_km' => null,
                        'created_at' => $event->created_at ?? null,
                    ];
                }
            }

            return array_slice($matches, 0, $limit);
        } catch (\Throwable $e) {
            // Table may not exist — degrade gracefully
            return [];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get listing IDs the user has dismissed.
     */
    private function getDismissedListingIds(int $userId, int $tenantId): array
    {
        try {
            return DB::table('match_dismissals')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->pluck('listing_id')
                ->map(fn($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the user's skill keywords extracted from their profile.
     */
    private function getUserSkillKeywords(int $userId, int $tenantId): array
    {
        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select('skills', 'bio')
                ->first();

            if (!$user) {
                return [];
            }

            return $this->smartMatchingEngine->extractKeywords(
                ($user->skills ?? '') . ' ' . ($user->bio ?? '')
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get category IDs from the user's active listings.
     */
    private function getUserCategoryIds(int $userId, int $tenantId): array
    {
        try {
            return DB::table('listings')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->map(fn($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Score a group match based on text similarity to user skills.
     */
    private function scoreGroupMatch(object $group, array $userSkills, array $userCategoryIds): float
    {
        $score = 25; // Base score for any group

        // Text relevance
        $textScore = $this->scoreTextRelevance(
            $userSkills,
            ($group->name ?? '') . ' ' . ($group->description ?? '')
        );
        $score += $textScore * 0.5;

        // Popularity boost
        $memberCount = (int) ($group->member_count ?? 0);
        if ($memberCount >= 10) {
            $score += 10;
        } elseif ($memberCount >= 5) {
            $score += 5;
        }

        return min(90, $score);
    }

    /**
     * Score text relevance by keyword overlap between user skills and target text.
     *
     * @return float Score 0-100
     */
    private function scoreTextRelevance(array $userKeywords, string $targetText): float
    {
        if (empty($userKeywords) || empty(trim($targetText))) {
            return 0;
        }

        $targetKeywords = $this->smartMatchingEngine->extractKeywords($targetText);

        if (empty($targetKeywords)) {
            return 0;
        }

        $overlap = count(array_intersect($userKeywords, $targetKeywords));
        $union = count(array_unique(array_merge($userKeywords, $targetKeywords)));

        if ($union === 0) {
            return 0;
        }

        $jaccard = $overlap / $union;

        // Scale: 0.0 → 0, 0.1 → 40, 0.2 → 60, 0.4+ → 80+
        return min(100, round($jaccard * 400, 1));
    }
}
