{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $themes = $themes ?? ['light', 'dark', 'system'];
        $currentTheme = $currentTheme ?? 'system';
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_settings.common.back_to_settings') }}</a>

            @if ($status === 'appearance-saved')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="appearance-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="appearance-status-title">{{ __('govuk_alpha_settings.common.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_settings.states.appearance-saved') }}</p>
                    </div>
                </div>
            @elseif (in_array($status, ['appearance-invalid', 'appearance-failed'], true))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_settings.common.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#theme">{{ __('govuk_alpha_settings.states.' . $status) }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <span class="govuk-caption-xl">{{ __('govuk_alpha_settings.appearance.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_settings.appearance.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha_settings.appearance.description') }}</p>

            <form method="post" action="{{ route('govuk-alpha.settings.appearance.update', ['tenantSlug' => $tenantSlug]) }}">
                @csrf
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset" aria-describedby="theme-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            <h2 class="govuk-fieldset__heading" id="theme">{{ __('govuk_alpha_settings.appearance.theme_legend') }}</h2>
                        </legend>
                        <div class="govuk-radios" data-module="govuk-radios">
                            @foreach ($themes as $theme)
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="theme_{{ $theme }}" name="theme" type="radio" value="{{ $theme }}"
                                        @checked($currentTheme === $theme) aria-describedby="theme_{{ $theme }}-hint">
                                    <label class="govuk-label govuk-radios__label" for="theme_{{ $theme }}">{{ __('govuk_alpha_settings.appearance.themes.' . $theme) }}</label>
                                    <div id="theme_{{ $theme }}-hint" class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_settings.appearance.theme_hints.' . $theme) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_settings.appearance.save') }}</button>
            </form>
        </div>
    </div>
@endsection
