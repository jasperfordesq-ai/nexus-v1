{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $ns = is_array($nexusScore ?? null) ? $nexusScore : [];
        $hasScore = isset($ns['total_score']);
        $total = (float) ($ns['total_score'] ?? 0);
        $max = (int) ($ns['max_score'] ?? 1000);
        $percentile = $ns['percentile'] ?? null;
        $tier = is_array($ns['tier'] ?? null) ? $ns['tier'] : [];
        $tierName = trim((string) ($tier['name'] ?? ''));
        $tierIcon = trim((string) ($tier['icon'] ?? ''));
        $breakdown = is_array($ns['breakdown'] ?? null) ? $ns['breakdown'] : [];
        $insights = is_array($ns['insights'] ?? null) ? $ns['insights'] : [];
        $catKeys = ['engagement', 'quality', 'volunteer', 'activity', 'badges', 'impact'];
        $scoreTitle = trim(($tierIcon !== '' ? $tierIcon . ' ' : '') . __('govuk_alpha.nexus_score.out_of', ['score' => number_format($total, 0), 'max' => number_format($max, 0)]));
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.nexus_score.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.nexus_score.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.nexus_score.description') }}</p>

    @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.tiers'))
        <nav class="govuk-!-margin-bottom-6" aria-label="{{ __('govuk_alpha_gamification.nav.tiers_related_heading') }}">
            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_gamification.nav.tiers_related_heading') }}</h2>
            <ul class="govuk-list">
                <li><a class="govuk-link" href="{{ route('govuk-alpha.gamification.tiers', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.nav.tiers') }}</a></li>
            </ul>
        </nav>
    @endif

    @if (!$hasScore)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.nexus_score.unavailable') }}</p></div>
    @else
        <div class="govuk-panel govuk-panel--confirmation nexus-alpha-panel">
            <h2 class="govuk-panel__title">{{ $scoreTitle }}</h2>
            <div class="govuk-panel__body">
                {{ $tierName }}
                @if (!is_null($percentile) && $percentile !== '')
                    <br>{{ __('govuk_alpha.nexus_score.percentile', ['percent' => (int) $percentile]) }}
                @endif
            </div>
        </div>

        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.nexus_score.breakdown_title') }}</h2>
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.nexus_score.breakdown_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.nexus_score.category_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.nexus_score.score_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($catKeys as $key)
                    @php $cat = is_array($breakdown[$key] ?? null) ? $breakdown[$key] : null; @endphp
                    @if ($cat !== null)
                        @php
                            $cScore = (int) round((float) ($cat['score'] ?? 0));
                            $cMax = (int) round((float) ($cat['max'] ?? 0));
                            $cPct = max(0, min(100, (int) round((float) ($cat['percentage'] ?? 0))));
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ __('govuk_alpha.nexus_score.categories.' . $key) }}</td>
                            <td class="govuk-table__cell">
                                <span class="govuk-!-margin-right-2">{{ $cScore }} / {{ $cMax }}</span>
                                <progress max="100" value="{{ $cPct }}" aria-label="{{ __('govuk_alpha.nexus_score.categories.' . $key) }}: {{ $cPct }}%">{{ $cPct }}%</progress>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        @if (!empty($insights))
            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.nexus_score.insights_title') }}</h2>
            <ul class="govuk-list govuk-list--bullet">
                @foreach ($insights as $ins)
                    @php $insText = is_string($ins) ? trim($ins) : trim((string) ($ins['message'] ?? ($ins['text'] ?? ($ins['tip'] ?? '')))); @endphp
                    @if ($insText !== '')
                        <li>{{ $insText }}</li>
                    @endif
                @endforeach
            </ul>
        @endif
    @endif
@endsection
