{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $jobId = (int) ($job['id'] ?? 0);
        $jobTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
        $a = $analytics ?? [];
        $p = $predictions ?? null;

        $stageLabel = function (string $s): string {
            $key = 'govuk_alpha_jobs.stage.' . $s;
            $label = __($key);
            return $label === $key ? ucfirst(str_replace('_', ' ', $s)) : $label;
        };

        $viewsByDay = is_array($a['views_by_day'] ?? null) ? $a['views_by_day'] : [];
        $weeklyTrend = is_array($a['weekly_trend'] ?? null) ? $a['weekly_trend'] : [];
        $byStage = is_array($a['applications_by_stage'] ?? null) ? $a['applications_by_stage'] : [];
        $totalApps = (int) ($a['total_applications'] ?? 0);
        $referral = is_array($a['referral_stats'] ?? null) ? $a['referral_stats'] : null;
        $scorecard = $a['scorecard_avg'] ?? null;

        $maxViews = 1;
        foreach ($viewsByDay as $d) { $maxViews = max($maxViews, (int) ($d['count'] ?? 0)); }
        $maxWeekly = 1;
        foreach ($weeklyTrend as $w) { $maxWeekly = max($maxWeekly, (int) ($w['count'] ?? 0)); }
    @endphp

    <a href="{{ route('govuk-alpha.jobs.applicants', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.shared.back_to_my_postings') }}</a>

    <span class="govuk-caption-xl">{{ $jobTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.analytics.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.analytics.description') }}</p>

    <ul class="govuk-list nexus-alpha-actions govuk-!-margin-bottom-6">
        <li><a class="govuk-link" href="{{ route('govuk-alpha.jobs.pipeline', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha_jobs.analytics.view_pipeline') }}</a></li>
        <li><a class="govuk-link" href="{{ route('govuk-alpha.jobs.applicants.export', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha_jobs.analytics.export_csv') }}</a></li>
        <li><a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha_jobs.shared.view_opportunity') }}</a></li>
    </ul>

    {{-- Key metrics --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.key_metrics_heading') }}</h2>
    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.total_views') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($a['total_views'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.unique_viewers') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($a['unique_viewers'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.total_applications') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format($totalApps) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.conversion_rate') }}</dt>
            <dd class="govuk-summary-list__value">{{ (float) ($a['conversion_rate'] ?? 0) }}%</dd>
        </div>
        @if (($a['avg_time_to_apply_hours'] ?? null) !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.avg_time_to_apply') }}</dt>
                <dd class="govuk-summary-list__value">{{ (float) $a['avg_time_to_apply_hours'] }} {{ __('govuk_alpha_jobs.analytics.hours') }}</dd>
            </div>
        @endif
        @if (($a['time_to_fill_days'] ?? null) !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.time_to_fill') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) $a['time_to_fill_days'] }} {{ __('govuk_alpha_jobs.analytics.days') }}</dd>
            </div>
        @endif
    </dl>

    {{-- Referrals + scorecard --}}
    @if ($referral !== null || $scorecard !== null)
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.referral_heading') }}</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            @if ($referral !== null)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.referral_shares') }}</dt>
                    <dd class="govuk-summary-list__value">{{ number_format((int) ($referral['total_shares'] ?? 0)) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.referral_apps') }}</dt>
                    <dd class="govuk-summary-list__value">{{ number_format((int) ($referral['referral_applications'] ?? 0)) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.referral_conversion') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (float) ($referral['referral_conversion_pct'] ?? 0) }}%</dd>
                </div>
            @endif
            @if ($scorecard !== null)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.scorecard_avg') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (float) $scorecard }}%</dd>
                </div>
            @endif
        </dl>
    @endif

    {{-- Views over time --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.views_over_time') }}</h2>
    @if (empty($viewsByDay))
        <p class="govuk-body">{{ __('govuk_alpha_jobs.analytics.no_views_yet') }}</p>
    @else
        <table class="govuk-table govuk-!-margin-bottom-6">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_jobs.analytics.views_over_time') }}</caption>
            <tbody class="govuk-table__body">
                @foreach ($viewsByDay as $d)
                    @php
                        $dCount = (int) ($d['count'] ?? 0);
                        $dDate = !empty($d['date']) ? \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('j M') : '';
                    @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $dDate }}</th>
                        <td class="govuk-table__cell">
                            <progress value="{{ $dCount }}" max="{{ $maxViews }}" aria-label="{{ __('govuk_alpha_jobs.analytics.views_on_date', ['date' => $dDate]) }}">{{ $dCount }}</progress>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $dCount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Weekly application trend --}}
    @if (!empty($weeklyTrend))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.weekly_trend') }}</h2>
        <table class="govuk-table govuk-!-margin-bottom-6">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_jobs.analytics.weekly_trend') }}</caption>
            <tbody class="govuk-table__body">
                @foreach ($weeklyTrend as $w)
                    @php $wCount = (int) ($w['count'] ?? 0); $wWeek = (string) ($w['week'] ?? ''); @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $wWeek }}</th>
                        <td class="govuk-table__cell">
                            <progress value="{{ $wCount }}" max="{{ $maxWeekly }}" aria-label="{{ __('govuk_alpha_jobs.analytics.applications_in_week', ['week' => $wWeek]) }}">{{ $wCount }}</progress>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $wCount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Applications by stage --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.applications_by_stage') }}</h2>
    @if (empty($byStage))
        <p class="govuk-body">{{ __('govuk_alpha_jobs.analytics.no_applications_yet') }}</p>
    @else
        <table class="govuk-table govuk-!-margin-bottom-6">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_jobs.analytics.applications_by_stage') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.analytics.applications_by_stage') }}</th>
                    <th scope="col" class="govuk-table__header"></th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.analytics.total_applications') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($byStage as $row)
                    @php
                        $sStage = (string) ($row['stage'] ?? 'applied');
                        $sCount = (int) ($row['count'] ?? 0);
                        $sPct = $totalApps > 0 ? (int) round(($sCount / $totalApps) * 100) : 0;
                    @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $stageLabel($sStage) }}</th>
                        <td class="govuk-table__cell">
                            <progress value="{{ $sCount }}" max="{{ max(1, $totalApps) }}" aria-label="{{ $stageLabel($sStage) }}: {{ $sPct }}%">{{ $sPct }}%</progress>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $sCount }} ({{ $sPct }}%)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Predictions --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.analytics.predictions_heading') }}</h2>
    @if ($p === null || (int) ($p['similar_jobs_analyzed'] ?? 0) === 0)
        <p class="govuk-body">{{ __('govuk_alpha_jobs.analytics.no_predictions') }}</p>
    @else
        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_jobs.analytics.predictions_based_on', ['count' => (int) ($p['similar_jobs_analyzed'] ?? 0)]) }}</p>
        @php
            $ea = $p['expected_applications'] ?? [];
            $ttf = $p['estimated_time_to_fill'] ?? [];
            $cr = $p['conversion_rate'] ?? [];
            $sc = $p['salary_comparison'] ?? null;
        @endphp
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.expected_apps') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ (int) ($ea['value'] ?? 0) }}
                    <span class="govuk-!-margin-left-2">
                        <strong class="govuk-tag {{ ($ea['above_average'] ?? false) ? 'govuk-tag--green' : 'govuk-tag--yellow' }}">
                            {{ ($ea['above_average'] ?? false) ? __('govuk_alpha_jobs.analytics.above_average') : __('govuk_alpha_jobs.analytics.below_average') }}
                        </strong>
                    </span>
                    <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_jobs.analytics.current_label') }}: {{ (int) ($ea['current'] ?? 0) }}</span>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.estimated_time_to_fill') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if (($ttf['value'] ?? null) !== null)
                        {{ __('govuk_alpha_jobs.analytics.days_value', ['count' => (int) $ttf['value']]) }}
                    @else
                        {{ __('govuk_alpha_jobs.analytics.not_available') }}
                    @endif
                    <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_jobs.analytics.posted_days_ago', ['days' => (int) ($ttf['days_posted'] ?? 0)]) }}</span>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.conversion_comparison') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ __('govuk_alpha_jobs.analytics.yours') }}: {{ (float) ($cr['yours'] ?? 0) }}% &middot; {{ __('govuk_alpha_jobs.analytics.market_average') }}: {{ (float) ($cr['average'] ?? 0) }}%
                    <span class="govuk-!-margin-left-2">
                        <strong class="govuk-tag {{ ($cr['above_average'] ?? false) ? 'govuk-tag--green' : 'govuk-tag--yellow' }}">
                            {{ ($cr['above_average'] ?? false) ? __('govuk_alpha_jobs.analytics.above_average') : __('govuk_alpha_jobs.analytics.below_average') }}
                        </strong>
                    </span>
                </dd>
            </div>
            @if ($sc !== null)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.analytics.salary_comparison') }}</dt>
                    <dd class="govuk-summary-list__value">
                        {{ __('govuk_alpha_jobs.analytics.yours') }}: {{ number_format((float) ($sc['your_salary'] ?? 0)) }} &middot; {{ __('govuk_alpha_jobs.analytics.market_average') }}: {{ number_format((float) ($sc['market_avg'] ?? 0)) }}
                        @php $diff = (int) ($sc['diff_percent'] ?? 0); @endphp
                        <span class="govuk-!-margin-left-2">
                            <strong class="govuk-tag {{ $diff >= 0 ? 'govuk-tag--green' : 'govuk-tag--red' }}">
                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}% {{ $diff > 0 ? __('govuk_alpha_jobs.analytics.salary_above') : ($diff < 0 ? __('govuk_alpha_jobs.analytics.salary_below') : __('govuk_alpha_jobs.analytics.salary_at')) }}
                            </strong>
                        </span>
                    </dd>
                </div>
            @endif
        </dl>
    @endif
@endsection
