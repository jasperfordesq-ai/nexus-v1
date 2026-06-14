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
                'key' => 'dashboard',
                'title' => __('govuk_alpha.dashboard.title'),
                'description' => __('govuk_alpha.dashboard.description'),
                'href' => ($isAuthenticated ?? false)
                    ? route('govuk-alpha.dashboard', ['tenantSlug' => $tenantSlug])
                    : route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']),
                'available' => $isAuthenticated ?? false,
                'auth_required' => true,
            ],
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
            [
                'key' => 'events',
                'title' => __('govuk_alpha.events.title'),
                'description' => __('govuk_alpha.events.description'),
                'href' => route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]),
                'available' => \App\Core\TenantContext::hasFeature('events'),
            ],
            [
                'key' => 'volunteering',
                'title' => __('govuk_alpha.volunteering.title'),
                'description' => __('govuk_alpha.volunteering.description'),
                'href' => route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]),
                'available' => \App\Core\TenantContext::hasFeature('volunteering'),
            ],
            [
                'key' => 'messages',
                'title' => __('govuk_alpha.messages.title'),
                'description' => __('govuk_alpha.messages.description'),
                'href' => ($isAuthenticated ?? false)
                    ? route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug])
                    : route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']),
                'available' => ($isAuthenticated ?? false) && \App\Services\BrokerControlConfigService::isDirectMessagingEnabled(),
                'auth_required' => true,
            ],
            [
                'key' => 'exchanges',
                'title' => __('govuk_alpha.exchanges.title'),
                'description' => __('govuk_alpha.exchanges.description'),
                'href' => ($isAuthenticated ?? false)
                    ? route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug])
                    : route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']),
                'available' => ($isAuthenticated ?? false) && \App\Core\TenantContext::hasModule('listings') && \App\Services\BrokerControlConfigService::isExchangeWorkflowEnabled(),
                'auth_required' => true,
            ],
            [
                'key' => 'wallet',
                'title' => __('govuk_alpha.wallet.title'),
                'description' => __('govuk_alpha.wallet.description'),
                'href' => ($isAuthenticated ?? false)
                    ? route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug])
                    : route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']),
                'available' => ($isAuthenticated ?? false) && \App\Core\TenantContext::hasModule('wallet'),
                'auth_required' => true,
            ],
            [
                'key' => 'profile',
                'title' => __('govuk_alpha.nav.profile'),
                'description' => __('govuk_alpha.profile_settings.description'),
                'href' => ($isAuthenticated ?? false)
                    ? route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug])
                    : route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']),
                'available' => $isAuthenticated ?? false,
                'auth_required' => true,
            ],
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            @if (($status ?? '') === 'signed-out')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="home-status-title">
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
            @if (!empty($tenant['tagline']))
                <p class="govuk-body-l govuk-!-font-weight-bold">{{ $tenant['tagline'] }}</p>
            @endif
            <p class="govuk-body">{{ __('govuk_alpha.home.supporting_text') }}</p>

            <div class="nexus-alpha-actions govuk-!-margin-bottom-8">
                @if ($isAuthenticated ?? false)
                    <a class="govuk-button" href="{{ route('govuk-alpha.dashboard', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.home.primary_authenticated') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.home.profile_action') }}</a>
                @else
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.home.primary_guest') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.home.secondary_guest') }}</a>
                @endif
            </div>
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="govuk-panel govuk-panel--confirmation nexus-alpha-panel">
                <h2 class="govuk-panel__title">{{ __('govuk_alpha.home.panel_title') }}</h2>
                <div class="govuk-panel__body">{{ __('govuk_alpha.home.panel_body') }}</div>
            </div>
        </div>
    </div>

    @if (is_array($stats ?? null) && ! empty($stats))
        <dl class="nexus-alpha-stat-grid govuk-!-margin-bottom-8">
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.about.stats.members') }}</dt>
                <dd>{{ number_format((int) ($stats['members'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.about.stats.hours_exchanged') }}</dt>
                <dd>{{ number_format((float) ($stats['hours_exchanged'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.about.stats.active_listings') }}</dt>
                <dd>{{ number_format((int) ($stats['listings'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.about.stats.communities') }}</dt>
                <dd>{{ number_format((int) ($stats['communities'] ?? 0)) }}</dd>
            </div>
        </dl>
    @endif

    <div class="govuk-inset-text">
        {{ __('govuk_alpha.home.accessibility_note') }}
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.home.modules_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.home.modules_intro', ['community' => $communityName]) }}</p>
    <div class="nexus-alpha-card-list">
        @foreach ($moduleRows as $module)
            @php
                // An auth-gated module that is only "unavailable" because the viewer is
                // signed out is enabled — it just needs sign-in. Distinguish that from a
                // module the community has genuinely disabled.
                $needsSignIn = ! $module['available'] && ! empty($module['auth_required']) && ! ($isAuthenticated ?? false);
                $isLinked = $module['available'] || $needsSignIn;
            @endphp
            <article class="nexus-alpha-card">
                <div class="nexus-alpha-module-row">
                    <div>
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            @if ($isLinked)
                                <a class="govuk-link" href="{{ $module['href'] }}">{{ $module['title'] }}</a>
                            @else
                                {{ $module['title'] }}
                            @endif
                        </h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ $module['description'] }}</p>
                        @if ($needsSignIn)
                            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha.home.module_signin_hint') }}</p>
                        @elseif (! $module['available'])
                            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha.home.module_unavailable_hint') }}</p>
                        @endif
                    </div>
                    @if ($module['available'])
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.home.module_available') }}</strong>
                    @elseif ($needsSignIn)
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.home.module_signin') }}</strong>
                    @else
                        <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.home.module_unavailable') }}</strong>
                    @endif
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
    </div>
@endsection
