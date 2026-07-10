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

            @if (in_array($status ?? '', ['login-failed', 'two-factor-required', 'two-factor-expired', 'rate-limited', 'email-not-verified', 'pending-verification', 'account-suspended'], true))
                @php
                    $loginErrorMessage = match ($status) {
                        'two-factor-required'   => __('govuk_alpha.auth.two_factor_required'),
                        'two-factor-expired'    => __('govuk_alpha.auth.two_factor_expired'),
                        'rate-limited'          => __('govuk_alpha.auth.rate_limited'),
                        'email-not-verified'    => __('govuk_alpha.auth.email_not_verified'),
                        'pending-verification'  => __('govuk_alpha.auth.pending_verification'),
                        'account-suspended'     => __('govuk_alpha.auth.account_suspended'),
                        default                 => __('govuk_alpha.auth.login_failed'),
                    };
                    // Anchor the error summary to the most useful control: the email
                    // field for a failed sign-in, the resend form for unverified /
                    // pending accounts (it renders directly below), else main content.
                    $loginErrorAnchor = match ($status) {
                        'login-failed' => '#email',
                        'email-not-verified', 'pending-verification' => '#resend_email',
                        default => '#main-content',
                    };
                @endphp
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li>
                                    <a href="{{ $loginErrorAnchor }}">{{ $loginErrorMessage }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                @if (in_array($status, ['email-not-verified', 'pending-verification'], true))
                    <form method="post" action="{{ route('govuk-alpha.login.resend', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6" novalidate>
                        @csrf
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="resend_email">{{ __('govuk_alpha.auth.resend_email_label') }}</label>
                            <div id="resend-email-hint" class="govuk-hint">{{ __('govuk_alpha.auth.resend_verification_hint') }}</div>
                            <input class="govuk-input govuk-!-width-two-thirds" id="resend_email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" aria-describedby="resend-email-hint" required>
                        </div>
                        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.auth.resend_verification_button') }}</button>
                    </form>
                @endif
            @elseif (($status ?? '') === 'verification-resent')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="verification-resent-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="verification-resent-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.verification_resent') }}</p>
                    </div>
                </div>
            @elseif (($status ?? '') === 'register-created')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="register-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="register-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.register_created') }}</p>
                    </div>
                </div>
            @elseif (($status ?? '') === 'account-deletion-requested')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="account-deleted-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="account-deleted-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.delete_account.success') }}</p>
                    </div>
                </div>
            @elseif (($status ?? '') === 'password-reset')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="password-reset-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="password-reset-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.auth.password_reset') }}</p>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.login.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf
                <div class="govuk-form-group{{ ($status ?? '') === 'login-failed' ? ' govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="email">{{ __('govuk_alpha.auth.email_label') }}</label>
                    @if (($status ?? '') === 'login-failed')
                        <p class="govuk-error-message" id="email-error"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_title') }}:</span> {{ $loginErrorMessage ?? __('govuk_alpha.auth.login_failed') }}</p>
                    @endif
                    <input class="govuk-input{{ ($status ?? '') === 'login-failed' ? ' govuk-input--error' : '' }}" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" @if (($status ?? '') === 'login-failed') aria-describedby="email-error" @endif required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.auth.password_label') }}</label>
                    <input class="govuk-input" id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                {{-- Cloudflare Turnstile removed 2026-05-16 — member feedback
                     found the widget too confusing. Bot defence is now the
                     backend per-email + per-IP brute-force limiter plus
                     route-level throttle. --}}

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.auth.login_action') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.login.forgot', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.forgot_link') }}</a>
            </p>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.need_account') }}</a>
            </p>
        </div>
    </div>
@endsection
