{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav')

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.free_items.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.free_items.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.free_items.description') }}</p>

    @if (empty($listings))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.free_items.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $card)
                @include('accessible-frontend::partials.commerce-listing-card', ['card' => $card])
            @endforeach
        </div>
    @endif
@endsection
