{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $feedItemTypeLabel = fn (?string $type): string => \Illuminate\Support\Facades\Lang::has('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            ? __('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            : __('govuk_alpha.feed.item_types.activity');
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.dashboard.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.dashboard.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.dashboard.description') }}</p>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.timebank_title') }}</h2>
            <dl class="nexus-alpha-stat-grid">
                @if (is_array($wallet ?? null))
                    <div class="nexus-alpha-stat">
                        <dt>{{ __('govuk_alpha.dashboard.balance_label') }}</dt>
                        <dd>{{ __('govuk_alpha.dashboard.hours_value', ['value' => number_format((float) ($wallet['balance'] ?? 0), 1)]) }}</dd>
                    </div>
                @endif
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.hours_given_label') }}</dt>
                    <dd>{{ number_format((float) $profileStats['hours_given'], 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.hours_received_label') }}</dt>
                    <dd>{{ number_format((float) $profileStats['hours_received'], 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.active_listings_label') }}</dt>
                    <dd>{{ (int) $profileStats['listings_count'] }}</dd>
                </div>
            </dl>

            @if (is_array($gamification ?? null))
                @php
                    $level = (int) ($gamification['level'] ?? 1);
                    $levelName = trim((string) ($gamification['level_name'] ?? ''));
                    $xp = (int) ($gamification['xp'] ?? 0);
                    $levelProgress = $gamification['level_progress'] ?? [];
                    $progressPct = (int) round((float) ($levelProgress['progress_percentage'] ?? 0));
                    $progressPct = max(0, min(100, $progressPct));
                    $badgesCount = (int) ($gamification['badges_count'] ?? count($badges ?? []));
                @endphp
                <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.dashboard.progress_title') }}</h2>
                <p class="govuk-body govuk-!-margin-bottom-1">
                    <strong>{{ __('govuk_alpha.dashboard.level_label', ['level' => $level]) }}</strong>
                    @if ($levelName !== '')
                        <span class="nexus-alpha-meta">({{ $levelName }})</span>
                    @endif
                    <span aria-hidden="true"> · </span>
                    <span class="nexus-alpha-meta">{{ __('govuk_alpha.dashboard.xp_label', ['xp' => number_format($xp)]) }}</span>
                </p>
                <label class="govuk-visually-hidden" for="xp-progress">{{ __('govuk_alpha.dashboard.progress_to_next', ['percent' => $progressPct]) }}</label>
                <progress id="xp-progress" max="100" value="{{ $progressPct }}">{{ $progressPct }}%</progress>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-top-1">{{ __('govuk_alpha.dashboard.progress_to_next', ['percent' => $progressPct]) }}</p>

                <h3 class="govuk-heading-m">{{ __('govuk_alpha.dashboard.badges_title', ['count' => $badgesCount]) }}</h3>
                @if (!empty($badges))
                    <ul class="govuk-list nexus-alpha-actions">
                        @foreach (array_slice($badges, 0, 8) as $badge)
                            <li>
                                @if (!empty($badge['icon']))
                                    <span aria-hidden="true">{{ $badge['icon'] }}</span>
                                @endif
                                <strong class="govuk-tag govuk-tag--blue">{{ $badge['name'] ?? '' }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="govuk-body">{{ __('govuk_alpha.dashboard.badges_empty') }}</p>
                @endif
            @endif

            @if (!empty($upcomingEvents))
                <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.dashboard.upcoming_events_title') }}</h2>
                <ul class="govuk-list govuk-list--spaced">
                    @foreach ($upcomingEvents as $event)
                        @php $eventStart = !empty($event['start_time']) ? \Illuminate\Support\Carbon::parse($event['start_time'])->translatedFormat('j F Y, g:ia') : null; @endphp
                        <li>
                            <a class="govuk-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ $event['title'] }}</a>
                            @if ($eventStart || !empty($event['location']))
                                <br>
                                <span class="govuk-body-s nexus-alpha-meta">
                                    @if ($eventStart){{ $eventStart }}@endif
                                    @if ($eventStart && !empty($event['location'])) <span aria-hidden="true"> · </span> @endif
                                    @if (!empty($event['location'])){{ $event['location'] }}@endif
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.quick_links_title') }}</h2>
            <ul class="govuk-list govuk-list--spaced">
                <li><a class="govuk-link" href="{{ route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_profile') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.edit_profile') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_feed') }}</a></li>
                @if (\App\Core\TenantContext::hasModule('listings'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_listings') }}</a></li>
                @endif
                @if (\App\Core\TenantContext::hasFeature('events'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.events') }}</a></li>
                @endif
                @if (\App\Core\TenantContext::hasFeature('volunteering'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.volunteering') }}</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="govuk-grid-row govuk-!-margin-top-8">
        <div class="govuk-grid-column-one-half">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.recent_feed_title') }}</h2>
            @if (empty($feedItems))
                <div class="govuk-inset-text">{{ __('govuk_alpha.dashboard.empty_feed') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($feedItems as $item)
                        @php
                            $itemType = $item['type'] ?? 'post';
                            $itemTitle = $item['title'] ?? $feedItemTypeLabel($itemType);
                            $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha.feed.unknown_author');
                        @endphp
                        <article class="nexus-alpha-card">
                            <strong class="govuk-tag govuk-tag--grey">{{ $feedItemTypeLabel($itemType) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">{{ $itemTitle }}</h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.feed.posted_by', ['name' => $authorName]) }}</p>
                            @if (!empty($item['content']))
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item['content']), 180) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="govuk-grid-column-one-half">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.recent_listings_title') }}</h2>
            @if (empty($listings))
                <div class="govuk-inset-text">{{ __('govuk_alpha.dashboard.empty_listings') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($listings as $listing)
                        @php
                            $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
                            $typeClass = $type === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue';
                        @endphp
                        <article class="nexus-alpha-card">
                            <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ $listing['title'] }}</a>
                            </h3>
                            @if (!empty($listing['description']))
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing['description']), 180) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
