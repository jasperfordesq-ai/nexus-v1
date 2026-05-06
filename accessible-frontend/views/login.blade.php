{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.login_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.auth.login_description', ['community' => $tenant['name'] ?? $tenantSlug]) }}</p>

            @if (in_array($status ?? '', ['login-failed', 'two-factor-required'], true))
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>
                                    <a href="#email">
                                        {{ ($status ?? '') === 'two-factor-required' ? __('govuk_alpha.auth.two_factor_required') : __('govuk_alpha.auth.login_failed') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @elseif (($status ?? '') === 'register-created')
                <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="register-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="register-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.register_created') }}</p>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.login.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label" for="email">{{ __('govuk_alpha.auth.email_label') }}</label>
                    <input class="govuk-input" id="email" name="email" type="email" autocomplete="email" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.auth.password_label') }}</label>
                    <input class="govuk-input" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.auth.login_action') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.need_account') }}</a>
            </p>
        </div>
    </div>
@endsection
