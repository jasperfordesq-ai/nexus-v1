{{-- Copyright Â© 2024â€“2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ $template ? route('govuk-alpha.events.templates.index', ['tenantSlug' => $tenantSlug]) : route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $sourceEventId]) }}">{{ __('event_templates.back') }}</a>

    @if ($errors->has('template'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('template') }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $preview['configuration']['title'] }}</span>
    <h1 class="govuk-heading-xl">{{ $template ? __('event_templates.review_revision_title') : __('event_templates.capture_preview_title') }}</h1>
    <p class="govuk-body-l">{{ __('event_templates.capture_preview_intro') }}</p>

    <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="template-safe-title">
        <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="template-safe-title">{{ __('event_templates.safe_title') }}</h2></div>
        <div class="govuk-notification-banner__content"><p>{{ __('event_templates.safe_description') }}</p></div>
    </div>

    <h2 class="govuk-heading-l">{{ __('event_templates.copied_title') }}</h2>
    <ul class="govuk-list govuk-list--bullet">
        @foreach ($preview['copied_fields'] as $field)
            <li>{{ __('event_templates.fields.' . $field) }}</li>
        @endforeach
    </ul>

    <h2 class="govuk-heading-l">{{ __('event_templates.never_copied_title') }}</h2>
    <p class="govuk-body">{{ __('event_templates.never_copied_description') }}</p>
    <ul class="govuk-list govuk-list--bullet">
        @foreach (['people', 'invitations', 'forms', 'attendance', 'tickets', 'notifications', 'federation', 'lifecycle'] as $excluded)
            <li>{{ __('event_templates.never_copied.' . $excluded) }}</li>
        @endforeach
    </ul>

    <h2 class="govuk-heading-l">{{ __('event_templates.checklist_title') }}</h2>
    <ul class="govuk-list govuk-list--tick">
        @foreach ($preview['checklist'] as $check)
            <li>{{ __('event_templates.checks.' . $check['code']) }}</li>
        @endforeach
    </ul>

    <form method="post" action="{{ route('govuk-alpha.events.templates.capture', ['tenantSlug' => $tenantSlug, 'id' => $sourceEventId]) }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        @if ($template)
            <input type="hidden" name="template_id" value="{{ $template['id'] }}">
            <input type="hidden" name="expected_version" value="{{ $template['current_version'] }}">
        @endif
        <button class="govuk-button" data-module="govuk-button">{{ $template ? __('event_templates.confirm_revision') : __('event_templates.confirm_capture') }}</button>
    </form>
@endsection
