{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Marketplace sub-navigation — a server-rendered GOV.UK tab strip (plain links,
    no JS). Each destination is rendered only once its route exists. Set
    $commerceActiveTab in the parent view to highlight the current tab.
--}}
@php
    $commerceTabs = [
        'browse' => [
            'label' => __('govuk_alpha_commerce.nav.browse'),
            'href' => route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]),
        ],
    ];
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.saved')) {
        $commerceTabs['saved'] = [
            'label' => __('govuk_alpha_commerce.nav.saved'),
            'href' => route('govuk-alpha.marketplace.saved', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.offers')) {
        $commerceTabs['offers'] = [
            'label' => __('govuk_alpha_commerce.nav.offers'),
            'href' => route('govuk-alpha.marketplace.offers', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.orders.buyer')) {
        $commerceTabs['orders'] = [
            'label' => __('govuk_alpha_commerce.nav.orders'),
            'href' => route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.orders.seller')) {
        $commerceTabs['sales'] = [
            'label' => __('govuk_alpha_commerce.nav.sales'),
            'href' => route('govuk-alpha.marketplace.orders.seller', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.mine')) {
        $commerceTabs['mine'] = [
            'label' => __('govuk_alpha_commerce.nav.mine'),
            'href' => route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.marketplace.create')) {
        $commerceTabs['sell'] = [
            'label' => __('govuk_alpha_commerce.nav.sell'),
            'href' => route('govuk-alpha.marketplace.create', ['tenantSlug' => $tenantSlug]),
        ];
    }
    $commerceActive = $commerceActiveTab ?? 'browse';
@endphp
<nav class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_commerce.nav.heading') }}">
    <h2 class="govuk-tabs__title">{{ __('govuk_alpha_commerce.nav.heading') }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($commerceTabs as $tabKey => $tab)
            <li class="govuk-tabs__list-item{{ $commerceActive === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ $tab['href'] }}" @if ($commerceActive === $tabKey) aria-current="page" @endif>{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
