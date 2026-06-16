{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $partners = $partners ?? [];
        $statusKey = (string) ($status ?? '');
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.optin.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.optin.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.optin.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.optin.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if ($statusKey === 'optin-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>{{ __('govuk_alpha.federation.optin.failed') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($statusKey === 'unavailable')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>{{ __('govuk_alpha.federation.optin.unavailable') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- BENEFITS --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha.federation.optin.benefits_heading') }}</h2>
    <div class="govuk-grid-row govuk-!-margin-bottom-6">
        <div class="govuk-grid-column-one-third">
            <div class="nexus-alpha-card">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.optin.benefit_discover_title') }}</h3>
                <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha.federation.optin.benefit_discover_body') }}</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="nexus-alpha-card">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.optin.benefit_meet_title') }}</h3>
                <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha.federation.optin.benefit_meet_body') }}</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="nexus-alpha-card">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.optin.benefit_exchange_title') }}</h3>
                <p class="govuk-body-s govuk-!-margin-bottom-0">{{ __('govuk_alpha.federation.optin.benefit_exchange_body') }}</p>
            </div>
        </div>
    </div>

    {{-- PREFERENCE FORM — the data-parity piece. Defaults mirror the React opt-in
         flow: everything shared by default except location, which stays off. --}}
    <form method="post" action="{{ route('govuk-alpha.federation.opt-in.store', ['tenantSlug' => $tenantSlug]) }}">
        @csrf
        <input type="hidden" name="preferences_submitted" value="1">

        {{-- Visibility --}}
        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.optin.privacy_legend') }}</legend>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="profile_visible_federated" name="profile_visible_federated" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="profile_visible_federated">{{ __('govuk_alpha.federation.settings.profile_visible_label') }}</label>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="appear_in_federated_search" name="appear_in_federated_search" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="appear_in_federated_search">{{ __('govuk_alpha.federation.settings.appear_in_search_label') }}</label>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="show_skills_federated" name="show_skills_federated" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="show_skills_federated">{{ __('govuk_alpha.federation.settings.show_skills_label') }}</label>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="show_location_federated" name="show_location_federated" type="checkbox" value="1" aria-describedby="show_location_federated-hint">
                    <label class="govuk-label govuk-checkboxes__label" for="show_location_federated">{{ __('govuk_alpha.federation.settings.show_location_label') }}</label>
                    <div id="show_location_federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha.federation.optin.location_hint') }}</div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="show_reviews_federated" name="show_reviews_federated" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="show_reviews_federated">{{ __('govuk_alpha.federation.settings.show_reviews_label') }}</label>
                </div>
            </div>
        </fieldset>

        {{-- Communication --}}
        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.optin.communication_legend') }}</legend>
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="messaging_enabled_federated" name="messaging_enabled_federated" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="messaging_enabled_federated">{{ __('govuk_alpha.polish_federation.settings_messaging_label') }}</label>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="transactions_enabled_federated" name="transactions_enabled_federated" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="transactions_enabled_federated">{{ __('govuk_alpha.polish_federation.settings_transactions_label') }}</label>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="email_notifications" name="email_notifications" type="checkbox" value="1" checked>
                    <label class="govuk-label govuk-checkboxes__label" for="email_notifications">{{ __('govuk_alpha.federation.settings.email_notifications_label') }}</label>
                </div>
            </div>
        </fieldset>

        {{-- Service reach --}}
        <fieldset class="govuk-fieldset govuk-!-margin-bottom-2">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.optin.reach_legend') }}</legend>

            <div class="govuk-form-group">
                <label class="govuk-label" for="service_reach">{{ __('govuk_alpha.federation.settings.reach_legend') }}</label>
                <select class="govuk-select" id="service_reach" name="service_reach">
                    <option value="local_only" selected>{{ __('govuk_alpha.federation.settings.reach_local_only') }}</option>
                    <option value="remote_ok">{{ __('govuk_alpha.federation.settings.reach_remote_ok') }}</option>
                    <option value="travel_ok">{{ __('govuk_alpha.federation.settings.reach_travel_ok') }}</option>
                </select>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="travel_radius_km">{{ __('govuk_alpha.federation.optin.travel_radius_label') }}</label>
                <div id="travel_radius_km-hint" class="govuk-hint">{{ __('govuk_alpha.federation.optin.travel_radius_hint') }}</div>
                <div class="govuk-input__wrapper">
                    <input class="govuk-input govuk-input--width-5" id="travel_radius_km" name="travel_radius_km" type="number" min="0" max="500" step="1" value="25" aria-describedby="travel_radius_km-hint" spellcheck="false">
                    <div class="govuk-input__suffix" aria-hidden="true">{{ __('govuk_alpha.federation.optin.travel_radius_suffix') }}</div>
                </div>
            </div>
        </fieldset>

        <div class="govuk-button-group">
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.federation.optin.submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.optin.do_this_later') }}</a>
        </div>
    </form>

    {{-- PARTNER PREVIEW --}}
    <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha.federation.optin.partners_heading') }}</h2>
    @if (empty($partners))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.optin.partners_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($partners as $partner)
                @php
                    $pName = trim((string) ($partner['name'] ?? '')) ?: $tenantSlug;
                    $pLoc = trim((string) ($partner['location'] ?? ''));
                    $pCount = (int) ($partner['member_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $pName }}</h3>
                    @if ($pLoc !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.location_label') }}: {{ $pLoc }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha.federation.members_label') }}: {{ $pCount }}</p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
