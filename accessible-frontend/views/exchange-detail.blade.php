{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusKey = $exchange['status'] ?? 'pending_provider';
        $isRequester = (int) ($exchange['requester_id'] ?? 0) === $currentUserId;
        $isProvider = (int) ($exchange['provider_id'] ?? 0) === $currentUserId;
        $otherUserId = $isRequester ? (int) ($exchange['provider_id'] ?? 0) : (int) ($exchange['requester_id'] ?? 0);
        $roleText = $isRequester ? __('govuk_alpha.exchanges.role_requester') : __('govuk_alpha.exchanges.role_provider');
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $canAccept = $isProvider && $statusKey === 'pending_provider';
        $canDecline = $isProvider && $statusKey === 'pending_provider';
        $canStart = ($isRequester || $isProvider) && $statusKey === 'accepted';
        $canComplete = ($isRequester || $isProvider) && $statusKey === 'in_progress';
        $hasRequesterConfirmed = !empty($exchange['requester_confirmed_at']);
        $hasProviderConfirmed = !empty($exchange['provider_confirmed_at']);
        $canConfirm = ($isRequester || $isProvider) && in_array($statusKey, ['in_progress', 'pending_confirmation'], true)
            && !(($isRequester && $hasRequesterConfirmed) || ($isProvider && $hasProviderConfirmed));
        $canCancel = ($isRequester || $isProvider) && in_array($statusKey, ['pending_provider', 'pending_broker', 'accepted'], true);
        $hasActions = $canAccept || $canDecline || $canStart || $canComplete || $canConfirm || $canCancel;
        $riskKey = $exchange['risk_level'] ?? 'unknown';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_exchanges') }}</a>

    @if ($status === 'exchange-created' || $status === 'exchange-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="exchange-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="exchange-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'exchange-created' ? __('govuk_alpha.exchanges.created') : __('govuk_alpha.exchanges.updated') }}</p>
            </div>
        </div>
    @elseif ($status === 'exchange-action-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.exchanges.failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.exchanges.detail_title') }}</span>
    <h1 class="govuk-heading-xl">{{ $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title') }}</h1>
    <p class="govuk-body-l">{{ $roleText }}</p>
    <strong class="govuk-tag govuk-!-margin-bottom-6">{{ __('govuk_alpha.exchanges.statuses.' . $statusKey) }}</strong>

    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.exchanges.summary_title') }}</h2>
    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.listing_label') }}</dt>
            <dd class="govuk-summary-list__value">
                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $exchange['listing_id']]) }}">{{ $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title') }}</a>
            </dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.requester_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $exchange['requester_name'] ?? __('govuk_alpha.members.unknown_member') }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.provider_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $exchange['provider_name'] ?? __('govuk_alpha.members.unknown_member') }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.proposed_hours_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.exchanges.hours', ['count' => (float) ($exchange['proposed_hours'] ?? 0)]) }}</dd>
        </div>
        @if (!empty($exchange['prep_time']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.prep_time_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.exchanges.hours', ['count' => (float) $exchange['prep_time']]) }}</dd>
            </div>
        @endif
        @if (!empty($exchange['final_hours']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.final_hours_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.exchanges.hours', ['count' => (float) $exchange['final_hours']]) }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.risk_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.exchanges.risk_values.' . $riskKey) }}</dd>
        </div>
        @if (!empty($exchange['created_at']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.created_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $formatDate($exchange['created_at']) }}</dd>
            </div>
        @endif
    </dl>

    @if (!empty($exchange['requester_notes']))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.exchanges.message_label') }}</h2>
        <div class="govuk-inset-text">{!! nl2br(e((string) $exchange['requester_notes'])) !!}</div>
    @endif

    <div class="nexus-alpha-actions govuk-!-margin-bottom-7">
        @if ($otherUserId > 0)
            <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.messages.show', ['tenantSlug' => $tenantSlug, 'userId' => $otherUserId]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.exchanges.message_member') }}</a>
        @endif
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.exchanges.actions_title') }}</h2>
    @if (!$hasActions)
        <div class="govuk-inset-text">{{ __('govuk_alpha.exchanges.no_action') }}</div>
    @else
        @if ($canAccept)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="govuk-!-margin-bottom-3">
                @csrf
                <input type="hidden" name="action" value="accept">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.accept') }}</button>
            </form>
        @endif

        @if ($canStart)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="govuk-!-margin-bottom-3">
                @csrf
                <input type="hidden" name="action" value="start">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.start_exchange') }}</button>
            </form>
        @endif

        @if ($canComplete)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="govuk-!-margin-bottom-3">
                @csrf
                <input type="hidden" name="action" value="complete">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.mark_ready') }}</button>
            </form>
        @endif

        @if ($canConfirm)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="govuk-!-margin-bottom-5">
                @csrf
                <input type="hidden" name="action" value="confirm">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="hours">{{ __('govuk_alpha.exchanges.confirm_hours_label') }}</label>
                    <input class="govuk-input govuk-input--width-5" id="hours" name="hours" type="number" min="0.25" max="24" step="0.25" value="{{ (float) ($exchange['proposed_hours'] ?? 1) }}" required>
                </div>
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.confirm_hours') }}</button>
            </form>
        @endif

        @if ($canDecline)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="govuk-!-margin-bottom-5">
                @csrf
                <input type="hidden" name="action" value="decline">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="decline-reason">{{ __('govuk_alpha.exchanges.reason_label') }}</label>
                    <textarea class="govuk-textarea" id="decline-reason" name="reason" rows="3"></textarea>
                </div>
                <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.actions.decline') }}</button>
            </form>
        @endif

        @if ($canCancel)
            <form method="post" action="{{ route('govuk-alpha.exchanges.action.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">
                @csrf
                <input type="hidden" name="action" value="cancel">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="cancel-reason">{{ __('govuk_alpha.exchanges.reason_label') }}</label>
                    <textarea class="govuk-textarea" id="cancel-reason" name="reason" rows="3"></textarea>
                </div>
                <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.actions.cancel_exchange') }}</button>
            </form>
        @endif
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.exchanges.timeline_title') }}</h2>
    @if (empty($history))
        <div class="govuk-inset-text">{{ __('govuk_alpha.exchanges.empty_timeline') }}</div>
    @else
        <ol class="govuk-list govuk-list--spaced">
            @foreach ($history as $entry)
                <li>
                    <strong>{{ __('govuk_alpha.exchanges.statuses.' . ($entry['new_status'] ?? $entry['old_status'] ?? $statusKey)) }}</strong>
                    @if (!empty($entry['created_at']))
                        <span class="govuk-hint govuk-!-margin-bottom-0">{{ $formatDate($entry['created_at']) }}</span>
                    @endif
                    @if (!empty($entry['notes']))
                        <p class="govuk-body">{!! nl2br(e((string) $entry['notes'])) !!}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
@endsection
