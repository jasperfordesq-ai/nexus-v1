{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $settings = $settings ?? [];
        $optedIn = (bool) ($optedIn ?? false);
        $reach = (string) ($settings['service_reach'] ?? 'local_only');
        $checked = fn (string $k): bool => (bool) ($settings[$k] ?? false);
        $statusBanners = [
            'settings-saved' => ['success', __('govuk_alpha.federation.settings.saved')],
            'settings-failed' => ['error', __('govuk_alpha.federation.settings.failed')],
        ];
        $banner = $statusBanners[$status ?? ''] ?? null;
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.settings.back') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.settings.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.settings.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.federation.settings.description') }}</p>

            @if ($banner)
                @if ($banner[0] === 'error')
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert">
                            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                            <div class="govuk-error-summary__body">
                                <ul class="govuk-list govuk-error-summary__list">
                                    <li><a href="#profile_visible_federated">{{ $banner[1] }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="fed-settings-status">
                        <div class="govuk-notification-banner__header">
                            <h2 class="govuk-notification-banner__title" id="fed-settings-status">{{ __('govuk_alpha.states.success_title') }}</h2>
                        </div>
                        <div class="govuk-notification-banner__content">
                            <p class="govuk-notification-banner__heading">{{ $banner[1] }}</p>
                        </div>
                    </div>
                @endif
            @endif

            @if (!$optedIn)
                <p class="govuk-inset-text">{{ __('govuk_alpha.federation.settings.not_opted_in') }}</p>
                <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.federation.opt-in', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.settings.optin_cta') }}</a>
            @else
                <form method="post" action="{{ route('govuk-alpha.federation.settings.update', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf

                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.settings.visibility_legend') }}</legend>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="profile_visible_federated" name="profile_visible_federated" type="checkbox" value="1" @checked($checked('profile_visible_federated'))>
                                <label class="govuk-label govuk-checkboxes__label" for="profile_visible_federated">{{ __('govuk_alpha.federation.settings.profile_visible_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="appear_in_federated_search" name="appear_in_federated_search" type="checkbox" value="1" @checked($checked('appear_in_federated_search'))>
                                <label class="govuk-label govuk-checkboxes__label" for="appear_in_federated_search">{{ __('govuk_alpha.federation.settings.appear_in_search_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_skills_federated" name="show_skills_federated" type="checkbox" value="1" @checked($checked('show_skills_federated'))>
                                <label class="govuk-label govuk-checkboxes__label" for="show_skills_federated">{{ __('govuk_alpha.federation.settings.show_skills_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_location_federated" name="show_location_federated" type="checkbox" value="1" @checked($checked('show_location_federated'))>
                                <label class="govuk-label govuk-checkboxes__label" for="show_location_federated">{{ __('govuk_alpha.federation.settings.show_location_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="show_reviews_federated" name="show_reviews_federated" type="checkbox" value="1" @checked($checked('show_reviews_federated'))>
                                <label class="govuk-label govuk-checkboxes__label" for="show_reviews_federated">{{ __('govuk_alpha.federation.settings.show_reviews_label') }}</label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.settings.notifications_legend') }}</legend>
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="email_notifications" name="email_notifications" type="checkbox" value="1" @checked($checked('email_notifications'))>
                                <label class="govuk-label govuk-checkboxes__label" for="email_notifications">{{ __('govuk_alpha.federation.settings.email_notifications_label') }}</label>
                            </div>
                        </div>
                    </fieldset>

                    <div class="govuk-form-group govuk-!-margin-bottom-6">
                        <label class="govuk-label govuk-label--m" for="service_reach">{{ __('govuk_alpha.federation.settings.reach_legend') }}</label>
                        <div id="service_reach-hint" class="govuk-hint">{{ __('govuk_alpha.federation.settings.reach_hint') }}</div>
                        <select class="govuk-select" id="service_reach" name="service_reach" aria-describedby="service_reach-hint">
                            <option value="local_only" @selected($reach === 'local_only')>{{ __('govuk_alpha.federation.settings.reach_local_only') }}</option>
                            <option value="remote_ok" @selected($reach === 'remote_ok')>{{ __('govuk_alpha.federation.settings.reach_remote_ok') }}</option>
                            <option value="travel_ok" @selected($reach === 'travel_ok')>{{ __('govuk_alpha.federation.settings.reach_travel_ok') }}</option>
                        </select>
                    </div>

                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.federation.settings.submit') }}</button>
                        <a class="govuk-link" href="{{ route('govuk-alpha.federation.opt-out', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.settings.optout_link') }}</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
