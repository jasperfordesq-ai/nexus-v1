{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $score = (int) ($score ?? 100);
        $burnoutRisk = (string) ($burnoutRisk ?? 'low');
        $warnings = $warnings ?? [];
        $hoursThisWeek = (float) ($hoursThisWeek ?? 0);
        $hoursThisMonth = (float) ($hoursThisMonth ?? 0);
        $streakDays = (int) ($streakDays ?? 0);
        $recentCheckins = $recentCheckins ?? [];
        $status = $status ?? null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $riskTag = ['low' => 'govuk-tag--green', 'moderate' => 'govuk-tag--yellow', 'high' => 'govuk-tag--red'][$burnoutRisk] ?? 'govuk-tag--grey';
        $riskLabelKey = ['low' => 'govuk_alpha_volunteering.wellbeing.risk_low', 'moderate' => 'govuk_alpha_volunteering.wellbeing.risk_moderate', 'high' => 'govuk_alpha_volunteering.wellbeing.risk_high'][$burnoutRisk] ?? 'govuk_alpha_volunteering.wellbeing.risk_low';
        $warningLabelKey = [
            'frequency' => 'govuk_alpha_volunteering.wellbeing.warning_frequency',
            'cancellation' => 'govuk_alpha_volunteering.wellbeing.warning_cancellation',
            'hours' => 'govuk_alpha_volunteering.wellbeing.warning_hours',
            'engagement' => 'govuk_alpha_volunteering.wellbeing.warning_engagement',
        ];
        $moodLabelKey = [
            1 => 'govuk_alpha_volunteering.wellbeing.mood_1',
            2 => 'govuk_alpha_volunteering.wellbeing.mood_2',
            3 => 'govuk_alpha_volunteering.wellbeing.mood_3',
            4 => 'govuk_alpha_volunteering.wellbeing.mood_4',
            5 => 'govuk_alpha_volunteering.wellbeing.mood_5',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'checkin-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="wellbeing-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="wellbeing-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_volunteering.wellbeing.checkin_saved') }}</p></div>
        </div>
    @elseif (in_array($status, ['checkin-failed', 'mood-invalid'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $status === 'mood-invalid' ? __('govuk_alpha_volunteering.wellbeing.mood_invalid') : __('govuk_alpha_volunteering.wellbeing.checkin_failed') }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.wellbeing.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.wellbeing.description') }}</p>

    {{-- Score + risk --}}
    <div class="govuk-form-group">
        <label class="govuk-label govuk-label--m" for="wellbeing-score">{{ __('govuk_alpha_volunteering.wellbeing.score_label') }}</label>
        <p class="govuk-body govuk-!-margin-bottom-1">{{ $score }} {{ __('govuk_alpha_volunteering.wellbeing.score_out_of') }}</p>
        <progress id="wellbeing-score" max="100" value="{{ $score }}" aria-label="{{ __('govuk_alpha_volunteering.wellbeing.score_label') }}">{{ $score }}%</progress>
    </div>
    <p class="govuk-body">
        {{ __('govuk_alpha_volunteering.wellbeing.risk_label') }}:
        <strong class="govuk-tag {{ $riskTag }}">{{ __($riskLabelKey) }}</strong>
    </p>

    {{-- Stats --}}
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.wellbeing.hours_week') }}</dt>
            <dd>{{ number_format($hoursThisWeek, 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.wellbeing.hours_month') }}</dt>
            <dd>{{ number_format($hoursThisMonth, 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.wellbeing.streak_days') }}</dt>
            <dd>{{ $streakDays }}</dd>
        </div>
    </dl>

    {{-- Warnings --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.wellbeing.warnings_title') }}</h2>
    @if (empty($warnings))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.wellbeing.no_warnings') }}</div>
    @else
        <ul class="govuk-list govuk-list--bullet">
            @foreach ($warnings as $warning)
                @if (isset($warningLabelKey[$warning]))
                    <li>{{ __($warningLabelKey[$warning]) }}</li>
                @endif
            @endforeach
        </ul>
    @endif

    {{-- Mood check-in --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.wellbeing.checkin_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_volunteering.wellbeing.checkin_hint') }}</p>
    <form method="post" action="{{ route('govuk-alpha.volunteering.wellbeing.checkin', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-8">
        @csrf
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_volunteering.wellbeing.mood_label') }}</legend>
            <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                @foreach ([1, 2, 3, 4, 5] as $moodValue)
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="mood-{{ $moodValue }}" name="mood" type="radio" value="{{ $moodValue }}" @checked($moodValue === 3)>
                        <label class="govuk-label govuk-radios__label" for="mood-{{ $moodValue }}">{{ __($moodLabelKey[$moodValue]) }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>
        <div class="govuk-form-group govuk-!-margin-top-4">
            <label class="govuk-label" for="note">{{ __('govuk_alpha_volunteering.wellbeing.note_label') }} {{ __('govuk_alpha_volunteering.shared.optional') }}</label>
            <div id="note-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.wellbeing.note_hint') }}</div>
            <textarea class="govuk-textarea" id="note" name="note" rows="3" maxlength="500" aria-describedby="note-hint"></textarea>
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.wellbeing.checkin_button') }}</button>
    </form>

    {{-- Recent check-ins --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.wellbeing.recent_title') }}</h2>
    @if (empty($recentCheckins))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.wellbeing.recent_empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.wellbeing.recent_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.wellbeing.recent_when') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.wellbeing.recent_mood') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.wellbeing.note_label') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($recentCheckins as $checkin)
                    @php
                        $moodValue = (int) ($checkin['mood'] ?? 0);
                        $moodKey = $moodLabelKey[$moodValue] ?? null;
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $formatDateTime($checkin['created_at'] ?? null) ?? '—' }}</td>
                        <td class="govuk-table__cell">{{ $moodKey !== null ? __($moodKey) : $moodValue }}</td>
                        <td class="govuk-table__cell">{{ $checkin['note'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
