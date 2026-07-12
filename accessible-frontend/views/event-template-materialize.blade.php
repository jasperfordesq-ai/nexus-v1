{{-- Copyright Â© 2024â€“2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $configuration = $template['version']['configuration'];
        $formValues = $values['overrides'] ?? [];
        $allDay = (bool) ($formValues['all_day'] ?? $configuration['all_day']);
        $dateType = $allDay ? 'date' : 'datetime-local';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.templates.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('event_templates.back_to_library') }}</a>

    @if ($errors->has('template'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('template') }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $configuration['title'] }}</span>
    <h1 class="govuk-heading-xl">{{ $preview ? __('event_templates.review_draft_title') : __('event_templates.materialize_title') }}</h1>

    @if (!$preview)
        <p class="govuk-body-l">{{ __('event_templates.materialize_intro') }}</p>
        <div class="govuk-inset-text"><strong>{{ __('event_templates.draft_only_title') }}</strong><br>{{ __('event_templates.draft_only_description') }}</div>

        <form method="post" action="{{ route('govuk-alpha.events.templates.materialize.preview', ['tenantSlug' => $tenantSlug, 'templateId' => $template['id']]) }}" novalidate>
            @csrf
            <input type="hidden" name="template_version" value="{{ $template['current_version'] }}">
            <input type="hidden" name="all_day" value="{{ $allDay ? '1' : '0' }}">

            <div class="govuk-form-group">
                <label class="govuk-label" for="template-start-time">{{ __('event_templates.start_label') }}</label>
                <div id="template-time-hint" class="govuk-hint">{{ __('event_templates.timezone_hint', ['timezone' => old('timezone', $formValues['timezone'] ?? $configuration['timezone'])]) }}</div>
                <input class="govuk-input govuk-input--width-20" id="template-start-time" name="start_time" type="{{ $dateType }}" value="{{ old('start_time', $values['start_time'] ?? '') }}" aria-describedby="template-time-hint" required>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="template-end-time">{{ __('event_templates.end_label') }}</label>
                <input class="govuk-input govuk-input--width-20" id="template-end-time" name="end_time" type="{{ $dateType }}" value="{{ old('end_time', $values['end_time'] ?? '') }}" @if($allDay) required @endif>
            </div>

            <h2 class="govuk-heading-l">{{ __('event_templates.safe_overrides_title') }}</h2>
            <p class="govuk-body">{{ __('event_templates.safe_overrides_intro') }}</p>

            <div class="govuk-form-group">
                <label class="govuk-label" for="template-title">{{ __('event_templates.fields.title') }}</label>
                <input class="govuk-input" id="template-title" name="title" type="text" maxlength="255" value="{{ old('title', $formValues['title'] ?? $configuration['title']) }}" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="template-location">{{ __('event_templates.fields.location') }}</label>
                <input class="govuk-input" id="template-location" name="location" type="text" maxlength="255" value="{{ old('location', $formValues['location'] ?? $configuration['location']) }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="template-capacity">{{ __('event_templates.fields.max_attendees') }}</label>
                <input class="govuk-input govuk-input--width-5" id="template-capacity" name="max_attendees" type="number" min="1" value="{{ old('max_attendees', $formValues['max_attendees'] ?? $configuration['max_attendees']) }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="template-timezone">{{ __('event_templates.fields.timezone') }}</label>
                <input class="govuk-input govuk-input--width-20" id="template-timezone" name="timezone" type="text" maxlength="64" value="{{ old('timezone', $formValues['timezone'] ?? $configuration['timezone']) }}" required>
            </div>

            <button class="govuk-button" data-module="govuk-button">{{ __('event_templates.review_draft') }}</button>
        </form>
    @else
        <p class="govuk-body-l">{{ __('event_templates.review_draft_intro') }}</p>
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="draft-ready-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="draft-ready-title">{{ __('event_templates.ready_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p>{{ __('event_templates.ready_description') }}</p></div>
        </div>

        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_templates.event_title') }}</dt><dd class="govuk-summary-list__value">{{ $preview['configuration']['title'] }}</dd></div>
            <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_templates.publication_state') }}</dt><dd class="govuk-summary-list__value">{{ __('event_templates.draft') }}</dd></div>
            <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_templates.fields.timezone') }}</dt><dd class="govuk-summary-list__value">{{ $preview['schedule']['timezone'] }}</dd></div>
        </dl>

        <h2 class="govuk-heading-l">{{ __('event_templates.checklist_title') }}</h2>
        <ul class="govuk-list govuk-list--bullet">
            @foreach ($preview['checklist'] as $check)
                <li>{{ __('event_templates.checks.' . $check['code']) }}</li>
            @endforeach
        </ul>

        <h2 class="govuk-heading-l">{{ __('event_templates.never_copied_title') }}</h2>
        <ul class="govuk-list govuk-list--bullet">
            @foreach (['people', 'invitations', 'forms', 'attendance', 'tickets', 'notifications', 'federation', 'lifecycle'] as $excluded)
                <li>{{ __('event_templates.never_copied.' . $excluded) }}</li>
            @endforeach
        </ul>

        <form method="post" action="{{ route('govuk-alpha.events.templates.materialize', ['tenantSlug' => $tenantSlug, 'templateId' => $template['id']]) }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="template_version" value="{{ $values['template_version'] }}">
            <input type="hidden" name="start_time" value="{{ $values['start_time'] }}">
            <input type="hidden" name="end_time" value="{{ $values['end_time'] }}">
            <input type="hidden" name="title" value="{{ $values['overrides']['title'] }}">
            <input type="hidden" name="location" value="{{ $values['overrides']['location'] }}">
            <input type="hidden" name="max_attendees" value="{{ $values['overrides']['max_attendees'] }}">
            <input type="hidden" name="timezone" value="{{ $values['overrides']['timezone'] }}">
            <input type="hidden" name="all_day" value="{{ $values['overrides']['all_day'] ? '1' : '0' }}">
            <button class="govuk-button" data-module="govuk-button">{{ __('event_templates.create_draft') }}</button>
        </form>

        <form method="get" action="{{ route('govuk-alpha.events.templates.materialize.form', ['tenantSlug' => $tenantSlug, 'templateId' => $template['id']]) }}">
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('event_templates.change_details') }}</button>
        </form>
    @endif
@endsection
