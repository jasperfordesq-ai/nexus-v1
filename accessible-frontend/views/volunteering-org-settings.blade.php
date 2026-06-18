{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $orgId = (int) ($orgId ?? 0);
        $org = $org ?? [];
        $status = $status ?? null;
        $nameError = $status === 'name-required';
        $emailError = $status === 'email-invalid';
        $hasFieldError = $nameError || $emailError;
        $isSuccess = $status === 'settings-saved';
        $isFailure = $status === 'settings-failed';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.shared.back_to_dashboard') }}</a>

    @if ($hasFieldError)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @if ($nameError)
                            <li><a href="#name">{{ __('govuk_alpha_volunteering.org_settings.error_name_required') }}</a></li>
                        @endif
                        @if ($emailError)
                            <li><a href="#contact_email">{{ __('govuk_alpha_volunteering.org_settings.error_email_invalid') }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($isFailure)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha_volunteering.org_settings.save_failed') }}</p></div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.org_settings.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.org_settings.description') }}</p>

    @if ($isSuccess)
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="settings-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="settings-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_volunteering.org_settings.saved') }}</p></div>
        </div>
    @endif

    <form method="post" action="{{ route('govuk-alpha.volunteering.org.settings.update', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">
        @csrf

        <div class="govuk-form-group{{ $nameError ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="name">{{ __('govuk_alpha_volunteering.org_settings.name_label') }}</label>
            <div id="name-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_settings.name_hint') }}</div>
            @if ($nameError)
                <p id="name-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.org_settings.name_required') }}</p>
            @endif
            <input class="govuk-input" id="name" name="name" type="text" value="{{ $org['name'] ?? '' }}" aria-describedby="name-hint{{ $nameError ? ' name-error' : '' }}" required>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="description">{{ __('govuk_alpha_volunteering.org_settings.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_settings.description_hint') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="5" aria-describedby="description-hint">{{ $org['description'] ?? '' }}</textarea>
        </div>

        <div class="govuk-form-group{{ $emailError ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="contact_email">{{ __('govuk_alpha_volunteering.org_settings.contact_email_label') }}</label>
            <div id="contact_email-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_settings.contact_email_hint') }}</div>
            @if ($emailError)
                <p id="contact_email-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.org_settings.email_invalid') }}</p>
            @endif
            <input class="govuk-input" id="contact_email" name="contact_email" type="email" spellcheck="false" value="{{ $org['contact_email'] ?? '' }}" aria-describedby="contact_email-hint{{ $emailError ? ' contact_email-error' : '' }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="website">{{ __('govuk_alpha_volunteering.org_settings.website_label') }}</label>
            <div id="website-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_settings.website_hint') }}</div>
            <input class="govuk-input" id="website" name="website" type="url" spellcheck="false" value="{{ $org['website'] ?? '' }}" aria-describedby="website-hint">
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.org_settings.save_button') }}</button>
    </form>
@endsection
