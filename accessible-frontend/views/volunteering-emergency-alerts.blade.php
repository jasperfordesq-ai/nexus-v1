{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $alerts = $alerts ?? [];
        $status = $status ?? null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $priorityTag = [
            'normal' => 'govuk-tag--blue',
            'urgent' => 'govuk-tag--orange',
            'critical' => 'govuk-tag--red',
        ];
        $priorityLabel = [
            'normal' => 'govuk_alpha_volunteering.emergency.priority_normal',
            'urgent' => 'govuk_alpha_volunteering.emergency.priority_urgent',
            'critical' => 'govuk_alpha_volunteering.emergency.priority_critical',
        ];
        $successStates = [
            'alert-accepted' => 'govuk_alpha_volunteering.emergency.alert_accepted',
            'alert-declined' => 'govuk_alpha_volunteering.emergency.alert_declined',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if (isset($successStates[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="emergency-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="emergency-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __($successStates[$status]) }}</p></div>
        </div>
    @elseif ($status === 'alert-respond-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha_volunteering.emergency.alert_respond_failed') }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.emergency.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.emergency.description') }}</p>

    @if (empty($alerts))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.emergency.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($alerts as $alert)
                @php
                    $alertId = (int) ($alert['id'] ?? 0);
                    $priority = (string) ($alert['priority'] ?? 'urgent');
                    $myResponse = (string) ($alert['my_response'] ?? 'pending');
                    $opportunity = $alert['opportunity'] ?? [];
                    $organisation = $alert['organization'] ?? [];
                    $coordinator = $alert['coordinator'] ?? [];
                    $shift = $alert['shift'] ?? [];
                    $skills = is_array($alert['required_skills'] ?? null) ? $alert['required_skills'] : [];
                    $pTag = $priorityTag[$priority] ?? 'govuk-tag--grey';
                    $pLabelKey = $priorityLabel[$priority] ?? 'govuk_alpha_volunteering.emergency.priority_urgent';
                @endphp
                @if ($alertId > 0)
                    <article class="nexus-alpha-card">
                        <p class="govuk-!-margin-bottom-2">
                            <strong class="govuk-tag {{ $pTag }}">{{ __($pLabelKey) }}</strong>
                        </p>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title') }}</h2>

                        <dl class="govuk-summary-list govuk-!-margin-bottom-3">
                            @if (!empty($organisation['name']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.organisation_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $organisation['name'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($opportunity['location']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.location_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $opportunity['location'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($shift['start_time']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.shift_label') }}</dt>
                                    <dd class="govuk-summary-list__value">
                                        {{ $formatDateTime($shift['start_time']) }}@if (!empty($shift['end_time'])) &ndash; {{ $formatDateTime($shift['end_time']) }}@endif
                                    </dd>
                                </div>
                            @endif
                            @if (!empty($coordinator['name']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.coordinator_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $coordinator['name'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($skills))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.skills_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ implode(', ', array_map('strip_tags', $skills)) }}</dd>
                                </div>
                            @endif
                            @if (!empty($alert['expires_at']))
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.emergency.expires_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ $formatDateTime($alert['expires_at']) }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if (!empty($alert['message']))
                            <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha_volunteering.emergency.message_label') }}</strong></p>
                            <div class="govuk-inset-text govuk-!-margin-top-0">{{ $alert['message'] }}</div>
                        @endif

                        @if ($myResponse === 'accepted')
                            <p class="govuk-body"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_volunteering.emergency.response_accepted') }}</strong></p>
                        @elseif ($myResponse === 'declined')
                            <p class="govuk-body"><strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_volunteering.emergency.response_declined') }}</strong></p>
                        @else
                            <div class="govuk-warning-text">
                                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                <strong class="govuk-warning-text__text">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}</span>
                                    {{ __('govuk_alpha_volunteering.emergency.accept_warning') }}
                                </strong>
                            </div>
                            <form method="post" action="{{ route('govuk-alpha.volunteering.emergency-alerts.respond', ['tenantSlug' => $tenantSlug, 'id' => $alertId]) }}">
                                @csrf
                                <div class="govuk-button-group">
                                    <button class="govuk-button govuk-!-margin-bottom-0" name="response" value="accepted" data-module="govuk-button">{{ __('govuk_alpha_volunteering.emergency.accept_button') }}</button>
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" name="response" value="declined" data-module="govuk-button">{{ __('govuk_alpha_volunteering.emergency.decline_button') }}</button>
                                </div>
                            </form>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    @endif
@endsection
