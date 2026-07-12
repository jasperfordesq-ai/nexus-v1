{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $isApprove = $decision === 'approve';
        $actionRoute = $isApprove
            ? 'govuk-alpha.events.moderation.approve'
            : 'govuk-alpha.events.moderation.reject';
        $errorKey = $error ? 'govuk_alpha_events.moderation.' . $error : null;
        $timezone = (string) ($event['timezone'] ?? 'UTC');
        $start = $event['start_time']
            ? \Illuminate\Support\Carbon::parse($event['start_time'])->setTimezone($timezone)
            : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.moderation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.moderation.back_to_queue') }}</a>
    <span class="govuk-caption-l">{{ __('govuk_alpha_events.moderation.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __($isApprove ? 'govuk_alpha_events.moderation.approve_title' : 'govuk_alpha_events.moderation.reject_title') }}</h1>

    @if ($errorKey)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="{{ $error === 'reason_required' || $error === 'reason_too_long' ? '#reason' : '#confirmation' }}">{{ __($errorKey) }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.event') }}</dt>
            <dd class="govuk-summary-list__value">{{ $event['title'] }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.organizer') }}</dt>
            <dd class="govuk-summary-list__value">{{ $event['organizer_name'] }}</dd>
        </div>
        @if ($start)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.schedule') }}</dt>
                <dd class="govuk-summary-list__value">{{ $event['all_day'] ? $start->translatedFormat('j F Y') : $start->translatedFormat('j F Y, g:ia T') }}</dd>
            </div>
        @endif
    </dl>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}</span>
            {{ __($isApprove ? 'govuk_alpha_events.moderation.approve_warning' : 'govuk_alpha_events.moderation.reject_warning') }}
        </strong>
    </div>

    <form method="post" action="{{ route($actionRoute, ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" novalidate>
        @csrf

        @unless ($isApprove)
            <div class="govuk-form-group @if (in_array($error, ['reason_required', 'reason_too_long'], true)) govuk-form-group--error @endif">
                <label class="govuk-label govuk-label--m" for="reason">{{ __('govuk_alpha_events.moderation.reason_label') }}</label>
                <div id="reason-hint" class="govuk-hint">{{ __('govuk_alpha_events.moderation.reason_hint') }}</div>
                @if (in_array($error, ['reason_required', 'reason_too_long'], true))
                    <p id="reason-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ __($errorKey) }}</p>
                @endif
                <textarea class="govuk-textarea" id="reason" name="reason" rows="5" maxlength="2000" aria-describedby="reason-hint @if (in_array($error, ['reason_required', 'reason_too_long'], true)) reason-error @endif">{{ $reason }}</textarea>
            </div>
        @endunless

        <div class="govuk-form-group @if ($error === 'confirmation_required') govuk-form-group--error @endif">
            <fieldset class="govuk-fieldset" @if ($error === 'confirmation_required') aria-describedby="confirmation-error" @endif>
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_events.moderation.confirm_legend') }}</legend>
                @if ($error === 'confirmation_required')
                    <p id="confirmation-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ __($errorKey) }}</p>
                @endif
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="confirmation" name="confirmation" type="checkbox" value="{{ $decision }}">
                        <label class="govuk-label govuk-checkboxes__label" for="confirmation">{{ __($isApprove ? 'govuk_alpha_events.moderation.approve_confirmation' : 'govuk_alpha_events.moderation.reject_confirmation') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="govuk-button-group">
            <button class="govuk-button @unless ($isApprove) govuk-button--warning @endunless" data-module="govuk-button">{{ __($isApprove ? 'govuk_alpha_events.moderation.approve_button' : 'govuk_alpha_events.moderation.reject_button') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.events.moderation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.common.cancel') }}</a>
        </div>
    </form>
@endsection
