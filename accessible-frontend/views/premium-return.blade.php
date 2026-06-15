{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $tierName = trim((string) ($tierName ?? ''));
    @endphp

    <a href="{{ route('govuk-alpha.premium.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.polish_commerce.premium_return_back') }}</a>

    @if ($returnStatus === 'success')
        <div class="govuk-panel govuk-panel--confirmation">
            <h1 class="govuk-panel__title">{{ __('govuk_alpha.polish_commerce.premium_success_title') }}</h1>
            <div class="govuk-panel__body">
                @if ($tierName !== '')
                    {{ __('govuk_alpha.polish_commerce.premium_success_body', ['name' => $tierName]) }}
                @else
                    {{ __('govuk_alpha.polish_commerce.premium_unsubscribed_body') }}
                @endif
            </div>
        </div>
    @elseif ($returnStatus === 'pending')
        <div class="govuk-inset-text">
            <h1 class="govuk-heading-l">{{ __('govuk_alpha.polish_commerce.premium_pending_title') }}</h1>
            <p class="govuk-body">{{ __('govuk_alpha.polish_commerce.premium_pending_body') }}</p>
        </div>
    @else
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.polish_commerce.premium_failed_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.polish_commerce.premium_failed_body') }}</p>
                </div>
            </div>
        </div>
        <h1 class="govuk-heading-l">{{ __('govuk_alpha.polish_commerce.premium_failed_title') }}</h1>
        <p class="govuk-body">{{ __('govuk_alpha.polish_commerce.premium_failed_body') }}</p>
        <div class="govuk-button-group">
            <a class="govuk-button" href="{{ route('govuk-alpha.premium.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.premium.subscribe_button') }}</a>
        </div>
    @endif
@endsection
