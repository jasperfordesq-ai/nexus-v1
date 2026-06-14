{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $iTitle = trim((string) ($item['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
        $tc = (float) ($item['time_credit_price'] ?? 0);
        $money = (float) ($item['price'] ?? 0);
        if ($tc > 0) {
            $priceLabel = rtrim(rtrim(number_format($tc, 2), '0'), '.') . ' ' . __('govuk_alpha.marketplace.credits_label');
        } elseif ($money > 0) {
            $priceLabel = trim(trim((string) ($item['price_currency'] ?? '')) . ' ' . number_format($money, 2));
        } else {
            $priceLabel = __('govuk_alpha.marketplace.free');
        }
        // Images: detail returns an `images` array; fall back to the single `image`.
        $images = [];
        foreach ((array) ($item['images'] ?? []) as $img) {
            $u = $asUrl(trim((string) (is_array($img) ? ($img['url'] ?? '') : $img)));
            if ($u !== '') { $images[] = $u; }
        }
        if (empty($images)) {
            $u = $asUrl(trim((string) ($item['image']['url'] ?? '')));
            if ($u !== '') { $images[] = $u; }
        }
        $seller = trim((string) ($item['user']['name'] ?? ($item['seller_type'] ?? '')));
        $loc = trim((string) ($item['location'] ?? ''));
        $condition = trim((string) ($item['condition'] ?? ''));
        $delivery = trim((string) ($item['delivery_method'] ?? ''));
    @endphp

    <a href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.marketplace.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.marketplace.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $iTitle }}</h1>
        <strong class="govuk-tag govuk-tag--green">{{ $priceLabel }}</strong>
    </div>

    @if (!empty($images))
        <div class="nexus-alpha-detail-hero">
            <img src="{{ $images[0] }}" alt="{{ $iTitle }}" loading="lazy" decoding="async">
        </div>
    @endif

    @if (trim((string) ($item['description'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.marketplace.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e($item['description'])) !!}</div>
    @endif

    <dl class="govuk-summary-list">
        @if ($condition !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.marketplace.condition_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $condition }}</dd>
            </div>
        @endif
        @if ($loc !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.marketplace.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $loc }}</dd>
            </div>
        @endif
        @if ($delivery !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.marketplace.delivery_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $delivery }}</dd>
            </div>
        @endif
        @if ($seller !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.marketplace.seller_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $seller }}</dd>
            </div>
        @endif
    </dl>
@endsection
