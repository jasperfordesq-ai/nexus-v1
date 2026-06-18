{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $a = $assessment ?? [];
        $jobTitle = trim((string) ($a['job_title'] ?? '')) ?: __('govuk_alpha.jobs.title');
        $pct = (int) ($a['percentage'] ?? 0);
        $level = (string) ($a['level'] ?? 'low');
        $levelKey = 'govuk_alpha_jobs.qualification.level_' . $level;
        $levelLabel = __($levelKey);
        if ($levelLabel === $levelKey) { $levelLabel = ucfirst($level); }
        $levelTag = match ($level) {
            'excellent' => 'govuk-tag--green',
            'good' => 'govuk-tag--turquoise',
            'moderate' => 'govuk-tag--yellow',
            default => 'govuk-tag--grey',
        };
        $breakdown = is_array($a['breakdown'] ?? null) ? $a['breakdown'] : [];
        $dimensions = is_array($a['dimensions'] ?? null) ? $a['dimensions'] : [];
        $summary = trim((string) ($a['ai_summary'] ?? ''));
        $jid = (int) $jobId;
    @endphp

    <a href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jid]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.shared.back_to_opportunity') }}</a>

    <span class="govuk-caption-xl">{{ $jobTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.qualification.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.qualification.description') }}</p>

    {{-- Overall match --}}
    <div class="nexus-alpha-card govuk-!-margin-bottom-6">
        <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha_jobs.qualification.overall_match') }}</h2>
        <p class="govuk-heading-l govuk-!-margin-bottom-1">{{ $pct }}%
            <strong class="govuk-tag {{ $levelTag }} govuk-!-margin-left-2">{{ $levelLabel }}</strong>
        </p>
        <progress value="{{ $pct }}" max="100" aria-label="{{ __('govuk_alpha_jobs.qualification.overall_match') }}: {{ $pct }}%">{{ $pct }}%</progress>
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-top-1 govuk-!-margin-bottom-0">
            {{ __('govuk_alpha_jobs.qualification.matched_count', ['matched' => (int) ($a['total_matched'] ?? 0), 'total' => (int) ($a['total_required'] ?? 0)]) }}
        </p>
    </div>

    {{-- Required skills --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.qualification.skills_heading') }}</h2>
    @if (empty($breakdown))
        <p class="govuk-body">{{ __('govuk_alpha_jobs.qualification.no_skills') }}</p>
    @else
        <ul class="govuk-list govuk-!-margin-bottom-6">
            @foreach ($breakdown as $skill)
                @php
                    $sName = trim((string) ($skill['skill'] ?? ''));
                    $sMatched = (bool) ($skill['matched'] ?? false);
                @endphp
                @if ($sName !== '')
                    <li>
                        <strong class="govuk-tag {{ $sMatched ? 'govuk-tag--green' : 'govuk-tag--grey' }}">
                            {{ $sMatched ? __('govuk_alpha_jobs.qualification.skill_matched') : __('govuk_alpha_jobs.qualification.skill_missing') }}
                        </strong>
                        <span class="govuk-!-margin-left-1">{{ $sName }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    {{-- Dimensions --}}
    @if (!empty($dimensions))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.qualification.dimensions_heading') }}</h2>
        <table class="govuk-table govuk-!-margin-bottom-6">
            <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_jobs.qualification.dimensions_heading') }}</caption>
            <tbody class="govuk-table__body">
                @foreach ($dimensions as $dim)
                    @php
                        $dLabel = trim((string) ($dim['label'] ?? ''));
                        $dScore = (int) ($dim['score'] ?? 0);
                        $dDetail = trim((string) ($dim['detail'] ?? ''));
                    @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">
                            {{ $dLabel }}
                            @if ($dDetail !== '')<br><span class="govuk-body-s nexus-alpha-meta">{{ $dDetail }}</span>@endif
                        </th>
                        <td class="govuk-table__cell">
                            <progress value="{{ $dScore }}" max="100" aria-label="{{ __('govuk_alpha_jobs.qualification.score_aria', ['dimension' => $dLabel]) }}">{{ $dScore }}%</progress>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $dScore }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Summary --}}
    @if ($summary !== '')
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.qualification.summary_heading') }}</h2>
        <div class="govuk-inset-text"><p class="govuk-body govuk-!-margin-bottom-0">{{ $summary }}</p></div>
    @endif

    <ul class="govuk-list nexus-alpha-actions govuk-!-margin-top-4">
        <li><a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jid]) }}">{{ __('govuk_alpha_jobs.qualification.view_opportunity') }}</a></li>
    </ul>
@endsection
