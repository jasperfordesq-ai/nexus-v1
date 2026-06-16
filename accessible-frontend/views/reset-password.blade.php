{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $invalidLink = in_array($status ?? '', ['reset-token-missing', 'reset-token-invalid'], true) || empty($token);
        $fieldErrors = ['reset-weak', 'reset-pwned', 'reset-reused', 'reset-mismatch', 'reset-failed', 'reset-rate-limited'];
        $errorMessage = match ($status ?? '') {
            'reset-pwned'        => __('govuk_alpha.auth.reset_pwned'),
            'reset-reused'       => __('govuk_alpha.auth.reset_reused'),
            'reset-mismatch'     => __('govuk_alpha.auth.reset_mismatch'),
            'reset-rate-limited' => __('govuk_alpha.auth.reset_rate_limited'),
            'reset-failed'       => __('govuk_alpha.auth.reset_failed'),
            default              => __('govuk_alpha.auth.reset_weak'),
        };
        $errorAnchor = ($status ?? '') === 'reset-mismatch' ? '#password_confirmation' : '#password';
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>

            @if ($invalidLink)
                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.reset_link_invalid_title') }}</h1>
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <p class="govuk-body">{{ __('govuk_alpha.auth.reset_link_invalid_detail') }}</p>
                        </div>
                    </div>
                </div>
                <a class="govuk-button" href="{{ route('govuk-alpha.login.forgot', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.auth.reset_request_new') }}</a>
            @else
                @if (in_array($status ?? '', $fieldErrors, true))
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert">
                            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                            <div class="govuk-error-summary__body">
                                <ul class="govuk-list govuk-error-summary__list">
                                    <li><a href="{{ $errorAnchor }}">{{ $errorMessage }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.reset_title') }}</h1>
                <p class="govuk-body-l">{{ __('govuk_alpha.auth.reset_description') }}</p>

                <form method="post" action="{{ route('govuk-alpha.password.reset.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    @php $passwordHasError = in_array($status ?? '', ['reset-weak', 'reset-pwned', 'reset-reused', 'reset-failed'], true); @endphp
                    <div class="govuk-form-group{{ $passwordHasError ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="password">{{ __('govuk_alpha.auth.reset_password_label') }}</label>
                        <div id="password-hint" class="govuk-hint">{{ __('govuk_alpha.auth.reset_password_hint') }}</div>
                        @if ($passwordHasError)
                            <p id="password-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $errorMessage }}</p>
                        @endif
                        <input class="govuk-input{{ $passwordHasError ? ' govuk-input--error' : '' }}" id="password" name="password" type="password" autocomplete="new-password" spellcheck="false" aria-describedby="password-hint{{ $passwordHasError ? ' password-error' : '' }}">
                    </div>
                    @php $confirmHasError = ($status ?? '') === 'reset-mismatch'; @endphp
                    <div class="govuk-form-group{{ $confirmHasError ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="password_confirmation">{{ __('govuk_alpha.auth.reset_confirm_label') }}</label>
                        @if ($confirmHasError)
                            <p id="password_confirmation-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha.auth.reset_mismatch') }}</p>
                        @endif
                        <input class="govuk-input{{ $confirmHasError ? ' govuk-input--error' : '' }}" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" spellcheck="false" required @if ($confirmHasError) aria-describedby="password_confirmation-error" @endif>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.auth.reset_submit') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
