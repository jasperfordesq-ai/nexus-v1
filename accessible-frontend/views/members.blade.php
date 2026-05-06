{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = !empty($filters['q']) || ($filters['sort'] ?? 'name') !== 'name' || ($filters['order'] ?? 'ASC') !== 'ASC';
        $profileUrl = fn (array $member): string => '/' . $tenantSlug . '/profile/' . $member['id'];
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.members.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.members.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.members.description') }}</p>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" role="region" aria-labelledby="members-auth-required-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="members-auth-required-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.members.auth_required_detail', ['community' => $communityName]) }}</p>
                <div class="nexus-alpha-actions">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @else
        <form method="get" action="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            <fieldset class="govuk-fieldset" aria-describedby="members-filter-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.members.filters_title') }}</h2>
                </legend>
                <div id="members-filter-hint" class="govuk-hint">{{ __('govuk_alpha.members.filters_hint') }}</div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="q">{{ __('govuk_alpha.members.search_label') }}</label>
                            <div id="member-q-hint" class="govuk-hint">{{ __('govuk_alpha.members.search_hint') }}</div>
                            <input class="govuk-input" id="q" name="q" type="search" value="{{ $filters['q'] }}" aria-describedby="member-q-hint">
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="sort">{{ __('govuk_alpha.members.sort_label') }}</label>
                            <select class="govuk-select" id="sort" name="sort">
                                @foreach (['name', 'joined', 'rating', 'hours_given'] as $sort)
                                    <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ __('govuk_alpha.members.sort.' . $sort) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="order">{{ __('govuk_alpha.members.order_label') }}</label>
                            <select class="govuk-select" id="order" name="order">
                                @foreach (['ASC', 'DESC'] as $order)
                                    <option value="{{ $order }}" @selected($filters['order'] === $order)>{{ __('govuk_alpha.members.order.' . $order) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="nexus-alpha-actions">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </fieldset>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha.members.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.members.result_count', (int) ($meta['total_items'] ?? 0), ['count' => (int) ($meta['total_items'] ?? 0)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.members.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.members.empty') }}</p>
                @if ($hasFilters)
                    <p class="govuk-body">
                        <a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    </p>
                @endif
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $member)
                    @php
                        $displayName = $member['name'] ?: __('govuk_alpha.members.unknown_member');
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
                                    @if (!empty($member['is_verified']))
                                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.members.verified') }}</strong>
                                    @endif
                                </div>

                                @if (!empty($member['tagline']))
                                    <p class="govuk-body">{{ $member['tagline'] }}</p>
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
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug, 'q' => $filters['q'], 'sort' => $filters['sort'], 'order' => $filters['order'], 'offset' => $meta['offset'] + $meta['per_page']]) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha.members.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
    @endif
@endsection
