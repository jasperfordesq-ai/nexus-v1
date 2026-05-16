{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.register_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.auth.register_description', ['community' => $tenant['name'] ?? $tenantSlug]) }}</p>

            @if (in_array($status ?? '', ['register-failed', 'register-duplicate', 'register-password-pwned', 'register-validation'], true))
                @php
                    $registerErrorMessage = match ($status) {
                        'register-duplicate'        => __('govuk_alpha.auth.register_duplicate'),
                        'register-password-pwned'   => __('govuk_alpha.auth.register_password_pwned'),
                        'register-validation'       => __('govuk_alpha.auth.register_validation'),
                        default                     => __('govuk_alpha.auth.register_failed'),
                    };
                @endphp
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#first_name">{{ $registerErrorMessage }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.register.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf
                {{-- Honeypot — hidden from real users (CSS off-screen + aria-hidden + tabindex=-1)
                     but auto-filled by form-spam bots. RegistrationService::register() silently
                     no-ops if this comes back non-empty. Do NOT use `display:none` — many bots
                     skip those; off-screen positioning catches more. --}}
                <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                    <label for="website">Website (leave blank)</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="first_name">{{ __('govuk_alpha.auth.first_name_label') }}</label>
                    <input class="govuk-input govuk-!-width-two-thirds" id="first_name" name="first_name" type="text" autocomplete="given-name" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="last_name">{{ __('govuk_alpha.auth.last_name_label') }}</label>
                    <input class="govuk-input govuk-!-width-two-thirds" id="last_name" name="last_name" type="text" autocomplete="family-name" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="email">{{ __('govuk_alpha.auth.email_label') }}</label>
                    <input class="govuk-input" id="email" name="email" type="email" autocomplete="email" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="phone">{{ __('govuk_alpha.auth.phone_label') }}</label>
                    <div id="phone-hint" class="govuk-hint">{{ __('govuk_alpha.auth.phone_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="phone" name="phone" type="tel" autocomplete="tel" aria-describedby="phone-hint" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="location">{{ __('govuk_alpha.auth.location_label') }}</label>
                    <input class="govuk-input" id="location" name="location" type="text" autocomplete="address-level2" required>
                </div>

                <div class="govuk-form-group" id="password-form-group">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.auth.password_label') }}</label>
                    <div id="password-hint" class="govuk-hint">{{ __('govuk_alpha.auth.password_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-hint password-strength-msg" minlength="12" required>
                    <p id="password-strength-msg" class="govuk-body-s govuk-!-margin-top-2" aria-live="polite"></p>
                </div>

                <script src="/assets/js/password-strength.js" defer></script>

                <div class="govuk-form-group">
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="newsletter_opt_in" name="newsletter_opt_in" type="checkbox" value="1">
                            <label class="govuk-label govuk-checkboxes__label" for="newsletter_opt_in">{{ __('govuk_alpha.auth.newsletter_label') }}</label>
                        </div>
                    </div>
                </div>

                {{-- Cloudflare Turnstile removed from registration 2026-05-16
                     — member feedback found it deterred legitimate sign-ups.
                     Bot defence: honeypot input above + per-IP route throttle
                     + admin-approval gate. --}}

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.auth.register_action') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.have_account') }}</a>
            </p>
        </div>
    </div>
@endsection
