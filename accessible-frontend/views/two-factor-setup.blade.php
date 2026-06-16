{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.security_2fa.back') }}</a>

            @php
                $successStatuses = [
                    '2fa-enabled' => 'govuk_alpha.security_2fa.enabled_success',
                    '2fa-disabled' => 'govuk_alpha.security_2fa.disabled_success',
                ];
                $errorStatuses = [
                    '2fa-code-required' => 'govuk_alpha.security_2fa.code_required',
                    '2fa-code-invalid' => 'govuk_alpha.security_2fa.code_invalid',
                    '2fa-password-required' => 'govuk_alpha.security_2fa.password_required',
                    '2fa-disable-failed' => 'govuk_alpha.security_2fa.disable_failed',
                ];
            @endphp

            @if (isset($successStatuses[$status]))
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="tfa-success-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="tfa-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __($successStatuses[$status]) }}</p>
                    </div>
                </div>
            @elseif (isset($errorStatuses[$status]))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#tfa-form">{{ __($errorStatuses[$status]) }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.security_2fa.title') }}</h1>

            @if (!empty($backupCodes))
                {{-- Shown exactly once, immediately after enabling. --}}
                <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-bottom-6">
                    <h2 class="govuk-panel__title">{{ __('govuk_alpha.security_2fa.now_on_title') }}</h2>
                </div>
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.security_2fa.backup_codes_title') }}</h2>
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                        {{ __('govuk_alpha.security_2fa.backup_codes_warning') }}
                    </strong>
                </div>
                <ul class="govuk-list govuk-list--bullet nexus-alpha-backup-codes">
                    @foreach ($backupCodes as $bc)
                        <li><code>{{ $bc }}</code></li>
                    @endforeach
                </ul>
                <a class="govuk-button govuk-!-margin-top-2" data-module="govuk-button" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.security_2fa.done') }}</a>
            @elseif ($enabled)
                <p class="govuk-body-l">{{ __('govuk_alpha.security_2fa.is_on') }}</p>
                <p class="govuk-body">{{ trans_choice('govuk_alpha.security_2fa.backup_remaining', $backupCodesRemaining, ['count' => $backupCodesRemaining]) }}</p>

                <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha.security_2fa.disable_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.security_2fa.disable_intro') }}</p>
                <form id="tfa-form" method="post" action="{{ route('govuk-alpha.profile.2fa.disable', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="password">{{ __('govuk_alpha.security_2fa.password_label') }}</label>
                        <input class="govuk-input govuk-input--width-20" id="password" name="password" type="password" autocomplete="current-password" spellcheck="false">
                    </div>
                    <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.security_2fa.disable_button') }}</button>
                </form>
            @elseif ($setup)
                <p class="govuk-body-l">{{ __('govuk_alpha.security_2fa.setup_intro') }}</p>
                <ol class="govuk-list govuk-list--number">
                    <li>{{ __('govuk_alpha.security_2fa.step_app') }}</li>
                    <li>{{ __('govuk_alpha.security_2fa.step_scan') }}</li>
                    <li>{{ __('govuk_alpha.security_2fa.step_code') }}</li>
                </ol>

                <img class="nexus-alpha-qr" src="{{ $setup['qr_data_uri'] }}" alt="{{ __('govuk_alpha.security_2fa.qr_alt') }}" width="200" height="200">

                <p class="govuk-body govuk-!-margin-top-4">{{ __('govuk_alpha.security_2fa.manual_intro') }}</p>
                <div class="govuk-inset-text">
                    <p class="govuk-body"><code class="nexus-alpha-totp-secret">{{ $setup['secret'] }}</code></p>
                </div>

                <form id="tfa-form" method="post" action="{{ route('govuk-alpha.profile.2fa.verify', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-4">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="code">{{ __('govuk_alpha.security_2fa.code_label') }}</label>
                        <div id="code-hint" class="govuk-hint">{{ __('govuk_alpha.security_2fa.code_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" aria-describedby="code-hint">
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.security_2fa.verify_button') }}</button>
                </form>
            @else
                <div class="govuk-inset-text">{{ __('govuk_alpha.security_2fa.setup_unavailable') }}</div>
            @endif
        </div>
    </div>
@endsection
