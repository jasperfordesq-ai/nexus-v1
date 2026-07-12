{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    @php
        $eventTimezone = $event['timezone'] ?? 'UTC';
        $toLocal = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->setTimezone($eventTimezone)->format('Y-m-d\TH:i') : '';
        $visibleEnd = !empty($event['end_time'])
            ? \Illuminate\Support\Carbon::parse($event['end_time'])->setTimezone($eventTimezone)
                ->when(!empty($event['all_day']), static fn ($date) => $date->subDay())
                ->format('Y-m-d\TH:i')
            : '';
        $occWhen = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $supportsEffectiveRevisions = (bool) ($recurrenceCapabilities['supports_effective_revisions'] ?? false);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha_events.recurring_edit.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.recurring_edit.title') }}</h1>

            @if ($errors->any())
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
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

            <p class="govuk-body-l">{{ __('govuk_alpha_events.recurring_edit.intro') }}</p>

            <form method="post" action="{{ route('govuk-alpha.events.recurring.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" novalidate>
                @csrf
                <input type="hidden" name="timezone" value="{{ $eventTimezone }}">
                <input type="hidden" name="all_day" value="{{ !empty($event['all_day']) ? '1' : '0' }}">

                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_events.recurring_edit.details_legend') }}</h2>
                    </legend>

                    <div class="govuk-form-group{{ $errors->has('title') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="title">{{ __('govuk_alpha_events.recurring_edit.title_label') }}</label>
                        @error('title')
                            <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" value="{{ old('title', $event['title'] ?? '') }}" maxlength="255" @error('title') aria-describedby="title-error" @enderror required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('description') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="description">{{ __('govuk_alpha_events.recurring_edit.description_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_events.recurring_edit.description_hint') }}</div>
                        @error('description')
                            <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <textarea class="govuk-textarea{{ $errors->has('description') ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="6" aria-describedby="description-hint{{ $errors->has('description') ? ' description-error' : '' }}" required>{{ old('description', $event['description'] ?? '') }}</textarea>
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

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="location">{{ __('govuk_alpha_events.recurring_edit.location_label') }}</label>
                        <input class="govuk-input" id="location" name="location" type="text" value="{{ old('location', $event['location'] ?? '') }}" maxlength="255">
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_events.recurring_edit.time_legend') }}</h2>
                    </legend>

                    <div class="govuk-form-group{{ $errors->has('start_time') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="start_time">{{ __('govuk_alpha_events.recurring_edit.start_time_label') }}</label>
                        <div id="start-time-hint" class="govuk-hint">{{ __('govuk_alpha_events.recurring_edit.datetime_hint') }}</div>
                        @error('start_time')
                            <p id="start_time-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('start_time') ? ' govuk-input--error' : '' }}" id="start_time" name="start_time" type="datetime-local" value="{{ old('start_time', $toLocal($event['start_time'] ?? null)) }}" aria-describedby="start-time-hint{{ $errors->has('start_time') ? ' start_time-error' : '' }}" required>
                    </div>

                    <div class="govuk-form-group{{ $errors->has('end_time') ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="end_time">{{ __('govuk_alpha_events.recurring_edit.end_time_label') }}</label>
                        @error('end_time')
                            <p id="end_time-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ $message }}</p>
                        @enderror
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('end_time') ? ' govuk-input--error' : '' }}" id="end_time" name="end_time" type="datetime-local" value="{{ old('end_time', $visibleEnd) }}" @error('end_time') aria-describedby="end_time-error" @enderror>
                    </div>
                </fieldset>

                <fieldset class="govuk-fieldset govuk-!-margin-top-7">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.create_place_title') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                @php $isOnlineChecked = (bool) old('is_online', $event['is_online'] ?? false); @endphp
                                <input type="hidden" name="is_online" value="0">
                                <input class="govuk-checkboxes__input" id="is_online" name="is_online" type="checkbox" value="1" @checked($isOnlineChecked) data-aria-controls="is-online-conditional">
                                <label class="govuk-label govuk-checkboxes__label" for="is_online">{{ __('govuk_alpha.events.is_online_label') }}</label>
                            </div>
                            <div class="govuk-checkboxes__conditional{{ $isOnlineChecked ? '' : ' govuk-checkboxes__conditional--hidden' }}" id="is-online-conditional">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="online_link">{{ __('govuk_alpha.events.online_link_label') }}</label>
                                    <input class="govuk-input" id="online_link" name="online_link" type="url" value="{{ old('online_link', $event['online_link'] ?? '') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="govuk-form-group">
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                @php $allowRemoteChecked = (bool) old('allow_remote_attendance', $event['allow_remote_attendance'] ?? false); @endphp
                                <input type="hidden" name="allow_remote_attendance" value="0">
                                <input class="govuk-checkboxes__input" id="allow_remote_attendance" name="allow_remote_attendance" type="checkbox" value="1" @checked($allowRemoteChecked) data-aria-controls="remote-attendance-conditional">
                                <label class="govuk-label govuk-checkboxes__label" for="allow_remote_attendance">{{ __('govuk_alpha.events.polish_events.allow_remote_label') }}</label>
                                <div id="allow-remote-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha.events.polish_events.allow_remote_hint') }}</div>
                            </div>
                            <div class="govuk-checkboxes__conditional{{ $allowRemoteChecked ? '' : ' govuk-checkboxes__conditional--hidden' }}" id="remote-attendance-conditional">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="video_url">{{ __('govuk_alpha.events.polish_events.video_url_label') }}</label>
                                    <div id="video-url-hint" class="govuk-hint">{{ __('govuk_alpha.events.polish_events.video_url_hint') }}</div>
                                    <input class="govuk-input" id="video_url" name="video_url" type="url" value="{{ old('video_url', $event['video_url'] ?? '') }}" aria-describedby="video-url-hint">
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                @php
                    $venueAccess = [
                        'step_free_access' => $event['accessibility_step_free'] ?? null,
                        'accessible_toilet' => $event['accessibility_toilet'] ?? null,
                        'hearing_loop' => $event['accessibility_hearing_loop'] ?? null,
                        'quiet_space' => $event['accessibility_quiet_space'] ?? null,
                        'seating_available' => $event['accessibility_seating'] ?? null,
                        'accessible_parking' => $event['accessibility_parking'] ?? null,
                        'parking_details' => $event['accessibility_parking_details'] ?? null,
                        'transit_details' => $event['accessibility_transit_details'] ?? null,
                        'assistance_contact' => $event['accessibility_assistance_contact'] ?? null,
                        'notes' => $event['accessibility_notes'] ?? null,
                    ];
                @endphp
                @include('accessible-frontend::partials.event-venue-accessibility-fields', ['venueAccess' => $venueAccess])

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

                {{-- Scope chooser — the no-JS equivalent of React's edit-scope modal --}}
                @if ($supportsEffectiveRevisions)
                    <div class="govuk-warning-text govuk-!-margin-top-7">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}</span>
                            {{ __('govuk_alpha_events.recurring_edit.scope_all_warning') }}
                        </strong>
                    </div>
                @else
                    <div class="govuk-inset-text govuk-!-margin-top-7">{{ __('govuk_alpha_events.recurring_edit.unavailable') }}</div>
                @endif

                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset" aria-describedby="scope-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_events.recurring_edit.scope_legend') }}</h2>
                        </legend>
                        <div id="scope-hint" class="govuk-hint">{{ __('govuk_alpha_events.recurring_edit.scope_hint') }}</div>
                        <div class="govuk-radios" data-module="govuk-radios">
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="scope-single" name="scope" type="radio" value="single" @checked(old('scope', 'single') === 'single')>
                                <label class="govuk-label govuk-radios__label" for="scope-single">{{ __('govuk_alpha_events.recurring_edit.scope_single') }}</label>
                                <div class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_events.recurring_edit.scope_single_hint') }}</div>
                            </div>
                            @if ($supportsEffectiveRevisions)
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="scope-all" name="scope" type="radio" value="all" @checked(old('scope') === 'all')>
                                    <label class="govuk-label govuk-radios__label" for="scope-all">{{ __('govuk_alpha_events.recurring_edit.scope_all') }}</label>
                                    <div class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_events.recurring_edit.scope_all_hint') }}</div>
                                </div>
                            @endif
                        </div>
                    </fieldset>
                </div>

                <button class="govuk-button govuk-!-margin-top-2" data-module="govuk-button" type="submit">{{ __('govuk_alpha_events.recurring_edit.submit') }}</button>
            </form>

            {{-- Upcoming dates in this series (series_occurrences from getById) --}}
            @if (!empty($occurrences) && count($occurrences) > 1)
                <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha_events.recurring_edit.upcoming_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha_events.recurring_edit.upcoming_intro') }}</p>
                <ul class="govuk-list">
                    @foreach ($occurrences as $occ)
                        @php
                            $occId = (int) ($occ['id'] ?? 0);
                            $when = $occWhen($occ['start_time'] ?? null);
                            $isCurrent = $occId === (int) ($event['id'] ?? 0);
                        @endphp
                        <li>
                            @if ($isCurrent)
                                <span class="govuk-!-font-weight-bold">{{ $when }}</span>
                                <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_events.recurring_edit.this_date') }}</strong>
                            @else
                                <a class="govuk-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $occId]) }}">{{ $when ?? __('govuk_alpha_events.recurring_edit.view_date_link') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
