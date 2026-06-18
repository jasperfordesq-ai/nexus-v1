{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $activeTab = $tab ?? 'received';
        $statusMessages = [
            'offer-sent' => __('govuk_alpha_commerce.offers.status_offer_sent'),
            'accepted' => __('govuk_alpha_commerce.offers.status_accepted_done'),
            'declined' => __('govuk_alpha_commerce.offers.status_declined_done'),
            'withdrawn' => __('govuk_alpha_commerce.offers.status_withdrawn_done'),
            'accept-failed' => __('govuk_alpha_commerce.offers.status_action_failed'),
            'decline-failed' => __('govuk_alpha_commerce.offers.status_action_failed'),
            'withdraw-failed' => __('govuk_alpha_commerce.offers.status_action_failed'),
        ];
        $statusLabels = [
            'pending' => __('govuk_alpha_commerce.offers.status_pending'),
            'accepted' => __('govuk_alpha_commerce.offers.status_accepted'),
            'declined' => __('govuk_alpha_commerce.offers.status_declined'),
            'countered' => __('govuk_alpha_commerce.offers.status_countered'),
            'withdrawn' => __('govuk_alpha_commerce.offers.status_withdrawn'),
            'expired' => __('govuk_alpha_commerce.offers.status_expired'),
        ];
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav')

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.offers.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.offers.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.offers.description') }}</p>

    @if (($status ?? null) !== null && isset($statusMessages[$status]))
        <div class="govuk-notification-banner{{ \Illuminate\Support\Str::endsWith($status, 'failed') ? '' : ' govuk-notification-banner--success' }}" role="region" aria-labelledby="commerce-offers-status" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-offers-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <nav class="govuk-tabs govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_commerce.offers.title') }}">
        <ul class="govuk-tabs__list">
            @foreach (['received', 'sent'] as $tabKey)
                <li class="govuk-tabs__list-item{{ $activeTab === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ route('govuk-alpha.marketplace.offers', ['tenantSlug' => $tenantSlug, 'tab' => $tabKey]) }}" @if ($activeTab === $tabKey) aria-current="page" @endif>{{ __('govuk_alpha_commerce.offers.tab_' . $tabKey) }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if (empty($offers))
        <div class="govuk-inset-text"><p class="govuk-body">{{ $activeTab === 'sent' ? __('govuk_alpha_commerce.offers.empty_sent') : __('govuk_alpha_commerce.offers.empty_received') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($offers as $offer)
                @php
                    $oTitle = trim((string) ($offer['listing']['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
                    $amount = trim(trim((string) ($offer['currency'] ?? '')) . ' ' . number_format((float) ($offer['amount'] ?? 0), 2));
                    $oStatus = (string) ($offer['status'] ?? 'pending');
                    $counterparty = $activeTab === 'sent' ? ($offer['seller']['name'] ?? '') : ($offer['buyer']['name'] ?? '');
                    $listingId = (int) ($offer['listing']['id'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                            @if ($listingId > 0)
                                <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ $oTitle }}</a>
                            @else
                                {{ $oTitle }}
                            @endif
                        </h2>
                        <strong class="govuk-tag {{ $oStatus === 'accepted' ? 'govuk-tag--green' : ($oStatus === 'pending' ? 'govuk-tag--blue' : 'govuk-tag--grey') }}">{{ $statusLabels[$oStatus] ?? $oStatus }}</strong>
                    </div>
                    <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha_commerce.offers.amount_label') }}:</strong> {{ $amount }}</p>
                    @if (trim((string) $counterparty) !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ $activeTab === 'sent' ? __('govuk_alpha_commerce.offers.to_label') : __('govuk_alpha_commerce.offers.from_label') }}: {{ $counterparty }}</p>
                    @endif
                    @if (trim((string) ($offer['message'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($offer['message'], 200) }}</p>
                    @endif

                    @if ($oStatus === 'pending' || $oStatus === 'countered')
                        <div class="nexus-alpha-actions">
                            @if ($activeTab === 'received')
                                <form method="post" action="{{ route('govuk-alpha.marketplace.offers.accept', ['tenantSlug' => $tenantSlug, 'id' => $offer['id']]) }}" class="nexus-alpha-inline-form">
                                    @csrf
                                    <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.offers.accept') }}</button>
                                </form>
                                <form method="post" action="{{ route('govuk-alpha.marketplace.offers.decline', ['tenantSlug' => $tenantSlug, 'id' => $offer['id']]) }}" class="nexus-alpha-inline-form">
                                    @csrf
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.offers.decline') }}</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('govuk-alpha.marketplace.offers.withdraw', ['tenantSlug' => $tenantSlug, 'id' => $offer['id']]) }}" class="nexus-alpha-inline-form">
                                    @csrf
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.offers.withdraw') }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
