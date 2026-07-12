{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}

@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
        {{ __('govuk_alpha.events.calendar_back') }}
    </a>

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.calendar_actions_title') }}</h1>
    <p class="govuk-body-l">{{ $event['title'] }}</p>
    <p class="govuk-body">{{ __('govuk_alpha.events.calendar_actions_intro') }}</p>

    <ul class="govuk-list govuk-list--spaced">
        <li>
            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.events.calendar.download', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                {{ __('govuk_alpha.events.calendar_download') }}
            </a>
        </li>
        <li>
            <a class="govuk-link" href="{{ $calendarActions['google_url'] }}" rel="noopener noreferrer">
                {{ __('govuk_alpha.events.calendar_google') }}
            </a>
        </li>
        <li>
            <a class="govuk-link" href="{{ $calendarActions['outlook_url'] }}" rel="noopener noreferrer">
                {{ __('govuk_alpha.events.calendar_outlook') }}
            </a>
        </li>
    </ul>
@endsection
