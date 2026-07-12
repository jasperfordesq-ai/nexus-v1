{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.tickets.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('event_tickets.back_to_tickets') }}</a>

    @if ($errors->has('ticket'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('ticket') }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $ticket['name'] }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_tickets.cancel_title') }}</h1>
    <p class="govuk-body-l">{{ __('event_tickets.cancel_intro') }}</p>

    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.units') }}</dt><dd class="govuk-summary-list__value">{{ $entitlement['units'] }}</dd></div>
        <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.status_label') }}</dt><dd class="govuk-summary-list__value">{{ __('event_tickets.status.' . $entitlement['status']) }}</dd></div>
    </dl>

    <div class="govuk-inset-text">{{ __('event_tickets.cancel_free_only') }}</div>

    <form method="post" action="{{ route('govuk-alpha.events.tickets.cancel', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'entitlementId' => $entitlement['id']]) }}" novalidate>
        @csrf
        <input type="hidden" name="expected_version" value="{{ $entitlement['version'] }}">
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="govuk-form-group{{ $errors->has('ticket') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="ticket-cancellation-reason">{{ __('event_tickets.reason_label') }}</label>
            <div class="govuk-hint">{{ __('event_tickets.reason_hint') }}</div>
            <textarea class="govuk-textarea" id="ticket-cancellation-reason" name="reason" rows="4" maxlength="500" required>{{ old('reason') }}</textarea>
        </div>
        <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('event_tickets.confirm_cancel') }}</button>
    </form>
@endsection
