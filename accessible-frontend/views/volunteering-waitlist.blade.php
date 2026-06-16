{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $entries = $entries ?? [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'waitlist-left')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="waitlist-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="waitlist-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.vol_depth.waitlist_left') }}</p>
            </div>
        </div>
    @elseif ($status === 'waitlist-leave-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.vol_depth.waitlist_leave_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.vol_depth.waitlist_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.vol_depth.waitlist_description') }}</p>

    @if ($error)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    @elseif (empty($entries))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.vol_depth.waitlist_empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.vol_depth.waitlist_empty') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($entries as $entry)
                @php
                    $shift = $entry['shift'] ?? [];
                    $opportunity = $entry['opportunity'] ?? [];
                    $organization = $entry['organization'] ?? [];
                    $entryStatus = (string) ($entry['status'] ?? 'waiting');
                    $shiftId = (int) ($shift['id'] ?? 0);
                    $title = (string) ($opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title'));
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $title }}</h2>
                    @if ($entryStatus === 'notified')
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.vol_depth.waitlist_spot_available') }}</strong>
                    @else
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.vol_depth.waitlist_position', ['position' => (int) ($entry['position'] ?? 0)]) }}</strong>
                    @endif
                    @if ($entryStatus === 'notified')
                        <p class="govuk-body govuk-!-margin-top-3">{{ __('govuk_alpha.vol_depth.waitlist_notified_hint') }}</p>
                    @endif
                    <dl class="govuk-summary-list govuk-!-margin-top-4 govuk-!-margin-bottom-3">
                        @if (!empty($organization['name']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $organization['name'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($opportunity['location']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.location') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $opportunity['location'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($shift['start_time']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.shift_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                            </div>
                        @endif
                        @if (!empty($entry['joined_at']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_depth.waitlist_joined') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDateTime($entry['joined_at']) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($shiftId > 0)
                        <form method="post" action="{{ route('govuk-alpha.volunteering.waitlist.leave', ['tenantSlug' => $tenantSlug, 'shiftId' => $shiftId]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                {{ __('govuk_alpha.vol_depth.waitlist_leave') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.vol_depth.waitlist_leave_for', ['title' => $title]) }}</span>
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
