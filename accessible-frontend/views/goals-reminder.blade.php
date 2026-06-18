{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.reminder.title');
        $hasReminder = is_array($reminder) && !empty($reminder);
        $enabled = $hasReminder && (int) ($reminder['enabled'] ?? 0) === 1;
        $currentFrequency = $hasReminder ? (string) ($reminder['frequency'] ?? 'weekly') : 'weekly';
        $nextDate = null;
        if ($hasReminder && !empty($reminder['next_reminder_at'])) {
            try {
                $nextDate = \Illuminate\Support\Carbon::parse($reminder['next_reminder_at'])->isoFormat('D MMM YYYY, HH:mm');
            } catch (\Throwable $e) {
                $nextDate = null;
            }
        }
    @endphp

    @if ($status === 'reminder-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="reminder-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="reminder-status">{{ __('govuk_alpha_goals.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_goals.states.reminder-saved') }}</p></div>
        </div>
    @elseif ($status === 'reminder-removed')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="reminder-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="reminder-status">{{ __('govuk_alpha_goals.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_goals.states.reminder-removed') }}</p></div>
        </div>
    @elseif ($status === 'reminder-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_goals.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#frequency-{{ $frequencies[0] ?? 'weekly' }}">{{ __('govuk_alpha_goals.states.reminder-failed') }}</a></li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.reminder.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.reminder.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.reminder.intro') }}</p>

    <div class="govuk-inset-text">
        @if ($enabled)
            <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha_goals.reminder.status_active') }}</strong></p>
            <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha_goals.reminder.status_active_detail', ['frequency' => __('govuk_alpha_goals.frequency.' . (in_array($currentFrequency, ['daily', 'weekly', 'biweekly', 'monthly'], true) ? $currentFrequency : 'weekly'))]) }}</p>
            @if ($nextDate)
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha_goals.reminder.next_reminder', ['date' => $nextDate]) }}</p>
            @endif
        @else
            <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha_goals.reminder.status_none') }}</strong></p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_goals.reminder.status_none_detail') }}</p>
        @endif
    </div>

    <form method="post" action="{{ route('govuk-alpha.goals.reminder.save', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}" class="govuk-grid-row">
        @csrf
        <div class="govuk-grid-column-two-thirds">
            <fieldset class="govuk-fieldset govuk-form-group">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_goals.reminder.frequency_legend') }}</legend>
                <div class="govuk-radios" data-module="govuk-radios">
                    @foreach ($frequencies as $freq)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="frequency-{{ $freq }}" name="frequency" type="radio" value="{{ $freq }}" @if ($currentFrequency === $freq) checked @endif>
                            <label class="govuk-label govuk-radios__label" for="frequency-{{ $freq }}">{{ __('govuk_alpha_goals.frequency.' . $freq) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="govuk-checkboxes govuk-checkboxes--small govuk-form-group" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="enabled" name="enabled" type="checkbox" value="1" @if (!$hasReminder || $enabled) checked @endif>
                    <label class="govuk-label govuk-checkboxes__label" for="enabled">{{ __('govuk_alpha_goals.reminder.enabled_label') }}</label>
                </div>
            </div>

            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_goals.reminder.save') }}</button>
        </div>
    </form>

    @if ($hasReminder)
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_goals.common.error_title') }}</span>
                        {{ __('govuk_alpha_goals.reminder.remove_warning') }}
                    </strong>
                </div>
                <form method="post" action="{{ route('govuk-alpha.goals.reminder.delete', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_goals.reminder.remove') }}</button>
                </form>
            </div>
        </div>
    @endif
@endsection
