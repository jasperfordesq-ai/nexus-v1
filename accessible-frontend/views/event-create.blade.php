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
            @elseif (($status ?? '') === 'event-create-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
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

            <form method="post" action="{{ route('govuk-alpha.events.store', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" novalidate>
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
                        <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" @error('title') aria-describedby="title-error" @enderror required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('description') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="description">{{ __('govuk_alpha.events.description_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.events.description_hint') }}</div>
                        @error('description')
                            <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <textarea class="govuk-textarea{{ $errors->has('description') ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="6" aria-describedby="description-hint{{ $errors->has('description') ? ' description-error' : '' }}" required>{{ old('description') }}</textarea>
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

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="image">{{ __('govuk_alpha.events.create_image_label') }}</label>
                        <div id="image-hint" class="govuk-hint">{{ __('govuk_alpha.events.create_image_hint') }}</div>
                        <input class="govuk-file-upload" id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="image-hint">
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
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('start_time') ? ' govuk-input--error' : '' }}" id="start_time" name="start_time" type="datetime-local" value="{{ old('start_time') }}" aria-describedby="start-time-hint{{ $errors->has('start_time') ? ' start_time-error' : '' }}" required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('end_time') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="end_time">{{ __('govuk_alpha.events.end_time_label') }}</label>
                        <div id="end-time-hint" class="govuk-hint">{{ __('govuk_alpha.events.end_time_hint') }}</div>
                        @error('end_time')
                            <p id="end_time-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('end_time') ? ' govuk-input--error' : '' }}" id="end_time" name="end_time" type="datetime-local" value="{{ old('end_time') }}" aria-describedby="end-time-hint{{ $errors->has('end_time') ? ' end_time-error' : '' }}">
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

                    {{-- is_online with conditional-reveal for online_link — govuk-frontend JS handles show/hide --}}
                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="is_online" name="is_online" type="checkbox" value="1" @checked(old('is_online')) data-aria-controls="is-online-conditional">
                                <label class="govuk-label govuk-checkboxes__label" for="is_online">{{ __('govuk_alpha.events.is_online_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__conditional{{ old('is_online') ? '' : ' govuk-checkboxes__conditional--hidden' }}" id="is-online-conditional">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="online_link">{{ __('govuk_alpha.events.online_link_label') }}</label>
                                    <input class="govuk-input" id="online_link" name="online_link" type="url" value="{{ old('online_link') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- allow_remote_attendance with conditional-reveal for video_url — WAVE NIGHT-EVENTS --}}
                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="allow_remote_attendance" name="allow_remote_attendance" type="checkbox" value="1" @checked(old('allow_remote_attendance')) data-aria-controls="remote-attendance-conditional">
                                <label class="govuk-label govuk-checkboxes__label" for="allow_remote_attendance">{{ __('govuk_alpha.events.polish_events.allow_remote_label') }}</label>
                                <div id="allow-remote-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha.events.polish_events.allow_remote_hint') }}</div>
                            </div>
                            <div class="govuk-checkboxes__conditional{{ old('allow_remote_attendance') ? '' : ' govuk-checkboxes__conditional--hidden' }}" id="remote-attendance-conditional">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="video_url">{{ __('govuk_alpha.events.polish_events.video_url_label') }}</label>
                                    <div id="video-url-hint" class="govuk-hint">{{ __('govuk_alpha.events.polish_events.video_url_hint') }}</div>
                                    <input class="govuk-input" id="video_url" name="video_url" type="url" value="{{ old('video_url') }}" aria-describedby="video-url-hint">
                                </div>
                            </div>
                        </div>
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

                {{-- ===== Recurrence — WAVE NIGHT-EVENTS ===== --}}
                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.polish_events.recurrence_section_title') }}</h2>
                    </legend>
                    <div id="recurrence-hint" class="govuk-hint">{{ __('govuk_alpha.events.polish_events.recurrence_hint') }}</div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="is_recurring" name="is_recurring" type="checkbox" value="1" @checked(old('is_recurring')) data-aria-controls="recurrence-conditional" aria-describedby="recurrence-hint">
                                <label class="govuk-label govuk-checkboxes__label" for="is_recurring">{{ __('govuk_alpha.events.polish_events.recurrence_repeat_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__conditional{{ old('is_recurring') ? '' : ' govuk-checkboxes__conditional--hidden' }}" id="recurrence-conditional">
                                {{-- Frequency --}}
                                <div class="govuk-form-group">
                                    <fieldset class="govuk-fieldset">
                                        <legend class="govuk-fieldset__legend">{{ __('govuk_alpha.events.polish_events.recurrence_frequency_legend') }}</legend>
                                        <div class="govuk-radios govuk-radios--small" data-module="govuk-radios" id="recurrence_frequency">
                                            @foreach ([
                                                'daily'   => __('govuk_alpha.events.polish_events.recurrence_freq_daily'),
                                                'weekly'  => __('govuk_alpha.events.polish_events.recurrence_freq_weekly'),
                                                'biweekly' => __('govuk_alpha.events.polish_events.recurrence_freq_biweekly'),
                                                'monthly' => __('govuk_alpha.events.polish_events.recurrence_freq_monthly'),
                                            ] as $freq => $label)
                                                @php $freqValue = $freq === 'biweekly' ? 'weekly' : $freq; $interval = $freq === 'biweekly' ? 2 : 1; @endphp
                                                <div class="govuk-radios__item">
                                                    <input class="govuk-radios__input" id="freq-{{ $freq }}" name="recurrence_frequency" type="radio" value="{{ $freqValue }}"
                                                        @php
                                                            $oldFreq = old('recurrence_frequency', 'weekly');
                                                            $oldInterval = (int) old('recurrence_interval', 1);
                                                            $isChecked = ($oldFreq === $freqValue && (($freq === 'biweekly' && $oldInterval === 2) || ($freq !== 'biweekly' && $oldInterval <= 1)));
                                                        @endphp
                                                        @checked($isChecked)>
                                                    <label class="govuk-label govuk-radios__label" for="freq-{{ $freq }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                        {{-- Hidden interval; biweekly JS-free approach: frequency=weekly + interval=2 --}}
                                        <input type="hidden" name="recurrence_interval" value="{{ old('recurrence_interval', 1) }}">
                                    </fieldset>
                                </div>

                                {{-- End condition --}}
                                <div class="govuk-form-group">
                                    <fieldset class="govuk-fieldset">
                                        <legend class="govuk-fieldset__legend">{{ __('govuk_alpha.events.polish_events.recurrence_end_legend') }}</legend>
                                        <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                            <div class="govuk-radios__item">
                                                <input class="govuk-radios__input" id="rec-end-count" name="recurrence_ends_type" type="radio" value="after_count" @checked(old('recurrence_ends_type', 'after_count') === 'after_count') data-aria-controls="rec-count-conditional">
                                                <label class="govuk-label govuk-radios__label" for="rec-end-count">{{ __('govuk_alpha.events.polish_events.recurrence_end_after') }}</label>
                                            </div>
                                            <div class="govuk-radios__conditional{{ old('recurrence_ends_type', 'after_count') === 'after_count' ? '' : ' govuk-radios__conditional--hidden' }}" id="rec-count-conditional">
                                                <div class="govuk-form-group">
                                                    <label class="govuk-label" for="recurrence_ends_after_count">{{ __('govuk_alpha.events.polish_events.recurrence_count_label') }}</label>
                                                    <div id="rec-count-hint" class="govuk-hint">{{ __('govuk_alpha.events.polish_events.recurrence_count_hint') }}</div>
                                                    <input class="govuk-input govuk-input--width-3" id="recurrence_ends_after_count" name="recurrence_ends_after_count" type="number" min="1" max="52" value="{{ old('recurrence_ends_after_count', 10) }}" aria-describedby="rec-count-hint">
                                                </div>
                                            </div>
                                            <div class="govuk-radios__item">
                                                <input class="govuk-radios__input" id="rec-end-date" name="recurrence_ends_type" type="radio" value="on_date" @checked(old('recurrence_ends_type') === 'on_date') data-aria-controls="rec-date-conditional">
                                                <label class="govuk-label govuk-radios__label" for="rec-end-date">{{ __('govuk_alpha.events.polish_events.recurrence_end_on_date') }}</label>
                                            </div>
                                            <div class="govuk-radios__conditional{{ old('recurrence_ends_type') === 'on_date' ? '' : ' govuk-radios__conditional--hidden' }}" id="rec-date-conditional">
                                                <div class="govuk-form-group">
                                                    <label class="govuk-label" for="recurrence_ends_on_date">{{ __('govuk_alpha.events.polish_events.recurrence_end_date_label') }}</label>
                                                    <div id="rec-date-hint" class="govuk-hint">{{ __('govuk_alpha.events.polish_events.recurrence_end_date_hint') }}</div>
                                                    <input class="govuk-input govuk-!-width-one-half" id="recurrence_ends_on_date" name="recurrence_ends_on_date" type="date" value="{{ old('recurrence_ends_on_date') }}" aria-describedby="rec-date-hint">
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('govuk_alpha.actions.create_event') }}</button>
            </form>
        </div>
    </div>
@endsection
