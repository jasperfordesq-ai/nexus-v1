{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $activeTab = $tab ?? 'active';
        $tabKeys = ['active', 'draft', 'sold', 'expired'];
        $statusMessages = [
            'deleted' => __('govuk_alpha_commerce.my_listings.status_deleted'),
            'delete-failed' => __('govuk_alpha_commerce.my_listings.status_delete_failed'),
            'renewed' => __('govuk_alpha_commerce.my_listings.status_renewed'),
            'renew-failed' => __('govuk_alpha_commerce.my_listings.status_renew_failed'),
        ];
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav')

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.my_listings.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.my_listings.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.my_listings.description') }}</p>

    @if (($status ?? null) !== null && isset($statusMessages[$status]))
        <div class="govuk-notification-banner{{ \Illuminate\Support\Str::endsWith($status, 'failed') ? '' : ' govuk-notification-banner--success' }}" role="region" aria-labelledby="commerce-status-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-status-title">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <p class="govuk-body">
        <a class="govuk-button" href="{{ route('govuk-alpha.marketplace.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_commerce.my_listings.create_button') }}</a>
    </p>

    <nav class="govuk-tabs govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_commerce.my_listings.title') }}">
        <ul class="govuk-tabs__list">
            @foreach ($tabKeys as $tabKey)
                <li class="govuk-tabs__list-item{{ $activeTab === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug, 'tab' => $tabKey]) }}" @if ($activeTab === $tabKey) aria-current="page" @endif>
                        {{ __('govuk_alpha_commerce.my_listings.tab_' . $tabKey) }} ({{ (int) ($counts[$tabKey] ?? 0) }})
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if (empty($listings))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.my_listings.empty_' . $activeTab) }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $card)
                @php
                    $editHref = route('govuk-alpha.marketplace.edit', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]);
                    $viewHref = route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]);
                @endphp
                @include('accessible-frontend::partials.commerce-listing-card', ['card' => $card])
                <div class="nexus-alpha-actions govuk-!-margin-bottom-6">
                    <a class="govuk-link govuk-!-margin-right-3" href="{{ $viewHref }}">{{ __('govuk_alpha_commerce.my_listings.action_view') }}</a>
                    <a class="govuk-link govuk-!-margin-right-3" href="{{ $editHref }}">{{ __('govuk_alpha_commerce.my_listings.action_edit') }}</a>
                    @if ($activeTab === 'expired')
                        <form method="post" action="{{ route('govuk-alpha.marketplace.renew', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]) }}" class="nexus-alpha-inline-form">
                            @csrf
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.my_listings.action_renew') }}</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('govuk-alpha.marketplace.delete', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]) }}" class="nexus-alpha-inline-form">
                        @csrf
                        <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.my_listings.action_delete') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
@endsection
