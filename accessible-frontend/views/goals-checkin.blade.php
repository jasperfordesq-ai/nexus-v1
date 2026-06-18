{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.checkin.title');
        $fmtDate = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->isoFormat('D MMM YYYY, HH:mm');
            } catch (\Throwable $e) {
                return null;
            }
        };
    @endphp

    @if ($status === 'checkin-recorded')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="checkin-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="checkin-status">{{ __('govuk_alpha_goals.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_goals.states.checkin-recorded') }}</p></div>
        </div>
    @elseif ($status === 'checkin-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_goals.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#progress_percent">{{ __('govuk_alpha_goals.states.checkin-failed') }}</a></li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.checkin.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.checkin.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.checkin.intro') }}</p>

    <form method="post" action="{{ route('govuk-alpha.goals.checkin.store', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}" class="govuk-grid-row">
        @csrf
        <div class="govuk-grid-column-two-thirds">
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="progress_percent">{{ __('govuk_alpha_goals.checkin.progress_label') }}</label>
                <div id="progress-hint" class="govuk-hint">{{ __('govuk_alpha_goals.checkin.progress_help') }}</div>
                <input class="govuk-input govuk-input--width-5" id="progress_percent" name="progress_percent" type="number" min="0" max="100" step="1" inputmode="numeric" value="{{ $currentPercent }}" aria-describedby="progress-hint">
            </div>

            <fieldset class="govuk-fieldset govuk-form-group">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_goals.checkin.mood_legend') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="mood-none" name="mood" type="radio" value="" checked>
                        <label class="govuk-label govuk-radios__label" for="mood-none">{{ __('govuk_alpha_goals.checkin.mood_none') }}</label>
                    </div>
                    @foreach ($moods as $mood)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="mood-{{ $mood }}" name="mood" type="radio" value="{{ $mood }}">
                            <label class="govuk-label govuk-radios__label" for="mood-{{ $mood }}">{{ __('govuk_alpha_goals.mood.' . $mood) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="note">{{ __('govuk_alpha_goals.checkin.note_label') }}</label>
                <div id="note-hint" class="govuk-hint">{{ __('govuk_alpha_goals.checkin.note_help') }}</div>
                <textarea class="govuk-textarea" id="note" name="note" rows="3" maxlength="2000" aria-describedby="note-hint"></textarea>
            </div>

            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_goals.checkin.submit') }}</button>
        </div>
    </form>

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_goals.checkin.history_title') }}</h2>
    @if (empty($checkins))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_goals.checkin.history_empty') }}</p></div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($checkins as $checkin)
                @php
                    $cp = $checkin['progress_value'] ?? $checkin['progress_percent'] ?? null;
                    $cMood = (string) ($checkin['mood'] ?? '');
                    $moodLabels = __('govuk_alpha_goals.mood');
                    $cMoodLabel = ($cMood !== '' && is_array($moodLabels) && array_key_exists($cMood, $moodLabels)) ? __('govuk_alpha_goals.mood.' . $cMood) : null;
                    $cWhen = $fmtDate($checkin['created_at'] ?? null);
                @endphp
                <li class="nexus-alpha-card">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        <strong>{{ $cp === null ? __('govuk_alpha_goals.checkin.history_progress_unknown') : __('govuk_alpha_goals.checkin.history_progress', ['percent' => (int) round((float) $cp)]) }}</strong>
                    </p>
                    @if ($cMoodLabel)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_goals.checkin.history_mood', ['mood' => $cMoodLabel]) }}</p>
                    @endif
                    @if (trim((string) ($checkin['note'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ $checkin['note'] }}</p>
                    @endif
                    @if ($cWhen)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $cWhen }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
