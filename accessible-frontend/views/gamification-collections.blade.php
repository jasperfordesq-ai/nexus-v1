{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_achievements') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.collections.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.collections.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.collections.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'achievements', 'gamificationActiveTab' => 'collections'])

    @if (empty($collections))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.collections.empty') }}</p></div>
    @else
        <ul class="nexus-alpha-card-list govuk-list">
            @foreach ($collections as $col)
                @php
                    $colName = trim((string) ($col['name'] ?? ''));
                    $earned = (int) ($col['earned_count'] ?? 0);
                    $totalC = (int) ($col['total_count'] ?? 0);
                    $pct = max(0, min(100, (int) ($col['progress_percent'] ?? 0)));
                    $rewardXp = (int) ($col['bonus_xp'] ?? ($col['reward_xp'] ?? 0));
                    $isCompleted = (bool) ($col['is_completed'] ?? false);
                    $bonusClaimed = (bool) ($col['bonus_claimed'] ?? false);
                    $badges = is_array($col['badges'] ?? null) ? $col['badges'] : [];
                @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $colName }}</h2>
                        @if ($isCompleted)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_gamification.collections.completed') }}</strong>@endif
                    </div>
                    @if (!empty($col['description']))
                        <p class="govuk-body">{{ $col['description'] }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        {{ __('govuk_alpha_gamification.collections.progress', ['earned' => $earned, 'total' => $totalC]) }}
                        @if ($rewardXp > 0) · {{ __('govuk_alpha_gamification.collections.reward', ['xp' => number_format($rewardXp)]) }}@endif
                        @if ($bonusClaimed) · <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_gamification.collections.bonus_claimed') }}</strong>@endif
                    </p>
                    <progress max="100" value="{{ $pct }}" aria-label="{{ $colName }}: {{ $pct }}%">{{ $pct }}%</progress>
                    @if (!empty($badges))
                        <ul class="nexus-alpha-inline-list govuk-list govuk-!-margin-top-2">
                            @foreach ($badges as $b)
                                @php
                                    $bEarned = (bool) ($b['earned'] ?? false);
                                    $bName = trim((string) ($b['name'] ?? ''));
                                    $bKey = trim((string) ($b['key'] ?? ($b['badge_key'] ?? '')));
                                @endphp
                                <li>
                                    @if ($bKey !== '' && \Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.badge'))
                                        <a class="govuk-link" href="{{ route('govuk-alpha.gamification.badge', ['tenantSlug' => $tenantSlug, 'key' => $bKey]) }}">{{ $bName }}</a>
                                    @else
                                        {{ $bName }}
                                    @endif
                                    <strong class="govuk-tag {{ $bEarned ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $bEarned ? __('govuk_alpha_gamification.common.earned') : __('govuk_alpha_gamification.common.locked') }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
