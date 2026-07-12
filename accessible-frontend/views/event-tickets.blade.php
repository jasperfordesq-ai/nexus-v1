{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $successMessages = [
            'allocated' => __('event_tickets.allocated'),
            'cancelled' => __('event_tickets.cancelled'),
        ];
        $ticketNames = collect($catalogue['ticket_types'])->keyBy('id');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('event_tickets.back_to_event') }}</a>

    @if ($errors->has('ticket'))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $errors->first('ticket') }}</p></div>
            </div>
        </div>
    @elseif (isset($successMessages[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ $successMessages[$status] }}</p></div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $eventTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_tickets.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_tickets.intro') }}</p>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}:</span>
            {{ __('event_tickets.gateway_disabled') }}
        </strong>
    </div>

    <h2 class="govuk-heading-l">{{ __('event_tickets.my_tickets') }}</h2>
    @if (empty($catalogue['own_entitlements']))
        <p class="govuk-body">{{ __('event_tickets.no_tickets') }}</p>
    @else
        @foreach ($catalogue['own_entitlements'] as $entitlement)
            @php $ticket = $ticketNames->get($entitlement['ticket_type_id']); @endphp
            <section class="govuk-!-margin-bottom-7">
                <h3 class="govuk-heading-m">{{ $ticket['name'] ?? __('event_tickets.ticket_fallback') }}</h3>
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.units') }}</dt><dd class="govuk-summary-list__value">{{ $entitlement['units'] }}</dd></div>
                    <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.status_label') }}</dt><dd class="govuk-summary-list__value">{{ __('event_tickets.status.' . $entitlement['status']) }}</dd></div>
                </dl>
                @if ($entitlement['status'] === 'confirmed' && $entitlement['kind'] === 'free')
                    <a class="govuk-button govuk-button--warning" data-module="govuk-button" href="{{ route('govuk-alpha.events.tickets.cancel.form', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'entitlementId' => $entitlement['id']]) }}">{{ __('event_tickets.cancel_ticket') }}</a>
                @elseif ($entitlement['status'] === 'confirmed')
                    <p class="govuk-body">{{ __('event_tickets.time_credit_cancel_disabled') }}</p>
                @endif
            </section>
        @endforeach
    @endif

    <h2 class="govuk-heading-l">{{ __('event_tickets.catalogue') }}</h2>
    @if (empty($catalogue['ticket_types']))
        <p class="govuk-body">{{ __('event_tickets.catalogue_empty') }}</p>
    @else
        @foreach ($catalogue['ticket_types'] as $ticket)
            @php
                $availability = $ticket['availability'];
                $maximum = min($availability['allocation_remaining'], $availability['member_remaining']);
                $canAllocate = $catalogue['permissions']['allocate_self']
                    && $ticket['kind'] === 'free'
                    && $ticket['status'] === 'active'
                    && $availability['eligibility']['eligible']
                    && $availability['sales_window_open']
                    && $availability['materialization_supported']
                    && $maximum > 0;
            @endphp
            <section class="govuk-!-margin-bottom-8">
                <h3 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $ticket['name'] }}</h3>
                @if ($ticket['description'])<p class="govuk-body">{{ $ticket['description'] }}</p>@endif
                <p class="govuk-body-s">
                    <strong class="govuk-tag{{ $ticket['kind'] === 'time_credit' ? ' govuk-tag--grey' : ' govuk-tag--green' }}">{{ __('event_tickets.kind.' . $ticket['kind']) }}</strong>
                </p>
                <dl class="govuk-summary-list govuk-summary-list--no-border">
                    <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.remaining') }}</dt><dd class="govuk-summary-list__value">{{ $availability['allocation_remaining'] }}</dd></div>
                    <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_tickets.member_limit') }}</dt><dd class="govuk-summary-list__value">{{ $ticket['per_member_limit'] }}</dd></div>
                </dl>

                @if ($ticket['kind'] === 'time_credit')
                    <div class="govuk-inset-text">{{ __('event_tickets.time_credit_disabled', ['credits' => $ticket['unit_price_credits']]) }}</div>
                @elseif ($canAllocate)
                    <form method="post" action="{{ route('govuk-alpha.events.tickets.allocate', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'ticketTypeId' => $ticket['id']]) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="ticket-units-{{ $ticket['id'] }}">{{ __('event_tickets.units_to_claim') }}</label>
                            <div class="govuk-hint">{{ __('event_tickets.units_hint', ['count' => $maximum]) }}</div>
                            <input class="govuk-input govuk-input--width-3" id="ticket-units-{{ $ticket['id'] }}" name="units" type="number" min="1" max="{{ $maximum }}" value="1" required>
                        </div>
                        <button class="govuk-button" data-module="govuk-button">{{ __('event_tickets.claim_free') }}</button>
                    </form>
                @elseif (!$catalogue['permissions']['allocate_self'])
                    <p class="govuk-body">{{ __('event_tickets.registration_required') }}</p>
                @elseif (!$availability['eligibility']['eligible'])
                    <p class="govuk-body">{{ __('event_tickets.not_eligible') }}</p>
                @elseif (!$availability['sales_window_open'])
                    <p class="govuk-body">{{ __('event_tickets.sales_closed') }}</p>
                @else
                    <p class="govuk-body">{{ __('event_tickets.sold_out') }}</p>
                @endif
            </section>
        @endforeach
    @endif
@endsection
