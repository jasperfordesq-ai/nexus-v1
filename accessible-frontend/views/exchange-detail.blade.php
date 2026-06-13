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
        $canStart = $isProvider && $statusKey === 'accepted';
        $canComplete = $isProvider && $statusKey === 'in_progress';
        $hasRequesterConfirmed = !empty($exchange['requester_confirmed_at']);
        $hasProviderConfirmed = !empty($exchange['provider_confirmed_at']);
        $canConfirm = ($isRequester || $isProvider) && in_array($statusKey, ['in_progress', 'pending_confirmation'], true)
            && !(($isRequester && $hasRequesterConfirmed) || ($isProvider && $hasProviderConfirmed));
        $canCancel = ($isRequester || $isProvider) && in_array($statusKey, ['pending_provider', 'pending_broker', 'accepted'], true);
        $hasActions = $canAccept || $canDecline || $canStart || $canComplete || $canConfirm || $canCancel;
        $riskKey = $exchange['risk_level'] ?? 'unknown';
        $label = fn (string $ns, ?string $key): string => ($key !== null && $key !== '' && \Illuminate\Support\Facades\Lang::has("govuk_alpha.$ns.$key"))
            ? __("govuk_alpha.$ns.$key")
            : \Illuminate\Support\Str::headline((string) $key);
        // Colour the status tag by state (matches the exchanges list page) so the
        // single-exchange page conveys status by colour + text, not text alone.
        $statusTagClass = fn (string $key): string => match ($key) {
            'completed' => 'govuk-tag--green',
            'in_progress' => 'govuk-tag--blue',
            'accepted' => 'govuk-tag--turquoise',
            'pending_provider', 'pending_broker', 'pending_confirmation' => 'govuk-tag--yellow',
            'disputed' => 'govuk-tag--red',
            'cancelled', 'expired', 'declined' => 'govuk-tag--grey',
            default => 'govuk-tag--blue',
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_exchanges') }}</a>

    @if (in_array($status, ['exchange-created', 'exchange-updated', 'rating-submitted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="exchange-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="exchange-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'exchange-created' ? __('govuk_alpha.exchanges.created') : ($status === 'rating-submitted' ? __('govuk_alpha.exchanges.rating_submitted') : __('govuk_alpha.exchanges.updated')) }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['exchange-action-failed', 'rating-failed', 'rating-invalid'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>
                            <a href="{{ in_array($status, ['rating-invalid', 'rating-failed'], true) ? '#rating' : '#main-content' }}">{{ $status === 'rating-invalid' ? __('govuk_alpha.exchanges.rating_invalid') : ($status === 'rating-failed' ? __('govuk_alpha.exchanges.rating_failed') : __('govuk_alpha.exchanges.failed')) }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.exchanges.detail_title') }}</span>
    <h1 class="govuk-heading-xl">{{ $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title') }}</h1>
    <p class="govuk-body-l">{{ $roleText }}</p>
    <strong class="govuk-tag {{ $statusTagClass($statusKey) }} govuk-!-margin-bottom-4">{{ $label('exchanges.statuses', $statusKey) }}</strong>

    @php
        $statusDescriptionKey = "govuk_alpha.exchanges.status_descriptions.$statusKey";
        $statusDescription = \Illuminate\Support\Facades\Lang::has($statusDescriptionKey) ? __($statusDescriptionKey) : null;
    @endphp
    @if ($statusDescription && $statusKey !== 'disputed')
        <p class="govuk-body govuk-!-margin-bottom-6">{{ $statusDescription }}</p>
    @endif

    @if ($statusKey === 'disputed')
        <div class="govuk-inset-text">{{ __('govuk_alpha.exchanges.disputed_detail') }}</div>
    @endif

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
        @if (in_array($statusKey, ['in_progress', 'pending_confirmation', 'completed'], true))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.requester_confirmation_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if ($hasRequesterConfirmed)
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.exchanges.confirmed') }}</strong>
                    @else
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.exchanges.awaiting_confirmation') }}</strong>
                    @endif
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.provider_confirmation_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if ($hasProviderConfirmed)
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.exchanges.confirmed') }}</strong>
                    @else
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.exchanges.awaiting_confirmation') }}</strong>
                    @endif
                </dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.exchanges.risk_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $label('exchanges.risk_values', $riskKey) }}</dd>
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
                    <div id="hours-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.confirm_hours_hint') }}</div>
                    <input class="govuk-input govuk-input--width-5" id="hours" name="hours" type="number" min="0.25" max="24" step="0.25" value="{{ (float) ($exchange['proposed_hours'] ?? 1) }}" aria-describedby="hours-hint" required>
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

    @if (($exchange['status'] ?? '') === 'completed')
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.exchanges.review_title') }}</h2>
        @if ($canReview ?? false)
            <p class="govuk-body">{{ __('govuk_alpha.exchanges.review_hint') }}</p>
            <form method="post" action="{{ route('govuk-alpha.exchanges.rate.store', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label" for="rating">{{ __('govuk_alpha.exchanges.review_rating_label') }}</label>
                    <div id="rating-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.review_rating_hint') }}</div>
                    <select class="govuk-select govuk-input--width-10" id="rating" name="rating" aria-describedby="rating-hint" required>
                        @foreach ([5, 4, 3, 2, 1] as $ratingOption)
                            <option value="{{ $ratingOption }}">{{ __('govuk_alpha.exchanges.review_rating_value', ['rating' => $ratingOption]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="comment">{{ __('govuk_alpha.exchanges.review_comment_label') }}</label>
                    <div id="comment-hint" class="govuk-hint">{{ __('govuk_alpha.exchanges.review_comment_hint') }}</div>
                    <textarea class="govuk-textarea" id="comment" name="comment" rows="4" aria-describedby="comment-hint"></textarea>
                </div>
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.exchanges.review_submit') }}</button>
            </form>
        @else
            <div class="govuk-inset-text">{{ __('govuk_alpha.exchanges.review_thanks') }}</div>
        @endif
    @endif

    @if (($exchange['status'] ?? '') === 'completed' && !empty($ratings))
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.exchanges.ratings_title') }}</h2>
        <ul class="govuk-list">
            @foreach ($ratings as $rating)
                @php
                    $raterName = trim((string) (($rating['rater_first_name'] ?? '') . ' ' . ($rating['rater_last_name'] ?? '')));
                    if ($raterName === '') {
                        $raterName = (string) ($rating['rater_username'] ?? '') ?: __('govuk_alpha.members.unknown_member');
                    }
                @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-3">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        <strong>{{ $raterName }}</strong>
                        — {{ __('govuk_alpha.exchanges.review_rating_value', ['rating' => (int) ($rating['rating'] ?? 0)]) }}
                    </p>
                    @if (!empty($rating['comment']))
                        <p class="govuk-body govuk-!-margin-bottom-0">{!! nl2br(e((string) $rating['comment'])) !!}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.exchanges.timeline_title') }}</h2>
    @if (empty($history))
        <div class="govuk-inset-text">{{ __('govuk_alpha.exchanges.empty_timeline') }}</div>
    @else
        <ol class="govuk-list govuk-list--spaced">
            @foreach ($history as $entry)
                <li>
                    <strong>{{ $label('exchanges.statuses', $entry['new_status'] ?? $entry['old_status'] ?? $statusKey) }}</strong>
                    @if (!empty($entry['actor']['name']))
                        <span class="govuk-hint govuk-!-margin-bottom-0">{{ __('govuk_alpha.exchanges.timeline_by', ['name' => $entry['actor']['name']]) }}</span>
                    @endif
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
