{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $p = $profile ?? [];
        $addr = $address ?? [];
        $formErrors = session('commerceOnboardingErrors', []);
        $oldVal = function (string $key, $fallback = '') use ($p) {
            $current = old($key);
            if ($current !== null) {
                return $current;
            }
            return $p[$key] ?? $fallback;
        };
        $oldAddr = function (string $key, $fallback = '') use ($addr) {
            $current = old('address_' . $key);
            if ($current !== null) {
                return $current;
            }
            return $addr[$key] ?? $fallback;
        };
        $sellerTypeLabels = [
            'private' => __('govuk_alpha_commerce.onboarding.seller_type_private'),
            'business' => __('govuk_alpha_commerce.onboarding.seller_type_business'),
        ];
        $statusMessages = [
            'onboarding-complete' => ['msg' => __('govuk_alpha_commerce.onboarding.status_complete'), 'error' => false],
            'onboarding-failed' => ['msg' => __('govuk_alpha_commerce.onboarding.status_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.back_to_marketplace') }}</a>

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'sell'])

    @if ($statusEntry !== null && !$statusEntry['error'])
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
            </div>
        </div>
    @endif

    @if (!empty($formErrors) || ($statusEntry !== null && $statusEntry['error']))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($formErrors as $msg)
                            <li><a href="#display_name">{{ $msg }}</a></li>
                        @endforeach
                        @if ($statusEntry !== null && $statusEntry['error'])
                            <li>{{ $statusEntry['msg'] }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.onboarding.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.onboarding.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.onboarding.description') }}</p>

    @if (!empty($completed))
        <div class="govuk-notification-banner" role="region" aria-labelledby="onboarding-complete-banner" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="onboarding-complete-banner">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_commerce.onboarding.completed_banner') }}</p>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('govuk-alpha.marketplace.onboarding.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
        @csrf

        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.onboarding.section_identity') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.onboarding.seller_type_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($sellerTypes ?? array_keys($sellerTypeLabels)) as $idx => $st)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'seller_type' : 'seller_type-' . $st }}" name="seller_type" type="radio" value="{{ $st }}" @checked((string) $oldVal('seller_type', 'business') === $st)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'seller_type' : 'seller_type-' . $st }}">{{ $sellerTypeLabels[$st] ?? $st }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="display_name">{{ __('govuk_alpha_commerce.onboarding.display_name_label') }}</label>
                <div id="display_name-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.onboarding.display_name_hint') }}</div>
                <input class="govuk-input" id="display_name" name="display_name" type="text" maxlength="200" value="{{ $oldVal('display_name') }}" aria-describedby="display_name-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="business_name">{{ __('govuk_alpha_commerce.onboarding.business_name_label') }}</label>
                <div id="business_name-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.onboarding.business_name_hint') }}</div>
                <input class="govuk-input" id="business_name" name="business_name" type="text" maxlength="200" value="{{ $oldVal('business_name') }}" aria-describedby="business_name-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="bio">{{ __('govuk_alpha_commerce.onboarding.bio_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="bio-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.onboarding.bio_hint') }}</div>
                <textarea class="govuk-textarea" id="bio" name="bio" rows="3" aria-describedby="bio-hint">{{ $oldVal('bio') }}</textarea>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="business_registration">{{ __('govuk_alpha_commerce.onboarding.registration_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="business_registration-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.onboarding.registration_hint') }}</div>
                <input class="govuk-input govuk-input--width-20" id="business_registration" name="business_registration" type="text" maxlength="120" value="{{ $oldVal('business_registration') }}" aria-describedby="business_registration-hint">
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-top-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.onboarding.section_location') }}</h2>
            </legend>
            <p class="govuk-body">{{ __('govuk_alpha_commerce.onboarding.location_description') }}</p>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="address_street">{{ __('govuk_alpha_commerce.onboarding.address_street_label') }}</label>
                <input class="govuk-input" id="address_street" name="address_street" type="text" maxlength="200" value="{{ $oldAddr('street') }}" autocomplete="street-address">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="address_city">{{ __('govuk_alpha_commerce.onboarding.address_city_label') }}</label>
                <input class="govuk-input govuk-input--width-20" id="address_city" name="address_city" type="text" maxlength="120" value="{{ $oldAddr('city') }}" autocomplete="address-level2">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="address_postal_code">{{ __('govuk_alpha_commerce.onboarding.address_postal_code_label') }}</label>
                <input class="govuk-input govuk-input--width-10" id="address_postal_code" name="address_postal_code" type="text" maxlength="40" value="{{ $oldAddr('postal_code') }}" autocomplete="postal-code">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="address_country">{{ __('govuk_alpha_commerce.onboarding.address_country_label') }}</label>
                <input class="govuk-input govuk-input--width-20" id="address_country" name="address_country" type="text" maxlength="120" value="{{ $oldAddr('country') }}" autocomplete="country-name">
            </div>
        </fieldset>

        <div class="govuk-button-group govuk-!-margin-top-4">
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.onboarding.submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection
