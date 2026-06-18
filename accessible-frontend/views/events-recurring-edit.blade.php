{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    @php
        $toLocal = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
        $occWhen = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
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
                        <input class="govuk-input govuk-!-width-one-half{{ $errors->has('end_time') ? ' govuk-input--error' : '' }}" id="end_time" name="end_time" type="datetime-local" value="{{ old('end_time', $toLocal($event['end_time'] ?? null)) }}" @error('end_time') aria-describedby="end_time-error" @enderror>
                    </div>
                </fieldset>

                {{-- Scope chooser — the no-JS equivalent of React's edit-scope modal --}}
                <div class="govuk-warning-text govuk-!-margin-top-7">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}</span>
                        {{ __('govuk_alpha_events.recurring_edit.scope_all_warning') }}
                    </strong>
                </div>

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
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="scope-all" name="scope" type="radio" value="all" @checked(old('scope') === 'all')>
                                <label class="govuk-label govuk-radios__label" for="scope-all">{{ __('govuk_alpha_events.recurring_edit.scope_all') }}</label>
                                <div class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_events.recurring_edit.scope_all_hint') }}</div>
                            </div>
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
