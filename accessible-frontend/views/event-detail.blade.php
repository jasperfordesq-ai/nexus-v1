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
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="event-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.created') }}</p>
            </div>
        </div>
    @elseif ($status === 'rsvp-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="rsvp-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="rsvp-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.rsvp_updated') }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['event-updated', 'event-cancelled'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="event-organiser-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-organiser-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'event-updated' ? __('govuk_alpha.events.updated') : __('govuk_alpha.events.cancelled') }}</p>
            </div>
        </div>
    @elseif ($status === 'checkin-success')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="checkin-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="checkin-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.polish_events.checkin_success') }}</p>
            </div>
        </div>
    @elseif ($status === 'checkin-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.events.polish_events.checkin_failed') }}</p>
                </div>
            </div>
        </div>
    @elseif (in_array($status, ['waitlist-joined', 'waitlist-left', 'poll-voted'], true))
        @php
            $depthMessage = match ($status) {
                'waitlist-joined' => __('govuk_alpha.events.states.waitlist-joined'),
                'waitlist-left' => __('govuk_alpha.events.states.waitlist-left'),
                default => __('govuk_alpha.events.states.poll-voted'),
            };
        @endphp
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="event-depth-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-depth-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $depthMessage }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['rsvp-failed', 'rsvp-vetting-required', 'rsvp-policy-unavailable', 'event-update-failed', 'event-cancel-failed', 'waitlist-failed', 'waitlist-vetting-required', 'waitlist-policy-unavailable', 'poll-vote-failed'], true))
        @php
            $depthError = match ($status) {
                'rsvp-failed' => __('govuk_alpha.events.rsvp_failed'),
                'rsvp-vetting-required', 'waitlist-vetting-required' => __('safeguarding.errors.vetting_required_detail'),
                'rsvp-policy-unavailable', 'waitlist-policy-unavailable' => __('safeguarding.errors.policy_unavailable_detail'),
                'event-update-failed' => __('govuk_alpha.events.update_failed'),
                'event-cancel-failed' => __('govuk_alpha.events.cancel_failed'),
                'waitlist-failed' => __('govuk_alpha.events.states.waitlist-failed'),
                default => __('govuk_alpha.events.states.poll-vote-failed'),
            };
        @endphp
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $depthError }}</p>
                </div>
            </div>
        </div>
    @endif

    @php $isCancelled = ($event['status'] ?? '') === 'cancelled'; @endphp

    {{-- Cancelled event warning — rendered ABOVE the grid so it spans full width --}}
    @if ($isCancelled)
        <div class="govuk-warning-text govuk-!-margin-bottom-6">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                {{ __('govuk_alpha.events.polish_events.cancelled_banner_heading') }}
                @if (!empty($event['cancellation_reason']))
                    <br>
                    <span class="govuk-body govuk-!-margin-top-1">{{ __('govuk_alpha.events.polish_events.cancelled_reason_prefix') }} {{ $event['cancellation_reason'] }}</span>
                @endif
            </strong>
        </div>
    @endif

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.events.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ $event['title'] }}</h1>

            {{-- Capacity tag — moved into two-thirds column, immediately after h1, before image --}}
            @if (!empty($event['is_full']))
                <p class="govuk-!-margin-bottom-4"><strong class="govuk-tag govuk-tag--red">{{ __('govuk_alpha.events.full') }}</strong></p>
            @elseif (array_key_exists('spots_left', $event) && $event['spots_left'] !== null)
                <p class="govuk-!-margin-bottom-4"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.events.spots_left', ['count' => $event['spots_left']]) }}</strong></p>
            @endif

            @php
                // Mirror EventsParity::eventsIsSeries — only recurring events get the
                // series-edit flow; a non-series event would just be redirected.
                $eventIsSeries = !empty($event['is_series'])
                    || !empty($event['is_recurring_template'])
                    || !empty($event['parent_event_id'])
                    || (!empty($seriesEvents) && count($seriesEvents) > 1);
            @endphp
            @if ($isOwner ?? false)
                <div class="govuk-button-group govuk-!-margin-bottom-4">
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.events.edit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.events.edit_event') }}</a>
                    @if ($eventIsSeries)
                        <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.events.recurring.edit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_events.nav.edit_series') }}</a>
                    @endif
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.events.polls', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_events.nav.manage_polls') }}</a>
                </div>
                @unless($isCancelled)
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
                @endunless
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

    {{-- Summary, RSVP, polls, series and attendees stay inside the same two-thirds
         reading column as the title/description — the column closes before @endsection. --}}
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
            <dd class="govuk-summary-list__value">
                {{ $event['location'] ?? __('govuk_alpha.events.online') }}
                @php
                    $eventHasCoords = isset($event['latitude'], $event['longitude'])
                        && $event['latitude'] !== null && $event['longitude'] !== null
                        && empty($event['is_online']);
                @endphp
                @if ($eventHasCoords && \App\Core\TenantContext::hasFeature('maps'))
                    <br><a class="govuk-link" href="{{ route('govuk-alpha.events.map', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.nav.view_location') }}</a>
                @endif
            </dd>
        </div>
        @if (!empty($event['online_link']) && \Illuminate\Support\Str::startsWith((string) $event['online_link'], ['http://', 'https://']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.online_link_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    <a class="govuk-link" href="{{ $event['online_link'] }}" rel="noopener noreferrer">{{ __('govuk_alpha.events.online_link_text') }}</a>
                </dd>
            </div>
        @endif
        @php $videoUrl = $event['video_url'] ?? null; @endphp
        @if (!empty($videoUrl) && \Illuminate\Support\Str::startsWith((string) $videoUrl, ['http://', 'https://']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.polish_events.video_url_summary_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    <a class="govuk-link" href="{{ $videoUrl }}" rel="noopener noreferrer">{{ __('govuk_alpha.events.polish_events.video_url_link_text') }}</a>
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

    {{-- Share link — zero-JS mailto: fallback. Shown for all authenticated visitors on non-cancelled events. --}}
    @if (!$requiresAuth)
        @php
            $shareUrl = route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]);
            $shareSubject = rawurlencode($event['title'] ?? '');
            $shareBody = rawurlencode($shareUrl);
        @endphp
        <p class="govuk-body govuk-!-margin-top-4">
            <a class="govuk-link" href="mailto:?subject={{ $shareSubject }}&body={{ $shareBody }}">{{ __('govuk_alpha.events.polish_events.share_email_label') }}</a>
            <span aria-hidden="true"> &middot; </span>
            <a class="govuk-link" href="{{ route('govuk-alpha.events.translate', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.nav.translate_description') }}</a>
        </p>
    @endif

    @unless($isCancelled)
    @if ($requiresAuth)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="events-auth-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="events-auth-title">{{ __('govuk_alpha.states.auth_required') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.events.auth_required_detail', ['community' => $tenant['name'] ?? $tenantSlug]) }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @else
        @php
            $onWaitlist = isset($waitlistPosition) && $waitlistPosition !== null;
            // Show the waitlist join control when the event is full and the member
            // is not already attending. Once on the waitlist, show position + leave.
            $eventIsFull = !empty($event['is_full']);
            $isGoing = ($currentRsvp ?? null) === 'going';
        @endphp

        @if ($onWaitlist || ($eventIsFull && !$isGoing))
            <section class="govuk-!-margin-top-7" aria-labelledby="waitlist-heading">
                <h2 class="govuk-heading-m" id="waitlist-heading">{{ __('govuk_alpha.events.waitlist_heading') }}</h2>
                @if ($onWaitlist)
                    <div class="govuk-inset-text">
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha.events.waitlist_on_list_note') }}</p>
                        <p class="govuk-body govuk-!-margin-bottom-0 govuk-!-font-weight-bold">{{ __('govuk_alpha.events.waitlist_position', ['position' => $waitlistPosition]) }}</p>
                    </div>
                    <form method="post" action="{{ route('govuk-alpha.events.waitlist.leave', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                        @csrf
                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.waitlist_leave') }}</button>
                    </form>
                @else
                    <p class="govuk-body">{{ __('govuk_alpha.events.waitlist_full_note') }}</p>
                    <form method="post" action="{{ route('govuk-alpha.events.waitlist.join', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                        @csrf
                        <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.waitlist_join') }}</button>
                    </form>
                @endif
            </section>
        @endif

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
                            <input class="govuk-radios__input" id="status-{{ $rsvpStatus }}" name="status" type="radio" value="{{ $rsvpStatus }}" required @checked(($currentRsvp ?? null) === $rsvpStatus)>
                            <label class="govuk-label govuk-radios__label" for="status-{{ $rsvpStatus }}">{{ __('govuk_alpha.events.rsvp_status.' . $rsvpStatus) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('govuk_alpha.actions.rsvp') }}</button>
        </form>

        {{-- ===== Event polls ===== --}}
        @if (!empty($polls))
            @php $pollDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null; @endphp
            <section class="govuk-!-margin-top-8" aria-labelledby="event-polls-heading">
                <h2 class="govuk-heading-l" id="event-polls-heading">{{ __('govuk_alpha.events.polls_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.events.polls_intro') }}</p>

                @foreach ($polls as $poll)
                    @php
                        $pollId = (int) ($poll['id'] ?? 0);
                        $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha.events.polls_heading');
                        $pollOpen = ($poll['status'] ?? 'closed') === 'open';
                        $hasVoted = (bool) ($poll['has_voted'] ?? false);
                        $votedOptionId = $poll['voted_option_id'] ?? null;
                        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                        $resultsVisible = (bool) ($poll['results_visible'] ?? false);
                        $totalVotes = (int) ($poll['total_votes'] ?? 0);
                        $closesOn = $pollDate($poll['expires_at'] ?? null);
                        $maxVotes = 0;
                        foreach ($options as $o) { $maxVotes = max($maxVotes, (int) ($o['vote_count'] ?? 0)); }
                    @endphp
                    <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                        <div class="nexus-alpha-module-row">
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h3>
                            @if ($pollOpen)
                                <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.events.poll_open_tag') }}</strong>
                            @else
                                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.events.poll_closed_tag') }}</strong>
                            @endif
                        </div>
                        @if (!empty($poll['description']))
                            <p class="govuk-body">{{ $poll['description'] }}</p>
                        @endif

                        @if (empty($options))
                            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.events.poll_no_options') }}</p></div>
                        @elseif ($pollOpen && !$hasVoted)
                            {{-- Open + not voted: show the vote form. Totals stay hidden (ballot secrecy). --}}
                            <form method="post" action="{{ route('govuk-alpha.events.polls.vote', ['tenantSlug' => $tenantSlug, 'id' => $event['id'], 'pollId' => $pollId]) }}">
                                @csrf
                                <fieldset class="govuk-fieldset" aria-describedby="event-poll-{{ $pollId }}-hint">
                                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.events.poll_choose_label') }}</legend>
                                    <div id="event-poll-{{ $pollId }}-hint" class="govuk-hint">{{ __('govuk_alpha.events.poll_vote_once_hint') }}</div>
                                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                        @foreach ($options as $opt)
                                            <div class="govuk-radios__item">
                                                <input class="govuk-radios__input" id="event-poll-{{ $pollId }}-opt-{{ $opt['id'] }}" name="option_id" type="radio" value="{{ $opt['id'] }}">
                                                <label class="govuk-label govuk-radios__label" for="event-poll-{{ $pollId }}-opt-{{ $opt['id'] }}">{{ $opt['text'] ?? ($opt['label'] ?? '') }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <button class="govuk-button govuk-!-margin-top-2 govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.poll_vote_button') }}</button>
                            </form>
                        @elseif ($resultsVisible)
                            {{-- Closed (or creator): show results with accessible progress bars. --}}
                            <p class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha.events.poll_votes_count', $totalVotes, ['count' => $totalVotes]) }}</p>
                            @foreach ($options as $opt)
                                @php
                                    $pct = (float) ($opt['percentage'] ?? 0);
                                    $pctRounded = max(0, min(100, (int) round($pct)));
                                    $cnt = (int) ($opt['vote_count'] ?? 0);
                                    $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId;
                                    $isLeading = $totalVotes > 0 && $cnt === $maxVotes;
                                    $optLabel = (string) ($opt['text'] ?? ($opt['label'] ?? ''));
                                @endphp
                                <div class="govuk-!-margin-bottom-3">
                                    <p class="govuk-body govuk-!-margin-bottom-1">
                                        {{ $optLabel }}
                                        @if ($isLeading)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.events.poll_leading') }}</strong>@endif
                                        @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.events.poll_your_choice') }}</strong>@endif
                                    </p>
                                    <progress max="100" value="{{ $pct }}" aria-label="{{ $optLabel }}: {{ $pctRounded }}%">{{ $pctRounded }}%</progress>
                                    <span class="govuk-body-s">{{ $pctRounded }}% — {{ trans_choice('govuk_alpha.events.poll_per_option_votes', $cnt, ['count' => $cnt]) }}</span>
                                </div>
                            @endforeach
                        @else
                            {{-- Open + already voted: hide running totals until close. --}}
                            <div class="govuk-inset-text">{{ __('govuk_alpha.events.poll_results_pending_note') }}</div>
                            <ul class="govuk-list">
                                @foreach ($options as $opt)
                                    @php $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId; @endphp
                                    <li>
                                        {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                                        @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.events.poll_your_choice') }}</strong>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
    @endif
    @endunless {{-- /isCancelled --}}

    {{-- ===== Recurring series navigation ===== --}}
    @if (!empty($seriesEvents) && count($seriesEvents) > 1)
        @php $seriesDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y, g:ia') : null; @endphp
        <details class="govuk-details govuk-!-margin-top-8" data-module="govuk-details">
            <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha.events.series_heading') }}</span></summary>
            <div class="govuk-details__text">
                <p class="govuk-body">{{ __('govuk_alpha.events.series_intro') }}</p>
                <ul class="govuk-list">
                    @foreach ($seriesEvents as $sibling)
                        @php
                            $siblingId = (int) ($sibling['id'] ?? 0);
                            $siblingTitle = trim((string) ($sibling['title'] ?? '')) ?: __('govuk_alpha.events.detail_title');
                            $siblingWhen = $seriesDate($sibling['start_time'] ?? null);
                            $isCurrent = $siblingId === (int) ($event['id'] ?? 0);
                        @endphp
                        <li>
                            @if ($isCurrent)
                                <span class="govuk-!-font-weight-bold">{{ $siblingTitle }}</span>
                                <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.events.series_this_event') }}</strong>
                                @if ($siblingWhen)<span class="nexus-alpha-meta govuk-body-s"> — {{ $siblingWhen }}</span>@endif
                            @else
                                <a class="govuk-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $siblingId]) }}">{{ $siblingTitle }}</a>
                                @if ($siblingWhen)<span class="nexus-alpha-meta govuk-body-s"> — {{ $siblingWhen }}</span>@endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>
    @endif

    {{-- ===== Attendee list (govuk-list, no custom class on <li>) ===== --}}
    @if (!empty($attendees))
        <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.events.attendees_title') }}</h2>

        {{-- ===== Organiser check-in panel (owner only) — WAVE NIGHT-EVENTS ===== --}}
        @if ($isOwner ?? false)
            <section class="govuk-!-margin-bottom-6" aria-labelledby="checkin-heading">
                <h3 class="govuk-heading-m" id="checkin-heading">{{ __('govuk_alpha.events.polish_events.checkin_heading') }}</h3>
                <p class="govuk-body">{{ __('govuk_alpha.events.polish_events.checkin_intro') }}</p>
                <dl class="govuk-summary-list">
                    @foreach ($attendees as $attendee)
                        @php
                            $attendeeName = trim((string) ($attendee['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                            $attendeeId = (int) ($attendee['id'] ?? $attendee['user_id'] ?? 0);
                            $isAttended = ($attendee['rsvp_status'] ?? '') === 'attended';
                        @endphp
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ $attendeeName }}</dt>
                            <dd class="govuk-summary-list__value">
                                @if ($isAttended)
                                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.events.polish_events.checkin_done_tag') }}</strong>
                                @else
                                    <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.events.rsvp_status.going') }}</strong>
                                @endif
                            </dd>
                            <dd class="govuk-summary-list__actions">
                                @unless($isAttended)
                                    <form method="post" action="{{ route('govuk-alpha.events.checkin', ['tenantSlug' => $tenantSlug, 'id' => $event['id'], 'attendeeId' => $attendeeId]) }}">
                                        @csrf
                                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.events.polish_events.checkin_button') }}</button>
                                    </form>
                                @endunless
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <ul class="govuk-list">
            @foreach ($attendees as $attendee)
                @php
                    $attendeeName = trim((string) ($attendee['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $rsvpDisplay = in_array(($attendee['rsvp_status'] ?? ''), ['going', 'attended'], true) ? 'going' : 'interested';
                @endphp
                <li>
                    @if (!empty($attendee['avatar_url']))
                        <img class="nexus-alpha-avatar" src="{{ $attendee['avatar_url'] }}" alt="" loading="lazy" decoding="async" width="48" height="48">
                    @else
                        <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($attendeeName, 0, 1)) }}</span>
                    @endif
                    <span class="govuk-body govuk-!-margin-bottom-0 govuk-!-font-weight-bold">{{ $attendeeName }}</span>
                    <strong class="govuk-tag {{ $rsvpDisplay === 'going' ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ __('govuk_alpha.events.rsvp_status.' . $rsvpDisplay) }}</strong>
                </li>
            @endforeach
        </ul>
    @endif
        </div>
    </div>
@endsection
