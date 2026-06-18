{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'achievements', 'gamificationActiveTab' => ''])

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
        {{-- POLISH: visible percentage adjacent to progress bar --}}
        <span class="govuk-body-s govuk-!-margin-right-2">{{ (int) round($levelPct) }}%</span><progress max="100" value="{{ (int) round($levelPct) }}" aria-label="{{ (int) round($levelPct) }}%">{{ (int) round($levelPct) }}%</progress>
    @endif

    {{-- ===== WAVE POLISH-GAMIFY: Daily Reward ===== --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.polish_gamify.daily_reward_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.polish_gamify.daily_reward_description') }}</p>

    @if ($dailyRewardStatus ?? null)
        {{-- Success / already-claimed notification --}}
        @if ($dailyRewardStatus === 'daily-reward-claimed')
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="dr-status">
                <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="dr-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
                <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polish_gamify.daily_reward_success', ['xp' => (int) ($dailyRewardXp ?? 5)]) }}</p></div>
            </div>
        @elseif ($dailyRewardStatus === 'daily-reward-failed')
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.polish_gamify.daily_reward_failed') }}</li></ul></div></div>
            </div>
        @endif
    @endif

    @php
        $canClaim = (bool) ($dailyCanClaim ?? true);
        $streak = (int) ($dailyStreak ?? 0);
        $nextXp = (int) ($dailyNextXp ?? 5);
    @endphp

    <dl class="govuk-summary-list govuk-!-margin-bottom-4">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.polish_gamify.daily_reward_streak', ['days' => $streak]) }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.polish_gamify.daily_reward_next_xp', ['xp' => $nextXp]) }}</dd>
        </div>
    </dl>

    @if ($canClaim)
        <form method="post" action="{{ route('govuk-alpha.achievements.daily-reward', ['tenantSlug' => $tenantSlug]) }}">
            @csrf
            <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.polish_gamify.daily_reward_button') }}</button>
        </form>
    @else
        <p class="govuk-body"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.polish_gamify.daily_reward_claimed') }}</strong></p>
    @endif

    {{-- ===== WAVE POLISH-GAMIFY: Active Challenges ===== --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.polish_gamify.challenges_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.polish_gamify.challenges_description') }}</p>

    @if ($challengeStatus ?? null)
        @if ($challengeStatus === 'challenge-claimed')
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="ch-status">
                <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="ch-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
                <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polish_gamify.challenge_claim_success') }}</p></div>
            </div>
        @elseif ($challengeStatus === 'challenge-claim-failed')
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.polish_gamify.challenge_claim_failed') }}</li></ul></div></div>
            </div>
        @endif
    @endif

    @if (empty($challenges ?? []))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polish_gamify.challenges_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-8">
            @foreach ($challenges as $ch)
                @php
                    $chTitle = trim((string) ($ch['name'] ?? ''));
                    $chDesc = trim((string) ($ch['description'] ?? ''));
                    $chPct = (int) ($ch['progress_percent'] ?? 0);
                    $chProgress = (int) ($ch['user_progress'] ?? 0);
                    $chTarget = (int) ($ch['target_count'] ?? 0);
                    $chXp = (int) ($ch['reward_xp'] ?? 0);
                    $chDays = (int) ceil((float) ($ch['days_remaining'] ?? 0));
                    $chEndDate = trim((string) ($ch['end_date'] ?? ''));
                    $chCompleted = (bool) ($ch['is_completed'] ?? false);
                    $chClaimed = (bool) ($ch['reward_claimed'] ?? false);
                    $chId = (int) ($ch['id'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $chTitle }}</h3>
                        @if ($chClaimed)
                            <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.polish_gamify.challenge_claimed_tag') }}</strong>
                        @elseif ($chCompleted)
                            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.polish_gamify.challenge_completed_tag') }}</strong>
                        @endif
                    </div>
                    @if ($chDesc !== '')
                        <p class="govuk-body-s govuk-!-margin-bottom-1">{{ $chDesc }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        {{ __('govuk_alpha.polish_gamify.challenge_progress_label', ['current' => $chProgress, 'target' => $chTarget]) }}
                        &middot; {{ __('govuk_alpha.polish_gamify.challenge_reward_xp', ['xp' => $chXp]) }}
                        @if ($chDays > 0) &middot; {{ __('govuk_alpha.polish_gamify.challenge_days_left', ['days' => $chDays]) }}@endif
                    </p>
                    <span class="govuk-body-s govuk-!-margin-right-2">{{ $chPct }}%</span><progress max="100" value="{{ $chPct }}" aria-label="{{ $chPct }}%">{{ $chPct }}%</progress>
                    @if ($chCompleted && !$chClaimed && $chId > 0)
                        <form method="post" action="{{ route('govuk-alpha.achievements.claim-challenge', ['tenantSlug' => $tenantSlug, 'id' => $chId]) }}" class="govuk-!-margin-top-2">
                            @csrf
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('govuk_alpha.polish_gamify.challenge_claim_button') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    {{-- ===== Earned badges ===== --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.achievements.earned_title') }}</h2>
    @if (empty($earnedBadges))
        {{-- POLISH: govuk-inset-text must be a div wrapper --}}
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.achievements.earned_empty') }}</p></div>
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
                {{-- POLISH: visible percentage adjacent to progress bar --}}
                <span class="govuk-body-s govuk-!-margin-right-2">{{ $bpPct }}%</span><progress max="100" value="{{ $bpPct }}" aria-label="{{ $bpPct }}%">{{ $bpPct }}%</progress>
            </div>
        @endforeach
    @endif
@endsection
