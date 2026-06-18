{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $cId = (int) ($challenge['id'] ?? 0);
        $cTitle = trim((string) ($challenge['title'] ?? '')) ?: __('govuk_alpha_ideation.nav.challenges');
        $o = $outcome ?? [];
        $currentStatus = (string) ($o['status'] ?? 'not_started');
        $currentWinningId = (int) ($o['winning_idea_id'] ?? 0);
        $currentImpact = trim((string) ($o['impact_description'] ?? ''));
        $statusOptions = ['not_started', 'in_progress', 'implemented', 'abandoned'];
    @endphp

    <a href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.idea.back_to_challenge') }}</a>

    @if ($status === 'outcome-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="outcome-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="outcome-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.outcome-saved') }}</p></div>
        </div>
    @elseif ($status === 'outcome-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.outcome-failed') }}</li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $cTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_ideation.outcomes.edit_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_ideation.outcomes.edit_intro') }}</p>

    <form method="post" action="{{ route('govuk-alpha.ideation.outcome.store', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label" for="winning_idea_id">{{ __('govuk_alpha_ideation.outcomes.winning_idea_label') }}</label>
            <select class="govuk-select" id="winning_idea_id" name="winning_idea_id">
                <option value="">{{ __('govuk_alpha_ideation.outcomes.winning_idea_none') }}</option>
                @foreach ($ideas as $idea)
                    @php $iid = (int) ($idea['id'] ?? 0); @endphp
                    <option value="{{ $iid }}"{{ $currentWinningId === $iid ? ' selected' : '' }}>{{ trim((string) ($idea['title'] ?? '')) }}</option>
                @endforeach
            </select>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.outcomes.status_label') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach ($statusOptions as $opt)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="outcome-status-{{ $opt }}" name="outcome_status" type="radio" value="{{ $opt }}"{{ $currentStatus === $opt ? ' checked' : '' }}>
                            <label class="govuk-label govuk-radios__label" for="outcome-status-{{ $opt }}">{{ __('govuk_alpha_ideation.outcomes.status_' . $opt) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="impact_description">{{ __('govuk_alpha_ideation.outcomes.impact_label') }}</label>
            <div id="impact_description-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.outcomes.impact_hint') }}</div>
            <textarea class="govuk-textarea" id="impact_description" name="impact_description" rows="4" maxlength="5000" aria-describedby="impact_description-hint">{{ $currentImpact }}</textarea>
        </div>

        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_ideation.outcomes.submit') }}</button>
    </form>
@endsection
