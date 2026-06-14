{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.optin.back') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.optin.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.optin.title') }}</h1>

            @if (($status ?? '') === 'unavailable')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>{{ __('govuk_alpha.federation.optin.unavailable') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <p class="govuk-body-l">{{ __('govuk_alpha.federation.optin.description') }}</p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.federation.optin.intro_heading') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.federation.optin.intro_body') }}</p>

            <form method="post" action="{{ route('govuk-alpha.federation.opt-in.store', ['tenantSlug' => $tenantSlug]) }}">
                @csrf
                <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.federation.optin.submit') }}</button>
            </form>
        </div>
    </div>
@endsection
