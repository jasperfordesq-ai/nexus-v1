{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $seasonDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $cur = is_array($currentSeason ?? null) ? $currentSeason : [];
        $curRow = is_array($cur['season'] ?? null) ? $cur['season'] : [];
        $hasCurrent = !empty($curRow);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_leaderboard') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.seasons.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.seasons.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.seasons.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'leaderboard', 'gamificationActiveTab' => 'seasons'])

    {{-- ===== Current season ===== --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_gamification.seasons.current_heading') }}</h2>
    @if (!$hasCurrent)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.seasons.no_current') }}</p></div>
    @else
        @php
            $seasonName = trim((string) ($curRow['name'] ?? ''));
            $start = $seasonDate($curRow['start_date'] ?? null);
            $end = $seasonDate($curRow['end_date'] ?? null);
            $daysRemaining = (int) ($cur['days_remaining'] ?? 0);
            $endingSoon = (bool) ($cur['is_ending_soon'] ?? false);
            $participants = (int) ($cur['total_participants'] ?? 0);
            $userData = is_array($cur['user_data'] ?? null) ? $cur['user_data'] : [];
            $rewards = is_array($cur['rewards'] ?? null) ? $cur['rewards'] : [];
            $top = is_array($cur['leaderboard'] ?? null) ? $cur['leaderboard'] : [];
        @endphp
        <article class="nexus-alpha-card govuk-!-margin-bottom-6">
            <div class="nexus-alpha-module-row">
                <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $seasonName !== '' ? $seasonName : __('govuk_alpha_gamification.seasons.current_heading') }}</h3>
                @if ($endingSoon)<strong class="govuk-tag govuk-tag--orange">{{ __('govuk_alpha_gamification.seasons.ending_soon') }}</strong>@endif
            </div>
            <p class="govuk-body-s nexus-alpha-meta">
                @if ($start && $end){{ __('govuk_alpha_gamification.seasons.date_range', ['start' => $start, 'end' => $end]) }} · @endif
                {{ trans_choice('govuk_alpha_gamification.seasons.days_remaining', $daysRemaining, ['count' => $daysRemaining]) }}
                · {{ trans_choice('govuk_alpha_gamification.seasons.participants', $participants, ['count' => $participants]) }}
            </p>
            @if (!empty($userData))
                <dl class="govuk-summary-list govuk-!-margin-bottom-2">
                    @if (isset($userData['rank']) && $userData['rank'] !== null)
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.seasons.your_rank', ['rank' => '']) }}</dt>
                            <dd class="govuk-summary-list__value">{{ (int) $userData['rank'] }}</dd>
                        </div>
                    @endif
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.seasons.xp_column') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($userData['xp_earned'] ?? 0)) }}</dd>
                    </div>
                </dl>
            @endif

            {{-- Rewards --}}
            <h4 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_gamification.seasons.rewards_heading') }}</h4>
            @if (empty($rewards))
                <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_gamification.seasons.no_rewards') }}</p>
            @else
                <ul class="govuk-list govuk-list--bullet">
                    @foreach ($rewards as $rank => $reward)
                        <li>{{ __('govuk_alpha_gamification.seasons.reward_row', ['rank' => is_numeric($rank) ? (int) $rank : $rank]) }} — {{ is_array($reward) ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $reward)) : (string) $reward }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- Top members this season --}}
            @if (!empty($top))
                <h4 class="govuk-heading-s govuk-!-margin-top-4 govuk-!-margin-bottom-1">{{ __('govuk_alpha_gamification.seasons.top_members') }}</h4>
                <table class="govuk-table">
                    <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.seasons.top_members') }}</caption>
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.seasons.rank_column') }}</th>
                            <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.seasons.member_column') }}</th>
                            <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.seasons.xp_column') }}</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        @foreach ($top as $i => $m)
                            @php
                                $mName = trim((string) (($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?: trim((string) ($m['name'] ?? '')) ?: __('govuk_alpha_gamification.common.unknown_member');
                                $mXp = (int) ($m['season_xp'] ?? ($m['xp_earned'] ?? ($m['xp'] ?? 0)));
                            @endphp
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell govuk-table__cell--numeric">{{ $i + 1 }}</td>
                                <td class="govuk-table__cell">{{ $mName }}</td>
                                <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format($mXp) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </article>
    @endif

    {{-- ===== Past seasons ===== --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha_gamification.seasons.history_heading') }}</h2>
    @if (empty($allSeasons))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.seasons.no_history') }}</p></div>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.seasons.history_heading') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.seasons.season_name_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.seasons.period_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($allSeasons as $s)
                    @php
                        $sName = trim((string) ($s['name'] ?? '')) ?: __('govuk_alpha_gamification.seasons.season_name_column');
                        $sStart = $seasonDate($s['start_date'] ?? null);
                        $sEnd = $seasonDate($s['end_date'] ?? null);
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $sName }}</td>
                        <td class="govuk-table__cell">@if ($sStart && $sEnd){{ __('govuk_alpha_gamification.seasons.date_range', ['start' => $sStart, 'end' => $sEnd]) }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
