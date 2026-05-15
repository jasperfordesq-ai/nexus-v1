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

            @if (($status ?? '') === 'register-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#first_name">{{ __('govuk_alpha.auth.register_failed') }}</a></li>
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

                <div class="govuk-form-group">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.auth.password_label') }}</label>
                    <div id="password-hint" class="govuk-hint">{{ __('govuk_alpha.auth.password_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-hint" required>
                </div>

                <div class="govuk-form-group">
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="newsletter_opt_in" name="newsletter_opt_in" type="checkbox" value="1">
                            <label class="govuk-label govuk-checkboxes__label" for="newsletter_opt_in">{{ __('govuk_alpha.auth.newsletter_label') }}</label>
                        </div>
                    </div>
                </div>

                @if($turnstileSiteKey)
                    {{-- Cloudflare Turnstile challenge. Renders an invisible
                         or interactive bot-check; the resolved token is posted
                         back as a `cf-turnstile-response` hidden input which
                         the server verifies via siteverify. Without the site
                         key (dev/CI) the widget is omitted entirely and the
                         server-side TurnstileService no-ops. --}}
                    <div class="govuk-form-group">
                        <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="auto"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.auth.register_action') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.have_account') }}</a>
            </p>
        </div>
    </div>
@endsection
