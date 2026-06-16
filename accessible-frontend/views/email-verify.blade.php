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
                    <h1 class="govuk-panel__title">{{ __('govuk_alpha.auth.verify_email_success_title') }}</h1>
                    <div class="govuk-panel__body">{{ __('govuk_alpha.auth.verify_email_success_body') }}</div>
                </div>
                <p class="govuk-body govuk-!-margin-top-6">
                    <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.verify_email_sign_in') }}</a>
                </p>
            @else
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.verify_email_title') }}</h1>
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>
                                    @if ($state === 'missing')
                                        {{ __('govuk_alpha.auth.verify_email_missing') }}
                                    @else
                                        {{ __('govuk_alpha.auth.verify_email_invalid') }}
                                    @endif
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="govuk-body">{{ __('govuk_alpha.auth.verify_email_resend_hint') }}</p>
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.verify_email_back_to_sign_in') }}</a>
                </p>
            @endif
        </div>
    </div>
@endsection
