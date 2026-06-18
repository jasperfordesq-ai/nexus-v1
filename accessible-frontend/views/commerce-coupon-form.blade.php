{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $isEdit = ($mode ?? 'create') === 'edit';
        $c = $coupon ?? null;
        $oldVal = function (string $key, $fallback = '') use ($c) {
            $current = old($key);
            if ($current !== null) {
                return $current;
            }
            if (is_array($c) && array_key_exists($key, $c)) {
                return $c[$key];
            }
            return $fallback;
        };
        $formErrors = session('commerceCouponErrors', []);
        $discountTypeLabels = [
            'percent' => __('govuk_alpha_commerce.coupons.discount_type_percent'),
            'fixed' => __('govuk_alpha_commerce.coupons.discount_type_fixed'),
            'bogo' => __('govuk_alpha_commerce.coupons.discount_type_bogo'),
        ];
        $statusLabels = [
            'draft' => __('govuk_alpha_commerce.coupons.status_draft'),
            'active' => __('govuk_alpha_commerce.coupons.status_active'),
            'paused' => __('govuk_alpha_commerce.coupons.status_paused'),
            'expired' => __('govuk_alpha_commerce.coupons.status_expired'),
        ];
        $statusMessages = [
            'coupon-saved' => ['msg' => __('govuk_alpha_commerce.coupons.status_coupon_saved'), 'error' => false],
            'coupon-save-failed' => ['msg' => __('govuk_alpha_commerce.coupons.status_coupon_save_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
        $couponId = is_array($c) ? (int) ($c['id'] ?? 0) : 0;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.marketplace.coupons', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.coupons.back_to_coupons') }}</a>

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
                            <li><a href="#coupon_title">{{ $msg }}</a></li>
                        @endforeach
                        @if ($statusEntry !== null && $statusEntry['error'])
                            <li>{{ $statusEntry['msg'] }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ $isEdit ? __('govuk_alpha_commerce.coupons.title_edit') : __('govuk_alpha_commerce.coupons.title_create') }}</h1>

    <form method="post" action="{{ $formAction }}" novalidate>
        @csrf

        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.coupons.section_basics') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="coupon_title">{{ __('govuk_alpha_commerce.coupons.coupon_title_label') }}</label>
                <div id="coupon_title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.coupon_title_hint') }}</div>
                <input class="govuk-input" id="coupon_title" name="title" type="text" maxlength="200" value="{{ $oldVal('title') }}" aria-describedby="coupon_title-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="code">{{ __('govuk_alpha_commerce.coupons.code_form_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="code-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.code_form_hint') }}</div>
                <input class="govuk-input govuk-input--width-20" id="code" name="code" type="text" maxlength="64" value="{{ $oldVal('code') }}" aria-describedby="code-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.coupons.coupon_description_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.coupon_description_hint') }}</div>
                <textarea class="govuk-textarea" id="description" name="description" rows="3" aria-describedby="description-hint">{{ $oldVal('description') }}</textarea>
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-top-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.coupons.section_discount') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.coupons.discount_type_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($discountTypes ?? array_keys($discountTypeLabels)) as $idx => $dt)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'discount_type' : 'discount_type-' . $dt }}" name="discount_type" type="radio" value="{{ $dt }}" @checked((string) $oldVal('discount_type', 'percent') === $dt)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'discount_type' : 'discount_type-' . $dt }}">{{ $discountTypeLabels[$dt] ?? $dt }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="discount_value">{{ __('govuk_alpha_commerce.coupons.discount_value_label') }}</label>
                <div id="discount_value-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.discount_value_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="discount_value" name="discount_value" type="text" inputmode="decimal" value="{{ $oldVal('discount_value', '') }}" aria-describedby="discount_value-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="min_order_cents">{{ __('govuk_alpha_commerce.coupons.min_order_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="min_order_cents-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.min_order_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="min_order_cents" name="min_order_cents" type="text" inputmode="numeric" value="{{ $oldVal('min_order_cents', '') }}" aria-describedby="min_order_cents-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="max_uses">{{ __('govuk_alpha_commerce.coupons.max_uses_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="max_uses-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.max_uses_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="max_uses" name="max_uses" type="text" inputmode="numeric" value="{{ $oldVal('max_uses', '') }}" aria-describedby="max_uses-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="valid_until">{{ __('govuk_alpha_commerce.coupons.valid_until_form_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="valid_until-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.coupons.valid_until_form_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="valid_until" name="valid_until" type="date" value="{{ $oldVal('valid_until', '') }}" aria-describedby="valid_until-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="status">{{ __('govuk_alpha_commerce.coupons.status_form_label') }}</label>
                <select class="govuk-select" id="status" name="status">
                    @foreach (($statuses ?? array_keys($statusLabels)) as $st)
                        <option value="{{ $st }}" @selected((string) $oldVal('status', 'draft') === $st)>{{ $statusLabels[$st] ?? $st }}</option>
                    @endforeach
                </select>
            </div>
        </fieldset>

        <div class="govuk-button-group govuk-!-margin-top-4">
            <button class="govuk-button" data-module="govuk-button">{{ $isEdit ? __('govuk_alpha_commerce.coupons.submit_edit') : __('govuk_alpha_commerce.coupons.submit_create') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.coupons', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>

    @if ($isEdit && $couponId > 0)
        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.coupons.delete_heading') }}</h2>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                {{ __('govuk_alpha_commerce.coupons.delete_warning') }}
            </strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.marketplace.coupons.delete', ['tenantSlug' => $tenantSlug, 'id' => $couponId]) }}">
            @csrf
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.coupons.action_delete') }}</button>
        </form>
    @endif
@endsection
