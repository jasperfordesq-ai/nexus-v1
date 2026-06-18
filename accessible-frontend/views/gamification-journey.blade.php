{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $j = is_array($journey ?? null) ? $journey : [];
        $summary = is_array($j['summary'] ?? null) ? $j['summary'] : [];
        $milestones = is_array($j['milestones'] ?? null) ? $j['milestones'] : [];
        $monthly = is_array($j['monthly_activity'] ?? null) ? $j['monthly_activity'] : [];
        $badgeProg = is_array($j['badge_progression'] ?? null) ? $j['badge_progression'] : [];
        $isEmpty = empty($summary) && empty($milestones) && empty($monthly) && empty($badgeProg);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_leaderboard') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.journey.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.journey.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.journey.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'leaderboard', 'gamificationActiveTab' => 'journey'])

    @if ($isEmpty)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.journey.empty') }}</p></div>
    @else
        {{-- Summary --}}
        @if (!empty($summary))
            <h2 class="govuk-heading-l">{{ __('govuk_alpha_gamification.journey.summary_heading') }}</h2>
            <dl class="govuk-summary-list">
                @foreach ($summary as $key => $value)
                    @if (is_scalar($value))
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ ucwords(str_replace('_', ' ', (string) $key)) }}</dt>
                            <dd class="govuk-summary-list__value">{{ is_numeric($value) ? number_format((float) $value, ((float) $value == (int) $value) ? 0 : 1) : (string) $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif

        {{-- Milestones --}}
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_gamification.journey.milestones_heading') }}</h2>
        @if (empty($milestones))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.journey.no_milestones') }}</p></div>
        @else
            <ul class="govuk-list govuk-list--bullet">
                @foreach ($milestones as $m)
                    @php
                        $label = is_array($m)
                            ? trim((string) ($m['label'] ?? ($m['title'] ?? ($m['name'] ?? ($m['description'] ?? '')))))
                            : trim((string) $m);
                    @endphp
                    @if ($label !== '')<li>{{ $label }}</li>@endif
                @endforeach
            </ul>
        @endif

        {{-- Monthly activity --}}
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_gamification.journey.activity_heading') }}</h2>
        @if (empty($monthly))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.journey.no_activity') }}</p></div>
        @else
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.journey.activity_heading') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.journey.month_column') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.journey.activity_column') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($monthly as $row)
                        @php
                            $label = is_array($row) ? trim((string) ($row['month'] ?? ($row['label'] ?? ($row['year_month'] ?? '')))) : '';
                            $count = is_array($row) ? (int) ($row['activity_count'] ?? ($row['count'] ?? ($row['activities'] ?? 0))) : 0;
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $label }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format($count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Badge progression --}}
        @if (!empty($badgeProg))
            <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_gamification.journey.badges_heading') }}</h2>
            <ul class="govuk-list govuk-list--bullet">
                @foreach ($badgeProg as $b)
                    @php
                        $label = is_array($b) ? trim((string) ($b['name'] ?? ($b['label'] ?? ($b['badge_key'] ?? '')))) : trim((string) $b);
                    @endphp
                    @if ($label !== '')<li>{{ $label }}</li>@endif
                @endforeach
            </ul>
        @endif
    @endif
@endsection
