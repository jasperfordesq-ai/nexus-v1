{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_events') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.events.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.create_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.events.create_description') }}</p>

            @if (($status ?? '') === 'event-create-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#title">{{ __('govuk_alpha.events.create_failed') }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.events.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf

                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_details_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="title">{{ __('govuk_alpha.events.title_label') }}</label>
                        <input class="govuk-input" id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" required>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="description">{{ __('govuk_alpha.events.description_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.events.description_hint') }}</div>
                        <textarea class="govuk-textarea" id="description" name="description" rows="6" aria-describedby="description-hint" required>{{ old('description') }}</textarea>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="category_id">{{ __('govuk_alpha.events.category_label') }}</label>
                        <select class="govuk-select" id="category_id" name="category_id">
                            <option value="">{{ __('govuk_alpha.events.no_category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}" @selected((string) old('category_id') === (string) $category['id'])>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_time_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="start_time">{{ __('govuk_alpha.events.start_time_label') }}</label>
                        <div id="start-time-hint" class="govuk-hint">{{ __('govuk_alpha.events.datetime_hint') }}</div>
                        <input class="govuk-input govuk-!-width-one-half" id="start_time" name="start_time" type="datetime-local" value="{{ old('start_time') }}" aria-describedby="start-time-hint" required>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="end_time">{{ __('govuk_alpha.events.end_time_label') }}</label>
                        <div id="end-time-hint" class="govuk-hint">{{ __('govuk_alpha.events.end_time_hint') }}</div>
                        <input class="govuk-input govuk-!-width-one-half" id="end_time" name="end_time" type="datetime-local" value="{{ old('end_time') }}" aria-describedby="end-time-hint">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_place_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="location">{{ __('govuk_alpha.events.location_label') }}</label>
                        <input class="govuk-input" id="location" name="location" type="text" value="{{ old('location') }}" maxlength="255">
                    </div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="is_online" name="is_online" type="checkbox" value="1" @checked(old('is_online'))>
                                <label class="govuk-label govuk-checkboxes__label" for="is_online">{{ __('govuk_alpha.events.is_online_label') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="online_link">{{ __('govuk_alpha.events.online_link_label') }}</label>
                        <input class="govuk-input" id="online_link" name="online_link" type="url" value="{{ old('online_link') }}">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_capacity_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="max_attendees">{{ __('govuk_alpha.events.max_attendees_label') }}</label>
                        <div id="max-attendees-hint" class="govuk-hint">{{ __('govuk_alpha.events.max_attendees_hint') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="max_attendees" name="max_attendees" type="number" min="1" step="1" value="{{ old('max_attendees') }}" aria-describedby="max-attendees-hint">
                    </div>
                </fieldset>

                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha.actions.create_event') }}</button>
            </form>
        </div>
    </div>
@endsection
