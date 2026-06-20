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

    @if (($status ?? null) === 'onboarding-complete')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="ob-done-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="ob-done-title">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.onboarding.complete_banner') }}</p></div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.dashboard.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.dashboard.title') }}</h1>
    @if (!empty($firstName))
        <p class="govuk-body-l">{{ __('govuk_alpha.dashboard.welcome', ['name' => $firstName]) }}</p>
    @else
        <p class="govuk-body-l">{{ __('govuk_alpha.dashboard.description') }}</p>
    @endif

    @if (!($onboardingCompleted ?? true))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="dashboard-onboarding-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="dashboard-onboarding-title">{{ __('govuk_alpha.dashboard.onboarding_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.dashboard.onboarding_body') }}</p>
                <p class="govuk-body">
                    <a class="govuk-notification-banner__link" href="{{ route('govuk-alpha.onboarding', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.onboarding_link') }}</a>
                </p>
            </div>
        </div>
    @endif

    {{-- "Exchanges need your attention" — driven by the exchange workflow
         (exchange_requests via ExchangeService::countNeedingAttention): a request
         to accept, a completion to confirm, or a completed exchange to review.
         NOT raw wallet transactions, so it stays silent unless a real exchange is
         waiting on the member. --}}
    {{-- Parity with React's ExchangesAttentionCard gating: useFeature('exchange_workflow')
         (feature flag) for the card render + the API's isExchangeWorkflowEnabled() (broker
         config) guard, which ExchangeService::countNeedingAttention now enforces so the count
         is already 0 when the workflow is off. --}}
    @if (($exchangeAttentionCount ?? 0) > 0 && \App\Core\TenantContext::hasFeature('exchange_workflow') && \Illuminate\Support\Facades\Route::has('govuk-alpha.exchanges.index'))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="dashboard-reviews-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="dashboard-reviews-title">{{ __('govuk_alpha.dashboard.pending_reviews_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ trans_choice('govuk_alpha.dashboard.pending_reviews_body', $exchangeAttentionCount, ['count' => $exchangeAttentionCount]) }}</p>
                <p class="govuk-body">
                    <a class="govuk-notification-banner__link" href="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.pending_reviews_link') }}</a>
                </p>
            </div>
        </div>
    @endif

    @if (\App\Core\TenantContext::hasModule('listings'))
        <div class="govuk-button-group govuk-!-margin-bottom-6">
            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.new_listing') }}</a>
        </div>
    @endif

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
                {{-- <progress> is not a labelable element, so name it with aria-label
                     rather than a <label for>; the visible caption is aria-hidden to
                     avoid a duplicate screen-reader announcement. --}}
                <progress id="xp-progress" max="100" value="{{ $progressPct }}" aria-label="{{ __('govuk_alpha.dashboard.progress_to_next', ['percent' => $progressPct]) }}">{{ $progressPct }}%</progress>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-top-1" aria-hidden="true">{{ __('govuk_alpha.dashboard.progress_to_next', ['percent' => $progressPct]) }}</p>

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

            @if (!empty($endorsements))
                <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.dashboard.endorsements_title') }}</h2>
                <ul class="govuk-list nexus-alpha-skill-list">
                    @foreach (array_slice($endorsements, 0, 6) as $endorsement)
                        @php $endorseCount = (int) ($endorsement['count'] ?? 0); @endphp
                        <li>
                            <span class="govuk-!-font-weight-bold">{{ $endorsement['skill_name'] ?? '' }}</span>
                            <strong class="govuk-tag govuk-tag--green">{{ trans_choice('govuk_alpha.dashboard.endorsement_count', $endorseCount, ['count' => $endorseCount]) }}</strong>
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
                @if (\App\Core\TenantContext::hasModule('messages'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.messages') }}</a></li>
                @endif
                @if (\App\Core\TenantContext::hasFeature('connections'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.members') }}</a></li>
                @endif
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
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2"><a class="govuk-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ $itemTitle }}</a></h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                                @if (!empty($item['author']['avatar_url']))
                                    <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $item['author']['avatar_url'] }}" alt="" loading="lazy" decoding="async" width="32" height="32">
                                @endif
                                {{ __('govuk_alpha.feed.posted_by', ['name' => $authorName]) }}
                            </p>
                            @if (!empty($item['content']))
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item['content']), 180) }}</p>
                            @endif
                            @php
                                $feedImage = $item['image_url'] ?? ($item['media'][0]['thumbnail_url'] ?? ($item['media'][0]['file_url'] ?? null));
                            @endphp
                            @if (!empty($feedImage))
                                <img class="nexus-alpha-card-thumb" src="{{ $feedImage }}" alt="{{ __('govuk_alpha.feed.image_alt', ['title' => $itemTitle]) }}" width="120" height="90" loading="lazy" decoding="async">
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
                            @if (!empty($listing['image_url']))
                                <img class="nexus-alpha-card-thumb" src="{{ $listing['image_url'] }}" alt="{{ __('govuk_alpha.listings.image_alt', ['title' => $listing['title']]) }}" width="120" height="90" loading="lazy" decoding="async">
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
