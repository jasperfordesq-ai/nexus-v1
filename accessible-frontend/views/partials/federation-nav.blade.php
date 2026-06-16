{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Federation sub-navigation — a server-rendered GOV.UK tab strip (plain links,
    no JS) shared by every federation sub-page so members can move between
    Overview / Partners / Members / Listings / Events / Groups / Connections /
    Messages / Settings without a hub round-trip. Each destination is rendered
    only once its route exists (Route::has guards), and the active tab is driven
    by $federationActiveTab passed from each federation* controller method.
--}}
@php
    $federationNavItems = [
        'overview' => [
            'route' => 'govuk-alpha.federation.index',
            'label' => __('govuk_alpha.federation.nav.overview'),
        ],
        'partners' => [
            'route' => 'govuk-alpha.federation.partners.index',
            'label' => __('govuk_alpha.federation.nav.partners'),
        ],
        'members' => [
            'route' => 'govuk-alpha.federation.members.index',
            'label' => __('govuk_alpha.federation.nav.members'),
        ],
        'listings' => [
            'route' => 'govuk-alpha.federation.listings.index',
            'label' => __('govuk_alpha.federation.nav.listings'),
        ],
        'events' => [
            'route' => 'govuk-alpha.federation.events.index',
            'label' => __('govuk_alpha.federation.nav.events'),
        ],
        'groups' => [
            'route' => 'govuk-alpha.federation.groups.index',
            'label' => __('govuk_alpha.federation.nav.groups'),
        ],
        'connections' => [
            'route' => 'govuk-alpha.federation.connections.index',
            'label' => __('govuk_alpha.federation.nav.connections'),
        ],
        'messages' => [
            'route' => 'govuk-alpha.federation.messages.index',
            'label' => __('govuk_alpha.federation.nav.messages'),
        ],
        'settings' => [
            'route' => 'govuk-alpha.federation.settings',
            'label' => __('govuk_alpha.federation.nav.settings'),
        ],
    ];
    $federationActive = $federationActiveTab ?? 'overview';
@endphp
<nav class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-6" aria-label="{{ __('govuk_alpha.federation.nav.label') }}">
    <h2 class="govuk-tabs__title">{{ __('govuk_alpha.federation.title') }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($federationNavItems as $navKey => $nav)
            @if (\Illuminate\Support\Facades\Route::has($nav['route']))
                <li class="govuk-tabs__list-item{{ $federationActive === $navKey ? ' govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ route($nav['route'], ['tenantSlug' => $tenantSlug]) }}" @if ($federationActive === $navKey) aria-current="page" @endif>{{ $nav['label'] }}</a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
