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
        $askingPrice = $money > 0
            ? \App\Support\MarketplaceMoneyFormatter::format(
                $money,
                (string) ($item['price_currency'] ?? ''),
            )
            : '';
        $offerErrors = session('commerceOfferErrors', []);
    @endphp

    <a href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_listing') }}</a>

    @if (!empty($offerErrors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($offerErrors as $msg)
                            <li><a href="#amount">{{ $msg }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.offer.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.offer.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.offer.description') }}</p>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.offer.item_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $iTitle }}</dd>
        </div>
        @if ($askingPrice !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.offer.asking_price_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $askingPrice }}</dd>
            </div>
        @endif
    </dl>

    <form method="post" action="{{ route('govuk-alpha.marketplace.offer.store', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" novalidate>
        @csrf
        <div class="govuk-form-group{{ !empty($offerErrors) ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="amount">{{ __('govuk_alpha_commerce.offer.amount_label') }}</label>
            <div id="amount-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.offer.amount_hint') }}</div>
            @if (!empty($offerErrors))
                <p id="amount-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_prefix') }}</span> {{ $offerErrors[0] }}</p>
            @endif
            <input class="govuk-input govuk-input--width-10{{ !empty($offerErrors) ? ' govuk-input--error' : '' }}" id="amount" name="amount" type="text" inputmode="decimal" value="{{ old('amount') }}" aria-describedby="amount-hint{{ !empty($offerErrors) ? ' amount-error' : '' }}">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="message">{{ __('govuk_alpha_commerce.offer.message_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.offer.message_hint') }}</div>
            <textarea class="govuk-textarea" id="message" name="message" rows="3" aria-describedby="message-hint">{{ old('message') }}</textarea>
        </div>
        <div class="govuk-button-group">
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.offer.submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection
