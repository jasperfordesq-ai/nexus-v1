{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>

            @if (($status ?? null) === 'forgot-sent')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="forgot-sent-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="forgot-sent-title">{{ __('govuk_alpha.auth.forgot_sent_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.forgot_sent_detail') }}</p>
                    </div>
                </div>
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>
                </p>
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.login.forgot', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.forgot_resend') }}</a>
                </p>
            @else
                @if (in_array($status ?? '', ['forgot-invalid', 'forgot-rate-limited'], true))
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert">
                            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                            <div class="govuk-error-summary__body">
                                <ul class="govuk-list govuk-error-summary__list">
                                    <li><a href="#email">{{ ($status ?? '') === 'forgot-rate-limited' ? __('govuk_alpha.auth.forgot_rate_limited') : __('govuk_alpha.auth.forgot_invalid') }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.forgot_title') }}</h1>
                <p class="govuk-body-l">{{ __('govuk_alpha.auth.forgot_description') }}</p>

                <form method="post" action="{{ route('govuk-alpha.login.forgot.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf
                    <div class="govuk-form-group{{ ($status ?? '') === 'forgot-invalid' ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="email">{{ __('govuk_alpha.auth.forgot_email_label') }}</label>
                        <div id="email-hint" class="govuk-hint">{{ __('govuk_alpha.auth.forgot_email_hint') }}</div>
                        @if (($status ?? '') === 'forgot-invalid')
                            <p id="email-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha.auth.forgot_invalid') }}</p>
                        @endif
                        <input class="govuk-input{{ ($status ?? '') === 'forgot-invalid' ? ' govuk-input--error' : '' }}" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" aria-describedby="email-hint{{ ($status ?? '') === 'forgot-invalid' ? ' email-error' : '' }}">
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.auth.forgot_submit') }}</button>
                </form>

                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>
                </p>
            @endif
        </div>
    </div>
@endsection
