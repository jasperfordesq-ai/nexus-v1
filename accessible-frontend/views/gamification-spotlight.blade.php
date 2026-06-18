{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_leaderboard') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.spotlight.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.spotlight.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.spotlight.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'leaderboard', 'gamificationActiveTab' => 'spotlight'])

    @if (empty($spotlightMembers))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.spotlight.empty') }}</p></div>
    @else
        <ul class="nexus-alpha-card-list govuk-list">
            @foreach ($spotlightMembers as $member)
                @php
                    $memberId = (int) ($member['id'] ?? 0);
                    $name = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?: __('govuk_alpha_gamification.common.unknown_member');
                    $bio = trim((string) ($member['bio'] ?? ''));
                    $level = (int) ($member['level'] ?? 1);
                    $xp = (int) ($member['xp'] ?? 0);
                    $memberSince = trim((string) ($member['member_since'] ?? ''));
                    $activity = trim((string) ($member['recent_activity'] ?? ''));
                @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $name }}</h2>
                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ __('govuk_alpha_gamification.spotlight.level', ['level' => $level]) }}
                        · {{ __('govuk_alpha_gamification.spotlight.xp', ['xp' => number_format($xp)]) }}
                        @if ($memberSince !== '') · {{ __('govuk_alpha_gamification.spotlight.member_since', ['date' => $memberSince]) }}@endif
                    </p>
                    @if ($bio !== '')<p class="govuk-body">{{ $bio }}</p>@endif
                    @if ($activity !== '')<p class="govuk-body-s">{{ $activity }}</p>@endif
                    @if ($memberId > 0 && \App\Core\TenantContext::hasFeature('connections'))
                        <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">{{ __('govuk_alpha_gamification.spotlight.view_profile') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
