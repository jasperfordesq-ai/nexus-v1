{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $p = is_array($gamProfile ?? null) ? $gamProfile : [];
        $level = (int) ($p['level'] ?? 0);
        $levelName = trim((string) ($p['level_name'] ?? ''));
        $xp = (int) ($p['xp'] ?? 0);
        $badgesCount = (int) ($p['badges_count'] ?? count($earnedBadges ?? []));
        $lp = is_array($p['level_progress'] ?? null) ? $p['level_progress'] : [];
        $levelPct = max(0, min(100, (float) ($lp['progress_percentage'] ?? 0)));
        $atMaxLevel = array_key_exists('xp_for_next_level', $lp) && $lp['xp_for_next_level'] === null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.achievements.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.achievements.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.achievements.description') }}</p>

    <dl class="govuk-summary-list govuk-!-margin-bottom-4">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.achievements.level_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $level }}@if ($levelName !== '') — {{ $levelName }}@endif</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.achievements.xp_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format($xp) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.achievements.badges_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format($badgesCount) }}</dd>
        </div>
    </dl>

    @if ($atMaxLevel)
        <p class="govuk-body">{{ __('govuk_alpha.achievements.max_level') }}</p>
    @else
        <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha.achievements.progress_to_next', ['percent' => (int) round($levelPct)]) }}</p>
        <progress max="100" value="{{ (int) round($levelPct) }}" aria-label="{{ (int) round($levelPct) }}%">{{ (int) round($levelPct) }}%</progress>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.achievements.earned_title') }}</h2>
    @if (empty($earnedBadges))
        <p class="govuk-inset-text">{{ __('govuk_alpha.achievements.earned_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($earnedBadges as $b)
                @php
                    $bName = trim((string) ($b['name'] ?? ''));
                    $bIcon = trim((string) ($b['icon'] ?? ''));
                    $bMsg = trim((string) ($b['msg'] ?? ($b['description'] ?? '')));
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">@if ($bIcon !== ''){{ $bIcon }} @endif{{ $bName }}</h3>
                    @if ($bMsg !== '')
                        <p class="govuk-body-s govuk-!-margin-bottom-0">{{ $bMsg }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if (!empty($badgeProgress))
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.achievements.progress_title') }}</h2>
        @foreach ($badgeProgress as $bp)
            @php
                $badge = is_array($bp['badge'] ?? null) ? $bp['badge'] : [];
                $bpName = trim((string) ($badge['name'] ?? ''));
                $bpIcon = trim((string) ($badge['icon'] ?? ''));
                $bpPct = max(0, min(100, (int) round((float) ($bp['percent'] ?? 0))));
                $bpRemaining = (int) ($bp['remaining'] ?? 0);
            @endphp
            <div class="govuk-!-margin-bottom-3">
                <p class="govuk-body govuk-!-margin-bottom-1">@if ($bpIcon !== ''){{ $bpIcon }} @endif{{ $bpName }} — {{ __('govuk_alpha.achievements.progress_remaining', ['remaining' => $bpRemaining]) }}</p>
                <progress max="100" value="{{ $bpPct }}" aria-label="{{ $bpPct }}%">{{ $bpPct }}%</progress>
            </div>
        @endforeach
    @endif
@endsection
