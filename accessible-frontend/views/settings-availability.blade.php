{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $availabilityByDay = is_array($availabilityByDay ?? null) ? $availabilityByDay : [];
        $displayDays = is_array($displayDays ?? null) ? $displayDays : [1, 2, 3, 4, 5, 6, 0];
        $slotsPerDay = (int) ($slotsPerDay ?? 3);
        $dayLabels = __('govuk_alpha_settings.availability.day_labels');
        $dayLabels = is_array($dayLabels) ? $dayLabels : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.account', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_settings.availability.back_link') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_settings.availability.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_settings.availability.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_settings.availability.description') }}</p>

    @if (($status ?? null) === 'availability-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="availability-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="availability-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_settings.states.availability-saved') }}</p></div>
        </div>
    @elseif (in_array($status ?? null, ['availability-invalid', 'availability-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_settings.states.' . $status) }}</p></div></div>
        </div>
    @endif

    <div class="govuk-inset-text" id="availability">{{ __('govuk_alpha_settings.availability.instructions') }}</div>

    <form method="post" action="{{ route('govuk-alpha.settings.availability.update', ['tenantSlug' => $tenantSlug]) }}" novalidate>
        @csrf
        @foreach ($displayDays as $pos => $backendDay)
            @php
                $dayName = $dayLabels[$pos] ?? ('Day ' . $pos);
                $existing = is_array($availabilityByDay[$backendDay] ?? null) ? array_values($availabilityByDay[$backendDay]) : [];
            @endphp
            <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ $dayName }}</legend>
                @for ($i = 0; $i < $slotsPerDay; $i++)
                    @php
                        $slotStart = $existing[$i]['start'] ?? '';
                        $slotEnd = $existing[$i]['end'] ?? '';
                    @endphp
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="start-{{ $backendDay }}-{{ $i }}">{{ __('govuk_alpha_settings.availability.slot_start_label') }}</label>
                                <input class="govuk-input govuk-input--width-5" id="start-{{ $backendDay }}-{{ $i }}" name="slots[{{ $backendDay }}][{{ $i }}][start]" type="time" value="{{ $slotStart }}">
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="end-{{ $backendDay }}-{{ $i }}">{{ __('govuk_alpha_settings.availability.slot_end_label') }}</label>
                                <input class="govuk-input govuk-input--width-5" id="end-{{ $backendDay }}-{{ $i }}" name="slots[{{ $backendDay }}][{{ $i }}][end]" type="time" value="{{ $slotEnd }}">
                            </div>
                        </div>
                    </div>
                @endfor
            </fieldset>
        @endforeach
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_settings.availability.save_button') }}</button>
    </form>
@endsection
