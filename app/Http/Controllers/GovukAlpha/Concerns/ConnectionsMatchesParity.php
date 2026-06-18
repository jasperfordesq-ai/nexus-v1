<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connections & matches — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * The core AlphaController already ships basic connections() / matches() pages.
 * These methods deliver the React-parity richer experiences:
 *   - connectionsNetwork: cursor-paginated "My network" with connected-since
 *     dates, message buttons, three-way tabs and per-tab empty states (parity
 *     with react-frontend/src/pages/connections/ConnectionsPage.tsx).
 *   - connectionsMatchesBoard: matches dashboard with the 4-card stats row,
 *     per-source tab counts, matched-user info + relative time, score progress
 *     bars, description previews, 3-reasons-plus-more and a reason-aware dismiss
 *     form (parity with react-frontend/src/pages/matches/MatchesPage.tsx).
 */
trait ConnectionsMatchesParity
{
    /**
     * "My network" — full-parity connections page. Mirrors the React
     * three-tab layout (My connections / Pending / Sent) with cursor-based
     * load-more pagination per tab, connected-since dates on accepted cards,
     * a "Message" action linking to the conversation composer, and a name /
     * location search. Backed by the same tenant-scoped ConnectionService the
     * React API (ConnectionsController::index) calls.
     */
    public function connectionsNetwork(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('connections'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Active tab — drives which section is expanded by default and which
        // section the load-more cursor applies to.
        $tab = $this->allowed($request->query('tab'), ['accepted', 'pending_received', 'pending_sent'], 'accepted');
        $connSearch = trim(self::asStr($request->query('q')));

        // React loads per_page=20 with cursor-based load-more. We mirror that:
        // each section paginates independently. A single ?cursor= + ?tab= pair
        // drives the active section's "load more" (the others stay on page one).
        $perPage = 20;
        $cursor = trim(self::asStr($request->query('cursor')));

        $sections = [
            'accepted'         => ['items' => [], 'cursor' => null, 'has_more' => false],
            'pending_received' => ['items' => [], 'cursor' => null, 'has_more' => false],
            'pending_sent'     => ['items' => [], 'cursor' => null, 'has_more' => false],
        ];
        $counts = ['received' => 0, 'sent' => 0, 'total_friends' => 0];

        try {
            $svc = \App\Services\ConnectionService::class;
            foreach (array_keys($sections) as $statusKey) {
                $filters = ['status' => $statusKey, 'limit' => $perPage];
                // Only the active tab consumes the cursor — keeps each section's
                // pagination independent without needing JS, matching React's
                // per-tab cursor state.
                if ($cursor !== '' && $statusKey === $tab) {
                    $filters['cursor'] = $cursor;
                }
                $result = $svc::getConnections($userId, $filters);
                $sections[$statusKey] = [
                    'items'    => $result['items'] ?? [],
                    'cursor'   => $result['cursor'] ?? null,
                    'has_more' => (bool) ($result['has_more'] ?? false),
                ];
            }
            $counts = $svc::getPendingCounts($userId);

            // Server-side name/location filter (React filters client-side over the
            // loaded set; we filter the loaded page the same way for no-JS parity).
            if ($connSearch !== '') {
                $lcSearch = mb_strtolower($connSearch);
                $matchName = static function (array $c) use ($lcSearch): bool {
                    $p = $c['partner'] ?? $c['user'] ?? [];
                    $name = mb_strtolower(trim(
                        ($p['name'] ?? '') !== ''
                            ? (string) $p['name']
                            : (string) ($p['first_name'] ?? '') . ' ' . (string) ($p['last_name'] ?? '')
                    ));
                    $loc = mb_strtolower((string) ($p['location'] ?? ''));
                    return str_contains($name, $lcSearch) || str_contains($loc, $lcSearch);
                };
                foreach (array_keys($sections) as $statusKey) {
                    $sections[$statusKey]['items'] = array_values(array_filter($sections[$statusKey]['items'], $matchName));
                    // A name filter collapses pagination — there is no meaningful
                    // cursor over a filtered subset.
                    $sections[$statusKey]['has_more'] = false;
                    $sections[$statusKey]['cursor'] = null;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::connections-network', [
            'title' => __('govuk_alpha_connections.network.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'connections',
            'activeTab' => $tab,
            'sections' => $sections,
            'connectionCounts' => $counts,
            'connSearch' => $connSearch,
            'perPage' => $perPage,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Matches dashboard — full-parity cross-module matches board. Mirrors the
     * React stats grid (total / average score / hot matches / source types), the
     * per-source filter tabs with counts, score progress bars, description
     * previews, matched-user attribution with relative time, up-to-3 reasons
     * with a "+N more" overflow chip, and a reason-aware dismiss form. Uses the
     * same CrossModuleMatchingService the React API (MatchingController::allMatches)
     * calls. Gated by the listings module since matches are listing-seeded.
     */
    public function connectionsMatchesBoard(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasModule('listings'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $source = $this->allowed($request->query('source'), ['all', 'listing', 'group', 'volunteering', 'event'], 'all');
        // Stats must reflect the FULL match set (across every source), not just
        // the filtered view — so we always fetch all modules, then filter the
        // rendered list in the blade by the active source. This matches React,
        // which computes stats from `matches` and filters with `filteredMatches`.
        $allMatches = [];
        try {
            $result = app(\App\Services\CrossModuleMatchingService::class)->getAllMatches($userId, [
                'limit'   => 50,
                'modules' => ['listings', 'groups', 'volunteering', 'events'],
            ]);
            $allMatches = $result['matches'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Normalise each match into a flat shape the blade can render without
        // any further coercion. Scores arrive on a 0–1 (engine) or 0–100
        // (cold-start) scale; we normalise to an integer percentage once here.
        $normalised = array_map(static function (array $m): array {
            $raw = (float) ($m['match_score'] ?? 0);
            $pct = $raw > 1 ? (int) round(min(100, $raw)) : (int) round($raw * 100);
            $module = (string) ($m['module'] ?? 'listing');
            $reasons = array_values(array_filter(
                is_array($m['match_reasons'] ?? null) ? $m['match_reasons'] : [],
                static fn ($r) => is_string($r) && trim($r) !== ''
            ));
            return [
                'module'       => $module,
                'pct'          => $pct,
                'title'        => trim((string) ($m['title'] ?? '')),
                'description'  => trim((string) ($m['description'] ?? '')),
                'type'         => ($m['type'] ?? 'offer') === 'request' ? 'request' : 'offer',
                'category'     => trim((string) ($m['category_name'] ?? '')),
                'user_id'      => (int) ($m['user_id'] ?? 0),
                'user_name'    => trim((string) ($m['user_name'] ?? '')),
                'created_at'   => $m['created_at'] ?? null,
                'reasons'      => $reasons,
                'listing_id'   => (int) ($m['listing_id'] ?? 0),
                'group_id'     => (int) ($m['group_id'] ?? 0),
                'event_id'     => (int) ($m['event_id'] ?? 0),
                'organization_id' => (int) ($m['organization_id'] ?? 0),
            ];
        }, $allMatches);

        // Per-source counts (drives the tab labels: "Listings (3)") and the
        // stats row "source types" metric.
        $sourceCounts = ['listing' => 0, 'group' => 0, 'volunteering' => 0, 'event' => 0];
        foreach ($normalised as $m) {
            if (isset($sourceCounts[$m['module']])) {
                $sourceCounts[$m['module']]++;
            }
        }

        $total = count($normalised);
        $avgScore = $total > 0
            ? (int) round(array_sum(array_map(static fn ($m) => $m['pct'], $normalised)) / $total)
            : 0;
        $hotMatches = count(array_filter($normalised, static fn ($m) => $m['pct'] >= 80));
        $sourceTypes = count(array_filter($sourceCounts, static fn ($c) => $c > 0));

        return $this->view('accessible-frontend::connections-matches-board', [
            'title' => __('govuk_alpha_connections.matches.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'matches',
            'matches' => $normalised,
            'activeSource' => $source,
            'sourceCounts' => $sourceCounts,
            'matchStats' => [
                'total'        => $total,
                'avg_score'    => $avgScore,
                'hot_matches'  => $hotMatches,
                'source_types' => $sourceTypes,
            ],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Dismiss a listing match with a chosen reason. Mirrors
     * MatchingController::dismiss — records a negative signal so the listing
     * ranks lower in future results. Only listing matches are dismissable
     * (the React board also gates dismiss to source_type === 'listing'). The
     * reason is whitelisted against the same set the API validates.
     */
    public function connectionsDismissMatch(Request $request, string $tenantSlug, int $listingId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasModule('listings'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = \App\Core\TenantContext::getId();

        // Verify the listing exists and is tenant-scoped before inserting a
        // dismissal — mirrors the sibling dismissMatch()/the API, and avoids an
        // FK violation on match_dismissals.listing_id. Cross-tenant / missing → 404.
        $exists = \Illuminate\Support\Facades\DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('id', $listingId)
            ->exists();
        abort_unless($exists, 404);

        $reason = $this->allowed(
            $request->input('reason'),
            ['not_relevant', 'too_far', 'already_done', 'other'],
            'not_relevant'
        );

        $ok = false;
        try {
            // Upsert — a re-dismiss is a no-op that just refreshes the reason.
            \Illuminate\Support\Facades\DB::table('match_dismissals')->updateOrInsert(
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'listing_id' => $listingId],
                ['reason' => $reason, 'created_at' => now()]
            );
            // Record the negative learning signal exactly as the API does.
            app(\App\Services\MatchLearningService::class)
                ->recordInteraction($userId, $listingId, 'dismissed', []);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.connections.matches-board', [
            'tenantSlug' => $tenantSlug,
            'source' => $this->allowed($request->input('source'), ['all', 'listing', 'group', 'volunteering', 'event'], 'all'),
            'status' => $ok ? 'match-dismissed' : 'match-dismiss-failed',
        ])->withFragment('matches-top');
    }
}
