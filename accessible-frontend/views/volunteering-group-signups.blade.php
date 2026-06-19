{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $reservations = $reservations ?? [];
        $status = $status ?? null;
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;

        $resStatusTag = [
            'active' => 'govuk-tag--green',
            'confirmed' => 'govuk-tag--green',
            'pending' => 'govuk-tag--yellow',
            'cancelled' => 'govuk-tag--grey',
        ];
        $resStatusLabel = [
            'active' => 'govuk_alpha_volunteering.group_signups.status_active',
            'confirmed' => 'govuk_alpha_volunteering.group_signups.status_confirmed',
            'pending' => 'govuk_alpha_volunteering.group_signups.status_pending',
            'cancelled' => 'govuk_alpha_volunteering.group_signups.status_cancelled',
        ];
        $memberStatusLabel = [
            'confirmed' => 'govuk_alpha_volunteering.group_signups.member_status_confirmed',
            'pending' => 'govuk_alpha_volunteering.group_signups.member_status_pending',
            'declined' => 'govuk_alpha_volunteering.group_signups.member_status_declined',
        ];
        $memberStatusTag = [
            'confirmed' => 'govuk-tag--green',
            'pending' => 'govuk-tag--yellow',
            'declined' => 'govuk-tag--red',
        ];

        $successMsg = [
            'member-added' => 'govuk_alpha_volunteering.group_signups.success_member_added',
            'member-removed' => 'govuk_alpha_volunteering.group_signups.success_member_removed',
            'reservation-cancelled' => 'govuk_alpha_volunteering.group_signups.success_reservation_cancelled',
        ];
        $errorMsg = [
            'member-id-required' => 'govuk_alpha_volunteering.group_signups.error_member_id_required',
            'member-add-failed' => 'govuk_alpha_volunteering.group_signups.error_member_add_failed',
            'member-remove-failed' => 'govuk_alpha_volunteering.group_signups.error_member_remove_failed',
            'reservation-cancel-failed' => 'govuk_alpha_volunteering.group_signups.error_reservation_cancel_failed',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if (isset($successMsg[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="group-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="group-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __($successMsg[$status]) }}</p></div>
        </div>
    @elseif (isset($errorMsg[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>{{ __($errorMsg[$status]) }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_volunteering.group_signups.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.group_signups.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.group_signups.description') }}</p>

    @if (empty($reservations))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.group_signups.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($reservations as $reservation)
                @php
                    $resId = (int) ($reservation['id'] ?? 0);
                    $groupName = (string) ($reservation['group_name'] ?? '');
                    $resStatus = (string) ($reservation['status'] ?? 'active');
                    $isLeader = (bool) ($reservation['is_leader'] ?? false);
                    $opportunity = $reservation['opportunity'] ?? [];
                    $organization = $reservation['organization'] ?? [];
                    $shift = $reservation['shift'] ?? [];
                    $members = $reservation['members'] ?? [];
                    $maxMembers = $reservation['max_members'] ?? null;
                    $confirmedCount = 0;
                    foreach ($members as $m) {
                        if ((string) ($m['status'] ?? '') === 'confirmed') {
                            $confirmedCount++;
                        }
                    }
                    $rTag = $resStatusTag[$resStatus] ?? 'govuk-tag--grey';
                    $rLabelKey = $resStatusLabel[$resStatus] ?? 'govuk_alpha_volunteering.group_signups.status_active';
                    $isCancelled = $resStatus === 'cancelled';
                    $canAddMembers = $isLeader && !$isCancelled && ($maxMembers === null || $confirmedCount < (int) $maxMembers);
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $groupName !== '' ? $groupName : __('govuk_alpha_volunteering.group_signups.title') }}</h2>
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        <strong class="govuk-tag {{ $rTag }}">{{ __($rLabelKey) }}</strong>
                        @if ($isLeader)
                            <strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha_volunteering.group_signups.leader_tag') }}</strong>
                        @endif
                    </p>

                    <dl class="govuk-summary-list govuk-!-margin-bottom-4">
                        @if (!empty($opportunity['title']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.group_signups.opportunity_label') }}</dt>
                                <dd class="govuk-summary-list__value">
                                    @if (!empty($opportunity['id']))
                                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => (int) $opportunity['id']]) }}">{{ $opportunity['title'] }}</a>
                                    @else
                                        {{ $opportunity['title'] }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if (!empty($organization['name']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.group_signups.organisation_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $organization['name'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($opportunity['location']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.group_signups.location_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $opportunity['location'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($shift['start_time']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.group_signups.shift_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                            </div>
                        @endif
                        @if (!empty($reservation['created_at']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.group_signups.created_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDate($reservation['created_at']) }}</dd>
                            </div>
                        @endif
                    </dl>

                    {{-- Members --}}
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                        {{ __('govuk_alpha_volunteering.group_signups.members_title') }}
                        @if ($maxMembers !== null)
                            ({{ __('govuk_alpha_volunteering.group_signups.members_count', ['filled' => $confirmedCount, 'total' => (int) $maxMembers]) }})
                        @else
                            ({{ __('govuk_alpha_volunteering.group_signups.members_count_open', ['count' => count($members)]) }})
                        @endif
                    </h3>
                    @if (empty($members))
                        <p class="govuk-body">{{ __('govuk_alpha_volunteering.group_signups.no_members') }}</p>
                    @else
                        <table class="govuk-table">
                            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.group_signups.members_title') }}</caption>
                            <thead class="govuk-table__head">
                                <tr class="govuk-table__row">
                                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.group_signups.members_title') }}</th>
                                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.group_signups.status_label') }}</th>
                                    @if ($isLeader && !$isCancelled)
                                        <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.group_signups.remove_member_button') }}</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="govuk-table__body">
                                @foreach ($members as $member)
                                    @php
                                        $memberId = (int) ($member['id'] ?? 0);
                                        $memberName = (string) ($member['name'] ?? '');
                                        $memberStatus = (string) ($member['status'] ?? 'pending');
                                        $mTag = $memberStatusTag[$memberStatus] ?? 'govuk-tag--grey';
                                        $mLabelKey = $memberStatusLabel[$memberStatus] ?? 'govuk_alpha_volunteering.group_signups.member_status_pending';
                                    @endphp
                                    @if ($memberStatus !== 'cancelled')
                                        <tr class="govuk-table__row">
                                            <td class="govuk-table__cell">{{ $memberName !== '' ? $memberName : '#' . $memberId }}</td>
                                            <td class="govuk-table__cell"><strong class="govuk-tag {{ $mTag }}">{{ __($mLabelKey) }}</strong></td>
                                            @if ($isLeader && !$isCancelled)
                                                <td class="govuk-table__cell">
                                                    @if ($memberId > 0 && $memberStatus === 'confirmed')
                                                        <form method="post" action="{{ route('govuk-alpha.volunteering.group-signups.members.remove', ['tenantSlug' => $tenantSlug, 'id' => $resId, 'userId' => $memberId]) }}">
                                                            @csrf
                                                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                                                {{ __('govuk_alpha_volunteering.group_signups.remove_member_button') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_volunteering.group_signups.remove_member_for', ['name' => $memberName !== '' ? $memberName : '#' . $memberId]) }}</span>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Leader actions --}}
                    @if ($isLeader && !$isCancelled)
                        @if ($canAddMembers)
                            <h3 class="govuk-heading-s govuk-!-margin-top-4 govuk-!-margin-bottom-2">{{ __('govuk_alpha_volunteering.group_signups.add_member_title') }}</h3>
                            <form method="post" action="{{ route('govuk-alpha.volunteering.group-signups.members.add', ['tenantSlug' => $tenantSlug, 'id' => $resId]) }}">
                                @csrf
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="user_id_{{ $resId }}">{{ __('govuk_alpha_volunteering.group_signups.add_member_label') }}</label>
                                    <div id="user_id_{{ $resId }}-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.group_signups.add_member_hint') }}</div>
                                    <input class="govuk-input govuk-input--width-10" id="user_id_{{ $resId }}" name="user_id" type="number" min="1" inputmode="numeric" aria-describedby="user_id_{{ $resId }}-hint">
                                </div>
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_volunteering.group_signups.add_member_button') }}</button>
                            </form>
                        @endif

                        <h3 class="govuk-heading-s govuk-!-margin-top-4 govuk-!-margin-bottom-2">{{ __('govuk_alpha_volunteering.group_signups.cancel_title') }}</h3>
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}</span>
                                {{ __('govuk_alpha_volunteering.group_signups.cancel_warning') }}
                            </strong>
                        </div>
                        <form method="post" action="{{ route('govuk-alpha.volunteering.group-signups.cancel', ['tenantSlug' => $tenantSlug, 'id' => $resId]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--warning" data-module="govuk-button">
                                {{ __('govuk_alpha_volunteering.group_signups.cancel_button') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_volunteering.group_signups.cancel_button_for', ['group' => $groupName !== '' ? $groupName : __('govuk_alpha_volunteering.group_signups.title')]) }}</span>
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
