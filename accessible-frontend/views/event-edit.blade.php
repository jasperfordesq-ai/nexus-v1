{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha.events.back_to_event') }}</a>

    @php
        $toLocal = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha.events.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.edit_title') }}</h1>

            @if ($errors->any())
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                @foreach ($errors->keys() as $field)
                                    <li><a href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.events.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" novalidate>
                @csrf

                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_details_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group{{ $errors->has('title') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="title">{{ __('govuk_alpha.events.title_label') }}</label>
                        @error('title')
                            <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" value="{{ old('title', $event['title'] ?? '') }}" maxlength="255" @error('title') aria-describedby="title-error" @enderror required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('description') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="description">{{ __('govuk_alpha.events.description_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.events.description_hint') }}</div>
                        @error('description')
                            <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <textarea class="govuk-textarea{{ $errors->has('description') ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="6" aria-describedby="description-hint @error('description') description-error @enderror" required>{{ old('description', $event['description'] ?? '') }}</textarea>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="category_id">{{ __('govuk_alpha.events.category_label') }}</label>
                        <select class="govuk-select" id="category_id" name="category_id">
                            <option value="">{{ __('govuk_alpha.events.no_category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}" @selected((string) old('category_id', $event['category_id'] ?? '') === (string) $category['id'])>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_time_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group{{ $errors->has('start_time') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="start_time">{{ __('govuk_alpha.events.start_time_label') }}</label>
                        <div id="start-time-hint" class="govuk-hint">{{ __('govuk_alpha.events.datetime_hint') }}</div>
                        @error('start_time')
                            <p id="start_time-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('start_time') ? ' govuk-input--error' : '' }}" id="start_time" name="start_time" type="datetime-local" value="{{ old('start_time', $toLocal($event['start_time'] ?? null)) }}" aria-describedby="start-time-hint @error('start_time') start_time-error @enderror" required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('end_time') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="end_time">{{ __('govuk_alpha.events.end_time_label') }}</label>
                        <div id="end-time-hint" class="govuk-hint">{{ __('govuk_alpha.events.end_time_hint') }}</div>
                        @error('end_time')
                            <p id="end_time-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('end_time') ? ' govuk-input--error' : '' }}" id="end_time" name="end_time" type="datetime-local" value="{{ old('end_time', $toLocal($event['end_time'] ?? null)) }}" aria-describedby="end-time-hint @error('end_time') end_time-error @enderror">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_place_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="location">{{ __('govuk_alpha.events.location_label') }}</label>
                        <input class="govuk-input" id="location" name="location" type="text" value="{{ old('location', $event['location'] ?? '') }}" maxlength="255">
                    </div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="is_online" name="is_online" type="checkbox" value="1" @checked(old('is_online', $event['is_online'] ?? false))>
                                <label class="govuk-label govuk-checkboxes__label" for="is_online">{{ __('govuk_alpha.events.is_online_label') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="online_link">{{ __('govuk_alpha.events.online_link_label') }}</label>
                        <input class="govuk-input" id="online_link" name="online_link" type="url" value="{{ old('online_link', $event['online_link'] ?? '') }}">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_capacity_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="max_attendees">{{ __('govuk_alpha.events.max_attendees_label') }}</label>
                        <div id="max-attendees-hint" class="govuk-hint">{{ __('govuk_alpha.events.max_attendees_hint') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="max_attendees" name="max_attendees" type="number" min="1" step="1" value="{{ old('max_attendees', $event['max_attendees'] ?? '') }}" aria-describedby="max-attendees-hint">
                    </div>
                </fieldset>

                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha.events.update_submit') }}</button>
            </form>
        </div>
    </div>
@endsection
