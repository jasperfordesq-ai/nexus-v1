{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $r        = is_array($report) ? $report : [];
        $period   = is_array($r['period'] ?? null) ? $r['period'] : [];
        $funnel   = is_array($r['funnel'] ?? null) ? $r['funnel'] : [];
        $rates    = is_array($r['rejection_rates'] ?? null) ? $r['rejection_rates'] : [];
        $times    = is_array($r['avg_time_in_stage'] ?? null) ? $r['avg_time_in_stage'] : [];
        $skills   = is_array($r['skills_match_correlation'] ?? null) ? $r['skills_match_correlation'] : [];
        $sources  = is_array($r['source_effectiveness'] ?? null) ? $r['source_effectiveness'] : [];
        $velocity = isset($r['hiring_velocity_days']) ? $r['hiring_velocity_days'] : null;
        $total    = (int) ($r['total_applications'] ?? 0);

        $stageLabel = function (string $s): string {
            $key   = 'govuk_alpha_jobs.stage.' . $s;
            $label = __($key);
            return $label === $key ? ucfirst(str_replace('_', ' ', $s)) : $label;
        };

        $noData = $report === null || $total === 0;

        $periodFrom = $period['from'] ?? null;
        $periodTo   = $period['to'] ?? null;
    @endphp

    <a href="{{ url('/' . $tenantSlug . '/alpha/admin') }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.bias_audit.back_to_admin') }}</a>

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.bias_audit.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.bias_audit.description') }}</p>

    {{-- Date-range + job filter --}}
    <form method="get" class="govuk-!-margin-bottom-6">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="from">{{ __('govuk_alpha_jobs.bias_audit.filter_from') }}</label>
                    <input class="govuk-input" type="date" id="from" name="from" value="{{ $filterFrom ?? '' }}">
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="to">{{ __('govuk_alpha_jobs.bias_audit.filter_to') }}</label>
                    <input class="govuk-input" type="date" id="to" name="to" value="{{ $filterTo ?? '' }}">
                </div>
            </div>
            @if (!empty($jobs))
                <div class="govuk-grid-column-one-quarter">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="job_id">{{ __('govuk_alpha_jobs.bias_audit.filter_job') }}</label>
                        <select class="govuk-select" id="job_id" name="job_id">
                            <option value="">{{ __('govuk_alpha_jobs.bias_audit.filter_all_jobs') }}</option>
                            @foreach ($jobs as $jId => $jTitle)
                                <option value="{{ $jId }}" @selected((int) ($filterJob ?? 0) === (int) $jId)>{{ $jTitle }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
            <div class="govuk-grid-column-one-quarter govuk-!-padding-top-6">
                <button type="submit" class="govuk-button govuk-!-margin-top-1">{{ __('govuk_alpha_jobs.bias_audit.apply_filter') }}</button>
            </div>
        </div>
    </form>

    @if ($periodFrom && $periodTo)
        <p class="govuk-body govuk-!-margin-bottom-6">
            {{ __('govuk_alpha_jobs.bias_audit.period_label', ['from' => $periodFrom, 'to' => $periodTo]) }}
        </p>
    @endif

    @if ($report === null)
        <div class="govuk-inset-text">
            {{ __('govuk_alpha_jobs.bias_audit.error_loading') }}
        </div>
    @elseif ($noData)
        <div class="govuk-inset-text">
            {{ __('govuk_alpha_jobs.bias_audit.no_data') }}
        </div>
    @else

        {{-- Summary metric --}}
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.bias_audit.total_applications') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format($total) }}</dd>
            </div>
            @if ($velocity !== null)
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_jobs.bias_audit.hiring_velocity') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $velocity }} {{ __('govuk_alpha_jobs.bias_audit.days') }}</dd>
                </div>
            @endif
        </dl>

        {{-- 1. Application funnel --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.bias_audit.funnel_heading') }}</h2>
        @if (empty($funnel))
            <div class="govuk-inset-text">{{ __('govuk_alpha_jobs.bias_audit.no_funnel_data') }}</div>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-table__caption--s">{{ __('govuk_alpha_jobs.bias_audit.funnel_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.bias_audit.col_stage') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_count') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_pct_of_total') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($funnel as $stage => $count)
                        @php
                            $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $stageLabel($stage) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) $count) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ $pct }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- 2. Rejection rates by stage --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.bias_audit.rejection_rates_heading') }}</h2>
        @if (empty($rates))
            <div class="govuk-inset-text">{{ __('govuk_alpha_jobs.bias_audit.no_rejection_data') }}</div>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-table__caption--s">{{ __('govuk_alpha_jobs.bias_audit.rejection_rates_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.bias_audit.col_stage') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_entered') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_rejected') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_rejection_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($rates as $stage => $data)
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $stageLabel($stage) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($data['total'] ?? 0)) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($data['rejected'] ?? 0)) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ (float) ($data['rate'] ?? 0) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- 3. Average time in stage --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.bias_audit.time_in_stage_heading') }}</h2>
        @if (empty($times))
            <div class="govuk-inset-text">{{ __('govuk_alpha_jobs.bias_audit.no_time_data') }}</div>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-table__caption--s">{{ __('govuk_alpha_jobs.bias_audit.time_in_stage_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.bias_audit.col_stage') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_avg_days') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($times as $stage => $avgDays)
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $stageLabel($stage) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ (float) $avgDays }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- 4. Skills match correlation --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.bias_audit.skills_match_heading') }}</h2>
        @if (empty($skills))
            <div class="govuk-inset-text">{{ __('govuk_alpha_jobs.bias_audit.no_skills_data') }}</div>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-table__caption--s">{{ __('govuk_alpha_jobs.bias_audit.skills_match_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.bias_audit.col_outcome') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_count') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_proportion') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ __('govuk_alpha_jobs.stage.accepted') }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($skills['accepted_count'] ?? 0)) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ round((float) ($skills['accepted_avg'] ?? 0) * 100, 1) }}%</td>
                    </tr>
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ __('govuk_alpha_jobs.stage.rejected') }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($skills['rejected_count'] ?? 0)) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ round((float) ($skills['rejected_avg'] ?? 0) * 100, 1) }}%</td>
                    </tr>
                </tbody>
            </table>
        @endif

        {{-- 5. Source effectiveness --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.bias_audit.source_effectiveness_heading') }}</h2>
        @if (empty($sources))
            <div class="govuk-inset-text">{{ __('govuk_alpha_jobs.bias_audit.no_source_data') }}</div>
        @else
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-table__caption--s">{{ __('govuk_alpha_jobs.bias_audit.source_effectiveness_caption') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_jobs.bias_audit.col_source') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_applications') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_accepted') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_jobs.bias_audit.col_acceptance_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($sources as $source => $data)
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ __('govuk_alpha_jobs.bias_audit.source_' . $source, [], null) ?: ucfirst($source) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($data['applications'] ?? 0)) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((int) ($data['accepted'] ?? 0)) }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ (float) ($data['rate'] ?? 0) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @endif {{-- /noData --}}

@endsection
