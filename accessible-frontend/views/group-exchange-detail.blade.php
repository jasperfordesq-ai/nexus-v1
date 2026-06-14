{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusTag = [
            'draft' => 'govuk-tag--grey', 'pending' => 'govuk-tag--yellow', 'approved' => 'govuk-tag--blue',
            'active' => 'govuk-tag--turquoise', 'completed' => 'govuk-tag--green', 'cancelled' => 'govuk-tag--red',
        ];
        $exStatus = in_array($exchange['status'] ?? '', array_keys($statusTag), true) ? $exchange['status'] : 'draft';
        $isClosed = in_array($exStatus, ['completed', 'cancelled'], true);
        $successStates = ['created', 'participant-added', 'participant-removed', 'confirmed', 'completed', 'cancelled'];
        $errorStates = ['add-failed', 'complete-failed', 'failed'];
        $allConfirmed = !empty($participants) && collect($participants)->every(fn ($p) => (bool) ($p['confirmed'] ?? false));
        $hoursFor = fn ($p): float => array_key_exists((int) ($p['user_id'] ?? 0), $splitByUser)
            ? (float) $splitByUser[(int) $p['user_id']]
            : (float) ($p['hours'] ?? 0);
    @endphp

    <span class="govuk-caption-xl" id="group-exchange-top">{{ __('govuk_alpha.group_exchanges.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $exchange['title'] ?: __('govuk_alpha.group_exchanges.title') }}</h1>
        <strong class="govuk-tag {{ $statusTag[$exStatus] }}">{{ __('govuk_alpha.group_exchanges.statuses.' . $exStatus) }}</strong>
    </div>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="ge-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="ge-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.group_exchanges.states.' . $status) }}</p>
            </div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.group_exchanges.states.' . $status) }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($exchange['description']))
        <p class="govuk-body-l">{{ $exchange['description'] }}</p>
    @endif

    <dl class="govuk-summary-list govuk-!-margin-bottom-8">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.group_exchanges.total_hours_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((float) ($exchange['total_hours'] ?? 0), 2) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.group_exchanges.status_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.group_exchanges.statuses.' . $exStatus) }}</dd>
        </div>
    </dl>

    {{-- Participants --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.group_exchanges.participants_title') }}</h2>
    @if (empty($participants))
        <p class="govuk-inset-text">{{ __('govuk_alpha.group_exchanges.no_participants') }}</p>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.group_exchanges.participants_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.group_exchanges.name_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.group_exchanges.role_column') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.group_exchanges.hours_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.group_exchanges.confirmed_column') }}</th>
                    @if ($editable)
                        <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha.group_exchanges.remove_button') }}</span></th>
                    @endif
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($participants as $p)
                    @php
                        $pName = trim((string) ($p['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                        $roleLabel = ($p['role'] ?? 'provider') === 'receiver'
                            ? __('govuk_alpha.group_exchanges.role_receiver')
                            : __('govuk_alpha.group_exchanges.role_provider');
                        $confirmedLabel = ($p['confirmed'] ?? false)
                            ? __('govuk_alpha.group_exchanges.confirmed_yes')
                            : __('govuk_alpha.group_exchanges.confirmed_no');
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $pName }}</td>
                        <td class="govuk-table__cell">{{ $roleLabel }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format($hoursFor($p), 2) }}</td>
                        <td class="govuk-table__cell">
                            @if ($p['confirmed'] ?? false)
                                <strong class="govuk-tag govuk-tag--green">{{ $confirmedLabel }}</strong>
                            @else
                                <strong class="govuk-tag govuk-tag--grey">{{ $confirmedLabel }}</strong>
                            @endif
                        </td>
                        @if ($editable)
                            <td class="govuk-table__cell">
                                <form method="post" action="{{ route('govuk-alpha.group-exchanges.participants.remove', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id'], 'participantUserId' => $p['user_id']]) }}" class="nexus-alpha-linkform">
                                    @csrf
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.remove_button') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Confirm own participation --}}
    @if ($isParticipant && !$isClosed)
        <section aria-labelledby="confirm-heading" class="govuk-!-margin-top-6">
            <h2 class="govuk-heading-l" id="confirm-heading">{{ __('govuk_alpha.group_exchanges.confirm_title') }}</h2>
            @if ($viewerConfirmed)
                <p class="govuk-inset-text">{{ __('govuk_alpha.group_exchanges.confirmed_already') }}</p>
            @else
                <p class="govuk-body">{{ __('govuk_alpha.group_exchanges.confirm_body') }}</p>
                <form method="post" action="{{ route('govuk-alpha.group-exchanges.confirm', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">
                    @csrf
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.confirm_button') }}</button>
                </form>
            @endif
        </section>
    @endif

    {{-- Organiser: add a person --}}
    @if ($editable)
        <section aria-labelledby="add-heading" class="govuk-!-margin-top-6">
            <h2 class="govuk-heading-l" id="add-heading">{{ __('govuk_alpha.group_exchanges.add_title') }}</h2>

            <form method="get" action="{{ route('govuk-alpha.group-exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="participant_q">{{ __('govuk_alpha.group_exchanges.add_search_label') }}</label>
                    <div id="pq-hint" class="govuk-hint">{{ __('govuk_alpha.group_exchanges.add_search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="participant_q" name="participant_q" type="search" value="{{ $participantQuery ?? '' }}" aria-describedby="pq-hint">
                </div>
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
            </form>

            @if (($participantQuery ?? '') !== '')
                @if (empty($participantResults))
                    <p class="govuk-body">{{ __('govuk_alpha.group_exchanges.add_search_empty') }}</p>
                @else
                    @foreach ($participantResults as $cand)
                        @php $candName = trim((string) ($cand['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member'); @endphp
                        <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ $candName }}</h3>
                            <form method="post" action="{{ route('govuk-alpha.group-exchanges.participants.add', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">
                                @csrf
                                <input type="hidden" name="participant_id" value="{{ $cand['id'] }}">
                                <div class="govuk-form-group govuk-!-margin-bottom-3">
                                    <label class="govuk-label" for="role-{{ $cand['id'] }}">{{ __('govuk_alpha.group_exchanges.add_role_label') }}</label>
                                    <select class="govuk-select" id="role-{{ $cand['id'] }}" name="role">
                                        <option value="provider">{{ __('govuk_alpha.group_exchanges.role_provider') }}</option>
                                        <option value="receiver">{{ __('govuk_alpha.group_exchanges.role_receiver') }}</option>
                                    </select>
                                </div>
                                <div class="govuk-form-group govuk-!-margin-bottom-3">
                                    <label class="govuk-label" for="hours-{{ $cand['id'] }}">{{ __('govuk_alpha.group_exchanges.add_hours_label') }}</label>
                                    <input class="govuk-input govuk-input--width-5" id="hours-{{ $cand['id'] }}" name="hours" type="number" min="0" max="1000" step="0.25" inputmode="decimal" value="0">
                                </div>
                                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.add_button') }}</button>
                            </form>
                        </div>
                    @endforeach
                @endif
            @endif
        </section>
    @endif

    {{-- Organiser: complete / cancel --}}
    @if ($isOrganizer && !$isClosed)
        <section aria-labelledby="complete-heading" class="govuk-!-margin-top-7">
            <h2 class="govuk-heading-l" id="complete-heading">{{ __('govuk_alpha.group_exchanges.complete_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.group_exchanges.complete_body') }}</p>
            <div class="nexus-alpha-actions">
                <form method="post" action="{{ route('govuk-alpha.group-exchanges.complete', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="nexus-alpha-linkform">
                    @csrf
                    <button class="govuk-button govuk-!-margin-bottom-0 @if (empty($participants) || !$allConfirmed) govuk-button--disabled @endif" data-module="govuk-button" @if (empty($participants) || !$allConfirmed) disabled aria-disabled="true" @endif>{{ __('govuk_alpha.group_exchanges.complete_button') }}</button>
                </form>
                <form method="post" action="{{ route('govuk-alpha.group-exchanges.cancel', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}" class="nexus-alpha-linkform">
                    @csrf
                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.cancel_button') }}</button>
                </form>
            </div>
        </section>
    @endif
@endsection
