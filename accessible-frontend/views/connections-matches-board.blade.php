{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $stats = $matchStats ?? ['total' => 0, 'avg_score' => 0, 'hot_matches' => 0, 'source_types' => 0];
        $counts = $sourceCounts ?? ['listing' => 0, 'group' => 0, 'volunteering' => 0, 'event' => 0];
        $all = $matches ?? [];
        // Filter the rendered list by the active source (stats stay over the full set).
        $visible = $activeSource === 'all'
            ? $all
            : array_values(array_filter($all, fn ($m) => ($m['module'] ?? '') === $activeSource));
        // Defined once so both the filter nav and the empty-state heading can use it.
        $sourceTabs = [
            'all' => __('govuk_alpha_connections.matches.source_all'),
            'listing' => __('govuk_alpha_connections.matches.source_listing'),
            'group' => __('govuk_alpha_connections.matches.source_group'),
            'volunteering' => __('govuk_alpha_connections.matches.source_volunteering'),
            'event' => __('govuk_alpha_connections.matches.source_event'),
        ];
    @endphp

    <span class="govuk-caption-xl" id="matches-top">{{ __('govuk_alpha_connections.matches.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_connections.matches.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_connections.matches.description') }}</p>

    {{-- Status banners --}}
    @if ($status === 'match-dismissed')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="matches-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="matches-status-title">{{ __('govuk_alpha_connections.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_connections.matches_states.dismissed') }}</p>
            </div>
        </div>
    @elseif ($status === 'match-dismiss-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_connections.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha_connections.matches_states.dismiss_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($all))
        {{-- Stats dashboard: 4 metrics (parity: React GlassCard stats grid) --}}
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_connections.matches.stats_total') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($stats['total'] ?? 0) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_connections.matches.stats_avg_score') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($stats['avg_score'] ?? 0) }}%</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">
                    {{ __('govuk_alpha_connections.matches.stats_hot') }}
                    <span class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-0">{{ __('govuk_alpha_connections.matches.stats_hot_hint') }}</span>
                </dt>
                <dd class="govuk-summary-list__value">{{ (int) ($stats['hot_matches'] ?? 0) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_connections.matches.stats_source_types') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($stats['source_types'] ?? 0) }}</dd>
            </div>
        </dl>

        {{-- Source filter tabs with per-source counts (parity: React tab counts) --}}
        <nav aria-label="{{ __('govuk_alpha_connections.matches.source_legend') }}" class="govuk-!-margin-bottom-6">
            <ul class="nexus-alpha-filter-nav">
                @foreach ($sourceTabs as $srcKey => $srcLabel)
                    @php
                        // 'all' shows the full total; each source shows its own count.
                        $tabCount = $srcKey === 'all' ? (int) ($stats['total'] ?? 0) : (int) ($counts[$srcKey] ?? 0);
                        // Hide a source tab with no matches (parity: React returns null for count===0), but always keep 'all'.
                        $showTab = $srcKey === 'all' || $tabCount > 0;
                    @endphp
                    @if ($showTab)
                        <li>
                            <a class="govuk-link{{ $activeSource === $srcKey ? ' govuk-link--no-visited-state' : '' }}"
                               href="{{ route('govuk-alpha.connections.matches-board', ['tenantSlug' => $tenantSlug, 'source' => $srcKey]) }}#matches-top"
                               @if ($activeSource === $srcKey) aria-current="true" @endif>{{ __('govuk_alpha_connections.matches.source_count', ['label' => $srcLabel, 'count' => $tabCount]) }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    @endif

    @if (empty($visible))
        {{-- Empty state with Browse Listings CTA (parity: React EmptyState) --}}
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $activeSource === 'all' ? __('govuk_alpha_connections.matches_empty.title_all') : __('govuk_alpha_connections.matches_empty.title_filtered', ['source' => $sourceTabs[$activeSource] ?? $activeSource]) }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_connections.matches_empty.body') }}</p>
        </div>
        <a class="govuk-button" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_connections.matches_empty.browse_listings') }}</a>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-top-4">
            @foreach ($visible as $m)
                @php
                    $module = $m['module'] ?? 'listing';
                    $pct = (int) ($m['pct'] ?? 0);
                    $title = trim((string) ($m['title'] ?? '')) ?: __('govuk_alpha_connections.matches.view_match');
                    $owner = trim((string) ($m['user_name'] ?? '')) ?: __('govuk_alpha_connections.common.unknown_member');
                    $category = trim((string) ($m['category'] ?? ''));
                    $reasons = is_array($m['reasons'] ?? null) ? $m['reasons'] : [];
                    $shownReasons = array_slice($reasons, 0, 3);
                    $extraReasons = max(0, count($reasons) - 3);
                    $listingId = (int) ($m['listing_id'] ?? 0);
                    $groupId = (int) ($m['group_id'] ?? 0);
                    $eventId = (int) ($m['event_id'] ?? 0);
                    // Module tag label + colour.
                    $moduleLabelKey = 'govuk_alpha_connections.matches.module_' . $module;
                    $moduleLabel = \Illuminate\Support\Facades\Lang::has($moduleLabelKey) ? __($moduleLabelKey) : ucfirst($module);
                    // Relative matched-time.
                    $matchedWhen = null;
                    if (!empty($m['created_at'])) {
                        try { $matchedWhen = \Illuminate\Support\Carbon::parse($m['created_at'])->diffForHumans(); } catch (\Throwable $e) { $matchedWhen = null; }
                    }
                    // Detail link per module.
                    $detailUrl = null;
                    if ($module === 'listing' && $listingId > 0) {
                        $detailUrl = route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]);
                    } elseif ($module === 'group' && $groupId > 0) {
                        $detailUrl = route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $groupId]);
                    } elseif ($module === 'event' && $eventId > 0) {
                        $detailUrl = route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]);
                    }
                    // Score progress-bar tag colour (parity: green >=80, amber 60-79, grey <60).
                    $scoreTagClass = $pct >= 80 ? 'govuk-tag--green' : ($pct >= 60 ? 'govuk-tag--yellow' : 'govuk-tag--grey');
                    $metaParts = array_values(array_filter([
                        __('govuk_alpha_connections.matches.by_label', ['name' => $owner]),
                        $category !== '' ? $category : null,
                        $matchedWhen !== null ? __('govuk_alpha_connections.matches.matched_when', ['when' => $matchedWhen]) : null,
                    ]));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                            @if ($detailUrl)
                                <a class="govuk-link" href="{{ $detailUrl }}">{{ $title }}</a>
                            @else
                                {{ $title }}
                            @endif
                        </h2>
                        <strong class="govuk-tag govuk-tag--grey">{{ $moduleLabel }}</strong>
                        @if ($module === 'listing')
                            <strong class="govuk-tag {{ ($m['type'] ?? 'offer') === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue' }}">
                                {{ ($m['type'] ?? 'offer') === 'request' ? __('govuk_alpha_connections.matches.type_request') : __('govuk_alpha_connections.matches.type_offer') }}
                            </strong>
                        @endif
                    </div>

                    @if (!empty($metaParts))
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ implode(' · ', $metaParts) }}</p>
                    @endif

                    {{-- Description preview (parity: React 2-line clamp) --}}
                    @if (trim((string) ($m['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($m['description'], 160) }}</p>
                    @endif

                    {{-- Score: visual progress bar + tag (parity: React colour-coded Progress) --}}
                    <div class="govuk-!-margin-bottom-2">
                        <span class="govuk-body-s govuk-!-margin-right-2"><strong class="govuk-tag {{ $scoreTagClass }}">{{ __('govuk_alpha_connections.matches.score_label', ['percent' => $pct]) }}</strong></span>
                        <progress max="100" value="{{ $pct }}" aria-label="{{ __('govuk_alpha_connections.matches.score_bar_label', ['percent' => $pct]) }}">{{ $pct }}%</progress>
                    </div>

                    {{-- Match reasons: up to 3 inline + "+N more" overflow (parity: React reasons.slice(0,3)) --}}
                    @if (!empty($shownReasons))
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_connections.matches.reasons_label') }}</h3>
                        <ul class="govuk-list nexus-alpha-inline-list govuk-!-margin-bottom-2">
                            @foreach ($shownReasons as $reason)
                                <li><strong class="govuk-tag govuk-tag--blue">{{ $reason }}</strong></li>
                            @endforeach
                            @if ($extraReasons > 0)
                                <li><strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_connections.matches.reasons_more', ['count' => $extraReasons]) }}</strong></li>
                            @endif
                        </ul>
                    @endif

                    {{-- Dismiss with reason (parity: only listing matches are dismissable) --}}
                    @if ($module === 'listing' && $listingId > 0)
                        <div class="govuk-warning-text govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_connections.common.error_title') }}</span>
                                {{ __('govuk_alpha_connections.matches.dismiss_warning') }}
                            </strong>
                        </div>
                        <form method="post" action="{{ route('govuk-alpha.connections.matches-board.dismiss', ['tenantSlug' => $tenantSlug, 'listingId' => $listingId]) }}">
                            @csrf
                            <input type="hidden" name="source" value="{{ $activeSource }}">
                            <fieldset class="govuk-fieldset" aria-describedby="dismiss-legend-{{ $listingId }}">
                                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s" id="dismiss-legend-{{ $listingId }}">
                                    {{ __('govuk_alpha_connections.matches.dismiss_reason_label') }}
                                </legend>
                                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                    @foreach (['not_relevant', 'too_far', 'already_done', 'other'] as $i => $reasonKey)
                                        <div class="govuk-radios__item">
                                            <input class="govuk-radios__input" id="dismiss-{{ $listingId }}-{{ $reasonKey }}" name="reason" type="radio" value="{{ $reasonKey }}" @if ($i === 0) checked @endif>
                                            <label class="govuk-label govuk-radios__label" for="dismiss-{{ $listingId }}-{{ $reasonKey }}">{{ __('govuk_alpha_connections.matches.reason_' . $reasonKey) }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-top-2" data-module="govuk-button">
                                {{ __('govuk_alpha_connections.matches.dismiss_button') }}
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_connections.matches.dismiss_sr', ['title' => $title]) }}</span>
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <p class="govuk-body govuk-!-margin-top-6">
        <a class="govuk-link" href="{{ route('govuk-alpha.matches.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_connections.matches.back_to_matches') }}</a>
    </p>
@endsection
