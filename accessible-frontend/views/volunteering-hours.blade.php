{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $summary = $summary ?? [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'hours-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="hours-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="hours-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.volunteering.hours_created') }}</p>
            </div>
        </div>
    @elseif ($status === 'hours-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.volunteering.hours_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.volunteering.hours_title') }}</h1>

    {{-- Make the credit flow explicit: logged hours are reviewed by the
         organisation, and approval automatically credits the wallet. --}}
    <div class="govuk-inset-text" role="note">
        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.hours_autocredit_note') }}</p>
    </div>

    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.approved_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['total_approved_hours'] ?? $summary['approved_hours'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.pending_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['pending_hours'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.this_month_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['this_month_hours'] ?? 0), 1) }}</dd>
        </div>
    </dl>

    @php
        // Progress toward the next round-number goal (mirrors the React hours view,
        // computed in-view since getHoursSummary returns no target).
        $approvedTotal = (float) ($summary['total_approved_hours'] ?? $summary['approved_hours'] ?? 0);
        $hoursGoal = $approvedTotal > 0 ? (int) (ceil($approvedTotal / 50) * 50) : 0;
    @endphp
    @if ($hoursGoal > 0)
        <div class="govuk-!-margin-bottom-6">
            <label class="govuk-visually-hidden" for="hours-goal-progress">{{ __('govuk_alpha.volunteering.hours_of_goal', ['hours' => number_format($approvedTotal, 1), 'goal' => $hoursGoal]) }}</label>
            <progress id="hours-goal-progress" max="{{ $hoursGoal }}" value="{{ $approvedTotal }}">{{ number_format($approvedTotal, 1) }} / {{ $hoursGoal }}</progress>
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-top-1">{{ __('govuk_alpha.volunteering.hours_of_goal', ['hours' => number_format($approvedTotal, 1), 'goal' => $hoursGoal]) }}</p>
        </div>
    @endif

    @php
        $summaryByOrg = $summary['by_organization'] ?? [];
        $summaryByMonth = $summary['by_month'] ?? [];
    @endphp
    @if (!empty($summaryByOrg))
        <h2 class="govuk-heading-m govuk-!-margin-top-7">{{ __('govuk_alpha.volunteering.hours_by_org_title') }}</h2>
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.volunteering.hours_by_org_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.volunteering.organization') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.volunteering.approved_hours') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($summaryByOrg as $orgRow)
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $orgRow['name'] ?? '' }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((float) ($orgRow['hours'] ?? 0), 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if (!empty($summaryByMonth))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.volunteering.hours_by_month_title') }}</h2>
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.volunteering.hours_by_month_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.volunteering.month_label') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.volunteering.approved_hours') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($summaryByMonth as $monthRow)
                    @php
                        $monthLabel = (string) ($monthRow['month'] ?? '');
                        try {
                            $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $monthRow['month'])->translatedFormat('F Y');
                        } catch (\Throwable) {
                            // Fall back to the raw 'YYYY-MM' string on any parse issue.
                        }
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $monthLabel }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((float) ($monthRow['hours'] ?? 0), 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <form method="post" action="{{ route('govuk-alpha.volunteering.hours.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-7">
        @csrf
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.volunteering.log_hours_title') }}</h2>
            </legend>
            <div class="govuk-hint">{{ __('govuk_alpha.vol_clarity.log_hours_form_hint') }}</div>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="organization_id">{{ __('govuk_alpha.volunteering.organization_label') }}</label>
                        <div id="organization-id-hint" class="govuk-hint">{{ __('govuk_alpha.vol_clarity.log_hours_organisation_hint') }}</div>
                        <select class="govuk-select" id="organization_id" name="organization_id" aria-describedby="organization-id-hint" required>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization['id'] }}">{{ $organization['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="opportunity_id">{{ __('govuk_alpha.volunteering.opportunity_label') }}</label>
                        <select class="govuk-select" id="opportunity_id" name="opportunity_id">
                            <option value="">{{ __('govuk_alpha.volunteering.no_related_opportunity') }}</option>
                            @foreach ($applications as $application)
                                @continue(empty($application['opportunity']['id']))
                                <option value="{{ $application['opportunity']['id'] }}">{{ $application['opportunity']['title'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="date">{{ __('govuk_alpha.volunteering.date_label') }}</label>
                        <div id="date-hint" class="govuk-hint">{{ __('govuk_alpha.vol_clarity.log_hours_date_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="date" name="date" type="date" value="{{ now()->toDateString() }}" aria-describedby="date-hint" required>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="hours">{{ __('govuk_alpha.volunteering.hours_label') }}</label>
                        <div id="log-hours-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.hours_log_hint') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="hours" name="hours" type="number" min="0.25" max="24" step="0.25" aria-describedby="log-hours-hint" required>
                    </div>
                </div>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">{{ __('govuk_alpha.volunteering.description_label') }}</label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.vol_clarity.log_hours_description_hint') }}</div>
                <textarea class="govuk-textarea" id="description" name="description" rows="5" aria-describedby="description-hint"></textarea>
            </div>
        </fieldset>
        <button class="govuk-button" data-module="govuk-button" @disabled(empty($organizations))>{{ __('govuk_alpha.actions.log_hours') }}</button>
    </form>

    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.volunteering.recent_hours_title') }}</h2>
    @if (empty($logs))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha.volunteering.empty_hours') }}</p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.empty_hours_cta') }}</p>
        </div>
    @else
        {{-- Status trail: each logged hour is "Submitted" (pending review) → "Approved".
             Approved hours are credited automatically, 1 time credit per hour. --}}
        <p class="govuk-body">{{ __('govuk_alpha.vol_clarity.status_trail_intro') }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($logs as $log)
                @php
                    $logStatus = (string) ($log['status'] ?? 'pending');
                    // Map the raw vol_logs status to a clear label + GOV.UK tag colour.
                    // 'pending' is shown as "Submitted" so the volunteer understands it
                    // is awaiting the organisation's approval.
                    $statusTag = [
                        'pending'   => ['label' => __('govuk_alpha.vol_clarity.status_submitted'), 'class' => 'govuk-tag--yellow'],
                        'approved'  => ['label' => __('govuk_alpha.volunteering.status_values.approved'), 'class' => 'govuk-tag--green'],
                        'declined'  => ['label' => __('govuk_alpha.volunteering.status_values.declined'), 'class' => 'govuk-tag--red'],
                        'rejected'  => ['label' => __('govuk_alpha.volunteering.status_values.declined'), 'class' => 'govuk-tag--red'],
                    ][$logStatus] ?? [
                        'label' => \Illuminate\Support\Facades\Lang::has('govuk_alpha.volunteering.status_values.' . $logStatus)
                            ? __('govuk_alpha.volunteering.status_values.' . $logStatus)
                            : \Illuminate\Support\Str::headline($logStatus),
                        'class' => 'govuk-tag--grey',
                    ];
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $formatDate($log['date'] ?? $log['logged_at'] ?? $log['created_at'] ?? null) }}</h3>
                    <p class="govuk-!-margin-bottom-2">
                        <strong class="govuk-tag {{ $statusTag['class'] }}">{{ $statusTag['label'] }}</strong>
                    </p>
                    @if ($logStatus === 'approved')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.vol_clarity.status_approved_credited') }}</p>
                    @elseif ($logStatus === 'pending')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.vol_clarity.status_pending_note') }}</p>
                    @endif
                    <dl class="nexus-alpha-inline-list">
                        <div>
                            <dt>{{ __('govuk_alpha.volunteering.hours_label') }}</dt>
                            <dd>{{ number_format((float) ($log['hours'] ?? 0), 1) }}</dd>
                        </div>
                        @if (!empty($log['organization']['name']))
                            <div>
                                <dt>{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                <dd>{{ $log['organization']['name'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if (!empty($log['description']))
                        <p class="govuk-body govuk-!-margin-top-3">{{ $log['description'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
