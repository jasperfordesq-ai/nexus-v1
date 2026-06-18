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

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.saved.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.saved.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.saved.description') }}</p>

    @if (session('commerce_status') === 'unsaved')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="commerce-saved-status" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-saved-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_commerce.saved.status_unsaved') }}</p>
            </div>
        </div>
    @endif

    @if (empty($listings))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.saved.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $card)
                @include('accessible-frontend::partials.commerce-listing-card', ['card' => $card])
                <form method="post" action="{{ route('govuk-alpha.marketplace.unsave', ['tenantSlug' => $tenantSlug, 'id' => $card['id']]) }}" class="govuk-!-margin-bottom-6">
                    @csrf
                    <input type="hidden" name="redirect_to" value="saved">
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.saved.remove') }}</button>
                </form>
            @endforeach
        </div>
    @endif
@endsection
