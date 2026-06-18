{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $isSeller = ($orderRole ?? 'buyer') === 'seller';
        $activeTab = $tab ?? 'all';
        $tabKeys = $isSeller ? ['all', 'paid', 'shipped', 'completed'] : ['all', 'active', 'completed', 'cancelled'];
        $tabRoute = $isSeller ? 'govuk-alpha.marketplace.orders.seller' : 'govuk-alpha.marketplace.orders.buyer';
        $statusMessages = [
            'ordered' => __('govuk_alpha_commerce.orders.status_ordered'),
            'shipped' => __('govuk_alpha_commerce.orders.status_shipped_done'),
            'ship-failed' => __('govuk_alpha_commerce.orders.status_ship_failed'),
            'confirmed' => __('govuk_alpha_commerce.orders.status_confirmed'),
            'confirm-failed' => __('govuk_alpha_commerce.orders.status_confirm_failed'),
            'cancelled' => __('govuk_alpha_commerce.orders.status_cancelled_done'),
            'cancel-failed' => __('govuk_alpha_commerce.orders.status_cancel_failed'),
            'rated' => __('govuk_alpha_commerce.orders.status_rated'),
            'rate-failed' => __('govuk_alpha_commerce.orders.status_rate_failed'),
            'rate-invalid' => __('govuk_alpha_commerce.orders.status_rate_invalid'),
        ];
        $statusLabels = [
            'pending_payment' => __('govuk_alpha_commerce.orders.status_pending_payment'),
            'paid' => __('govuk_alpha_commerce.orders.status_paid'),
            'shipped' => __('govuk_alpha_commerce.orders.status_shipped'),
            'delivered' => __('govuk_alpha_commerce.orders.status_delivered'),
            'completed' => __('govuk_alpha_commerce.orders.status_completed'),
            'cancelled' => __('govuk_alpha_commerce.orders.status_cancelled'),
        ];
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav')

    <span class="govuk-caption-xl">{{ $isSeller ? __('govuk_alpha_commerce.orders_seller.caption', ['community' => $communityName]) : __('govuk_alpha_commerce.orders_buyer.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $isSeller ? __('govuk_alpha_commerce.orders_seller.title') : __('govuk_alpha_commerce.orders_buyer.title') }}</h1>
    <p class="govuk-body-l">{{ $isSeller ? __('govuk_alpha_commerce.orders_seller.description') : __('govuk_alpha_commerce.orders_buyer.description') }}</p>

    @if (($status ?? null) !== null && isset($statusMessages[$status]))
        <div class="govuk-notification-banner{{ \Illuminate\Support\Str::contains($status, ['failed', 'invalid']) ? '' : ' govuk-notification-banner--success' }}" role="region" aria-labelledby="commerce-orders-status" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-orders-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <nav class="govuk-tabs govuk-!-margin-bottom-4" aria-label="{{ $isSeller ? __('govuk_alpha_commerce.orders_seller.title') : __('govuk_alpha_commerce.orders_buyer.title') }}">
        <ul class="govuk-tabs__list">
            @foreach ($tabKeys as $tabKey)
                <li class="govuk-tabs__list-item{{ $activeTab === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ route($tabRoute, ['tenantSlug' => $tenantSlug, 'tab' => $tabKey]) }}" @if ($activeTab === $tabKey) aria-current="page" @endif>{{ __('govuk_alpha_commerce.orders.tab_' . $tabKey) }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if (empty($orders))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.orders.empty') }}</p></div>
    @else
        @foreach ($orders as $order)
            @php
                $oNumber = (string) ($order['order_number'] ?? $order['id'] ?? '');
                $oStatus = (string) ($order['status'] ?? '');
                $oTitle = trim((string) ($order['listing']['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
                $total = trim(trim((string) ($order['currency'] ?? '')) . ' ' . number_format((float) ($order['total_price'] ?? 0), 2));
                $counterparty = $isSeller ? ($order['buyer']['name'] ?? '') : ($order['seller']['name'] ?? '');
                $tracking = trim((string) ($order['tracking_number'] ?? ''));
                $orderId = (int) ($order['id'] ?? 0);
                $alreadyRated = false;
                foreach ((array) ($order['ratings'] ?? []) as $r) {
                    if (($r['rater_role'] ?? '') === ($isSeller ? 'seller' : 'buyer')) { $alreadyRated = true; }
                }
            @endphp
            <section class="nexus-alpha-card govuk-!-margin-bottom-6">
                <div class="nexus-alpha-module-row">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_commerce.orders.order_number', ['number' => $oNumber]) }}</h2>
                    <strong class="govuk-tag {{ in_array($oStatus, ['completed', 'delivered'], true) ? 'govuk-tag--green' : (in_array($oStatus, ['cancelled'], true) ? 'govuk-tag--red' : 'govuk-tag--blue') }}">{{ $statusLabels[$oStatus] ?? $oStatus }}</strong>
                </div>
                <dl class="govuk-summary-list govuk-summary-list--no-border govuk-!-margin-bottom-2">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.buy.item_label') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $oTitle }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.orders.total_label') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $total }}</dd>
                    </div>
                    @if (trim((string) $counterparty) !== '')
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ $isSeller ? __('govuk_alpha_commerce.orders.buyer_label') : __('govuk_alpha_commerce.orders.seller_label') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $counterparty }}</dd>
                        </div>
                    @endif
                    @if ($tracking !== '')
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.orders.tracking_label') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $tracking }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Seller: mark shipped when paid --}}
                @if ($isSeller && in_array($oStatus, ['paid', 'shipped'], true))
                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_commerce.orders.action_ship') }}</span></summary>
                        <div class="govuk-details__text">
                            <form method="post" action="{{ route('govuk-alpha.marketplace.orders.ship', ['tenantSlug' => $tenantSlug, 'id' => $orderId]) }}">
                                @csrf
                                <div class="govuk-form-group">
                                    <label class="govuk-label govuk-label--s" for="tracking_number_{{ $orderId }}">{{ __('govuk_alpha_commerce.orders.ship_tracking_label') }}</label>
                                    <input class="govuk-input govuk-input--width-20" id="tracking_number_{{ $orderId }}" name="tracking_number" type="text" maxlength="255">
                                </div>
                                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.orders.action_ship') }}</button>
                            </form>
                        </div>
                    </details>
                @endif

                {{-- Buyer: confirm delivery when shipped/paid/delivered --}}
                @if (!$isSeller && in_array($oStatus, ['shipped', 'paid', 'delivered'], true))
                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                            {{ __('govuk_alpha_commerce.orders.confirm_warning') }}
                        </strong>
                    </div>
                    <form method="post" action="{{ route('govuk-alpha.marketplace.orders.confirm', ['tenantSlug' => $tenantSlug, 'id' => $orderId]) }}" class="govuk-!-margin-bottom-2">
                        @csrf
                        <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.orders.action_confirm') }}</button>
                    </form>
                @endif

                {{-- Cancel (both, before shipping) --}}
                @if (in_array($oStatus, ['pending_payment', 'paid'], true))
                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_commerce.orders.action_cancel') }}</span></summary>
                        <div class="govuk-details__text">
                            <div class="govuk-warning-text">
                                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                <strong class="govuk-warning-text__text">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                                    {{ __('govuk_alpha_commerce.orders.cancel_warning') }}
                                </strong>
                            </div>
                            <form method="post" action="{{ route('govuk-alpha.marketplace.orders.cancel', ['tenantSlug' => $tenantSlug, 'id' => $orderId]) }}">
                                @csrf
                                <div class="govuk-form-group">
                                    <label class="govuk-label govuk-label--s" for="reason_{{ $orderId }}">{{ __('govuk_alpha_commerce.orders.cancel_reason_label') }}</label>
                                    <input class="govuk-input" id="reason_{{ $orderId }}" name="reason" type="text" maxlength="500">
                                </div>
                                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.orders.action_cancel') }}</button>
                            </form>
                        </div>
                    </details>
                @endif

                {{-- Rate (both, when completed/delivered and not yet rated) --}}
                @if (in_array($oStatus, ['completed', 'delivered'], true) && !$alreadyRated)
                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_commerce.orders.action_rate') }}</span></summary>
                        <div class="govuk-details__text">
                            <form method="post" action="{{ route('govuk-alpha.marketplace.orders.rate', ['tenantSlug' => $tenantSlug, 'id' => $orderId]) }}">
                                @csrf
                                <div class="govuk-form-group">
                                    <fieldset class="govuk-fieldset">
                                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.orders.rate_label') }}</legend>
                                        <div class="govuk-radios govuk-radios--inline govuk-radios--small" data-module="govuk-radios">
                                            @foreach ([5, 4, 3, 2, 1] as $star)
                                                <div class="govuk-radios__item">
                                                    <input class="govuk-radios__input" id="rating_{{ $orderId }}_{{ $star }}" name="rating" type="radio" value="{{ $star }}">
                                                    <label class="govuk-label govuk-radios__label" for="rating_{{ $orderId }}_{{ $star }}">{{ __('govuk_alpha_commerce.orders.rate_stars', ['count' => $star]) }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                </div>
                                <div class="govuk-form-group">
                                    <label class="govuk-label govuk-label--s" for="comment_{{ $orderId }}">{{ __('govuk_alpha_commerce.orders.rate_comment_label') }}</label>
                                    <textarea class="govuk-textarea" id="comment_{{ $orderId }}" name="comment" rows="2"></textarea>
                                </div>
                                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.orders.action_rate') }}</button>
                            </form>
                        </div>
                    </details>
                @endif
            </section>
        @endforeach
    @endif
@endsection
