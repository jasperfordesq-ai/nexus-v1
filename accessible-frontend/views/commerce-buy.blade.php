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
        $timeCredits = (float) ($item['time_credit_price'] ?? 0);
        $formatMoney = static fn (float $amount, mixed $currency): string =>
            \App\Support\MarketplaceMoneyFormatter::format($amount, (string) $currency);
        $isHybridOrder = $money > 0 && $timeCredits > 0;
        if ($isHybridOrder) {
            $priceLabel = __('govuk_alpha_commerce.buy.hybrid_price', [
                'money' => $formatMoney($money, $item['price_currency'] ?? ''),
                'credits' => rtrim(rtrim(number_format($timeCredits, 2), '0'), '.'),
            ]);
        } elseif ($timeCredits > 0) {
            $priceLabel = rtrim(rtrim(number_format($timeCredits, 2), '0'), '.') . ' ' . __('govuk_alpha.marketplace.credits_label');
        } elseif ($money > 0) {
            $priceLabel = $formatMoney($money, $item['price_currency'] ?? '');
        } else {
            $priceLabel = __('govuk_alpha.marketplace.free');
        }
        $selectedPaymentMethod = (string) old('payment_method', $isHybridOrder ? '' : ($money > 0 ? 'cash' : ($timeCredits > 0 ? 'time_credits' : 'free')));
        $isCashOrder = $selectedPaymentMethod === 'cash';
        $seller = trim((string) ($item['user']['name'] ?? ''));
        $buyError = session('commerceBuyError');
        $acceptedOfferCheckout = (bool) ($isAcceptedOffer ?? false);
        $deliveryMethod = (string) ($item['delivery_method'] ?? 'pickup');
        $shippingOptions = array_values(array_filter((array) ($shippingOptions ?? []), 'is_array'));
        $pickupSlots = array_values(array_filter((array) ($pickupSlots ?? []), 'is_array'));
        $requiresDeliveryChoice = in_array($deliveryMethod, ['shipping', 'both'], true);
        $supportsPickup = $deliveryMethod === 'both';
        $hasDeliveryChoice = !$requiresDeliveryChoice || $supportsPickup || count($shippingOptions) > 0;
        $selectedDeliveryChoice = (string) old('delivery_choice', '');
        $selectedPickupSlot = (int) old('pickup_slot_id', 0);
        $errorTarget = (string) session(
            'commerceBuyErrorTarget',
            count($pickupSlots) > 0 && $deliveryMethod === 'pickup'
                ? 'pickup_slot_id'
                : ($requiresDeliveryChoice ? 'delivery_choice' : ($isHybridOrder ? 'payment_method' : 'quantity')),
        );
        $formActionUrl = (string) ($formAction ?? route('govuk-alpha.marketplace.buy.store', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]));
        $backHref = (string) ($backUrl ?? route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]));
        $backText = (string) ($backLabel ?? __('govuk_alpha_commerce.common.back_to_listing'));
        $cancelHref = (string) ($cancelUrl ?? $backHref);
        $formatSlot = static function (mixed $value): string {
            try {
                return $value
                    ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, H:i')
                    : '';
            } catch (\Throwable $e) {
                return '';
            }
        };
    @endphp

    <a href="{{ $backHref }}" class="govuk-back-link">{{ $backText }}</a>

    @if ($buyError)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#{{ $errorTarget }}">{{ $buyError }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.buy.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $acceptedOfferCheckout ? __('govuk_alpha_commerce.buy.accepted_offer_title') : __('govuk_alpha_commerce.buy.title') }}</h1>
    <p class="govuk-body-l">{{ $acceptedOfferCheckout ? __('govuk_alpha_commerce.buy.accepted_offer_description') : __('govuk_alpha_commerce.buy.description') }}</p>

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

    @if ($isCashOrder)
    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
            {{ __('govuk_alpha_commerce.buy.warning') }}
        </strong>
    </div>
    @endif

    <form method="post" action="{{ $formActionUrl }}" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        @if ($isHybridOrder)
            <div class="govuk-form-group @if($buyError && $errorTarget === 'payment_method') govuk-form-group--error @endif">
                <fieldset class="govuk-fieldset" aria-describedby="payment_method-hint @if($buyError && $errorTarget === 'payment_method') payment_method-error @endif">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.buy.payment_method_label') }}</legend>
                    <div id="payment_method-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.buy.payment_method_hint') }}</div>
                    @if ($buyError && $errorTarget === 'payment_method')
                        <p id="payment_method-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_title') }}:</span> {{ $buyError }}</p>
                    @endif
                    <div class="govuk-radios" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="payment_method" name="payment_method" type="radio" value="cash" @checked($selectedPaymentMethod === 'cash')>
                            <label class="govuk-label govuk-radios__label" for="payment_method">{{ __('govuk_alpha_commerce.buy.pay_with_money', ['amount' => $formatMoney($money, $item['price_currency'] ?? '')]) }}</label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="payment_method-time-credits" name="payment_method" type="radio" value="time_credits" @checked($selectedPaymentMethod === 'time_credits')>
                            <label class="govuk-label govuk-radios__label" for="payment_method-time-credits">{{ __('govuk_alpha_commerce.buy.pay_with_time_credits', ['count' => rtrim(rtrim(number_format($timeCredits, 2), '0'), '.')]) }}</label>
                        </div>
                    </div>
                </fieldset>
            </div>
        @endif
        @if ($acceptedOfferCheckout)
            <input type="hidden" name="quantity" value="1">
        @else
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="quantity">{{ __('govuk_alpha_commerce.buy.quantity_label') }}</label>
                <input class="govuk-input govuk-input--width-5" id="quantity" name="quantity" type="number" min="1" step="1" inputmode="numeric" value="{{ old('quantity', 1) }}">
            </div>
        @endif
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="delivery_notes">{{ __('govuk_alpha_commerce.buy.delivery_notes_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="delivery_notes-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.buy.delivery_notes_hint') }}</div>
            <textarea class="govuk-textarea" id="delivery_notes" name="delivery_notes" rows="3" aria-describedby="delivery_notes-hint">{{ old('delivery_notes') }}</textarea>
        </div>
        @if ($requiresDeliveryChoice)
            <div class="govuk-form-group @if($buyError && $errorTarget === 'delivery_choice') govuk-form-group--error @endif">
                <fieldset class="govuk-fieldset" aria-describedby="delivery_choice-hint @if($buyError && $errorTarget === 'delivery_choice') delivery_choice-error @endif">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.buy.delivery_option_label') }}</legend>
                    <div id="delivery_choice-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.buy.delivery_option_hint') }}</div>
                    @if ($buyError && $errorTarget === 'delivery_choice')
                        <p id="delivery_choice-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_title') }}:</span> {{ $buyError }}</p>
                    @endif
                    <div class="govuk-radios" data-module="govuk-radios">
                        @if ($supportsPickup)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="delivery_choice" name="delivery_choice" type="radio" value="pickup" @checked($selectedDeliveryChoice === 'pickup')>
                                <label class="govuk-label govuk-radios__label" for="delivery_choice">{{ __('govuk_alpha_commerce.listing_form.delivery_pickup') }}</label>
                            </div>
                        @endif
                        @foreach ($shippingOptions as $optionIndex => $option)
                            @php
                                $optionId = (int) ($option['id'] ?? 0);
                                $optionValue = 'shipping:' . $optionId;
                                $optionName = trim((string) ($option['courier_name'] ?? '')) ?: __('govuk_alpha_commerce.listing_form.delivery_shipping');
                                $optionCurrency = trim((string) ($option['currency'] ?? ''));
                                $optionPrice = $formatMoney((float) ($option['price'] ?? 0), $optionCurrency);
                                $optionInputId = ($supportsPickup || $optionIndex > 0) ? 'delivery_choice-' . $optionId : 'delivery_choice';
                            @endphp
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $optionInputId }}" name="delivery_choice" type="radio" value="{{ $optionValue }}" @checked($selectedDeliveryChoice === $optionValue)>
                                <label class="govuk-label govuk-radios__label" for="{{ $optionInputId }}">{{ $optionName }} — {{ $optionPrice }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                @if (!$hasDeliveryChoice)
                    <p class="govuk-body">{{ __('govuk_alpha_commerce.buy.delivery_unavailable') }}</p>
                @endif
            </div>
        @endif
        @if (count($pickupSlots) > 0)
            <div class="govuk-form-group @if($buyError && $errorTarget === 'pickup_slot_id') govuk-form-group--error @endif">
                <fieldset class="govuk-fieldset" aria-describedby="pickup_slot_id-hint @if($buyError && $errorTarget === 'pickup_slot_id') pickup_slot_id-error @endif">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.buy.pickup_slot_label') }}</legend>
                    <div id="pickup_slot_id-hint" class="govuk-hint">{{ $deliveryMethod === 'both' ? __('govuk_alpha_commerce.buy.pickup_slot_both_hint') : __('govuk_alpha_commerce.buy.pickup_slot_hint') }}</div>
                    @if ($buyError && $errorTarget === 'pickup_slot_id')
                        <p id="pickup_slot_id-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_title') }}:</span> {{ $buyError }}</p>
                    @endif
                    <div class="govuk-radios" data-module="govuk-radios">
                        @foreach ($pickupSlots as $slotIndex => $slot)
                            @php
                                $slotId = (int) ($slot['id'] ?? 0);
                                $slotInputId = $slotIndex === 0 ? 'pickup_slot_id' : 'pickup_slot_id-' . $slotId;
                                $slotStart = $formatSlot($slot['slot_start'] ?? null);
                                $slotEnd = $formatSlot($slot['slot_end'] ?? null);
                            @endphp
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $slotInputId }}" name="pickup_slot_id" type="radio" value="{{ $slotId }}" @checked($selectedPickupSlot === $slotId)>
                                <label class="govuk-label govuk-radios__label" for="{{ $slotInputId }}">{{ __('govuk_alpha_commerce.buy.pickup_slot_option', ['start' => $slotStart, 'end' => $slotEnd]) }}</label>
                                <div class="govuk-hint govuk-radios__hint">{{ trans_choice('govuk_alpha_commerce.buy.pickup_slot_remaining', (int) ($slot['remaining'] ?? 0), ['count' => (int) ($slot['remaining'] ?? 0)]) }}</div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
        @endif
        <div class="govuk-button-group">
            @if ($hasDeliveryChoice)
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.buy.confirm') }}</button>
            @endif
            <a class="govuk-link" href="{{ $cancelHref }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection
