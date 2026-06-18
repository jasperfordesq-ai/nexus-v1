{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // 9-tier ladder, thresholds mirroring the React NexusScorePage TIERS config.
        $tiers = [
            ['key' => 'novice', 'min' => 0],
            ['key' => 'beginner', 'min' => 200],
            ['key' => 'developing', 'min' => 300],
            ['key' => 'intermediate', 'min' => 400],
            ['key' => 'proficient', 'min' => 500],
            ['key' => 'advanced', 'min' => 600],
            ['key' => 'expert', 'min' => 700],
            ['key' => 'elite', 'min' => 800],
            ['key' => 'legendary', 'min' => 900],
        ];
        $ns = is_array($tierScore ?? null) ? $tierScore : [];
        $hasScore = isset($ns['total_score']);
        $total = (int) round((float) ($ns['total_score'] ?? 0));
        $max = (int) ($ns['max_score'] ?? 1000);
        $tierData = is_array($ns['tier'] ?? null) ? $ns['tier'] : [];
        $currentTierName = trim((string) ($tierData['name'] ?? ''));

        // Work out which tier the score currently sits in + the next tier threshold.
        $currentIndex = 0;
        foreach ($tiers as $i => $tier) {
            if ($total >= $tier['min']) {
                $currentIndex = $i;
            }
        }
        $nextTier = $tiers[$currentIndex + 1] ?? null;
        $pointsToNext = $nextTier !== null ? max(0, (int) $nextTier['min'] - $total) : 0;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.nexus-score', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_nexus_score') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.tiers.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.tiers.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.tiers.description') }}</p>

    @if (!$hasScore)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.tiers.unavailable') }}</p></div>
    @else
        <div class="govuk-panel govuk-panel--confirmation nexus-alpha-panel govuk-!-margin-bottom-6">
            <div class="govuk-panel__body">
                {{ __('govuk_alpha_gamification.tiers.your_score', ['score' => number_format($total), 'max' => number_format($max)]) }}
                @if ($currentTierName !== '')<br>{{ __('govuk_alpha_gamification.tiers.current_tier', ['tier' => $currentTierName]) }}@endif
            </div>
        </div>

        @if ($nextTier !== null)
            <div class="govuk-inset-text">{{ __('govuk_alpha_gamification.tiers.points_to_next', ['points' => number_format($pointsToNext), 'tier' => __('govuk_alpha_gamification.tiers.names.' . $nextTier['key'])]) }}</div>
        @else
            <div class="govuk-inset-text">{{ __('govuk_alpha_gamification.tiers.top_tier') }}</div>
        @endif

        <table class="govuk-table govuk-!-margin-top-4">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.tiers.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.tiers.tier_column') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.tiers.threshold_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.tiers.status_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($tiers as $i => $tier)
                    @php
                        $isCurrent = $i === $currentIndex;
                        $isReached = $total >= $tier['min'];
                        if ($isCurrent) {
                            $statusKey = 'status_current'; $statusTag = 'govuk-tag--blue';
                        } elseif ($isReached) {
                            $statusKey = 'status_reached'; $statusTag = 'govuk-tag--green';
                        } else {
                            $statusKey = 'status_locked'; $statusTag = 'govuk-tag--grey';
                        }
                    @endphp
                    <tr class="govuk-table__row @if ($isCurrent) nexus-alpha-row--active @endif">
                        <td class="govuk-table__cell">{{ __('govuk_alpha_gamification.tiers.names.' . $tier['key']) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format($tier['min']) }}</td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $statusTag }}">{{ __('govuk_alpha_gamification.tiers.' . $statusKey) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
