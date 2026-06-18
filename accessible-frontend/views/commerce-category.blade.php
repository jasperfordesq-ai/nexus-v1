{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $cat = $category ?? [];
        $catName = (string) ($cat['name'] ?? __('govuk_alpha_commerce.category.title'));
        $listings = $listings ?? [];
        $categoryQuery = trim((string) ($categoryQuery ?? ''));
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.category.back_to_marketplace') }}</a>

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'browse'])

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.category.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $catName }}</h1>
    <p class="govuk-body">{{ trans_choice('govuk_alpha_commerce.category.count', count($listings), ['count' => count($listings)]) }}</p>

    <form method="get" action="{{ route('govuk-alpha.marketplace.category', ['tenantSlug' => $tenantSlug, 'slug' => $categorySlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha_commerce.category.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.category.search_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="q" name="q" type="search" value="{{ $categoryQuery }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.category.search_submit') }}</button>
    </form>

    @if (empty($listings))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.category.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $card)
                @include('accessible-frontend::partials.commerce-listing-card', ['card' => $card])
            @endforeach
        </div>
    @endif
@endsection
