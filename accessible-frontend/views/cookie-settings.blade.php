{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php $cookieService = $tenant['name'] ?? __('govuk_alpha.service_name'); @endphp
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.cookie_settings.caption', ['service' => $cookieService]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.cookie_settings.title') }}</h1>

            @if (($status ?? '') === 'saved')
                <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" data-module="govuk-notification-banner" aria-labelledby="cookie-saved-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="cookie-saved-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.cookie_settings.saved_title') }}</p>
                    </div>
                </div>
            @endif

            <p class="govuk-body-l">{{ __('govuk_alpha.cookie_settings.intro') }}</p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.cookie_settings.essential_heading') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.cookie_settings.essential_body') }}</p>

            <form method="post" action="{{ route('govuk-alpha.cookies.store', ['tenantSlug' => $tenantSlug]) }}">
                @csrf
                <input type="hidden" name="cookies" value="save">

                <h2 class="govuk-heading-m">{{ __('govuk_alpha.cookie_settings.analytics_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.cookie_settings.analytics_body') }}</p>

                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.cookie_settings.analytics_legend') }}</legend>
                        <div class="govuk-radios" data-module="govuk-radios">
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="analytics-yes" name="analytics" type="radio" value="yes" @checked(($analyticsOn ?? false))>
                                <label class="govuk-label govuk-radios__label" for="analytics-yes">{{ __('govuk_alpha.cookie_settings.yes') }}</label>
                            </div>
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="analytics-no" name="analytics" type="radio" value="no" @checked(! ($analyticsOn ?? false))>
                                <label class="govuk-label govuk-radios__label" for="analytics-no">{{ __('govuk_alpha.cookie_settings.no') }}</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.cookie_settings.save') }}</button>
            </form>

            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.legal.cookies', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.cookie_settings.policy_link') }}</a>
            </p>
        </div>
    </div>
@endsection
