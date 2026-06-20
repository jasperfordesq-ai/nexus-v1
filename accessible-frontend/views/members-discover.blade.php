{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $directoryUrl = route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]);
        $profileUrl = fn (array $member): string => route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $member['id']]);
    @endphp

    <a class="govuk-back-link" href="{{ $directoryUrl }}">{{ __('govuk_alpha_members.filters.directory') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha.members.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_members.discover.heading') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_members.discover.description') }}</p>

    {{-- Quick-filter navigation across the directory variants. --}}
    <nav class="nexus-alpha-actions govuk-!-margin-bottom-6" aria-label="{{ __('govuk_alpha_members.filters.heading') }}">
        <a class="govuk-link" href="{{ $directoryUrl }}">{{ __('govuk_alpha_members.filters.all') }}</a>
        <a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug, 'sort' => 'joined', 'order' => 'DESC']) }}">{{ __('govuk_alpha_members.filters.new') }}</a>
        <a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug, 'sort' => 'hours_given', 'order' => 'DESC']) }}">{{ __('govuk_alpha_members.filters.active') }}</a>
        <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_members.filters.recommended') }}</strong>
        <a class="govuk-link" href="{{ route('govuk-alpha.members.nearby', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_members.filters.near_me') }}</a>
    </nav>

    @if (! $rankingEnabled)
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_members.discover.disabled_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_members.discover.disabled_detail') }}</p>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ $directoryUrl }}">{{ __('govuk_alpha_members.filters.directory') }}</a>
            </p>
        </div>
    @else
        <p class="govuk-hint">{{ __('govuk_alpha_members.discover.algorithm_note') }}</p>

        <form method="get" action="{{ route('govuk-alpha.members.discover', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="q">{{ __('govuk_alpha_members.discover.search_label') }}</label>
                <div id="discover-q-hint" class="govuk-hint">{{ __('govuk_alpha_members.discover.search_hint') }}</div>
                <input class="govuk-input govuk-input--width-20" id="q" name="q" type="search" value="{{ $search }}" aria-describedby="discover-q-hint">
            </div>
            <div class="nexus-alpha-actions">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                @if ($search !== '')
                    <a class="govuk-link" href="{{ route('govuk-alpha.members.discover', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                @endif
            </div>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha_members.discover.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.members.result_count', (int) ($meta['total_items'] ?? 0), ['count' => (int) ($meta['total_items'] ?? 0)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha_members.discover.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha_members.discover.empty') }}</p>
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $member)
                    @php
                        $displayName = ($member['name'] ?? '') !== '' ? $member['name'] : __('govuk_alpha.members.unknown_member');
                        $rankScore = $member['community_rank_score'] ?? null;
                        $rankPercent = $rankScore !== null ? (int) round(((float) $rankScore) * 100) : null;
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-member-row">
                            <div class="nexus-alpha-member-row__media">
                                @if (!empty($member['avatar']))
                                    <img class="nexus-alpha-avatar nexus-alpha-avatar--large" src="{{ $member['avatar'] }}" alt="{{ __('govuk_alpha.members.avatar_alt', ['name' => $displayName]) }}">
                                @else
                                    <span class="nexus-alpha-avatar nexus-alpha-avatar--large nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="nexus-alpha-member-row__content">
                                <div class="nexus-alpha-member-row__heading">
                                    <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                        <a class="govuk-link" href="{{ $profileUrl($member) }}">{{ $displayName }}</a>
                                    </h3>
                                    @if (!empty($member['identity_verified']))
                                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.members.verified') }}</strong>
                                    @endif
                                    @if (!empty($member['level']) && (int) $member['level'] > 0)
                                        <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.polish_members.member_level_label', ['n' => (int) $member['level']]) }}</strong>
                                    @endif
                                    @switch($member['connection_state'] ?? 'none')
                                        @case('connected')
                                            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.members.connection_connected') }}</strong>
                                            @break
                                        @case('pending_sent')
                                            <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.members.connection_request_sent') }}</strong>
                                            @break
                                        @case('pending_received')
                                            <strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha.members.connection_request_received') }}</strong>
                                            @break
                                    @endswitch
                                </div>

                                @if (!empty($member['tagline']))
                                    <p class="govuk-body">{{ $member['tagline'] }}</p>
                                @endif

                                @if ($rankPercent !== null)
                                    <p class="govuk-body govuk-!-margin-bottom-1">
                                        <strong>{{ __('govuk_alpha_members.discover.rank_score_label') }}:</strong>
                                        {{ __('govuk_alpha_members.discover.rank_score', ['percent' => $rankPercent]) }}
                                    </p>
                                    <progress class="nexus-alpha-progress" value="{{ max(0, min(100, $rankPercent)) }}" max="100" aria-label="{{ __('govuk_alpha_members.discover.rank_score_aria', ['percent' => $rankPercent]) }}">{{ $rankPercent }}%</progress>
                                @endif

                                <dl class="nexus-alpha-inline-list">
                                    @if (!empty($member['location']))
                                        <div>
                                            <dt>{{ __('govuk_alpha.members.location_label') }}</dt>
                                            <dd>{{ $member['location'] }}</dd>
                                        </div>
                                    @endif
                                    <div>
                                        <dt>{{ __('govuk_alpha.members.hours_given_label') }}</dt>
                                        <dd>{{ __('govuk_alpha.members.hours_given', ['count' => (int) ($member['total_hours_given'] ?? 0)]) }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('govuk_alpha.members.hours_received_label') }}</dt>
                                        <dd>{{ __('govuk_alpha.members.hours_received', ['count' => (int) ($member['total_hours_received'] ?? 0)]) }}</dd>
                                    </div>
                                    @if (!empty($member['rating']))
                                        <div>
                                            <dt>{{ __('govuk_alpha.members.rating_label') }}</dt>
                                            <dd>{{ __('govuk_alpha.members.rating', ['rating' => number_format((float) $member['rating'], 1)]) }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                            <div class="nexus-alpha-member-row__action">
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ $profileUrl($member) }}">
                                    {{ __('govuk_alpha.actions.view_profile') }}
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.members.profile_for', ['name' => $displayName]) }}</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if (!empty($meta['has_more']))
                <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.members.pagination_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.members.discover', ['tenantSlug' => $tenantSlug, 'q' => $search, 'offset' => (int) ($meta['offset'] ?? 0) + (int) ($meta['per_page'] ?? 24)]) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha_members.discover.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
    @endif
@endsection
