{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha.goals.back_to_goal') }}</a>

    @php
        $deadline = trim((string) ($goal['deadline'] ?? ''));
        $deadlineValue = $deadline !== '' ? \Illuminate\Support\Carbon::parse($deadline)->format('Y-m-d') : '';
        $freq = (string) ($goal['checkin_frequency'] ?? 'none');
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    @if (in_array($status, ['goal-failed', 'goal-invalid'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#title">{{ __('govuk_alpha.goals.states.' . $status) }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.edit_caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.goals.edit_title') }}</h1>

            <form method="post" action="{{ route('govuk-alpha.goals.update', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}" novalidate>
                @csrf

                <div class="govuk-form-group">
                    <label class="govuk-label" for="title">{{ __('govuk_alpha.goals.title_label') }}</label>
                    <input class="govuk-input" id="title" name="title" type="text" maxlength="255" value="{{ old('title', $goal['title'] ?? '') }}" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="target_value">{{ __('govuk_alpha.goals.target_label') }}</label>
                    <div id="tv-hint" class="govuk-hint">{{ __('govuk_alpha.goals.target_hint') }}</div>
                    <input class="govuk-input govuk-input--width-5" id="target_value" name="target_value" type="number" min="0.25" step="0.25" inputmode="decimal" value="{{ old('target_value', $num($goal['target_value'] ?? 0)) }}" required aria-describedby="tv-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="description">{{ __('govuk_alpha.goals.description_label') }}</label>
                    <textarea class="govuk-textarea" id="description" name="description" rows="3" maxlength="1000">{{ old('description', $goal['description'] ?? '') }}</textarea>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="deadline">{{ __('govuk_alpha.goals.deadline_label') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="deadline" name="deadline" type="date" value="{{ old('deadline', $deadlineValue) }}">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="checkin_frequency">{{ __('govuk_alpha.goals.frequency_label') }}</label>
                    <div id="freq-hint" class="govuk-hint">{{ __('govuk_alpha.goals.frequency_hint') }}</div>
                    <select class="govuk-select" id="checkin_frequency" name="checkin_frequency" aria-describedby="freq-hint">
                        @foreach (['none', 'daily', 'weekly', 'biweekly', 'monthly'] as $opt)
                            <option value="{{ $opt }}" @selected((string) old('checkin_frequency', $freq) === $opt)>{{ __('govuk_alpha.goals.frequency_' . $opt) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="govuk-checkboxes govuk-checkboxes--small govuk-form-group" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="is_public" name="is_public" type="checkbox" value="1" @checked(old('is_public', (bool) ($goal['is_public'] ?? false)))>
                        <label class="govuk-label govuk-checkboxes__label" for="is_public">{{ __('govuk_alpha.goals.public_label') }}</label>
                    </div>
                </div>

                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.save_button') }}</button>
            </form>

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.delete_title') }}</h2>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                    {{ __('govuk_alpha.goals.delete_warning') }}
                </strong>
            </div>
            <form method="post" action="{{ route('govuk-alpha.goals.delete', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
                @csrf
                <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.delete_button') }}</button>
            </form>
        </div>
    </div>
@endsection
