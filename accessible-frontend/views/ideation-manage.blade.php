{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $cId = (int) ($challenge['id'] ?? 0);
        $cTitle = trim((string) ($challenge['title'] ?? '')) ?: __('govuk_alpha_ideation.nav.challenges');
        $cStatus = (string) ($challenge['status'] ?? 'draft');
        $trans = is_array($transitions ?? null) ? $transitions : [];
        $isFavorited = $isFavorited ?? false;
        $successStates = ['challenge-status-updated', 'campaign-linked', 'favorited', 'unfavorited'];
        $errorStates = ['challenge-status-failed', 'challenge-failed', 'campaign-link-failed'];
        [$statusTagClass, $statusTagLabel] = match ($cStatus) {
            'open' => ['govuk-tag--green', __('govuk_alpha_ideation.status.open')],
            'voting' => ['govuk-tag--blue', __('govuk_alpha_ideation.status.voting')],
            'evaluating' => ['govuk-tag--purple', __('govuk_alpha_ideation.status.evaluating')],
            'closed' => ['govuk-tag--grey', __('govuk_alpha_ideation.status.closed')],
            'archived' => ['govuk-tag--grey', __('govuk_alpha_ideation.status.archived')],
            default => ['govuk-tag--grey', __('govuk_alpha_ideation.status.draft')],
        };
    @endphp

    <a href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.idea.back_to_challenge') }}</a>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="manage-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="manage-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.' . $status) }}</li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $cTitle }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.manage.heading') }}</h1>
        <strong class="govuk-tag {{ $statusTagClass }}">{{ $statusTagLabel }}</strong>
    </div>

    {{-- Quick links --}}
    <ul class="govuk-list">
        <li><a class="govuk-link" href="{{ route('govuk-alpha.ideation.edit', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ __('govuk_alpha_ideation.manage.edit_link') }}</a></li>
        <li><a class="govuk-link" href="{{ route('govuk-alpha.ideation.outcome', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ __('govuk_alpha_ideation.manage.outcome_link') }}</a></li>
    </ul>

    {{-- Favourite toggle --}}
    <form method="post" action="{{ route('govuk-alpha.ideation.favorite', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-!-margin-bottom-4">
        @csrf
        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
            {{ $isFavorited ? __('govuk_alpha_ideation.states.unfavorited') : __('govuk_alpha_ideation.states.favorited') }}
        </button>
    </form>

    {{-- Status lifecycle --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_ideation.manage.lifecycle_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_ideation.manage.lifecycle_help') }}</p>
    @if (empty($trans))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.manage.no_transitions') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.ideation.challenge.status', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
            @csrf
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.form.status_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach ($trans as $i => $opt)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="transition-{{ $opt }}" name="challenge_status" type="radio" value="{{ $opt }}"{{ $i === 0 ? ' checked' : '' }}>
                                <label class="govuk-label govuk-radios__label" for="transition-{{ $opt }}">{{ __('govuk_alpha_ideation.status.' . $opt) }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_ideation.manage.update_status') }}</button>
        </form>
    @endif

    {{-- Link to campaign --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_ideation.manage.link_campaign_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_ideation.manage.link_campaign_help') }}</p>
    @if (empty($campaigns))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.manage.link_campaign_none') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.ideation.link-campaign', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="campaign_id">{{ __('govuk_alpha_ideation.manage.link_campaign_label') }}</label>
                <select class="govuk-select" id="campaign_id" name="campaign_id">
                    @foreach ($campaigns as $cam)
                        <option value="{{ (int) ($cam['id'] ?? 0) }}">{{ trim((string) ($cam['title'] ?? '')) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_ideation.manage.link_campaign_submit') }}</button>
        </form>
    @endif

    {{-- Duplicate --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_ideation.manage.duplicate') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_ideation.manage.duplicate_help') }}</p>
    <form method="post" action="{{ route('govuk-alpha.ideation.duplicate', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
        @csrf
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_ideation.manage.duplicate') }}</button>
    </form>

    {{-- Delete --}}
    <details class="govuk-details govuk-!-margin-top-4" data-module="govuk-details">
        <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_ideation.manage.delete') }}</span></summary>
        <div class="govuk-details__text">
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span>{{ __('govuk_alpha_ideation.manage.delete_warning') }}</strong>
            </div>
            <form method="post" action="{{ route('govuk-alpha.ideation.delete', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
                @csrf
                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_ideation.manage.delete_confirm') }}</button>
            </form>
        </div>
    </details>
@endsection
