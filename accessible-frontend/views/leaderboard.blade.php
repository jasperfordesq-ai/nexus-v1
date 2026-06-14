{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.leaderboard.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.leaderboard.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.leaderboard.description') }}</p>

    {{-- Server-rendered filter; the selects auto-submit when JS is on, and the
         Update button is the no-JS fallback. --}}
    <form method="get" action="{{ route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]) }}" data-alpha-auto-submit class="govuk-!-margin-bottom-6">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="type">{{ __('govuk_alpha.leaderboard.metric_label') }}</label>
                    <select class="govuk-select" id="type" name="type">
                        @foreach ($leaderboardTypes as $t)
                            <option value="{{ $t }}" @selected($t === $leaderboardType)>{{ __('govuk_alpha.leaderboard.metrics.' . $t) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="period">{{ __('govuk_alpha.leaderboard.period_label') }}</label>
                    <select class="govuk-select" id="period" name="period">
                        @foreach ($leaderboardPeriods as $pr)
                            <option value="{{ $pr }}" @selected($pr === $leaderboardPeriod)>{{ __('govuk_alpha.leaderboard.periods.' . $pr) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.leaderboard.apply') }}</button>
    </form>

    @if (empty($leaderboardRows))
        <p class="govuk-inset-text">{{ __('govuk_alpha.leaderboard.empty') }}</p>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.leaderboard.metrics.' . $leaderboardType) }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.leaderboard.rank_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.leaderboard.member_column') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.leaderboard.score_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($leaderboardRows as $row)
                    @php
                        $rowName = trim((string) ($row['name'] ?? '')) ?: trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                        $rowName = $rowName !== '' ? $rowName : __('govuk_alpha.members.unknown_member');
                        $isMe = (bool) ($row['is_current_user'] ?? false);
                    @endphp
                    <tr class="govuk-table__row @if ($isMe) nexus-alpha-row--active @endif">
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ (int) ($row['rank'] ?? 0) }}</td>
                        <td class="govuk-table__cell">
                            {{ $rowName }}
                            @if ($isMe)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.leaderboard.you') }}</strong>@endif
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $row['score_display'] ?? $row['score'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
