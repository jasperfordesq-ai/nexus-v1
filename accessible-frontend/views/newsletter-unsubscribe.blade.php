{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            @if ($state === 'success')
                <div class="govuk-panel govuk-panel--confirmation">
                    <h1 class="govuk-panel__title">{{ __('govuk_alpha.auth.unsubscribe_success_title') }}</h1>
                    <div class="govuk-panel__body">{{ __('govuk_alpha.auth.unsubscribe_success_body') }}</div>
                </div>
                <p class="govuk-body govuk-!-margin-top-6">{{ __('govuk_alpha.auth.unsubscribe_success_detail') }}</p>
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.unsubscribe_back_home') }}</a>
                </p>
            @else
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.unsubscribe_title') }}</h1>
                <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="unsubscribe-banner-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="unsubscribe-banner-title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">
                            @if ($state === 'missing')
                                {{ __('govuk_alpha.auth.unsubscribe_missing') }}
                            @else
                                {{ __('govuk_alpha.auth.unsubscribe_invalid') }}
                            @endif
                        </p>
                    </div>
                </div>
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.unsubscribe_back_home') }}</a>
                </p>
            @endif
        </div>
    </div>
@endsection
