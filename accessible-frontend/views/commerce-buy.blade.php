{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $iTitle = trim((string) ($item['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
        $money = (float) ($item['price'] ?? 0);
        $priceLabel = trim(trim((string) ($item['price_currency'] ?? '')) . ' ' . number_format($money, 2));
        $seller = trim((string) ($item['user']['name'] ?? ''));
        $buyError = session('commerceBuyError');
    @endphp

    <a href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_listing') }}</a>

    @if ($buyError)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#quantity">{{ $buyError }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.buy.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.buy.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.buy.description') }}</p>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.buy.item_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $iTitle }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.buy.price_label') }}</dt>
            <dd class="govuk-summary-list__value"><strong class="govuk-tag govuk-tag--grey">{{ $priceLabel }}</strong></dd>
        </div>
        @if ($seller !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.buy.seller_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $seller }}</dd>
            </div>
        @endif
    </dl>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
            {{ __('govuk_alpha_commerce.buy.warning') }}
        </strong>
    </div>

    <form method="post" action="{{ route('govuk-alpha.marketplace.buy.store', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" novalidate>
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="quantity">{{ __('govuk_alpha_commerce.buy.quantity_label') }}</label>
            <input class="govuk-input govuk-input--width-5" id="quantity" name="quantity" type="number" min="1" step="1" inputmode="numeric" value="{{ old('quantity', 1) }}">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="delivery_notes">{{ __('govuk_alpha_commerce.buy.delivery_notes_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="delivery_notes-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.buy.delivery_notes_hint') }}</div>
            <textarea class="govuk-textarea" id="delivery_notes" name="delivery_notes" rows="3" aria-describedby="delivery_notes-hint">{{ old('delivery_notes') }}</textarea>
        </div>
        <div class="govuk-button-group">
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.buy.confirm') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection
