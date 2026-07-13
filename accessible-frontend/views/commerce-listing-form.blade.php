{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $isEdit = ($mode ?? 'create') === 'edit';
        $l = $listing ?? null;
        $oldVal = function (string $key, $fallback = '') use ($l) {
            $current = old($key);
            if ($current !== null) {
                return $current;
            }
            if (is_array($l) && array_key_exists($key, $l)) {
                return $l[$key];
            }
            return $fallback;
        };
        $formErrors = session('commerceListingErrors', []);
        $conditionLabels = [
            'new' => __('govuk_alpha_commerce.listing_form.condition_new'),
            'like_new' => __('govuk_alpha_commerce.listing_form.condition_like_new'),
            'good' => __('govuk_alpha_commerce.listing_form.condition_good'),
            'fair' => __('govuk_alpha_commerce.listing_form.condition_fair'),
            'poor' => __('govuk_alpha_commerce.listing_form.condition_poor'),
        ];
        $deliveryLabels = [
            'pickup' => __('govuk_alpha_commerce.listing_form.delivery_pickup'),
            'shipping' => __('govuk_alpha_commerce.listing_form.delivery_shipping'),
            'both' => __('govuk_alpha_commerce.listing_form.delivery_both'),
        ];
        $priceTypeLabels = [
            'fixed' => __('govuk_alpha_commerce.listing_form.price_type_fixed'),
            'negotiable' => __('govuk_alpha_commerce.listing_form.price_type_negotiable'),
            'free' => __('govuk_alpha_commerce.listing_form.price_type_free'),
            'contact' => __('govuk_alpha_commerce.listing_form.price_type_contact'),
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.back_to_my_listings') }}</a>

    @include('accessible-frontend::partials.commerce-marketplace-nav')

    @if (!empty($formErrors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($formErrors as $msg)
                            <li><a href="#title">{{ $msg }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.listing_form.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $isEdit ? __('govuk_alpha_commerce.listing_form.title_edit') : __('govuk_alpha_commerce.listing_form.title_create') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.listing_form.description') }}</p>

    <form method="post" action="{{ $formAction }}" novalidate>
        @csrf

        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.listing_form.section_about') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="title">{{ __('govuk_alpha_commerce.listing_form.title_label') }}</label>
                <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.title_hint') }}</div>
                <input class="govuk-input" id="title" name="title" type="text" maxlength="200" value="{{ $oldVal('title') }}" aria-describedby="title-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="tagline">{{ __('govuk_alpha_commerce.listing_form.tagline_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="tagline-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.tagline_hint') }}</div>
                <input class="govuk-input" id="tagline" name="tagline" type="text" maxlength="300" value="{{ $oldVal('tagline') }}" aria-describedby="tagline-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.listing_form.description_label') }}</label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.description_hint') }}</div>
                <textarea class="govuk-textarea" id="description" name="description" rows="6" aria-describedby="description-hint">{{ $oldVal('description') }}</textarea>
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-top-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.listing_form.section_price') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-describedby="price_type-hint">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.listing_form.price_type_label') }}</legend>
                    <div id="price_type-hint" class="govuk-hint govuk-visually-hidden">{{ __('govuk_alpha_commerce.listing_form.price_type_label') }}</div>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($priceTypes ?? ['fixed', 'negotiable', 'free', 'contact']) as $idx => $pt)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'price_type' : 'price_type-' . $pt }}" name="price_type" type="radio" value="{{ $pt }}" @checked((string) $oldVal('price_type', 'fixed') === $pt)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'price_type' : 'price_type-' . $pt }}">{{ $priceTypeLabels[$pt] ?? $pt }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="price">{{ __('govuk_alpha_commerce.listing_form.price_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                        <div id="price-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.price_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="price" name="price" type="text" inputmode="decimal" value="{{ $oldVal('price') }}" aria-describedby="price-hint">
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="price_currency">{{ __('govuk_alpha_commerce.listing_form.currency_label') }}</label>
                        <input class="govuk-input govuk-input--width-5" id="price_currency" name="price_currency" type="text" maxlength="3" value="{{ $oldVal('price_currency', $defaultCurrency ?? 'EUR') }}">
                    </div>
                </div>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="time_credit_price">{{ __('govuk_alpha_commerce.listing_form.time_credit_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="time_credit_price-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.time_credit_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="time_credit_price" name="time_credit_price" type="text" inputmode="decimal" value="{{ $oldVal('time_credit_price') }}" aria-describedby="time_credit_price-hint">
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-top-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.listing_form.section_details') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="condition">{{ __('govuk_alpha_commerce.listing_form.condition_label') }}</label>
                <select class="govuk-select" id="condition" name="condition">
                    <option value="">{{ __('govuk_alpha_commerce.listing_form.condition_none') }}</option>
                    @foreach (($conditions ?? array_keys($conditionLabels)) as $cond)
                        <option value="{{ $cond }}" @selected((string) $oldVal('condition') === $cond)>{{ $conditionLabels[$cond] ?? $cond }}</option>
                    @endforeach
                </select>
            </div>

            @if (!empty($categories))
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="category_id">{{ __('govuk_alpha_commerce.listing_form.category_label') }}</label>
                    <select class="govuk-select" id="category_id" name="category_id">
                        <option value="">{{ __('govuk_alpha_commerce.listing_form.category_none') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ (int) $cat['id'] }}" @selected((string) $oldVal('category_id') === (string) $cat['id'])>{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.listing_form.delivery_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($deliveryMethods ?? array_keys($deliveryLabels)) as $idx => $dm)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'delivery_method' : 'delivery_method-' . $dm }}" name="delivery_method" type="radio" value="{{ $dm }}" @checked((string) $oldVal('delivery_method', 'pickup') === $dm)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'delivery_method' : 'delivery_method-' . $dm }}">{{ $deliveryLabels[$dm] ?? $dm }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="location">{{ __('govuk_alpha_commerce.listing_form.location_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.listing_form.location_hint') }}</div>
                <input class="govuk-input" id="location" name="location" type="text" maxlength="255" value="{{ $oldVal('location') }}" autocomplete="off" aria-describedby="location-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="quantity">{{ __('govuk_alpha_commerce.listing_form.quantity_label') }}</label>
                <input class="govuk-input govuk-input--width-5" id="quantity" name="quantity" type="number" min="1" step="1" inputmode="numeric" value="{{ $oldVal('quantity', 1) }}">
            </div>
        </fieldset>

        <div class="govuk-button-group govuk-!-margin-top-4">
            <button class="govuk-button" data-module="govuk-button">{{ $isEdit ? __('govuk_alpha_commerce.listing_form.submit_edit') : __('govuk_alpha_commerce.listing_form.submit_create') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection
