{{--
  Copyright © 2024–2026 Jasper Ford
  SPDX-License-Identifier: AGPL-3.0-or-later
  Author: Jasper Ford
  See NOTICE file for attribution and acknowledgements.
--}}
@extends('accessible-frontend::layouts.app')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.registration.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        {{ __('event_registration.title') }}
    </a>

    <h1 class="govuk-heading-xl">{{ __('event_registration.forms.editor.' . ($form ? 'edit_title' : 'create_title')) }}</h1>
    <p class="govuk-body-l">{{ __('event_registration.forms.editor.rules_description') }}</p>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" role="alert" tabindex="-1">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    @foreach ($errors->all() as $error)<li><a href="#form-name">{{ $error }}</a></li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="govuk-inset-text">{{ __('event_registration.accessible.form_slots_hint') }}</div>

    <form method="post" action="{{ $form
        ? route('govuk-alpha.events.registration.forms.update', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'formId' => $form->id])
        : route('govuk-alpha.events.registration.forms.create', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="expected_settings_revision" value="{{ $settingsRevision }}">
        @if ($form)<input type="hidden" name="expected_form_revision" value="{{ $form->revision }}">@endif

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="form-name">{{ __('event_registration.forms.editor.name') }}</label>
            <input class="govuk-input" id="form-name" name="name" type="text" value="{{ old('name', $form?->name ?? '') }}" maxlength="255" required>
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label" for="form-description">{{ __('event_registration.forms.editor.description') }}</label>
            <textarea class="govuk-textarea" id="form-description" name="description" rows="4" maxlength="2000">{{ old('description', $form?->description ?? '') }}</textarea>
        </div>

        @foreach ($questionRows as $index => $row)
            @php
                $existing = !empty($row);
                $prefix = "questions[$index]";
                $type = old("questions.$index.question_type", $row['question_type'] ?? 'short_text');
                if ($type instanceof \BackedEnum) $type = $type->value;
                $classification = old("questions.$index.data_classification", $row['data_classification'] ?? 'internal');
                if ($classification instanceof \BackedEnum) $classification = $classification->value;
                $choices = old("questions.$index.choices", isset($row['choice_options']) && is_array($row['choice_options']) ? implode("\n", $row['choice_options']) : '');
                $validation = is_array($row['validation_rules'] ?? null) ? $row['validation_rules'] : [];
                $visibility = is_array($row['visibility_rules'] ?? null) ? $row['visibility_rules'] : [];
                $condition = is_array($visibility['conditions'][0] ?? null) ? $visibility['conditions'][0] : [];
            @endphp
            <fieldset class="govuk-fieldset govuk-!-margin-bottom-8">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                    <h2 class="govuk-fieldset__heading">{{ __('event_registration.forms.editor.question', ['number' => $index + 1]) }}</h2>
                </legend>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="question-{{ $index }}-enabled" name="{{ $prefix }}[enabled]" type="checkbox" value="1" @checked(old("questions.$index.enabled", $existing))>
                        <label class="govuk-label govuk-checkboxes__label" for="question-{{ $index }}-enabled">{{ __('event_registration.accessible.enabled') }}</label>
                    </div>
                </div>
                <input type="hidden" name="{{ $prefix }}[stable_key]" value="{{ old("questions.$index.stable_key", $row['stable_key'] ?? 'question_' . ($index + 1)) }}">
                <div class="govuk-form-group govuk-!-margin-top-4">
                    <label class="govuk-label" for="question-{{ $index }}-type">{{ __('event_registration.forms.editor.type') }}</label>
                    <select class="govuk-select" id="question-{{ $index }}-type" name="{{ $prefix }}[question_type]">
                        @foreach (['short_text', 'long_text', 'single_choice', 'multiple_choice', 'dietary', 'accessibility', 'consent', 'waiver'] as $option)
                            <option value="{{ $option }}" @selected($type === $option)>{{ __('event_registration.question_types.' . $option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-classification">{{ __('event_registration.forms.editor.classification') }}</label>
                    <select class="govuk-select" id="question-{{ $index }}-classification" name="{{ $prefix }}[data_classification]">
                        @foreach (['public', 'internal', 'confidential', 'sensitive'] as $option)
                            <option value="{{ $option }}" @selected($classification === $option)>{{ __('event_registration.classifications.' . $option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-prompt">{{ __('event_registration.forms.editor.prompt') }}</label>
                    <textarea class="govuk-textarea" id="question-{{ $index }}-prompt" name="{{ $prefix }}[prompt]" rows="2">{{ old("questions.$index.prompt", $row['prompt'] ?? '') }}</textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-help">{{ __('event_registration.forms.editor.help_text') }}</label>
                    <textarea class="govuk-textarea" id="question-{{ $index }}-help" name="{{ $prefix }}[help_text]" rows="2">{{ old("questions.$index.help_text", $row['help_text'] ?? '') }}</textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-purpose">{{ __('event_registration.forms.editor.purpose') }}</label>
                    <textarea class="govuk-textarea" id="question-{{ $index }}-purpose" name="{{ $prefix }}[purpose]" rows="2">{{ old("questions.$index.purpose", $row['purpose'] ?? '') }}</textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-retention">{{ __('event_registration.forms.editor.retention_days') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="question-{{ $index }}-retention" name="{{ $prefix }}[retention_days]" type="number" min="1" max="36500" value="{{ old("questions.$index.retention_days", $row['retention_days'] ?? 365) }}">
                </div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="question-{{ $index }}-required" name="{{ $prefix }}[is_required]" type="checkbox" value="1" @checked(old("questions.$index.is_required", $row['is_required'] ?? false))>
                        <label class="govuk-label govuk-checkboxes__label" for="question-{{ $index }}-required">{{ __('event_registration.forms.editor.required') }}</label>
                    </div>
                </div>
                <div class="govuk-form-group govuk-!-margin-top-4">
                    <label class="govuk-label" for="question-{{ $index }}-choices">{{ __('event_registration.forms.editor.choices') }}</label>
                    <div class="govuk-hint">{{ __('event_registration.forms.editor.choices_description') }}</div>
                    <textarea class="govuk-textarea" id="question-{{ $index }}-choices" name="{{ $prefix }}[choices]" rows="4">{{ $choices }}</textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-min">{{ __('event_registration.forms.editor.min_length') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="question-{{ $index }}-min" name="{{ $prefix }}[min_length]" type="number" min="0" value="{{ old("questions.$index.min_length", $validation['min_length'] ?? '') }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-max">{{ __('event_registration.forms.editor.max_length') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="question-{{ $index }}-max" name="{{ $prefix }}[max_length]" type="number" min="0" value="{{ old("questions.$index.max_length", $validation['max_length'] ?? '') }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-displayed">{{ __('event_registration.forms.editor.displayed_text') }}</label>
                    <textarea class="govuk-textarea" id="question-{{ $index }}-displayed" name="{{ $prefix }}[displayed_text]" rows="4">{{ old("questions.$index.displayed_text", $row['displayed_text'] ?? '') }}</textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-displayed-version">{{ __('event_registration.forms.editor.displayed_version') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="question-{{ $index }}-displayed-version" name="{{ $prefix }}[displayed_text_version]" type="text" value="{{ old("questions.$index.displayed_text_version", $row['displayed_text_version'] ?? '') }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-condition-key">{{ __('event_registration.accessible.condition_key') }}</label>
                    <input class="govuk-input" id="question-{{ $index }}-condition-key" name="{{ $prefix }}[condition_key]" type="text" value="{{ old("questions.$index.condition_key", $condition['question_key'] ?? '') }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-condition-operator">{{ __('event_registration.forms.editor.condition_operator') }}</label>
                    <select class="govuk-select" id="question-{{ $index }}-condition-operator" name="{{ $prefix }}[condition_operator]">
                        @foreach (['equals', 'not_equals', 'contains', 'not_contains'] as $operator)
                            <option value="{{ $operator }}" @selected(old("questions.$index.condition_operator", $condition['operator'] ?? 'equals') === $operator)>{{ __('event_registration.operators.' . $operator) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="question-{{ $index }}-condition-value">{{ __('event_registration.forms.editor.condition_value') }}</label>
                    <input class="govuk-input" id="question-{{ $index }}-condition-value" name="{{ $prefix }}[condition_value]" type="text" value="{{ old("questions.$index.condition_value", $condition['value'] ?? '') }}">
                </div>
            </fieldset>
        @endforeach

        <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_registration.common.save') }}</button>
        <a class="govuk-link govuk-!-margin-left-3" href="{{ route('govuk-alpha.events.registration.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('event_registration.common.cancel') }}</a>
    </form>
@endsection
