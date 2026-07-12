{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}">{{ __('govuk_alpha.events.back_to_event') }}</a>
    <span class="govuk-caption-l">{{ $event->title }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.check_in_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.check_in_intro') }}</p>

    @if (in_array($status, ['attendance-updated', 'attendance-code-updated'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="attendance-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="attendance-success-title">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.attendance_updated') }}</p></div>
        </div>
    @elseif (in_array($status, ['attendance-conflict', 'attendance-code-conflict'], true))
        <div class="govuk-notification-banner" role="alert" aria-labelledby="attendance-conflict-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="attendance-conflict-title">{{ __('govuk_alpha.states.warning_prefix') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.attendance_conflict') }}</p></div>
        </div>
    @elseif (in_array($status, ['attendance-invalid', 'attendance-failed', 'attendance-code-invalid', 'attendance-code-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha.events.attendance_failed') }}</p></div>
            </div>
        </div>
    @endif

    <div class="govuk-inset-text">
        <h2 class="govuk-heading-m">{{ __('event_offline_checkin.privacy.title') }}</h2>
        <p class="govuk-body">{{ __('event_offline_checkin.privacy.body') }}</p>
        <p class="govuk-body">{{ __('event_offline_checkin.privacy.no_wallet') }}</p>
    </div>

    <section class="govuk-!-margin-bottom-8" aria-labelledby="signed-code-heading">
        <h2 class="govuk-heading-l" id="signed-code-heading">{{ __('event_offline_checkin.code.title') }}</h2>
        <p class="govuk-body">{{ __('event_offline_checkin.code.intro') }}</p>
        <form method="post" action="{{ route('govuk-alpha.events.check-in.code', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="govuk-form-group">
                <label class="govuk-label" for="signed-attendee-code">{{ __('event_offline_checkin.code.label') }}</label>
                <div class="govuk-hint" id="signed-attendee-code-hint">{{ __('event_offline_checkin.code.hint') }}</div>
                <textarea class="govuk-textarea" id="signed-attendee-code" name="credential" rows="4" maxlength="1024" required aria-describedby="signed-attendee-code-hint" autocomplete="off" spellcheck="false"></textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="signed-code-action">{{ __('event_offline_checkin.code.action') }}</label>
                <select class="govuk-select" id="signed-code-action" name="action" required>
                    @foreach (['check_in', 'check_out', 'no_show', 'undo'] as $action)
                        <option value="{{ $action }}">{{ __('event_offline_checkin.actions.' . $action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="signed-code-reason">{{ __('event_offline_checkin.code.reason') }}</label>
                <div class="govuk-hint" id="signed-code-reason-hint">{{ __('event_offline_checkin.code.reason_hint') }}</div>
                <textarea class="govuk-textarea" id="signed-code-reason" name="reason" rows="2" maxlength="500" aria-describedby="signed-code-reason-hint"></textarea>
            </div>
            <div class="govuk-checkboxes govuk-checkboxes--small">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="signed-code-confirm" name="confirmation" type="checkbox" value="1" required>
                    <label class="govuk-label govuk-checkboxes__label" for="signed-code-confirm">{{ __('event_offline_checkin.code.confirm') }}</label>
                </div>
            </div>
            <button class="govuk-button govuk-!-margin-top-3" data-module="govuk-button">{{ __('event_offline_checkin.code.submit') }}</button>
        </form>
    </section>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
            {{ __('event_offline_checkin.device.lost') }}
        </strong>
    </div>

    <h2 class="govuk-heading-m">{{ __('govuk_alpha.events.check_in_metrics_title') }}</h2>
    <dl class="govuk-summary-list">
        @foreach (['confirmed', 'checked_in', 'checked_out', 'no_show', 'attended'] as $metric)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.people_metrics.' . $metric) }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($metrics[$metric] ?? 0) }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="get" action="{{ route('govuk-alpha.events.check-in', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}" class="govuk-!-margin-bottom-7">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m"><h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.check_in_search_title') }}</h2></legend>
            <div class="govuk-form-group">
                <label class="govuk-label" for="attendance-search">{{ __('govuk_alpha.events.check_in_search_label') }}</label>
                <input class="govuk-input govuk-!-width-two-thirds" id="attendance-search" name="search" type="search" value="{{ $query->search }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="attendance-state">{{ __('govuk_alpha.events.people_filters.attendance_state') }}</label>
                <select class="govuk-select" id="attendance-state" name="attendance_state">
                    <option value="">{{ __('govuk_alpha.events.people_states.all') }}</option>
                    @foreach (['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show'] as $state)
                        <option value="{{ $state }}" @selected($query->attendanceState === $state)>{{ __('govuk_alpha.events.people_states.' . $state) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </fieldset>
    </form>

    <p class="govuk-body" aria-live="polite">{{ trans_choice('govuk_alpha.events.check_in_result_count', $total, ['count' => $total]) }}</p>

    @if (empty($people))
        <div class="govuk-inset-text">{{ __('govuk_alpha.events.check_in_empty') }}</div>
    @else
        @foreach ($people as $person)
            @php
                $name = trim((string) ($person['member']['display_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                $actions = array_filter([
                    'check_in' => (bool) ($person['management_actions']['check_in'] ?? false),
                    'check_out' => (bool) ($person['management_actions']['check_out'] ?? false),
                    'no_show' => (bool) ($person['management_actions']['no_show'] ?? false),
                    'undo' => (bool) ($person['management_actions']['undo_attendance'] ?? false),
                ]);
            @endphp
            <section class="govuk-!-margin-bottom-6" aria-labelledby="attendee-{{ $person['member']['id'] }}">
                <h2 class="govuk-heading-m" id="attendee-{{ $person['member']['id'] }}">{{ $name }}</h2>
                <dl class="govuk-summary-list">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.people_registration') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.events.people_states.' . ($person['registration']['state'] ?? 'none')) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.people_attendance') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.events.people_states.' . ($person['attendance']['state'] ?? 'not_checked_in')) }}</dd>
                    </div>
                </dl>
                @if ($actions === [])
                    <p class="govuk-body">{{ __('govuk_alpha.events.attendance_no_action') }}</p>
                @else
                    <form method="post" action="{{ route('govuk-alpha.events.check-in.update', ['tenantSlug' => $tenantSlug, 'id' => $event->id, 'userId' => $person['member']['id']]) }}">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ (int) ($person['attendance']['version'] ?? 0) }}">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="attendance-action-{{ $person['member']['id'] }}">{{ __('govuk_alpha.events.attendance_action_label') }}</label>
                            <select class="govuk-select" id="attendance-action-{{ $person['member']['id'] }}" name="action" required>
                                @foreach (array_keys($actions) as $action)
                                    <option value="{{ $action }}">{{ __('govuk_alpha.events.attendance_actions.' . $action) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (isset($actions['undo']))
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="attendance-reason-{{ $person['member']['id'] }}">{{ __('govuk_alpha.events.attendance_reason_label') }}</label>
                                <div class="govuk-hint" id="attendance-reason-hint-{{ $person['member']['id'] }}">{{ __('govuk_alpha.events.attendance_reason_hint') }}</div>
                                <textarea class="govuk-textarea" id="attendance-reason-{{ $person['member']['id'] }}" name="reason" rows="2" aria-describedby="attendance-reason-hint-{{ $person['member']['id'] }}"></textarea>
                            </div>
                        @endif
                        <div class="govuk-checkboxes govuk-checkboxes--small">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="attendance-confirm-{{ $person['member']['id'] }}" name="confirmation" type="checkbox" value="1" required>
                                <label class="govuk-label govuk-checkboxes__label" for="attendance-confirm-{{ $person['member']['id'] }}">{{ __('govuk_alpha.events.attendance_confirmation', ['name' => $name]) }}</label>
                            </div>
                        </div>
                        <button class="govuk-button govuk-!-margin-top-3" data-module="govuk-button">{{ __('govuk_alpha.events.attendance_apply') }}</button>
                    </form>
                @endif
            </section>
        @endforeach
    @endif

    @if ($totalPages > 1)
        <nav class="govuk-pagination" aria-label="{{ __('govuk_alpha.events.check_in_pagination') }}">
            @if ($query->page > 1)
                <div class="govuk-pagination__prev"><a class="govuk-link govuk-pagination__link" rel="prev" href="{{ route('govuk-alpha.events.check-in', array_merge(request()->except('page'), ['tenantSlug' => $tenantSlug, 'id' => $event->id, 'page' => $query->page - 1])) }}">{{ __('govuk_alpha.events.check_in_previous') }}</a></div>
            @endif
            @if ($query->page < $totalPages)
                <div class="govuk-pagination__next"><a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.check-in', array_merge(request()->except('page'), ['tenantSlug' => $tenantSlug, 'id' => $event->id, 'page' => $query->page + 1])) }}">{{ __('govuk_alpha.events.check_in_next') }}</a></div>
            @endif
        </nav>
    @endif
@endsection
