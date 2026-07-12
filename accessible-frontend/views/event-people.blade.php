{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}">{{ __('govuk_alpha.events.back_to_event') }}</a>

    <span class="govuk-caption-l">{{ $event->title }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.people_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.people_intro') }}</p>

    @if (in_array($status, ['people-updated', 'people-partial'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="people-result-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="people-result-title">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.people_updated', ['updated' => $updated, 'failed' => $failed]) }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['people-invalid', 'people-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha.events.people_failed') }}</p></div>
            </div>
        </div>
    @endif

    @if ($canManageAttendance)
        <nav class="govuk-button-group" aria-label="{{ __('govuk_alpha.events.operations_navigation') }}">
            <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.events.check-in', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}">{{ __('govuk_alpha.events.check_in_title') }}</a>
        </nav>
    @endif

    <h2 class="govuk-heading-m">{{ __('govuk_alpha.events.people_metrics_title') }}</h2>
    <dl class="govuk-summary-list">
        @foreach (['confirmed', 'waitlisted', 'checked_in', 'checked_out', 'no_show', 'attended'] as $metric)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.people_metrics.' . $metric) }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($metrics[$metric] ?? 0) }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="get" action="{{ route('govuk-alpha.events.people', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}" class="govuk-!-margin-bottom-7">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m"><h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.people_filters_title') }}</h2></legend>
            <div class="govuk-form-group">
                <label class="govuk-label" for="people-search">{{ __('govuk_alpha.events.people_search_label') }}</label>
                <input class="govuk-input govuk-!-width-two-thirds" id="people-search" name="search" type="search" value="{{ $query->search }}">
            </div>
            <div class="govuk-grid-row">
                @foreach ([
                    'registration_state' => ['none', 'invited', 'pending', 'confirmed', 'declined', 'cancelled'],
                    'waitlist_state' => ['none', 'active', 'waiting', 'offered', 'accepted', 'expired', 'cancelled'],
                    'attendance_state' => ['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show'],
                    'engagement_state' => ['none', 'interested'],
                ] as $field => $options)
                    @php
                        $selectedFilter = match ($field) {
                            'registration_state' => $query->registrationState,
                            'waitlist_state' => $query->waitlistState,
                            'attendance_state' => $query->attendanceState,
                            default => $query->engagementState,
                        };
                    @endphp
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="{{ $field }}">{{ __('govuk_alpha.events.people_filters.' . $field) }}</label>
                            <select class="govuk-select" id="{{ $field }}" name="{{ $field }}">
                                <option value="">{{ __('govuk_alpha.events.people_states.all') }}</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected($selectedFilter === $option)>{{ __('govuk_alpha.events.people_states.' . $option) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </fieldset>
    </form>

    <p class="govuk-body" aria-live="polite">{{ trans_choice('govuk_alpha.events.people_result_count', $total, ['count' => $total]) }}</p>

    @if (empty($people))
        <div class="govuk-inset-text">{{ __('govuk_alpha.events.people_empty') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.events.people.update', ['tenantSlug' => $tenantSlug, 'id' => $event->id]) }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-table__caption--m">{{ __('govuk_alpha.events.people_table_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.people_select') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.people_member') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.people_registration') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.people_waitlist') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha.events.people_attendance') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($people as $person)
                        @php $name = trim((string) ($person['member']['display_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member'); @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">
                                <div class="govuk-checkboxes govuk-checkboxes--small">
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="person-{{ $person['member']['id'] }}" name="user_ids[]" type="checkbox" value="{{ $person['member']['id'] }}">
                                        <label class="govuk-label govuk-checkboxes__label" for="person-{{ $person['member']['id'] }}"><span class="govuk-visually-hidden">{{ __('govuk_alpha.events.people_select_member', ['name' => $name]) }}</span></label>
                                    </div>
                                </div>
                            </td>
                            <th class="govuk-table__header" scope="row">{{ $name }}</th>
                            <td class="govuk-table__cell">{{ __('govuk_alpha.events.people_states.' . ($person['registration']['state'] ?? 'none')) }}</td>
                            <td class="govuk-table__cell">
                                {{ __('govuk_alpha.events.people_states.' . ($person['waitlist']['state'] ?? 'none')) }}
                                @if (($person['waitlist']['position'] ?? null) !== null)<br><span class="govuk-hint">{{ __('govuk_alpha.events.people_queue_position', ['position' => $person['waitlist']['position']]) }}</span>@endif
                            </td>
                            <td class="govuk-table__cell">{{ __('govuk_alpha.events.people_states.' . ($person['attendance']['state'] ?? 'not_checked_in')) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m"><h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.people_action_title') }}</h2></legend>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="people-action">{{ __('govuk_alpha.events.people_action_label') }}</label>
                    <select class="govuk-select" id="people-action" name="action" required>
                        @foreach (['approve', 'reject', 'cancel'] as $action)
                            <option value="{{ $action }}">{{ __('govuk_alpha.events.people_actions.' . $action) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="people-reason">{{ __('govuk_alpha.events.people_reason_label') }}</label>
                    <div id="people-reason-hint" class="govuk-hint">{{ __('govuk_alpha.events.people_reason_hint') }}</div>
                    <textarea class="govuk-textarea" id="people-reason" name="reason" rows="3" aria-describedby="people-reason-hint"></textarea>
                </div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="people-confirmation" name="confirmation" type="checkbox" value="1" required>
                        <label class="govuk-label govuk-checkboxes__label" for="people-confirmation">{{ __('govuk_alpha.events.people_confirmation') }}</label>
                    </div>
                </div>
                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('govuk_alpha.events.people_apply_action') }}</button>
            </fieldset>
        </form>
    @endif

    @if ($totalPages > 1)
        <nav class="govuk-pagination" aria-label="{{ __('govuk_alpha.events.people_pagination') }}">
            @if ($query->page > 1)
                <div class="govuk-pagination__prev"><a class="govuk-link govuk-pagination__link" rel="prev" href="{{ route('govuk-alpha.events.people', array_merge(request()->except('page'), ['tenantSlug' => $tenantSlug, 'id' => $event->id, 'page' => $query->page - 1])) }}">{{ __('govuk_alpha.events.people_previous') }}</a></div>
            @endif
            @if ($query->page < $totalPages)
                <div class="govuk-pagination__next"><a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.people', array_merge(request()->except('page'), ['tenantSlug' => $tenantSlug, 'id' => $event->id, 'page' => $query->page + 1])) }}">{{ __('govuk_alpha.events.people_next') }}</a></div>
            @endif
        </nav>
    @endif
@endsection
