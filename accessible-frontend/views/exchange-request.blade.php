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
        $requestAuthorName = $listing['author_name'] ?? $listing['user']['name'] ?? null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1" autofocus>
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.polish_listings.exchange_request_error_summary_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors->all() as $error)
                            <li><a href="#proposed_hours">{{ $error }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'exchange-failed' || $status === 'compliance-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
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
        @if (!empty($listing['hours_estimate']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.hours_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.hours', ['count' => $listing['hours_estimate']]) }}</dd>
            </div>
        @endif
        @if ($requestAuthorName)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.posted_by_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $requestAuthorName }}</dd>
            </div>
        @endif
    </dl>

    @if (($walletBalance ?? null) !== null)
        <div class="govuk-inset-text">
            {{ __('govuk_alpha.exchanges.balance_context', ['balance' => number_format((float) $walletBalance, 1)]) }}
        </div>
        @if ((float) $walletBalance < $suggestedHours)
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.important') }}</span>
                    {{ __('govuk_alpha.exchanges.balance_low_warning', ['hours' => number_format($suggestedHours, 1)]) }}
                </strong>
            </div>
        @endif
    @endif

    <form method="post" action="{{ route('govuk-alpha.exchanges.request.store', ['tenantSlug' => $tenantSlug, 'listingId' => $listing['id']]) }}">
        @csrf
        <div class="govuk-form-group{{ $errors->has('proposed_hours') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="proposed_hours">{{ __('govuk_alpha.exchanges.hours_label') }}</label>
            <div id="proposed-hours-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.hours_hint') }}</div>
            @error('proposed_hours')
                <p id="proposed-hours-error" class="govuk-error-message"><span class="govuk-visually-hidden">Error:</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input govuk-input--width-5{{ $errors->has('proposed_hours') ? ' govuk-input--error' : '' }}" id="proposed_hours" name="proposed_hours" type="number" min="0.25" max="24" step="0.25" value="{{ old('proposed_hours', $suggestedHours) }}" aria-describedby="proposed-hours-hint{{ $errors->has('proposed_hours') ? ' proposed-hours-error' : '' }}" required>
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
