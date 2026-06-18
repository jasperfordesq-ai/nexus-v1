{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_groups.notifications.title');
        $prefFrequency = $prefFrequency ?? 'instant';
        $prefEmailEnabled = (bool) ($prefEmailEnabled ?? true);
        $prefPushEnabled = (bool) ($prefPushEnabled ?? true);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha_groups.common.back_to_group') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_groups.notifications.caption', ['group' => $gName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_groups.notifications.title') }}</h1>

    @if (($status ?? null) === 'prefs-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="prefs-status-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="prefs-status-banner">{{ __('govuk_alpha_groups.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_groups.states.prefs-saved') }}</p></div>
        </div>
    @elseif (($status ?? null) === 'prefs-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_groups.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_groups.states.prefs-failed') }}</p></div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_groups.notifications.intro') }}</p>

    <form method="post" action="{{ route('govuk-alpha.groups.notifications.update', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" novalidate>
        @csrf

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_groups.notifications.frequency_legend') }}</h2>
                </legend>
                <div class="govuk-radios" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="frequency-instant" name="frequency" type="radio" value="instant" @checked($prefFrequency === 'instant')>
                        <label class="govuk-label govuk-radios__label" for="frequency-instant">{{ __('govuk_alpha_groups.notifications.frequency_instant') }}</label>
                    </div>
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="frequency-digest" name="frequency" type="radio" value="digest" @checked($prefFrequency === 'digest')>
                        <label class="govuk-label govuk-radios__label" for="frequency-digest">{{ __('govuk_alpha_groups.notifications.frequency_digest') }}</label>
                    </div>
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="frequency-muted" name="frequency" type="radio" value="muted" @checked($prefFrequency === 'muted')>
                        <label class="govuk-label govuk-radios__label" for="frequency-muted">{{ __('govuk_alpha_groups.notifications.frequency_muted') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset" aria-describedby="channels-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_groups.notifications.channels_legend') }}</h2>
                </legend>
                <div id="channels-hint" class="govuk-hint">{{ __('govuk_alpha_groups.notifications.channels_hint') }}</div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="email_enabled" name="email_enabled" type="checkbox" value="1" @checked($prefEmailEnabled)>
                        <label class="govuk-label govuk-checkboxes__label" for="email_enabled">{{ __('govuk_alpha_groups.notifications.email_label') }}</label>
                    </div>
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="push_enabled" name="push_enabled" type="checkbox" value="1" @checked($prefPushEnabled)>
                        <label class="govuk-label govuk-checkboxes__label" for="push_enabled">{{ __('govuk_alpha_groups.notifications.push_label') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_groups.notifications.save_button') }}</button>
    </form>
@endsection
