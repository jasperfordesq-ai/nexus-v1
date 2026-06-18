{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $avatar = $asUrl(trim((string) ($sellerAvatar ?? '')));
        $name = trim((string) ($sellerName ?? '')) ?: __('govuk_alpha_commerce.seller.title');
        $sinceFmt = '';
        if (!empty($sellerSince)) {
            try {
                $sinceFmt = \Illuminate\Support\Carbon::parse($sellerSince)->translatedFormat('F Y');
            } catch (\Throwable $e) {
                $sinceFmt = '';
            }
        }
        $rating = $sellerRating ?? null;
        $hasRatings = is_array($rating) && (int) ($rating['total_ratings'] ?? 0) > 0;
    @endphp

    <a href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_marketplace') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.seller.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $name }}</h1>

    <div class="nexus-alpha-card govuk-!-margin-bottom-6">
        @if ($avatar !== '')
            <img class="nexus-alpha-avatar" src="{{ $avatar }}" alt="{{ $name }}" width="64" height="64" loading="lazy" decoding="async">
        @endif
        <dl class="govuk-summary-list govuk-!-margin-bottom-0">
            @if ($sellerVerified ?? false)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.seller.verified') }}</dt>
                    <dd class="govuk-summary-list__value"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_commerce.seller.verified') }}</strong></dd>
                </div>
            @endif
            @if ($sinceFmt !== '')
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.seller.member_since', ['date' => $sinceFmt]) }}</dt>
                    <dd class="govuk-summary-list__value">{{ $sinceFmt }}</dd>
                </div>
            @endif
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.seller.rating_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if ($hasRatings)
                        @php $avg = number_format((float) ($rating['avg_rating'] ?? 0), 1); @endphp
                        <progress value="{{ $avg }}" max="5" aria-label="{{ __('govuk_alpha_commerce.seller.rating', ['rating' => $avg, 'count' => (int) $rating['total_ratings']]) }}">{{ $avg }}</progress>
                        <span>{{ __('govuk_alpha_commerce.seller.rating', ['rating' => $avg, 'count' => (int) $rating['total_ratings']]) }}</span>
                    @else
                        {{ __('govuk_alpha_commerce.seller.no_ratings') }}
                    @endif
                </dd>
            </div>
            @if ($hasRatings && (int) ($rating['total_sales'] ?? 0) > 0)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.seller.total_sales', ['count' => (int) $rating['total_sales']]) }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) $rating['total_sales'] }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha_commerce.seller.listings_heading') }}</h2>
    @if (empty($listings))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.seller.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $card)
                @include('accessible-frontend::partials.commerce-listing-card', ['card' => $card])
            @endforeach
        </div>
    @endif
@endsection
