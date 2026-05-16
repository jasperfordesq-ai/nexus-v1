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

            @php
                $errorStatuses = [
                    'register-failed', 'register-duplicate', 'register-password-pwned',
                    'register-password-mismatch', 'register-terms-required',
                    'register-invite-required', 'register-invite-invalid',
                    'register-location-unverified', 'register-validation',
                ];
            @endphp
            @if (in_array($status ?? '', $errorStatuses, true))
                @php
                    $registerErrorMessage = match ($status) {
                        'register-duplicate'         => __('govuk_alpha.auth.register_duplicate'),
                        'register-password-pwned'    => __('govuk_alpha.auth.register_password_pwned'),
                        'register-password-mismatch' => __('govuk_alpha.auth.register_password_mismatch'),
                        'register-terms-required'    => __('govuk_alpha.auth.register_terms_required'),
                        'register-invite-required'   => __('govuk_alpha.auth.register_invite_required'),
                        'register-invite-invalid'    => __('govuk_alpha.auth.register_invite_invalid'),
                        'register-location-unverified' => __('govuk_alpha.auth.register_location_unverified'),
                        'register-validation'        => __('govuk_alpha.auth.register_validation'),
                        default                      => __('govuk_alpha.auth.register_failed'),
                    };
                    $errorAnchor = match ($status) {
                        'register-password-pwned',
                        'register-password-mismatch' => '#password',
                        'register-terms-required'    => '#terms_accepted',
                        'register-invite-required',
                        'register-invite-invalid'    => '#invite_code',
                        'register-location-unverified' => '#location',
                        default                       => '#first_name',
                    };
                @endphp
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="{{ $errorAnchor }}">{{ $registerErrorMessage }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.register.store', ['tenantSlug' => $tenantSlug]) }}" novalidate
                  data-requires-invite-code="{{ ($requiresInviteCode ?? false) ? '1' : '0' }}"
                  data-geocoding-provider="{{ $geocodingProvider ?? 'google' }}"
                  data-google-maps-key="{{ $googleMapsApiKey ?? '' }}">
                @csrf

                {{-- Bot honeypot — hidden from real users (off-screen + aria-hidden + tabindex=-1)
                     but auto-filled by form-spam bots. RegistrationService::register() silently
                     no-ops if this comes back non-empty. Do NOT use `display:none` — many bots
                     skip those; off-screen positioning catches more. --}}
                <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                    <label for="website">Website (leave blank)</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
                </div>

                {{-- Min-form-time bot gate. Submitted form must take >= 5s.
                     Hidden from users; checked server-side in RegistrationService. --}}
                <input type="hidden" name="form_started_at" value="{{ $formStartedAt ?? '' }}">

                {{-- ── Profile type ─────────────────────────────────────── --}}
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset" aria-describedby="profile_type-hint">
                        <legend class="govuk-fieldset__legend">{{ __('govuk_alpha.auth.profile_type_label') }}</legend>
                        <div id="profile_type-hint" class="govuk-hint">{{ __('govuk_alpha.auth.profile_type_hint') }}</div>
                        <div class="govuk-radios" data-module="govuk-radios">
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="profile_type-individual" name="profile_type" type="radio" value="individual"
                                       {{ old('profile_type', 'individual') === 'individual' ? 'checked' : '' }}
                                       data-aria-controls="conditional-profile-individual">
                                <label class="govuk-label govuk-radios__label" for="profile_type-individual">
                                    {{ __('govuk_alpha.auth.profile_type_individual') }}
                                </label>
                            </div>
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="profile_type-organisation" name="profile_type" type="radio" value="organisation"
                                       {{ old('profile_type') === 'organisation' ? 'checked' : '' }}
                                       data-aria-controls="conditional-profile-organisation">
                                <label class="govuk-label govuk-radios__label" for="profile_type-organisation">
                                    {{ __('govuk_alpha.auth.profile_type_organisation') }}
                                </label>
                            </div>
                            <div class="govuk-radios__conditional {{ old('profile_type') === 'organisation' ? '' : 'govuk-radios__conditional--hidden' }}" id="conditional-profile-organisation">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="organization_name">{{ __('govuk_alpha.auth.organization_name_label') }}</label>
                                    <input class="govuk-input govuk-!-width-two-thirds" id="organization_name" name="organization_name" type="text"
                                           autocomplete="organization" value="{{ old('organization_name') }}">
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                {{-- ── Invite code (conditional on tenant policy) ──────── --}}
                @if ($requiresInviteCode ?? false)
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="invite_code">{{ __('govuk_alpha.auth.invite_code_label') }}</label>
                        <div id="invite_code-hint" class="govuk-hint">{{ __('govuk_alpha.auth.invite_code_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="invite_code" name="invite_code" type="text"
                               aria-describedby="invite_code-hint" autocomplete="off"
                               style="text-transform: uppercase;" value="{{ old('invite_code') }}" required>
                    </div>
                @endif

                {{-- ── Personal details ─────────────────────────────────── --}}
                <div class="govuk-form-group">
                    <label class="govuk-label" for="first_name">{{ __('govuk_alpha.auth.first_name_label') }}</label>
                    <input class="govuk-input govuk-!-width-two-thirds" id="first_name" name="first_name" type="text"
                           autocomplete="given-name" value="{{ old('first_name') }}" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="last_name">{{ __('govuk_alpha.auth.last_name_label') }}</label>
                    <input class="govuk-input govuk-!-width-two-thirds" id="last_name" name="last_name" type="text"
                           autocomplete="family-name" value="{{ old('last_name') }}" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="phone">{{ __('govuk_alpha.auth.phone_label') }}</label>
                    <div id="phone-hint" class="govuk-hint">{{ __('govuk_alpha.auth.phone_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="phone" name="phone" type="tel"
                           autocomplete="tel" aria-describedby="phone-hint" value="{{ old('phone') }}" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="location">{{ __('govuk_alpha.auth.location_label') }}</label>
                    <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha.auth.location_hint') }}</div>
                    <input class="govuk-input" id="location" name="location" type="text"
                           autocomplete="address-level2" aria-describedby="location-hint"
                           value="{{ old('location') }}" required>
                    {{-- Lat/lng auto-populated by Google Places autocomplete (progressive
                         enhancement). The form works without JS — these stay empty and the
                         server accepts a plain location string. --}}
                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                </div>

                {{-- ── Account ──────────────────────────────────────────── --}}
                <div class="govuk-form-group">
                    <label class="govuk-label" for="email">{{ __('govuk_alpha.auth.email_label') }}</label>
                    <input class="govuk-input" id="email" name="email" type="email"
                           autocomplete="email" value="{{ old('email') }}" required>
                </div>

                <div class="govuk-form-group" id="password-form-group">
                    <label class="govuk-label" for="password">{{ __('govuk_alpha.auth.password_label') }}</label>
                    <div id="password-hint" class="govuk-hint">{{ __('govuk_alpha.auth.password_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="password" name="password" type="password"
                           autocomplete="new-password" aria-describedby="password-hint password-strength-msg"
                           minlength="12" required>
                    <p id="password-strength-msg" class="govuk-body-s govuk-!-margin-top-2" aria-live="polite"></p>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="password_confirmation">{{ __('govuk_alpha.auth.password_confirmation_label') }}</label>
                    <input class="govuk-input govuk-!-width-two-thirds" id="password_confirmation" name="password_confirmation" type="password"
                           autocomplete="new-password" minlength="12" required>
                </div>

                {{-- ── Consents ─────────────────────────────────────────── --}}
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha.auth.consents_legend') }}</legend>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="terms_accepted" name="terms_accepted" type="checkbox" value="1" required>
                                <label class="govuk-label govuk-checkboxes__label" for="terms_accepted">
                                    {!! __('govuk_alpha.auth.terms_label', [
                                        'terms' => '<a class="govuk-link" href="' . e(url($tenantSlug . '/terms')) . '">' . e(__('govuk_alpha.auth.terms_link_text')) . '</a>',
                                        'privacy' => '<a class="govuk-link" href="' . e(url($tenantSlug . '/privacy')) . '">' . e(__('govuk_alpha.auth.privacy_link_text')) . '</a>',
                                    ]) !!}
                                </label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="newsletter_opt_in" name="newsletter_opt_in" type="checkbox" value="1"
                                       {{ old('newsletter_opt_in') ? 'checked' : '' }}>
                                <label class="govuk-label govuk-checkboxes__label" for="newsletter_opt_in">{{ __('govuk_alpha.auth.newsletter_label') }}</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <p class="govuk-body-s">{{ __('govuk_alpha.auth.data_protection_notice') }}</p>

                {{-- Cloudflare Turnstile removed from registration 2026-05-16
                     — member feedback found it deterred legitimate sign-ups.
                     Bot defence on this path: honeypot input + min-form-time
                     gate (5s, server-enforced) + per-IP route throttle (5/5min)
                     + admin-approval gate. --}}

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.auth.register_action') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.auth.have_account') }}</a>
            </p>
        </div>
    </div>

    <script src="/assets/js/password-strength.js" defer></script>
    <script src="/assets/js/register-enhancements.js" defer></script>
    @if (!empty($googleMapsApiKey) && ($geocodingProvider ?? 'google') === 'google')
        <script async defer
                src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&callback=__nexusRegisterPlacesInit"></script>
    @endif
@endsection
