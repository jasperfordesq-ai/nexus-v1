{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $swaps = $swaps ?? [];
        $myShifts = $myShifts ?? [];
        $statusTagClass = [
            'pending' => 'govuk-tag--yellow',
            'admin_pending' => 'govuk-tag--yellow',
            'accepted' => 'govuk-tag--green',
            'admin_approved' => 'govuk-tag--green',
            'rejected' => 'govuk-tag--red',
            'admin_rejected' => 'govuk-tag--red',
            'cancelled' => 'govuk-tag--grey',
            'expired' => 'govuk-tag--grey',
        ];
        $statusKeys = ['pending', 'admin_pending', 'accepted', 'admin_approved', 'rejected', 'admin_rejected', 'cancelled', 'expired'];
        $statusLabel = function (string $value) use ($statusKeys): string {
            $key = in_array($value, $statusKeys, true) ? $value : 'pending';
            return __('govuk_alpha.vol_depth.swap_status_' . $key);
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @php
        $successStatuses = [
            'swap-requested' => 'govuk_alpha.vol_depth.swap_requested_success',
            'swap-accepted' => 'govuk_alpha.vol_depth.swap_accepted_success',
            'swap-rejected' => 'govuk_alpha.vol_depth.swap_rejected_success',
            'swap-cancelled' => 'govuk_alpha.vol_depth.swap_cancelled_success',
        ];
        $errorStatuses = [
            'swap-invalid' => 'govuk_alpha.vol_depth.swap_invalid',
            'swap-request-failed' => 'govuk_alpha.vol_depth.swap_request_failed',
            'swap-respond-failed' => 'govuk_alpha.vol_depth.swap_respond_failed',
            'swap-cancel-failed' => 'govuk_alpha.vol_depth.swap_cancel_failed',
        ];
    @endphp
    @if (isset($successStatuses[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="swap-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="swap-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($successStatuses[$status]) }}</p>
            </div>
        </div>
    @elseif (isset($errorStatuses[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __($errorStatuses[$status]) }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.vol_depth.swaps_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.vol_depth.swaps_description') }}</p>

    {{-- Request a new swap --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.vol_depth.swap_request_title') }}</h2>
    @if (empty($myShifts))
        <div class="govuk-inset-text">{{ __('govuk_alpha.vol_depth.swap_no_shifts') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.volunteering.swaps.request', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-8">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="from_shift_id">{{ __('govuk_alpha.vol_depth.swap_from_shift_label') }}</label>
                <div id="from_shift_id-hint" class="govuk-hint">{{ __('govuk_alpha.vol_depth.swap_from_shift_hint') }}</div>
                <select class="govuk-select" id="from_shift_id" name="from_shift_id" aria-describedby="from_shift_id-hint" required>
                    <option value="">{{ __('govuk_alpha.vol_depth.swap_select_shift') }}</option>
                    @foreach ($myShifts as $shift)
                        @php
                            $sid = (int) ($shift['id'] ?? 0);
                            $sTitle = (string) ($shift['opportunity_title'] ?? __('govuk_alpha.volunteering.detail_title'));
                            $sWhen = $formatDateTime($shift['start_time'] ?? null);
                        @endphp
                        @if ($sid > 0)
                            <option value="{{ $sid }}">{{ $sTitle }}@if ($sWhen) &mdash; {{ $sWhen }}@endif</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="to_shift_id">{{ __('govuk_alpha.vol_depth.swap_to_shift_label') }}</label>
                <div id="to_shift_id-hint" class="govuk-hint">{{ __('govuk_alpha.vol_depth.swap_to_shift_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="to_shift_id" name="to_shift_id" type="text" inputmode="numeric" pattern="[0-9]*" aria-describedby="to_shift_id-hint" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="to_user_id">{{ __('govuk_alpha.vol_depth.swap_to_user_label') }}</label>
                <div id="to_user_id-hint" class="govuk-hint">{{ __('govuk_alpha.vol_depth.swap_to_user_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="to_user_id" name="to_user_id" type="text" inputmode="numeric" pattern="[0-9]*" aria-describedby="to_user_id-hint" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="message">{{ __('govuk_alpha.vol_depth.swap_message_label') }}</label>
                <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha.vol_depth.swap_message_hint') }}</div>
                <textarea class="govuk-textarea" id="message" name="message" rows="3" aria-describedby="message-hint" maxlength="500"></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.vol_depth.swap_request_submit') }}</button>
        </form>
    @endif

    {{-- Existing swap requests --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.vol_depth.swaps_list_title') }}</h2>
    @if ($error)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    @elseif (empty($swaps))
        <div class="govuk-inset-text">{{ __('govuk_alpha.vol_depth.swaps_empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($swaps as $swap)
                @php
                    $swapId = (int) ($swap['id'] ?? 0);
                    $direction = (string) ($swap['direction'] ?? 'sent');
                    $swapStatus = (string) ($swap['status'] ?? 'pending');
                    $original = $swap['original_shift'] ?? [];
                    $proposed = $swap['proposed_shift'] ?? [];
                    $requester = $swap['requester'] ?? [];
                    $recipient = $swap['recipient'] ?? [];
                    $tagClass = $statusTagClass[$swapStatus] ?? 'govuk-tag--grey';
                @endphp
                <article class="nexus-alpha-card">
                    <p class="govuk-!-margin-bottom-2">
                        <strong class="govuk-tag {{ $direction === 'sent' ? 'govuk-tag--blue' : 'govuk-tag--purple' }}">{{ $direction === 'sent' ? __('govuk_alpha.vol_depth.swap_sent') : __('govuk_alpha.vol_depth.swap_received') }}</strong>
                        <strong class="govuk-tag {{ $tagClass }}">{{ $statusLabel($swapStatus) }}</strong>
                    </p>
                    @if ($direction === 'sent' && !empty($recipient['name']))
                        <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha.vol_depth.swap_to', ['name' => $recipient['name']]) }}</p>
                    @elseif ($direction === 'received' && !empty($requester['name']))
                        <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha.vol_depth.swap_from', ['name' => $requester['name']]) }}</p>
                    @endif

                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.vol_depth.swap_original_shift') }}</h3>
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ $original['opportunity_title'] ?? __('govuk_alpha.volunteering.detail_title') }}</p>
                            @if (!empty($original['organization_name']))
                                <p class="govuk-body-s govuk-!-margin-bottom-1">{{ $original['organization_name'] }}</p>
                            @endif
                            @if (!empty($original['start_time']))
                                <p class="govuk-body-s govuk-!-margin-bottom-0">{{ $formatDateTime($original['start_time']) }}</p>
                            @endif
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.vol_depth.swap_proposed_shift') }}</h3>
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ $proposed['opportunity_title'] ?? __('govuk_alpha.volunteering.detail_title') }}</p>
                            @if (!empty($proposed['organization_name']))
                                <p class="govuk-body-s govuk-!-margin-bottom-1">{{ $proposed['organization_name'] }}</p>
                            @endif
                            @if (!empty($proposed['start_time']))
                                <p class="govuk-body-s govuk-!-margin-bottom-0">{{ $formatDateTime($proposed['start_time']) }}</p>
                            @endif
                        </div>
                    </div>

                    @if (!empty($swap['message']))
                        <p class="govuk-body govuk-!-margin-top-3 govuk-!-margin-bottom-3"><span class="govuk-!-font-weight-bold">{{ __('govuk_alpha.vol_depth.swap_message_from') }}</span> {{ $swap['message'] }}</p>
                    @endif

                    @if ($swapId > 0 && $direction === 'received' && $swapStatus === 'pending')
                        {{-- Single form: accept and reject post to the same route, differing only
                             by the button's name="action" value, so the button-group wraps buttons. --}}
                        <form method="post" action="{{ route('govuk-alpha.volunteering.swaps.respond', ['tenantSlug' => $tenantSlug, 'id' => $swapId]) }}">
                            @csrf
                            <div class="govuk-button-group">
                                <button class="govuk-button govuk-!-margin-bottom-0" name="action" value="accept" data-module="govuk-button">{{ __('govuk_alpha.vol_depth.swap_accept') }}</button>
                                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" name="action" value="reject" data-module="govuk-button">{{ __('govuk_alpha.vol_depth.swap_reject') }}</button>
                            </div>
                        </form>
                    @elseif ($swapId > 0 && $direction === 'sent' && in_array($swapStatus, ['pending', 'admin_pending'], true))
                        <form method="post" action="{{ route('govuk-alpha.volunteering.swaps.cancel', ['tenantSlug' => $tenantSlug, 'id' => $swapId]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.vol_depth.swap_cancel') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
