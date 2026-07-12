{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $eventId = (int) ($event['id'] ?? 0);
        $resolved = is_array($preferences['resolved'] ?? null) ? $preferences['resolved'] : [];
        $channels = is_array($resolved['channels'] ?? null) ? $resolved['channels'] : [];
        $overrides = is_array($preferences['overrides'] ?? null) ? $preferences['overrides'] : [];
        $isEnabled = $overrides['reminders_enabled'] ?? ($resolved['reminders_enabled'] ?? false);
        $channelEnabled = static fn (string $channel): bool => (bool) (
            $overrides[$channel . '_enabled'] ?? ($channels[$channel] ?? false)
        );
        $presetOffsets = [10080, 1440, 60];
        $oldOffsets = old('offsets', $selectedOffsets);
        $oldOffsets = is_array($oldOffsets) ? $oldOffsets : $selectedOffsets;
        $customOffsets = array_values(array_filter(
            $selectedOffsets,
            static fn (int $offset): bool => !in_array($offset, $presetOffsets, true),
        ));
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        {{ __('govuk_alpha_events.reminders.back') }}
    </a>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors->all() as $error)
                            <li><a href="#event-reminders-form">{{ $error }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if ($status === 'saved' || $status === 'reset')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">{{ __('govuk_alpha_events.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    {{ $status === 'saved' ? __('govuk_alpha_events.reminders.saved') : __('govuk_alpha_events.reminders.reset_success') }}
                </p>
            </div>
        </div>
    @elseif ($status === 'conflict')
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">{{ __('govuk_alpha_events.common.warning') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_events.reminders.conflict') }}</p>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $event['title'] ?? '' }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.reminders.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_events.reminders.intro') }}</p>
    <p class="govuk-body">{{ __('govuk_alpha_events.reminders.resolved', ['source' => $sourceLabel]) }}</p>

    <form id="event-reminders-form" method="post" action="{{ route('govuk-alpha.events.reminders.update', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}" novalidate>
        @csrf
        <input type="hidden" name="expected_revision" value="{{ (int) ($preferences['revision'] ?? 0) }}">

        <div class="govuk-form-group">
            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input name="reminders_enabled" type="hidden" value="0">
                    <input class="govuk-checkboxes__input" id="reminders-enabled" name="reminders_enabled" type="checkbox" value="1" @checked(old('reminders_enabled', $isEnabled))>
                    <label class="govuk-label govuk-checkboxes__label" for="reminders-enabled">{{ __('govuk_alpha_events.reminders.enabled') }}</label>
                    <div class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_events.reminders.enabled_hint') }}</div>
                </div>
            </div>
        </div>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_events.reminders.timing') }}</legend>
            <div class="govuk-hint">{{ __('govuk_alpha_events.reminders.timing_hint') }}</div>
            <div class="govuk-checkboxes">
                @foreach ($presetOffsets as $offset)
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="reminder-offset-{{ $offset }}" name="offsets[]" type="checkbox" value="{{ $offset }}" @checked(in_array($offset, $oldOffsets, false))>
                        <label class="govuk-label govuk-checkboxes__label" for="reminder-offset-{{ $offset }}">{{ __('govuk_alpha_events.reminders.offset_' . $offset) }}</label>
                    </div>
                @endforeach
                @foreach ($customOffsets as $offset)
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="reminder-offset-{{ $offset }}" name="offsets[]" type="checkbox" value="{{ $offset }}" checked>
                        <label class="govuk-label govuk-checkboxes__label" for="reminder-offset-{{ $offset }}">{{ __('govuk_alpha_events.reminders.custom_value', ['count' => $offset]) }}</label>
                    </div>
                @endforeach
            </div>
            <div class="govuk-form-group govuk-!-margin-top-4">
                <label class="govuk-label" for="custom-offset">{{ __('govuk_alpha_events.reminders.custom_label') }}</label>
                <div class="govuk-hint" id="custom-offset-hint">{{ __('govuk_alpha_events.reminders.custom_hint', ['min' => $limits['minimum_offset_minutes'], 'max' => $limits['maximum_offset_minutes']]) }}</div>
                <input class="govuk-input govuk-input--width-10" id="custom-offset" name="custom_offset" type="number" min="{{ $limits['minimum_offset_minutes'] }}" max="{{ $limits['maximum_offset_minutes'] }}" step="1" inputmode="numeric" aria-describedby="custom-offset-hint" value="{{ old('custom_offset') }}">
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_events.reminders.channels') }}</legend>
            <div class="govuk-hint">{{ __('govuk_alpha_events.reminders.channels_hint') }}</div>
            <div class="govuk-checkboxes">
                @foreach (['email', 'in_app', 'web_push', 'fcm', 'realtime'] as $channel)
                    <div class="govuk-checkboxes__item">
                        <input name="channel_{{ $channel }}" type="hidden" value="0">
                        <input class="govuk-checkboxes__input" id="reminder-channel-{{ $channel }}" name="channel_{{ $channel }}" type="checkbox" value="1" @checked(old('channel_' . $channel, $channelEnabled($channel)))>
                        <label class="govuk-label govuk-checkboxes__label" for="reminder-channel-{{ $channel }}">{{ __('govuk_alpha_events.reminders.channel_' . $channel) }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_events.reminders.save') }}</button>
    </form>

    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_events.reminders.reset_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_events.reminders.reset_hint') }}</p>
    <form method="post" action="{{ route('govuk-alpha.events.reminders.reset', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        @csrf
        <input type="hidden" name="expected_revision" value="{{ (int) ($preferences['revision'] ?? 0) }}">
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_events.reminders.reset') }}</button>
    </form>
@endsection
