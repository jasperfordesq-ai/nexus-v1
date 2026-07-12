{{-- Copyright Â© 2024â€“2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $successMessages = [
            'captured' => __('event_templates.captured'),
            'revised' => __('event_templates.revised'),
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('event_templates.back_to_events') }}</a>

    @if ($errors->has('template'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('template') }}</p></div>
            </div>
        </div>
    @elseif (isset($successMessages[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ $successMessages[$status] }}</p></div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('event_templates.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_templates.intro') }}</p>

    <div class="govuk-inset-text">
        <strong>{{ __('event_templates.safety_title') }}</strong><br>
        {{ __('event_templates.safety_description') }}
    </div>

    <nav aria-label="{{ __('event_templates.filter_label') }}" class="govuk-button-group">
        @foreach (['active', 'archived', 'all'] as $filterValue)
            <a class="govuk-button{{ $filter === $filterValue ? '' : ' govuk-button--secondary' }}" data-module="govuk-button" href="{{ route('govuk-alpha.events.templates.index', ['tenantSlug' => $tenantSlug, 'filter' => $filterValue]) }}">
                {{ __('event_templates.filters.' . $filterValue) }}
            </a>
        @endforeach
    </nav>

    @if (empty($templates))
        <h2 class="govuk-heading-m">{{ __('event_templates.empty_title') }}</h2>
        <p class="govuk-body">{{ __('event_templates.empty_description') }}</p>
    @else
        <ul class="govuk-list">
            @foreach ($templates as $template)
                <li class="govuk-!-margin-bottom-8">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $template['version']['configuration']['title'] }}</h2>
                    <p class="govuk-body-s">
                        <strong class="govuk-tag{{ $template['status'] === 'archived' ? ' govuk-tag--grey' : '' }}">{{ __('event_templates.status.' . $template['status']) }}</strong>
                    </p>
                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('event_templates.source') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $template['source_event']['title'] }}</dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('event_templates.version') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $template['current_version'] }}</dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('event_templates.used') }}</dt>
                            <dd class="govuk-summary-list__value">{{ __('event_templates.use_count', ['count' => $template['usage']['materialization_count']]) }}</dd>
                        </div>
                    </dl>

                    <div class="govuk-button-group">
                        @if ($template['capabilities']['materialize'])
                            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.events.templates.materialize.form', ['tenantSlug' => $tenantSlug, 'templateId' => $template['id']]) }}">{{ __('event_templates.use_template') }}</a>
                        @endif
                        @if ($template['capabilities']['revise'])
                            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.events.templates.capture.preview', ['tenantSlug' => $tenantSlug, 'id' => $template['source_event']['id'], 'template_id' => $template['id']]) }}">{{ __('event_templates.refresh') }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if (!empty($pagination['has_more']) && !empty($pagination['next_cursor']))
        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.events.templates.index', ['tenantSlug' => $tenantSlug, 'filter' => $filter, 'cursor' => $pagination['next_cursor']]) }}">{{ __('event_templates.load_more') }}</a>
    @endif
@endsection
