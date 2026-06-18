{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $statusKey = $status ?? null;

        // Map each failure status to the field it should anchor to, so the error
        // summary links straight to the offending control (no-JS friendly).
        $errorField = [
            'org-name-invalid' => 'name',
            'org-description-invalid' => 'description',
            'org-email-invalid' => 'email',
            'org-website-invalid' => 'website',
            'org-terms-required' => 'agreed_terms',
            'org-failed' => 'name',
        ];
        $activeError = $statusKey !== null && isset($errorField[$statusKey]) ? $statusKey : null;
        $errorFor = function (string $field) use ($activeError, $errorField) {
            return $activeError !== null && ($errorField[$activeError] ?? null) === $field ? $activeError : null;
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.browse', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.common.back_to_organisations') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_organisations.common.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_organisations.register.heading') }}</h1>

    @if ($activeError !== null)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_organisations.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#{{ $errorField[$activeError] }}">{{ __('govuk_alpha_organisations.register.errors.' . $activeError) }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_organisations.register.description') }}</p>

    <form method="post" action="{{ route('govuk-alpha.organisations.register', ['tenantSlug' => $tenantSlug]) }}">
        @csrf
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                {{-- Name --}}
                <div class="govuk-form-group {{ $errorFor('name') ? 'govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="name">{{ __('govuk_alpha_organisations.register.name_label') }}</label>
                    <div id="name-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.register.name_hint') }}</div>
                    @if ($errorFor('name'))
                        <p id="name-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.common.error_title') }}:</span> {{ __('govuk_alpha_organisations.register.errors.' . $errorFor('name')) }}</p>
                    @endif
                    <input class="govuk-input {{ $errorFor('name') ? 'govuk-input--error' : '' }}" id="name" name="name" type="text" minlength="3" maxlength="255" value="{{ old('name') }}" aria-describedby="name-hint {{ $errorFor('name') ? 'name-error' : '' }}" required>
                </div>

                {{-- Description --}}
                <div class="govuk-form-group {{ $errorFor('description') ? 'govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="description">{{ __('govuk_alpha_organisations.register.description_label') }}</label>
                    <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.register.description_hint') }}</div>
                    @if ($errorFor('description'))
                        <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.common.error_title') }}:</span> {{ __('govuk_alpha_organisations.register.errors.' . $errorFor('description')) }}</p>
                    @endif
                    <textarea class="govuk-textarea {{ $errorFor('description') ? 'govuk-textarea--error' : '' }}" id="description" name="description" rows="4" minlength="20" maxlength="2000" aria-describedby="description-hint {{ $errorFor('description') ? 'description-error' : '' }}" required>{{ old('description') }}</textarea>
                </div>

                {{-- Email --}}
                <div class="govuk-form-group {{ $errorFor('email') ? 'govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="email">{{ __('govuk_alpha_organisations.register.email_label') }}</label>
                    <div id="email-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.register.email_hint') }}</div>
                    @if ($errorFor('email'))
                        <p id="email-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.common.error_title') }}:</span> {{ __('govuk_alpha_organisations.register.errors.' . $errorFor('email')) }}</p>
                    @endif
                    <input class="govuk-input {{ $errorFor('email') ? 'govuk-input--error' : '' }}" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" aria-describedby="email-hint {{ $errorFor('email') ? 'email-error' : '' }}" required>
                </div>

                {{-- Website --}}
                <div class="govuk-form-group {{ $errorFor('website') ? 'govuk-form-group--error' : '' }}">
                    <label class="govuk-label" for="website">{{ __('govuk_alpha_organisations.register.website_label') }}</label>
                    <div id="website-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.register.website_hint') }}</div>
                    @if ($errorFor('website'))
                        <p id="website-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.common.error_title') }}:</span> {{ __('govuk_alpha_organisations.register.errors.' . $errorFor('website')) }}</p>
                    @endif
                    <input class="govuk-input {{ $errorFor('website') ? 'govuk-input--error' : '' }}" id="website" name="website" type="url" inputmode="url" value="{{ old('website') }}" aria-describedby="website-hint {{ $errorFor('website') ? 'website-error' : '' }}">
                </div>

                {{-- Terms --}}
                <div class="govuk-inset-text">
                    <h2 class="govuk-heading-s">{{ __('govuk_alpha_organisations.register.terms_heading') }}</h2>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>{{ __('govuk_alpha_organisations.register.terms_item_1') }}</li>
                        <li>{{ __('govuk_alpha_organisations.register.terms_item_2') }}</li>
                        <li>{{ __('govuk_alpha_organisations.register.terms_item_3') }}</li>
                        <li>{{ __('govuk_alpha_organisations.register.terms_item_4') }}</li>
                        <li>{{ __('govuk_alpha_organisations.register.terms_item_5') }}</li>
                    </ul>
                </div>

                <div class="govuk-form-group {{ $errorFor('agreed_terms') ? 'govuk-form-group--error' : '' }}">
                    <fieldset class="govuk-fieldset" aria-describedby="{{ $errorFor('agreed_terms') ? 'agreed_terms-error' : '' }}">
                        <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha_organisations.register.terms_heading') }}</legend>
                        @if ($errorFor('agreed_terms'))
                            <p id="agreed_terms-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.common.error_title') }}:</span> {{ __('govuk_alpha_organisations.register.errors.' . $errorFor('agreed_terms')) }}</p>
                        @endif
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="agreed_terms" name="agreed_terms" type="checkbox" value="1" {{ old('agreed_terms') ? 'checked' : '' }} required>
                                <label class="govuk-label govuk-checkboxes__label" for="agreed_terms">{{ __('govuk_alpha_organisations.register.terms_agree') }}</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="govuk-button-group">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_organisations.register.submit') }}</button>
                    <a class="govuk-link" href="{{ route('govuk-alpha.organisations.browse', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.register.cancel') }}</a>
                </div>

                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_organisations.register.pending_notice') }}</p>
                </div>
            </div>
        </div>
    </form>
@endsection
