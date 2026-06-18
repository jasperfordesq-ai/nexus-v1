{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_leaderboard') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.competitive.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.competitive.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.competitive.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'leaderboard', 'gamificationActiveTab' => 'competitive'])

    {{-- ===== Active-season banner ===== --}}
    @php
        $season = is_array($compSeason ?? null) ? $compSeason : [];
        $seasonRow = is_array($season['season'] ?? null) ? $season['season'] : [];
        $hasSeason = !empty($seasonRow);
    @endphp
    @if ($hasSeason)
        @php
            $seasonName = trim((string) ($seasonRow['name'] ?? ''));
            $daysRemaining = (int) ($season['days_remaining'] ?? 0);
            $participants = (int) ($season['total_participants'] ?? 0);
            $userData = is_array($season['user_data'] ?? null) ? $season['user_data'] : [];
        @endphp
        <div class="nexus-alpha-card govuk-!-margin-bottom-6">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ __('govuk_alpha_gamification.competitive.season_card_title') }}@if ($seasonName !== ''): {{ $seasonName }}@endif</h2>
            <p class="govuk-body-s nexus-alpha-meta">
                {{ trans_choice('govuk_alpha_gamification.competitive.season_days_remaining', $daysRemaining, ['count' => $daysRemaining]) }}
                · {{ trans_choice('govuk_alpha_gamification.competitive.season_participants', $participants, ['count' => $participants]) }}
            </p>
            @if (!empty($userData))
                <p class="govuk-body">{{ __('govuk_alpha_gamification.seasons.your_xp', ['xp' => number_format((int) ($userData['xp_earned'] ?? 0))]) }}</p>
            @endif
            <a class="govuk-link" href="{{ route('govuk-alpha.gamification.seasons', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.competitive.season_view_all') }}</a>
        </div>
    @endif

    {{-- ===== Metric + period filter ===== --}}
    <form method="get" action="{{ route('govuk-alpha.gamification.competitive', ['tenantSlug' => $tenantSlug]) }}" data-alpha-auto-submit class="govuk-!-margin-bottom-6">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_gamification.competitive.filter_heading') }}</h2>
            </legend>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="type">{{ __('govuk_alpha_gamification.competitive.metric_label') }}</label>
                        <select class="govuk-select" id="type" name="type">
                            @foreach ($compTypes as $t)
                                <option value="{{ $t }}" @selected($t === $compType)>{{ __('govuk_alpha_gamification.competitive.metrics.' . $t) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="period">{{ __('govuk_alpha_gamification.competitive.period_label') }}</label>
                        <select class="govuk-select" id="period" name="period">
                            @foreach ($compPeriods as $pr)
                                <option value="{{ $pr }}" @selected($pr === $compPeriod)>{{ __('govuk_alpha_gamification.competitive.periods.' . $pr) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_gamification.competitive.apply') }}</button>
        </fieldset>
    </form>

    {{-- ===== Your rank banner ===== --}}
    <div class="govuk-inset-text">
        @if (!empty($compYourRank))
            {{ __('govuk_alpha_gamification.competitive.your_rank', ['rank' => (int) $compYourRank]) }}
        @else
            {{ __('govuk_alpha_gamification.competitive.your_rank_none') }}
        @endif
    </div>

    @if (empty($compRows))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.competitive.empty') }}</p></div>
    @else
        @php $compCount = count($compRows); @endphp
        <p class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha_gamification.competitive.showing_count', $compCount, ['count' => $compCount]) }}</p>
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.competitive.metrics.' . $compType) }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.competitive.rank_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.competitive.member_column') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.competitive.score_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($compRows as $row)
                    @php
                        $rowName = trim((string) ($row['name'] ?? '')) ?: __('govuk_alpha_gamification.common.unknown_member');
                        $isMe = (bool) ($row['is_current_user'] ?? false);
                        $rowUserId = (int) ($row['user_id'] ?? 0);
                    @endphp
                    <tr class="govuk-table__row @if ($isMe) nexus-alpha-row--active @endif">
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ (int) ($row['rank'] ?? 0) }}</td>
                        <td class="govuk-table__cell">
                            @if ($rowUserId > 0 && \App\Core\TenantContext::hasFeature('connections'))<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $rowUserId]) }}">{{ $rowName }}</a>@else{{ $rowName }}@endif
                            @if ($isMe)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_gamification.common.you') }}</strong>@endif
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $row['score_display'] ?? $row['score'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Load-more: a plain GET link that grows the visible window (no JS). --}}
        @if (!empty($compHasMore))
            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" href="{{ route('govuk-alpha.gamification.competitive', ['tenantSlug' => $tenantSlug, 'type' => $compType, 'period' => $compPeriod, 'limit' => (int) ($compNextLimit ?? 0)]) }}#leaderboard-end">{{ __('govuk_alpha_gamification.competitive.load_more') }}</a>
        @endif
        <span id="leaderboard-end" tabindex="-1"></span>
    @endif
@endsection
