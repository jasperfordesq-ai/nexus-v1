{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}

@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = static fn ($value): ?string => $value
            ? \Illuminate\Support\Carbon::parse($value)->setTimezone($calendarTimezone)->translatedFormat('j F Y, H:i T')
            : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">
        {{ __('govuk_alpha.events.calendar_subscriptions_back') }}
    </a>

    @if (! empty($errors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors as $error)
                            <li><a href="#calendar-label">{{ $error }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if ($status === 'revoked')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="calendar-subscription-status" tabindex="-1">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="calendar-subscription-status">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.calendar_subscription_revoked') }}</p>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.calendar_subscriptions_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.calendar_subscriptions_intro') }}</p>
    <div class="govuk-inset-text">{{ __('govuk_alpha.events.calendar_subscriptions_privacy') }}</div>

    @if (is_string($createdFeedUrl) && $createdFeedUrl !== '')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="calendar-subscription-created" tabindex="-1">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="calendar-subscription-created">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.calendar_subscription_created') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.events.calendar_subscription_copy_once') }}</p>
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="created-calendar-feed-url">
                        {{ __('govuk_alpha.events.calendar_subscription_feed_url') }}
                    </label>
                    <textarea class="govuk-textarea" id="created-calendar-feed-url" rows="4" readonly spellcheck="false" autocomplete="off">{{ $createdFeedUrl }}</textarea>
                </div>
            </div>
        </div>
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.events.calendar_subscription_create_title') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.events.calendar.subscriptions.create', ['tenantSlug' => $tenantSlug]) }}" novalidate>
        @csrf
        <div class="govuk-form-group{{ ! empty($errors) ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="calendar-label">
                {{ __('govuk_alpha.events.calendar_subscription_label') }}
            </label>
            <div id="calendar-label-hint" class="govuk-hint">{{ __('govuk_alpha.events.calendar_subscription_label_hint') }}</div>
            @if (! empty($errors))
                <p id="calendar-label-error" class="govuk-error-message">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_title') }}:</span>
                    {{ $errors[0] }}
                </p>
            @endif
            <input
                class="govuk-input govuk-input--width-20"
                id="calendar-label"
                name="label"
                type="text"
                value="{{ $label }}"
                maxlength="100"
                aria-describedby="calendar-label-hint{{ ! empty($errors) ? ' calendar-label-error' : '' }}"
            >
        </div>
        <button class="govuk-button" data-module="govuk-button" type="submit">
            {{ __('govuk_alpha.events.calendar_subscription_create') }}
        </button>
    </form>

    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.events.calendar_subscriptions_existing') }}</h2>
    @if (empty($tokens))
        <p class="govuk-body">{{ __('govuk_alpha.events.calendar_subscriptions_none') }}</p>
    @else
        @foreach ($tokens as $token)
            <article class="govuk-!-margin-bottom-7" aria-labelledby="calendar-token-{{ (int) $token['id'] }}">
                <h3 class="govuk-heading-m" id="calendar-token-{{ (int) $token['id'] }}">
                    {{ $token['label'] ?: __('govuk_alpha.events.calendar_subscription_unnamed') }}
                </h3>
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_status') }}</dt>
                        <dd class="govuk-summary-list__value">
                            <strong class="govuk-tag{{ ! $token['active'] ? ' govuk-tag--grey' : '' }}">
                                {{ $token['active'] ? __('govuk_alpha.events.calendar_subscription_active') : __('govuk_alpha.events.calendar_subscription_inactive') }}
                            </strong>
                        </dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_prefix') }}</dt>
                        <dd class="govuk-summary-list__value"><code>{{ $token['token_prefix'] }}</code></dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_created_at') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $formatDate($token['created_at']) ?: __('govuk_alpha.events.calendar_subscription_unknown_date') }}</dd>
                    </div>
                    @if ($token['last_used_at'])
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_last_used') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $formatDate($token['last_used_at']) }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($token['active'])
                    <p class="govuk-body">
                        <a class="govuk-button govuk-button--warning" data-module="govuk-button" href="{{ route('govuk-alpha.events.calendar.subscriptions.revoke.confirm', ['tenantSlug' => $tenantSlug, 'tokenId' => $token['id']]) }}">
                            {{ __('govuk_alpha.events.calendar_subscription_revoke') }}
                        </a>
                    </p>
                @endif
            </article>
        @endforeach
    @endif
@endsection
