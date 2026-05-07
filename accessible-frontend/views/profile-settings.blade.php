{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $profileType = ($profile['profile_type'] ?? 'individual') === 'organisation' ? 'organisation' : 'individual';
        $privacyProfile = $profile['privacy_profile'] ?? 'public';
        $privacySearch = (bool) ($profile['privacy_search'] ?? true);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_profile') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.profile_settings.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.profile_settings.description') }}</p>

            @if (($status ?? '') === 'profile-update-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#first_name">{{ __('govuk_alpha.profile_settings.failed') }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.profile.settings.update', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf

                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.profile_settings.personal_details') }}</h2>
                    </legend>

                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="first_name">{{ __('govuk_alpha.profile_settings.first_name_label') }}</label>
                                <input class="govuk-input" id="first_name" name="first_name" type="text" autocomplete="given-name" value="{{ $profile['first_name'] ?? '' }}">
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="last_name">{{ __('govuk_alpha.profile_settings.last_name_label') }}</label>
                                <input class="govuk-input" id="last_name" name="last_name" type="text" autocomplete="family-name" value="{{ $profile['last_name'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="phone">{{ __('govuk_alpha.profile_settings.phone_label') }}</label>
                        <div id="phone-hint" class="govuk-hint">{{ __('govuk_alpha.profile_settings.phone_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ $profile['phone'] ?? '' }}" aria-describedby="phone-hint">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.profile_settings.public_profile') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="profile_type">{{ __('govuk_alpha.profile_settings.profile_type_label') }}</label>
                        <select class="govuk-select" id="profile_type" name="profile_type">
                            <option value="individual" @selected($profileType === 'individual')>{{ __('govuk_alpha.profile.profile_type_individual') }}</option>
                            <option value="organisation" @selected($profileType === 'organisation')>{{ __('govuk_alpha.profile.profile_type_organisation') }}</option>
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="organization_name">{{ __('govuk_alpha.profile_settings.organization_name_label') }}</label>
                        <input class="govuk-input" id="organization_name" name="organization_name" type="text" value="{{ $profile['organization_name'] ?? '' }}">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="tagline">{{ __('govuk_alpha.profile_settings.tagline_label') }}</label>
                        <input class="govuk-input" id="tagline" name="tagline" type="text" value="{{ $profile['tagline'] ?? '' }}">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="bio">{{ __('govuk_alpha.profile_settings.bio_label') }}</label>
                        <textarea class="govuk-textarea" id="bio" name="bio" rows="6">{{ $profile['bio'] ?? '' }}</textarea>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="location">{{ __('govuk_alpha.profile_settings.location_label') }}</label>
                        <input class="govuk-input" id="location" name="location" type="text" autocomplete="address-level2" value="{{ $profile['location'] ?? '' }}">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7" aria-describedby="privacy-profile-hint">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.profile_settings.privacy_title') }}</h2>
                    </legend>
                    <div id="privacy-profile-hint" class="govuk-hint">{{ __('govuk_alpha.profile_settings.privacy_profile_hint') }}</div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="privacy_profile">{{ __('govuk_alpha.profile_settings.privacy_profile_label') }}</label>
                        <select class="govuk-select" id="privacy_profile" name="privacy_profile">
                            @foreach (['public', 'members', 'connections'] as $option)
                                <option value="{{ $option }}" @selected($privacyProfile === $option)>{{ __('govuk_alpha.profile_settings.privacy_options.' . $option) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="privacy_search" name="privacy_search" type="checkbox" value="1" @checked($privacySearch)>
                                <label class="govuk-label govuk-checkboxes__label" for="privacy_search">{{ __('govuk_alpha.profile_settings.privacy_search_label') }}</label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha.actions.save_changes') }}</button>
            </form>
        </div>
    </div>
@endsection
