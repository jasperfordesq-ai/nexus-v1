<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Achievements, leaderboard, nexus-score & polls — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Each method mirrors a React gamification page and calls the SAME services the
 * V2 API controllers use (XPShopService, BadgeCollectionService,
 * LeaderboardSeasonService, LeaderboardService, NexusScoreCacheService,
 * CommunityDashboardService, EngagementRecognitionService, PollService,
 * PollRankingService, PollExportService, GamificationService). No money/auth/
 * notification logic is reimplemented here.
 */
trait GamificationParity
{
    // ================================================================
    // SHARED GUARDS
    // ================================================================

    /**
     * Guard for gamification routes: confirm tenant slug, require auth, gate the
     * 'gamification' feature. Returns the user id, or a login redirect.
     */
    private function gamificationGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('gamification'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        return $userId;
    }

    /**
     * Guard for poll-related parity routes (ranked voting, management). Gates the
     * 'polls' feature. Returns the user id, or a login redirect.
     */
    private function gamificationPollGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('polls'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        return $userId;
    }

    // ================================================================
    // XP SHOP (high) — /achievements/shop
    // ================================================================

    /** GET: XP shop — browse purchasable cosmetics/perks against the member's XP balance. */
    public function gamificationShop(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $items = [];
        $userXp = 0;
        try {
            $data = \App\Services\XPShopService::getItemsWithUserStatus($userId);
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $userXp = (int) ($data['user_xp'] ?? 0);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-shop', [
            'title' => __('govuk_alpha_gamification.shop.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'shopItems' => $items,
            'shopUserXp' => $userXp,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** POST: purchase a shop item. Calls XPShopService (atomic XP deduction + stock checks). */
    public function gamificationPurchase(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $itemId = (int) $request->input('item_id');
        $status = 'purchase-failed';
        if ($itemId > 0) {
            try {
                $result = \App\Services\XPShopService::purchaseItem($userId, $itemId);
                $status = ($result['success'] ?? false) ? 'purchased' : 'purchase-failed';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.gamification.shop', [
            'tenantSlug' => $tenantSlug, 'status' => $status,
        ]);
    }

    // ================================================================
    // COMPETITIVE LEADERBOARD with NEXUS-SCORE metric + season banner (high)
    // /achievements/competitive
    // ================================================================

    /**
     * Competitive leaderboard mirroring React CompetitiveLeaderboard: the 4 headline
     * metrics (XP, volunteer hours, credits earned, NEXUS score) plus an active-season
     * banner and the viewer's own rank. Complements the existing /leaderboard page,
     * which lacks the nexus_score metric and the season context.
     */
    public function gamificationCompetitive(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        // React's 4 headline metrics + 4 periods.
        $validTypes = ['xp', 'volunteer_hours', 'credits_earned', 'nexus_score'];
        $validPeriods = ['all', 'season', 'month', 'week'];
        $type = $this->allowed($request->query('type'), $validTypes, 'xp');
        $period = $this->allowed($request->query('period'), $validPeriods, 'all');

        $rows = [];
        $yourRank = null;
        $unit = $type;
        try {
            [$rows, $yourRank, $unit] = $this->gamificationFetchCompetitive($tenantId, $userId, $type, $period);
        } catch (\Throwable $e) {
            report($e);
        }

        // Active-season summary card (best-effort).
        $season = [];
        try {
            $season = app(\App\Services\LeaderboardSeasonService::class)->getSeasonWithUserData($userId) ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-competitive', [
            'title' => __('govuk_alpha_gamification.competitive.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'leaderboard',
            'compRows' => $rows,
            'compType' => $type,
            'compPeriod' => $period,
            'compTypes' => $validTypes,
            'compPeriods' => $validPeriods,
            'compUnit' => $unit,
            'compYourRank' => $yourRank,
            'compSeason' => is_array($season) ? $season : [],
        ]);
    }

    /**
     * Fetch competitive leaderboard rows for a metric/period, mirroring the V2
     * controller's type/period maps and nexus_score special case.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: int|null, 2: string}
     */
    private function gamificationFetchCompetitive(int $tenantId, int $userId, string $type, string $period): array
    {
        $typeMap = [
            'xp' => 'xp',
            'volunteer_hours' => 'vol_hours',
            'credits_earned' => 'credits_earned',
            'nexus_score' => 'nexus_score',
        ];
        $periodMap = ['all' => 'all_time', 'season' => 'all_time', 'month' => 'monthly', 'week' => 'weekly'];
        $serviceType = $typeMap[$type] ?? 'xp';
        $servicePeriod = $periodMap[$period] ?? 'all_time';

        $rows = [];
        $yourRank = null;

        if ($serviceType === 'nexus_score') {
            // Mirrors GamificationV2Controller's nexus_score branch.
            $tableCheck = DB::select("SHOW TABLES LIKE 'nexus_score_cache'");
            if (empty($tableCheck)) {
                return [[], null, 'nexus_score'];
            }
            $result = DB::select(
                "SELECT n.user_id, n.total_score, u.name, u.first_name, u.last_name
                 FROM nexus_score_cache n
                 JOIN users u ON u.id = n.user_id
                 WHERE n.tenant_id = ? AND u.tenant_id = ? AND u.is_approved = 1
                 ORDER BY n.total_score DESC
                 LIMIT 20",
                [$tenantId, $tenantId]
            );
            $rank = 1;
            foreach ($result as $r) {
                $r = (array) $r;
                $isMe = (int) $r['user_id'] === $userId;
                if ($isMe) {
                    $yourRank = $rank;
                }
                $rows[] = [
                    'rank' => $rank,
                    'user_id' => (int) $r['user_id'],
                    'name' => trim((string) ($r['name'] ?? '')) ?: trim(((string) ($r['first_name'] ?? '')) . ' ' . ((string) ($r['last_name'] ?? ''))),
                    'score' => (int) round((float) $r['total_score']),
                    'score_display' => number_format((float) $r['total_score'], 0),
                    'is_current_user' => $isMe,
                ];
                $rank++;
            }
            return [$rows, $yourRank, 'nexus_score'];
        }

        $svc = app(\App\Services\LeaderboardService::class);
        $raw = $svc->getLeaderboardByType($tenantId, $serviceType, $servicePeriod, 20, $userId);
        foreach ($raw as $r) {
            $isMe = (bool) ($r['is_current_user'] ?? false);
            if ($isMe) {
                $yourRank = (int) ($r['rank'] ?? 0) ?: null;
            }
            $rows[] = [
                'rank' => (int) ($r['rank'] ?? 0),
                'user_id' => (int) ($r['user_id'] ?? 0),
                'name' => trim((string) ($r['name'] ?? '')) ?: trim(((string) ($r['first_name'] ?? '')) . ' ' . ((string) ($r['last_name'] ?? ''))),
                'score' => $r['score'] ?? 0,
                'score_display' => $svc->formatScore($r['score'] ?? 0, $serviceType),
                'is_current_user' => $isMe,
            ];
        }

        return [$rows, $yourRank, $serviceType];
    }

    // ================================================================
    // SEASONS (med) — /achievements/seasons
    // ================================================================

    /** Active season card (rewards, participants, days remaining, your rank) + all-seasons history. */
    public function gamificationSeasons(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $current = [];
        $allSeasons = [];
        try {
            $svc = app(\App\Services\LeaderboardSeasonService::class);
            $current = $svc->getSeasonWithUserData($userId) ?? [];
            $allSeasons = $svc->getAllSeasons();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-seasons', [
            'title' => __('govuk_alpha_gamification.seasons.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'leaderboard',
            'currentSeason' => is_array($current) ? $current : [],
            'allSeasons' => is_array($allSeasons) ? $allSeasons : [],
        ]);
    }

    // ================================================================
    // PERSONAL JOURNEY (med) — /achievements/journey
    // ================================================================

    /** Personal journey: the viewer's own activity timeline, milestones and summary. */
    public function gamificationPersonalJourney(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $journey = [];
        try {
            $journey = \App\Services\CommunityDashboardService::getPersonalJourney(TenantContext::getId(), $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-journey', [
            'title' => __('govuk_alpha_gamification.journey.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'leaderboard',
            'journey' => is_array($journey) ? $journey : [],
        ]);
    }

    // ================================================================
    // MEMBER SPOTLIGHT (low) — /achievements/spotlight
    // ================================================================

    /** Daily-rotating featured-member cards. */
    public function gamificationSpotlight(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $members = [];
        try {
            $members = \App\Services\CommunityDashboardService::getMemberSpotlight(TenantContext::getId(), 3);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-spotlight', [
            'title' => __('govuk_alpha_gamification.spotlight.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'leaderboard',
            'spotlightMembers' => is_array($members) ? $members : [],
        ]);
    }

    // ================================================================
    // JOURNEYS / COLLECTIONS (med) — /achievements/collections
    // ================================================================

    /** Badge collections with earned/total progress and reward XP. */
    public function gamificationCollections(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $collections = [];
        try {
            $collections = \App\Services\BadgeCollectionService::getCollectionsWithProgress($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-collections', [
            'title' => __('govuk_alpha_gamification.collections.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'collections' => is_array($collections) ? $collections : [],
        ]);
    }

    // ================================================================
    // ENGAGEMENT HISTORY (low) — /achievements/engagement
    // ================================================================

    /** 12-month engagement history (active month flags + activity counts). */
    public function gamificationEngagement(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $history = [];
        try {
            $history = \App\Services\EngagementRecognitionService::getEngagementHistory(TenantContext::getId(), $userId, 12);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-engagement', [
            'title' => __('govuk_alpha_gamification.engagement.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'engagementHistory' => is_array($history) ? $history : [],
        ]);
    }

    // ================================================================
    // BADGE DETAIL (med) — /achievements/badges/{key}
    // ================================================================

    /** Badge detail page: icon, name, description, rarity, xp value, tier, earned-at. */
    public function gamificationBadgeDetail(Request $request, string $tenantSlug, string $key): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $definition = null;
        try {
            $definition = \App\Services\GamificationService::getBadgeByKey($key);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($definition === null, 404);

        $userBadge = null;
        try {
            $row = DB::table('user_badges')
                ->where('user_id', $userId)
                ->where('badge_key', $key)
                ->where('tenant_id', TenantContext::getId())
                ->first();
            $userBadge = $row ? (array) $row : null;
        } catch (\Throwable $e) {
            report($e);
        }

        $badge = array_merge($definition, [
            'earned' => !empty($userBadge),
            'earned_at' => $userBadge['awarded_at'] ?? null,
            'is_showcased' => !empty($userBadge['is_showcased'] ?? false),
        ]);
        $badge['description'] = $badge['msg'] ?? $badge['description'] ?? null;

        return $this->view('accessible-frontend::gamification-badge', [
            'title' => trim((string) ($badge['name'] ?? '')) ?: __('govuk_alpha_gamification.badge.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'badge' => $badge,
            'badgeKey' => $key,
        ]);
    }

    // ================================================================
    // SHOWCASE (med) — /achievements/showcase
    // ================================================================

    /** Manage which earned badges (max 5) are showcased on the profile. */
    public function gamificationShowcase(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $earned = [];
        $showcasedKeys = [];
        try {
            $earned = \App\Services\GamificationService::getBadges($userId, TenantContext::getId());
            foreach ($earned as $b) {
                if (!empty($b['is_showcased'])) {
                    $showcasedKeys[] = (string) ($b['badge_key'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-showcase', [
            'title' => __('govuk_alpha_gamification.showcase.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'earnedBadges' => is_array($earned) ? $earned : [],
            'showcasedKeys' => array_values(array_filter($showcasedKeys)),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** POST: save the showcase selection (max 5, must be earned). Calls UserBadge::updateShowcase. */
    public function gamificationUpdateShowcase(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $raw = $request->input('badge_keys', []);
        $badgeKeys = array_values(array_filter(array_map(
            static fn ($k): string => trim((string) $k),
            is_array($raw) ? $raw : []
        )));

        $status = 'showcase-failed';
        if (count($badgeKeys) > 5) {
            $status = 'showcase-too-many';
        } else {
            try {
                // Defence in depth: only allow keys the member actually owns.
                $owned = \App\Models\UserBadge::getForUser($userId);
                $ownedKeys = array_column($owned, 'badge_key');
                $invalid = array_diff($badgeKeys, $ownedKeys);
                if (empty($invalid)) {
                    \App\Models\UserBadge::updateShowcase($userId, $badgeKeys);
                    $status = 'showcase-updated';
                } else {
                    $status = 'showcase-not-owned';
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.gamification.showcase', [
            'tenantSlug' => $tenantSlug, 'status' => $status,
        ]);
    }

    // ================================================================
    // NEXUS TIER LADDER (med) — /nexus-score/tiers
    // ================================================================

    /** Full 9-tier ladder with the member's current tier highlighted and points-to-next. */
    public function gamificationTierLadder(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $score = [];
        try {
            $score = app(\App\Services\NexusScoreCacheService::class)->getScore($userId, TenantContext::getId());
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-tiers', [
            'title' => __('govuk_alpha_gamification.tiers.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'nexus_score',
            'tierScore' => is_array($score) ? $score : [],
        ]);
    }

    // ================================================================
    // RANKED POLLS (high) — /polls/{pollId}/rank, results, export, delete, create
    // ================================================================

    /** Ranked-choice voting page: order the options 1..N, then submit. Mirrors openRankedVote. */
    public function gamificationRankedVote(Request $request, string $tenantSlug, int $pollId): Response|RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $poll = null;
        try {
            $poll = \App\Services\PollService::getById($pollId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($poll === null, 404);
        abort_unless(($poll['poll_type'] ?? 'standard') === 'ranked', 404);

        // Already-ranked members see the results instead of the form.
        $myRankings = null;
        $results = null;
        try {
            $myRankings = app(\App\Services\PollRankingService::class)->getUserRankings($pollId, $userId);
            $results = app(\App\Services\PollRankingService::class)->calculateResults($pollId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-poll-rank', [
            'title' => trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha_gamification.ranked.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'poll' => $poll,
            'myRankings' => is_array($myRankings) ? $myRankings : null,
            'rankedResults' => is_array($results) ? $results : ['total_voters' => 0, 'results' => []],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** POST: submit a ranked-choice ballot (ordered option ids). Calls PollRankingService::submitRanking. */
    public function gamificationStoreRankedVote(Request $request, string $tenantSlug, int $pollId): RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        // No-JS reorder: each option carries a "rank[optionId] = position" select
        // (1..N). Build a [{option_id, rank}] payload ordered by the chosen rank.
        $rawRanks = $request->input('rank', []);
        $rankByOption = [];
        if (is_array($rawRanks)) {
            foreach ($rawRanks as $optionId => $position) {
                $oid = (int) $optionId;
                $pos = (int) $position;
                if ($oid > 0 && $pos > 0) {
                    $rankByOption[$oid] = $pos;
                }
            }
        }
        asort($rankByOption);

        $rankings = [];
        $rank = 1;
        foreach (array_keys($rankByOption) as $optionId) {
            $rankings[] = ['option_id' => $optionId, 'rank' => $rank++];
        }

        $status = 'rank-failed';
        if (!empty($rankings)) {
            try {
                $ok = app(\App\Services\PollRankingService::class)->submitRanking($pollId, $userId, $rankings);
                $status = $ok ? 'ranked' : 'rank-failed';

                // Mirror the controller-level notification to the poll creator.
                if ($ok) {
                    $this->gamificationNotifyRanking($pollId, $userId);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.gamification.poll.rank', [
            'tenantSlug' => $tenantSlug, 'pollId' => $pollId, 'status' => $status,
        ]);
    }

    /** Notify a poll's creator that someone submitted a ranking (recipient-locale wrapped). */
    private function gamificationNotifyRanking(int $pollId, int $rankerId): void
    {
        try {
            $poll = \App\Models\Poll::find($pollId);
            if (!$poll || (int) $poll->tenant_id !== TenantContext::getId()) {
                return;
            }
            if ((int) $poll->user_id === $rankerId) {
                return;
            }
            $ranker = \App\Models\User::find($rankerId);
            $recipient = \App\Models\User::find((int) $poll->user_id);
            \App\I18n\LocaleContext::withLocale($recipient, function () use ($ranker, $poll, $pollId) {
                $rankerName = $ranker
                    ? trim(((string) ($ranker->first_name ?? '')) . ' ' . ((string) ($ranker->last_name ?? '')))
                    : __('emails.common.fallback_someone');
                $pollTitle = $poll->question ?? '';
                $message = __('api_controllers_3.polls.ranking_received', ['name' => $rankerName, 'title' => $pollTitle]);
                \App\Models\Notification::createNotification((int) $poll->user_id, $message, "/polls/{$pollId}", 'poll_vote');
                \App\Services\NotificationDispatcher::fanOutPush((int) $poll->user_id, 'poll_vote', $message, "/polls/{$pollId}");
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Create a poll from the parity form — supports the 'ranked' poll type (the React enum). */
    public function gamificationStorePoll(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $question = trim(self::asStr($request->input('question')));
        $rawOptions = $request->input('options', []);
        $options = array_values(array_filter(array_map(
            static fn ($o): string => trim((string) $o),
            is_array($rawOptions) ? $rawOptions : []
        )));
        // React API accepts only 'standard' | 'ranked'. The existing AlphaController
        // form sends the invalid 'multiple' enum; this parity form sends the right one.
        $pollType = $this->allowed($request->input('poll_type'), ['standard', 'ranked'], 'standard');

        if ($question === '' || count($options) < 2) {
            return redirect()->route('govuk-alpha.gamification.poll.create', [
                'tenantSlug' => $tenantSlug, 'status' => 'poll-create-failed',
            ]);
        }

        $status = 'poll-create-failed';
        try {
            \App\Services\PollService::create($userId, [
                'question' => $question,
                'description' => trim(self::asStr($request->input('description'))),
                'expires_at' => self::asStr($request->input('expires_at')) ?: null,
                'category' => trim(self::asStr($request->input('category'))) ?: null,
                'poll_type' => $pollType,
                'options' => $options,
            ]);
            $status = 'poll-created';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.gamification.poll.create', [
            'tenantSlug' => $tenantSlug, 'status' => $status,
        ]);
    }

    /** GET: the parity poll-create form (standard or ranked, with optional category). */
    public function gamificationCreatePoll(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $categories = [];
        try {
            $categories = \App\Services\PollService::getCategories();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-poll-create', [
            'title' => __('govuk_alpha_gamification.poll_create.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'pollCategories' => is_array($categories) ? $categories : [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** GET: download the poll's vote tally as CSV (creator-only — enforced by PollExportService). */
    public function gamificationExportPoll(Request $request, string $tenantSlug, int $pollId): Response|RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $csv = null;
        try {
            $csv = app(\App\Services\PollExportService::class)->exportToCsv($pollId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        // null = not found or not the owner.
        abort_if($csv === null, 404);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="poll-' . $pollId . '-export.csv"',
        ]);
    }

    /** POST: delete a poll (creator-only — enforced by PollService::delete). */
    public function gamificationDeletePoll(Request $request, string $tenantSlug, int $pollId): RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $status = 'poll-delete-failed';
        try {
            $ok = \App\Services\PollService::delete($pollId, $userId);
            $status = $ok ? 'poll-deleted' : 'poll-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.gamification.poll.manage', [
            'tenantSlug' => $tenantSlug, 'status' => $status,
        ]);
    }

    /** GET: the member's own polls with management actions (delete, export, ranked link). */
    public function gamificationManagePolls(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->gamificationPollGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $polls = [];
        try {
            $list = \App\Services\PollService::getAll(['limit' => 30, 'user_id' => $userId])['items'] ?? [];
            foreach ($list as $p) {
                $full = \App\Services\PollService::getById((int) ($p['id'] ?? 0), $userId);
                if ($full !== null) {
                    $polls[] = $full;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::gamification-poll-manage', [
            'title' => __('govuk_alpha_gamification.poll_manage.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'myPolls' => $polls,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }
}
