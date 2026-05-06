{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $moduleRows = [
            [
                'key' => 'feed',
                'title' => __('govuk_alpha.feed.title'),
                'description' => __('govuk_alpha.feed.description'),
                'href' => route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]),
                'available' => $modules['feed'] ?? true,
            ],
            [
                'key' => 'listings',
                'title' => __('govuk_alpha.listings.title'),
                'description' => __('govuk_alpha.listings.description'),
                'href' => route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]),
                'available' => $modules['listings'] ?? false,
            ],
            [
                'key' => 'members',
                'title' => __('govuk_alpha.members.title'),
                'description' => __('govuk_alpha.members.description'),
                'href' => route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]),
                'available' => $modules['members'] ?? true,
            ],
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            @if (($status ?? '') === 'signed-out')
                <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="home-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="home-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.signed_out') }}</p>
                    </div>
                </div>
            @endif

            <span class="govuk-caption-xl">{{ __('govuk_alpha.home.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.home.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.home.description', ['community' => $communityName]) }}</p>
            <p class="govuk-body">{{ __('govuk_alpha.home.supporting_text') }}</p>

            <div class="nexus-alpha-actions govuk-!-margin-bottom-8">
                @if ($isAuthenticated ?? false)
                    <a class="govuk-button" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.primary_authenticated') }}</a>
                @else
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.primary_guest') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.secondary_guest') }}</a>
                @endif
                <a class="govuk-button govuk-button--secondary" href="/{{ $tenantSlug }}">{{ __('govuk_alpha.home.current_app_action') }}</a>
            </div>
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="govuk-panel govuk-panel--confirmation nexus-alpha-panel">
                <h2 class="govuk-panel__title">{{ __('govuk_alpha.home.panel_title') }}</h2>
                <div class="govuk-panel__body">{{ __('govuk_alpha.home.panel_body') }}</div>
            </div>
        </div>
    </div>

    <div class="govuk-inset-text">
        {{ __('govuk_alpha.home.accessibility_note') }}
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.home.modules_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.home.modules_intro', ['community' => $communityName]) }}</p>
    <div class="nexus-alpha-card-list">
        @foreach ($moduleRows as $module)
            <article class="nexus-alpha-card">
                <div class="nexus-alpha-module-row">
                    <div>
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            @if ($module['available'])
                                <a class="govuk-link" href="{{ $module['href'] }}">{{ $module['title'] }}</a>
                            @else
                                {{ $module['title'] }}
                            @endif
                        </h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ $module['description'] }}</p>
                        @if (!$module['available'])
                            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha.home.module_unavailable_hint') }}</p>
                        @endif
                    </div>
                    <strong class="govuk-tag {{ $module['available'] ? 'govuk-tag--green' : 'govuk-tag--grey' }}">
                        {{ $module['available'] ? __('govuk_alpha.home.module_available') : __('govuk_alpha.home.module_unavailable') }}
                    </strong>
                </div>
            </article>
        @endforeach
    </div>

    <div class="govuk-grid-row govuk-!-margin-top-8">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.home.summary_title') }}</h2>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.home.summary_community_key') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $communityName }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.home.summary_service_key') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.home.summary_service_value') }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.home.summary_account_key') }}</dt>
                    <dd class="govuk-summary-list__value">
                        {{ ($isAuthenticated ?? false) ? __('govuk_alpha.home.summary_account_signed_in') : __('govuk_alpha.home.summary_account_signed_out') }}
                    </dd>
                </div>
            </dl>
        </div>
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.home.current_app_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.home.current_app_description') }}</p>
            <p class="govuk-body">
                <a class="govuk-link govuk-link--no-visited-state" href="/{{ $tenantSlug }}">{{ __('govuk_alpha.home.current_app_link') }}</a>
            </p>
        </div>
    </div>
@endsection
