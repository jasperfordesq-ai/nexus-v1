{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $start = $formatDateTime($event['start_time'] ?? $event['start_date'] ?? null);
        $end = $formatDateTime($event['end_time'] ?? $event['end_date'] ?? null);
        $categoryName = $event['category']['name'] ?? $event['category_name'] ?? null;
        $organiserName = $event['user']['name'] ?? trim(($event['user']['first_name'] ?? '') . ' ' . ($event['user']['last_name'] ?? ''));
        $currentRsvp = $event['my_rsvp'] ?? null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_events') }}</a>

    @if ($status === 'event-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="event-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.created') }}</p>
            </div>
        </div>
    @elseif ($status === 'rsvp-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="rsvp-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="rsvp-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.rsvp_updated') }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['event-updated', 'event-cancelled'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="event-organiser-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-organiser-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'event-updated' ? __('govuk_alpha.events.updated') : __('govuk_alpha.events.cancelled') }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['rsvp-failed', 'event-update-failed', 'event-cancel-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $status === 'rsvp-failed' ? __('govuk_alpha.events.rsvp_failed') : ($status === 'event-update-failed' ? __('govuk_alpha.events.update_failed') : __('govuk_alpha.events.cancel_failed')) }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.events.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ $event['title'] }}</h1>

            @if ($isOwner ?? false)
                <div class="nexus-alpha-actions govuk-!-margin-bottom-4">
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.events.edit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.events.edit_event') }}</a>
                </div>
                <details class="govuk-details govuk-!-margin-bottom-2" data-module="govuk-details">
                    <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha.events.cancel_event') }}</span></summary>
                    <div class="govuk-details__text">
                        <p class="govuk-body">{{ __('govuk_alpha.events.cancel_confirm') }}</p>
                        <form method="post" action="{{ route('govuk-alpha.events.cancel', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="cancel-reason">{{ __('govuk_alpha.events.cancel_reason_label') }}</label>
                                <textarea class="govuk-textarea" id="cancel-reason" name="reason" rows="3"></textarea>
                            </div>
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.cancel_event_button') }}</button>
                        </form>
                    </div>
                </details>
                <details class="govuk-details govuk-!-margin-bottom-4" data-module="govuk-details">
                    <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha.events.delete_event') }}</span></summary>
                    <div class="govuk-details__text">
                        <p class="govuk-body">{{ __('govuk_alpha.events.delete_confirm') }}</p>
                        <form method="post" action="{{ route('govuk-alpha.events.delete', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.delete_event_button') }}</button>
                        </form>
                    </div>
                </details>
            @endif

            @if (!empty($event['cover_image']))
                <figure class="nexus-alpha-detail-hero">
                    <img src="{{ $event['cover_image'] }}" alt="{{ __('govuk_alpha.events.image_alt', ['title' => $event['title']]) }}" width="640" height="360" decoding="async">
                </figure>
            @endif

            <h2 class="govuk-heading-l">{{ __('govuk_alpha.events.description_title') }}</h2>
            <div class="govuk-body">{!! nl2br(e((string) ($event['description'] ?? ''))) !!}</div>
        </div>
        <div class="govuk-grid-column-one-third">
            @if (!empty($event['is_full']))
                <strong class="govuk-tag govuk-tag--red">{{ __('govuk_alpha.events.full') }}</strong>
            @elseif (array_key_exists('spots_left', $event) && $event['spots_left'] !== null)
                <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.events.spots_left', ['count' => $event['spots_left']]) }}</strong>
            @endif
        </div>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.events.summary_title') }}</h2>
    <dl class="govuk-summary-list">
        @if ($start)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.starts') }}</dt>
                <dd class="govuk-summary-list__value">{{ $start }}</dd>
            </div>
        @endif
        @if ($end)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.ends') }}</dt>
                <dd class="govuk-summary-list__value">{{ $end }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.location') }}</dt>
            <dd class="govuk-summary-list__value">{{ $event['location'] ?? __('govuk_alpha.events.online') }}</dd>
        </div>
        @if (!empty($event['online_link']) && \Illuminate\Support\Str::startsWith((string) $event['online_link'], ['http://', 'https://']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.online_link_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    <a class="govuk-link" href="{{ $event['online_link'] }}" rel="noopener noreferrer">{{ __('govuk_alpha.events.online_link_text') }}</a>
                </dd>
            </div>
        @endif
        @if ($organiserName !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.organiser') }}</dt>
                <dd class="govuk-summary-list__value">{{ $organiserName }}</dd>
            </div>
        @endif
        @if ($categoryName)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.category') }}</dt>
                <dd class="govuk-summary-list__value">{{ $categoryName }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.attendees_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ trans_choice('govuk_alpha.events.attendees', (int) ($event['attendee_count'] ?? 0), ['count' => (int) ($event['attendee_count'] ?? 0)]) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.interested_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ trans_choice('govuk_alpha.events.interested', (int) ($event['interested_count'] ?? 0), ['count' => (int) ($event['interested_count'] ?? 0)]) }}</dd>
        </div>
    </dl>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" role="region" aria-labelledby="events-auth-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="events-auth-title">{{ __('govuk_alpha.states.auth_required') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.events.auth_required_detail', ['community' => $tenant['name'] ?? $tenantSlug]) }}</p>
                <div class="nexus-alpha-actions">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @else
        <form method="post" action="{{ route('govuk-alpha.events.rsvp.store', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" class="govuk-!-margin-top-7">
            @csrf
            <fieldset class="govuk-fieldset" aria-describedby="rsvp-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.rsvp_title') }}</h2>
                </legend>
                <div id="rsvp-hint" class="govuk-hint">{{ __('govuk_alpha.events.rsvp_hint') }}</div>
                <div class="govuk-radios" data-module="govuk-radios">
                    @foreach (['going', 'interested', 'not_going'] as $rsvpStatus)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="status-{{ $rsvpStatus }}" name="status" type="radio" value="{{ $rsvpStatus }}" @checked(($currentRsvp ?? 'going') === $rsvpStatus)>
                            <label class="govuk-label govuk-radios__label" for="status-{{ $rsvpStatus }}">{{ __('govuk_alpha.events.rsvp_status.' . $rsvpStatus) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('govuk_alpha.actions.rsvp') }}</button>
        </form>
    @endif

    @if (!empty($attendees))
        <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.events.attendees_title') }}</h2>
        <ul class="govuk-list">
            @foreach ($attendees as $attendee)
                @php
                    $attendeeName = trim((string) ($attendee['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $rsvp = ($attendee['rsvp_status'] ?? '') === 'going' ? 'going' : 'interested';
                @endphp
                <li class="nexus-alpha-card-head">
                    @if (!empty($attendee['avatar_url']))
                        <img class="nexus-alpha-avatar" src="{{ $attendee['avatar_url'] }}" alt="" loading="lazy" decoding="async" width="48" height="48">
                    @else
                        <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($attendeeName, 0, 1)) }}</span>
                    @endif
                    <span class="govuk-body govuk-!-margin-bottom-0 govuk-!-font-weight-bold">{{ $attendeeName }}</span>
                    <strong class="govuk-tag {{ $rsvp === 'going' ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ __('govuk_alpha.events.rsvp_status.' . $rsvp) }}</strong>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
