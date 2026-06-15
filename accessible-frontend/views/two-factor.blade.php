{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $hasError = in_array($status ?? '', ['two-factor-code-required', 'two-factor-invalid', 'two-factor-failed'], true);
        $errorMessage = match ($status ?? '') {
            'two-factor-invalid' => __('govuk_alpha.auth.two_factor_invalid'),
            'two-factor-failed'  => __('govuk_alpha.auth.two_factor_failed'),
            default              => __('govuk_alpha.auth.two_factor_code_required'),
        };
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>

            @if ($hasError)
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#code">{{ $errorMessage }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.two_factor_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.auth.two_factor_description') }}</p>

            <form method="post" action="{{ route('govuk-alpha.login.twofactor.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf
                <div class="govuk-form-group{{ $hasError ? ' govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="code">{{ __('govuk_alpha.auth.two_factor_code_label') }}</label>
                    <div id="code-hint" class="govuk-hint">{{ __('govuk_alpha.auth.two_factor_code_hint') }}</div>
                    @if ($hasError)
                        <p id="code-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $errorMessage }}</p>
                    @endif
                    <input class="govuk-input govuk-input--width-10{{ $hasError ? ' govuk-input--error' : '' }}" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autocapitalize="none" spellcheck="false" aria-describedby="code-hint{{ $hasError ? ' code-error' : '' }}">
                </div>

                <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="use_backup_code" name="use_backup_code" type="checkbox" value="1">
                        <label class="govuk-label govuk-checkboxes__label" for="use_backup_code">{{ __('govuk_alpha.auth.two_factor_use_backup_label') }}</label>
                    </div>
                </div>

                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.auth.two_factor_submit') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.back_to_sign_in') }}</a>
            </p>
        </div>
    </div>
@endsection
