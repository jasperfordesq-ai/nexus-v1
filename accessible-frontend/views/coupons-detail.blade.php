{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $cTitle = trim((string) ($coupon['title'] ?? '')) ?: trim((string) ($coupon['code'] ?? '')) ?: __('govuk_alpha.coupons.title');
        $code   = trim((string) ($coupon['code'] ?? ''));
        $discountType  = (string) ($coupon['discount_type'] ?? '');
        $discountValue = $coupon['discount_value'] ?? null;
        $discountLabel = '';
        if ($discountValue !== null && $discountValue !== '') {
            $num = rtrim(rtrim(number_format((float) $discountValue, 2), '0'), '.');
            $discountLabel = ($discountType === 'percentage' || $discountType === 'percent')
                ? __('govuk_alpha.coupons.percent_off', ['value' => $num])
                : __('govuk_alpha.coupons.amount_off', ['value' => $num]);
        }
        $validUntil = $coupon['valid_until'] ?? null;
        $validUntilFmt = $validUntil ? \Illuminate\Support\Carbon::parse($validUntil)->translatedFormat('j F Y') : null;
        $merchantName = trim((string) ($coupon['merchant']['name'] ?? ($coupon['merchant_name'] ?? '')));
    @endphp

    <a href="{{ route('govuk-alpha.coupons.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.polish_commerce.coupon_detail_back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.coupons.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ $cTitle }}</h1>

    @if ($discountLabel !== '')
        <p><strong class="govuk-tag govuk-tag--green govuk-!-margin-bottom-4">{{ $discountLabel }}</strong></p>
    @endif

    @if (trim((string) ($coupon['description'] ?? '')) !== '')
        <div class="govuk-body govuk-!-margin-bottom-4">{!! nl2br(e(strip_tags((string) $coupon['description']))) !!}</div>
    @endif

    @if ($code !== '')
        <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-bottom-4">
            <h2 class="govuk-panel__title">{{ __('govuk_alpha.polish_commerce.coupon_code_panel_title') }}</h2>
            <div class="govuk-panel__body">
                <strong>{{ $code }}</strong>
            </div>
        </div>
    @endif

    <h2 class="govuk-heading-m">{{ __('govuk_alpha.polish_commerce.coupon_redemption_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.polish_commerce.coupon_redemption_body') }}</p>

    <dl class="govuk-summary-list">
        @if ($merchantName !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.coupons.merchant_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $merchantName }}</dd>
            </div>
        @endif
        @if ($validUntilFmt !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.coupons.valid_until_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $validUntilFmt }}</dd>
            </div>
        @endif
    </dl>
@endsection
