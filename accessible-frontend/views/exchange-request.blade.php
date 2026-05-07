{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $suggestedHours = (float) ($listing['hours_estimate'] ?? $listing['estimated_hours'] ?? 1);
        $suggestedHours = max(0.25, min(24, $suggestedHours > 0 ? $suggestedHours : 1));
        $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    @if ($status === 'exchange-failed' || $status === 'compliance-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $status === 'compliance-failed' ? __('govuk_alpha.exchanges.compliance_failed') : __('govuk_alpha.exchanges.request_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.exchanges.request_title') }}</span>
    <h1 class="govuk-heading-xl">{{ $listing['title'] }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.exchanges.request_description') }}</p>

    <dl class="govuk-summary-list govuk-!-margin-bottom-7">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.type') }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.' . $type) }}</dd>
        </div>
        @if (!empty($listing['category_name']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.category') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['category_name'] }}</dd>
            </div>
        @endif
        @if (!empty($listing['location']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.location') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['location'] }}</dd>
            </div>
        @endif
    </dl>

    <form method="post" action="{{ route('govuk-alpha.exchanges.request.store', ['tenantSlug' => $tenantSlug, 'listingId' => $listing['id']]) }}">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label" for="proposed_hours">{{ __('govuk_alpha.exchanges.hours_label') }}</label>
            <div id="proposed-hours-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.hours_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="proposed_hours" name="proposed_hours" type="number" min="0.25" max="24" step="0.25" value="{{ $suggestedHours }}" aria-describedby="proposed-hours-hint" required>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="prep_time">{{ __('govuk_alpha.exchanges.prep_time_label') }}</label>
            <div id="prep-time-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.prep_time_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="prep_time" name="prep_time" type="number" min="0" max="24" step="0.25" aria-describedby="prep-time-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="message">{{ __('govuk_alpha.exchanges.request_message_label') }}</label>
            <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.request_message_hint') }}</div>
            <textarea class="govuk-textarea" id="message" name="message" rows="6" aria-describedby="message-hint"></textarea>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.send_request') }}</button>
    </form>
@endsection
