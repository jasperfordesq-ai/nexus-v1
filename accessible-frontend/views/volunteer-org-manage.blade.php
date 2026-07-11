{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $orgId = (int) ($orgId ?? 0);
        $orgName = trim((string) ($orgName ?? ''));
        $applications = $applications ?? [];
        $hours = $hours ?? [];
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $successStates = ['application-approved', 'application-declined', 'hours-approved', 'hours-declined'];
        $failStates = ['application-failed', 'application-safeguarding-restricted', 'application-safeguarding-unavailable', 'hours-verify-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'organisations']) }}">{{ __('govuk_alpha.vol_org.back_to_organisations') }}</a>

    <span class="govuk-caption-l">{{ $orgName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.vol_org.manage_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.vol_org.manage_description') }}</p>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="vol-org-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="vol-org-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.vol_org.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $failStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ match ($status) {
                    'application-safeguarding-restricted' => __('safeguarding.errors.interaction_not_allowed'),
                    'application-safeguarding-unavailable' => __('safeguarding.errors.policy_unavailable'),
                    default => __('govuk_alpha.vol_org.states.' . $status),
                } }}</p></div></div>
        </div>
    @endif

    {{-- ===== Section 1: applications awaiting a decision ===== --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.vol_org.applications_title') }}</h2>
    @if (empty($applications))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha.vol_org.applications_empty') }}</p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.org_applications_empty_cta') }}</p>
        </div>
    @else
        <p class="govuk-body">{{ __('govuk_alpha.vol_org.applications_description') }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($applications as $application)
                @php
                    $appId = (int) ($application['id'] ?? 0);
                    $applicantName = trim((string) ($application['user']['name'] ?? ''));
                    $applicantId = (int) ($application['user']['id'] ?? 0);
                    $opportunity = $application['opportunity'] ?? [];
                    $shift = $application['shift'] ?? null;
                @endphp
                @if ($appId > 0)
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            @if ($applicantId > 0)
                                <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $applicantId]) }}">{{ $applicantName !== '' ? $applicantName : '#' . $applicantId }}</a>
                            @else
                                {{ $applicantName !== '' ? $applicantName : __('govuk_alpha.vol_org.applicant_unknown') }}
                            @endif
                        </h3>
                        <dl class="govuk-summary-list govuk-!-margin-bottom-0">
                            @if (!empty($opportunity['title']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.opportunity_label') }}</dt>
                                    <dd class="govuk-summary-list__value">
                                        @if (!empty($opportunity['id']))
                                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ $opportunity['title'] }}</a>
                                        @else
                                            {{ $opportunity['title'] }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            @if (is_array($shift) && !empty($shift['start_time']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.shift_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                                </div>
                            @endif
                            @if (!empty($application['created_at']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.applied_on') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $formatDate($application['created_at']) }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($application['message']))
                            <p class="govuk-body govuk-!-margin-top-3 govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha.vol_org.applicant_message') }}</strong></p>
                            <div class="govuk-inset-text govuk-!-margin-top-0">{{ $application['message'] }}</div>
                        @endif
                        {{-- Single form: approve and decline post to the same route, differing
                             only by the button's name="action" value, so govuk-button-group
                             directly wraps buttons (valid) rather than nesting two forms. --}}
                        <form method="post" action="{{ route('govuk-alpha.volunteering.org.applications.handle', ['tenantSlug' => $tenantSlug, 'id' => $orgId, 'appId' => $appId]) }}">
                            @csrf
                            <p class="govuk-hint govuk-!-margin-top-2 govuk-!-margin-bottom-1">{{ __('govuk_alpha.vol_clarity.org_application_action_hint') }}</p>
                            <div class="govuk-button-group govuk-!-margin-top-0">
                                <button class="govuk-button govuk-!-margin-bottom-0" name="action" value="approve" data-module="govuk-button">{{ __('govuk_alpha.vol_org.approve') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.vol_org.application_for', ['name' => $applicantName]) }}</span></button>
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" name="action" value="decline" data-module="govuk-button">{{ __('govuk_alpha.vol_org.decline') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.vol_org.application_for', ['name' => $applicantName]) }}</span></button>
                            </div>
                        </form>
                    </article>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ===== Section 2: hours awaiting approval ===== --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.vol_org.hours_title') }}</h2>
    <div class="govuk-inset-text">
        <p class="govuk-body">{{ __('govuk_alpha.vol_org.hours_credit_notice') }}</p>
    </div>
    @if (empty($hours))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha.vol_org.hours_empty') }}</p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.org_hours_empty_cta') }}</p>
        </div>
    @else
        <p class="govuk-body">{{ __('govuk_alpha.vol_org.hours_description') }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($hours as $log)
                @php
                    $logId = (int) ($log['id'] ?? 0);
                    $volunteerName = trim((string) ($log['user']['name'] ?? ''));
                    $volunteerId = (int) ($log['user']['id'] ?? 0);
                    $opportunity = $log['opportunity'] ?? null;
                @endphp
                @if ($logId > 0)
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            @if ($volunteerId > 0)
                                <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $volunteerId]) }}">{{ $volunteerName !== '' ? $volunteerName : '#' . $volunteerId }}</a>
                            @else
                                {{ $volunteerName !== '' ? $volunteerName : __('govuk_alpha.vol_org.applicant_unknown') }}
                            @endif
                        </h3>
                        <dl class="govuk-summary-list govuk-!-margin-bottom-0">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.hours_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ number_format((float) ($log['hours'] ?? 0), 1) }}</dd>
                            </div>
                            @if (!empty($log['date']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.date_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $formatDate($log['date']) }}</dd>
                                </div>
                            @endif
                            @if (is_array($opportunity) && !empty($opportunity['title']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_org.opportunity_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $opportunity['title'] }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($log['description']))
                            <p class="govuk-body govuk-!-margin-top-3">{{ $log['description'] }}</p>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.volunteering.org.hours.verify', ['tenantSlug' => $tenantSlug, 'id' => $orgId, 'logId' => $logId]) }}">
                            @csrf
                            <p class="govuk-hint govuk-!-margin-top-2 govuk-!-margin-bottom-1">{{ __('govuk_alpha.vol_clarity.org_hours_action_hint') }}</p>
                            <div class="govuk-button-group govuk-!-margin-top-0">
                                <button class="govuk-button govuk-!-margin-bottom-0" name="action" value="approve" data-module="govuk-button">{{ __('govuk_alpha.vol_org.approve') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.vol_org.hours_for', ['name' => $volunteerName]) }}</span></button>
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" name="action" value="decline" data-module="govuk-button">{{ __('govuk_alpha.vol_org.decline') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.vol_org.hours_for', ['name' => $volunteerName]) }}</span></button>
                            </div>
                        </form>
                    </article>
                @endif
            @endforeach
        </div>
    @endif
@endsection
