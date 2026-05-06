{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-l">{{ __('govuk_alpha.service_name') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.members.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.members.description') }}</p>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" role="region" aria-labelledby="members-auth-required-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="members-auth-required-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
            </div>
        </div>
    @else
        <form method="get" action="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="q">{{ __('govuk_alpha.members.search_label') }}</label>
                        <input class="govuk-input" id="q" name="q" value="{{ $filters['q'] }}">
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
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        <p class="govuk-body">{{ trans_choice('govuk_alpha.members.result_count', (int) ($meta['total_items'] ?? 0), ['count' => (int) ($meta['total_items'] ?? 0)]) }}</p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                </div>
            </div>
        @elseif (empty($items))
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.members.empty') }}</p>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $member)
                    <article class="nexus-alpha-card nexus-alpha-summary">
                        @if (!empty($member['avatar']))
                            <img class="nexus-alpha-avatar" src="{{ $member['avatar'] }}" alt="">
                        @endif
                        <div>
                            <h2 class="govuk-heading-m">{{ $member['name'] }}</h2>
                            @if (!empty($member['is_verified']))
                                <strong class="govuk-tag">{{ __('govuk_alpha.members.verified') }}</strong>
                            @endif
                            @if (!empty($member['tagline']))
                                <p class="govuk-body">{{ $member['tagline'] }}</p>
                            @endif
                            <p class="govuk-body-s nexus-alpha-meta">
                                {{ __('govuk_alpha.members.hours_given', ['count' => (int) ($member['total_hours_given'] ?? 0)]) }}
                                ·
                                {{ __('govuk_alpha.members.hours_received', ['count' => (int) ($member['total_hours_received'] ?? 0)]) }}
                                @if (!empty($member['rating']))
                                    · {{ __('govuk_alpha.members.rating', ['rating' => number_format((float) $member['rating'], 1)]) }}
                                @endif
                            </p>
                            <p class="govuk-body">
                                <a class="govuk-link" href="/{{ $tenantSlug }}/profile/{{ $member['id'] }}">{{ __('govuk_alpha.actions.view_profile') }}</a>
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']))
                <p class="govuk-body govuk-!-margin-top-6">
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug, 'q' => $filters['q'], 'sort' => $filters['sort'], 'order' => $filters['order'], 'offset' => $meta['offset'] + $meta['per_page']]) }}">{{ __('govuk_alpha.actions.load_more') }}</a>
                </p>
            @endif
        @endif
    @endif
@endsection
