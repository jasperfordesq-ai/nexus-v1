{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.home.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.home.description', ['community' => $tenant['name'] ?? $tenantSlug]) }}</p>

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

            <div class="nexus-alpha-actions govuk-!-margin-bottom-8">
                @if ($isAuthenticated ?? false)
                    <a class="govuk-button" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.primary_authenticated') }}</a>
                @else
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.primary_guest') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.home.secondary_guest') }}</a>
                @endif
            </div>
        </div>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.home.modules_title') }}</h2>
    <div class="nexus-alpha-card-list">
        @foreach ([
            ['title' => __('govuk_alpha.feed.title'), 'description' => __('govuk_alpha.feed.description'), 'href' => route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug])],
            ['title' => __('govuk_alpha.listings.title'), 'description' => __('govuk_alpha.listings.description'), 'href' => route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug])],
            ['title' => __('govuk_alpha.members.title'), 'description' => __('govuk_alpha.members.description'), 'href' => route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug])],
        ] as $module)
            <article class="nexus-alpha-card">
                <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                    <a class="govuk-link" href="{{ $module['href'] }}">{{ $module['title'] }}</a>
                </h3>
                <p class="govuk-body">{{ $module['description'] }}</p>
            </article>
        @endforeach
    </div>
@endsection
