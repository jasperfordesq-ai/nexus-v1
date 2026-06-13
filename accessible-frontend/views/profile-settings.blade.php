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
        $marketingOptIn = (bool) ($marketingOptIn ?? false);
        $currentAvatar = $avatarUrl ?? null;
        $successStatuses = ['data-export-requested'];
        $infoStatuses = ['data-export-exists'];
        $errorStatuses = ['avatar-invalid', 'data-export-failed'];
        $statusMessage = [
            'data-export-requested' => __('govuk_alpha.states.data-export-requested'),
            'data-export-exists' => __('govuk_alpha.states.data-export-exists'),
            'data-export-failed' => __('govuk_alpha.states.data-export-failed'),
            'avatar-invalid' => __('govuk_alpha.states.avatar-invalid'),
        ];
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
            @elseif (in_array($status ?? '', $errorStatuses, true))
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#{{ ($status ?? '') === 'avatar-invalid' ? 'avatar' : 'data-export' }}">{{ $statusMessage[$status] ?? '' }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @elseif (in_array($status ?? '', $successStatuses, true))
                <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="settings-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="settings-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ $statusMessage[$status] ?? '' }}</p>
                    </div>
                </div>
            @elseif (in_array($status ?? '', $infoStatuses, true))
                <div class="govuk-notification-banner" role="region" aria-labelledby="settings-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="settings-status-title">{{ __('govuk_alpha.states.important') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ $statusMessage[$status] ?? '' }}</p>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.profile.settings.update', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" novalidate>
                @csrf

                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.profile_settings.photo_title') }}</h2>
                    </legend>

                    @if (!empty($currentAvatar))
                        <img src="{{ $currentAvatar }}" alt="{{ __('govuk_alpha.profile_settings.photo_current_alt') }}" class="nexus-alpha-avatar nexus-alpha-avatar--xl govuk-!-margin-bottom-4" width="96" height="96">
                    @else
                        <p class="govuk-body">{{ __('govuk_alpha.profile_settings.photo_none') }}</p>
                    @endif

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="avatar">{{ __('govuk_alpha.profile_settings.photo_label') }}</label>
                        <div id="avatar-hint" class="govuk-hint">{{ __('govuk_alpha.profile_settings.photo_hint') }}</div>
                        <input class="govuk-file-upload" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="avatar-hint">
                    </div>

                    @if (!empty($currentAvatar))
                        <div class="govuk-form-group">
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="remove_avatar" name="remove_avatar" type="checkbox" value="1">
                                    <label class="govuk-label govuk-checkboxes__label" for="remove_avatar">{{ __('govuk_alpha.profile_settings.photo_remove_label') }}</label>
                                </div>
                            </div>
                        </div>
                    @endif
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
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

                <fieldset class="govuk-fieldset govuk-!-margin-top-7" aria-describedby="marketing-hint">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.profile_settings.marketing_title') }}</h2>
                    </legend>
                    <div id="marketing-hint" class="govuk-hint">{{ __('govuk_alpha.profile_settings.marketing_hint') }}</div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="newsletter_opt_in" name="newsletter_opt_in" type="checkbox" value="1" @checked($marketingOptIn)>
                                <label class="govuk-label govuk-checkboxes__label" for="newsletter_opt_in">{{ __('govuk_alpha.profile_settings.marketing_label') }}</label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha.actions.save_changes') }}</button>
            </form>

            <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

            <section aria-labelledby="data-privacy-heading">
                <h2 class="govuk-heading-l" id="data-privacy-heading">{{ __('govuk_alpha.profile_settings.data_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.profile_settings.data_description') }}</p>

                <h3 class="govuk-heading-m" id="data-export">{{ __('govuk_alpha.profile_settings.data_export_heading') }}</h3>
                <p class="govuk-body">{{ __('govuk_alpha.profile_settings.data_export_body') }}</p>
                <form method="post" action="{{ route('govuk-alpha.profile.data-export', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('govuk_alpha.actions.download_data') }}</button>
                </form>

                <h3 class="govuk-heading-m govuk-!-margin-top-4">{{ __('govuk_alpha.profile_settings.delete_heading') }}</h3>
                <p class="govuk-body">{{ __('govuk_alpha.profile_settings.delete_body') }}</p>
                <a class="govuk-button govuk-button--warning" data-module="govuk-button" href="{{ route('govuk-alpha.profile.delete', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.delete_account') }}</a>
            </section>
        </div>
    </div>
@endsection
