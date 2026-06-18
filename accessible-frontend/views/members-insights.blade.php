{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $rating = $insightsStats['rating'] ?? null;
        $score = $nexusScore['total_score'] ?? null;
        $tierRaw = (string) ($nexusScore['tier'] ?? '');
        $tierKey = 'tier_' . strtolower(str_replace(' ', '_', $tierRaw));
        $percentile = (int) ($nexusScore['percentile'] ?? 0);
    @endphp

    <a class="govuk-back-link"
       href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">{{ __('govuk_alpha_members.insights.back_to_profile') }}</a>

    <span class="govuk-caption-l">{{ $displayName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_members.insights.heading') }}</h1>
    <p class="govuk-body-l">
        {{ ($isOwnProfile ?? false)
            ? __('govuk_alpha_members.insights.intro_own')
            : __('govuk_alpha_members.insights.intro_other', ['name' => $displayName]) }}
    </p>

    {{-- NEXUS score --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_members.insights.nexus_score_title') }}</h2>
    @if ($nexusScore !== null && $score !== null)
        <div class="nexus-alpha-card govuk-!-margin-bottom-6">
            <p class="govuk-body govuk-!-margin-bottom-2">
                <span class="govuk-!-font-size-48 govuk-!-font-weight-bold">{{ number_format((float) $score, 1) }}</span>
                <span class="govuk-body">{{ __('govuk_alpha_members.insights.nexus_score_out_of') }}</span>
            </p>
            <p class="govuk-!-margin-bottom-2">
                <strong class="govuk-tag govuk-tag--purple">{{ \Illuminate\Support\Facades\Lang::has('govuk_alpha_members.insights.' . $tierKey) ? __('govuk_alpha_members.insights.' . $tierKey) : $tierRaw }}</strong>
            </p>
            @if ($percentile > 0)
                <p class="govuk-body govuk-!-margin-bottom-2">{{ __('govuk_alpha_members.insights.nexus_percentile', ['percentile' => $percentile]) }}</p>
                <progress class="nexus-alpha-progress"
                          value="{{ $percentile }}"
                          max="100"
                          aria-label="{{ __('govuk_alpha_members.insights.nexus_percentile_aria', ['percentile' => $percentile]) }}">{{ $percentile }}%</progress>
            @endif
            @if ($isOwnProfile ?? false)
                <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha_members.insights.nexus_own_hint') }}</p>
            @endif
        </div>
    @else
        <div class="govuk-inset-text">{{ __('govuk_alpha_members.insights.nexus_empty') }}</div>
    @endif

    {{-- Full activity stats (incl. groups joined + events attended) --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha_members.insights.stats_title') }}</h2>
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_hours_given') }}</dt>
            <dd>{{ number_format((float) $insightsStats['hours_given'], 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_hours_received') }}</dt>
            <dd>{{ number_format((float) $insightsStats['hours_received'], 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_listings') }}</dt>
            <dd>{{ (int) $insightsStats['listings_count'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_groups') }}</dt>
            <dd>{{ (int) $insightsStats['groups_count'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_events') }}</dt>
            <dd>{{ (int) $insightsStats['events_attended'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_connections') }}</dt>
            <dd>{{ (int) $insightsStats['connections_count'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_reviews') }}</dt>
            <dd>{{ (int) $insightsStats['reviews_count'] }}</dd>
        </div>
        @if ($rating !== null)
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_members.insights.stat_rating') }}</dt>
                <dd>{{ number_format((float) $rating, 1) }}</dd>
            </div>
        @endif
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_level') }}</dt>
            <dd>{{ (int) $insightsStats['level'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_members.insights.stat_xp') }}</dt>
            <dd>{{ (int) $insightsStats['xp'] }}</dd>
        </div>
    </dl>

    {{-- Per-method verification badges --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha_members.insights.verification_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_members.insights.verification_intro') }}</p>
    @if (empty($verificationBadges))
        <div class="govuk-inset-text">{{ __('govuk_alpha_members.insights.verification_empty') }}</div>
    @else
        <ul class="govuk-list">
            @foreach ($verificationBadges as $vbadge)
                @php
                    $btype = (string) ($vbadge['badge_type'] ?? '');
                    $bkey = 'verification_type_' . $btype;
                    $blabelFallback = (string) ($vbadge['label'] ?? $btype);
                    $grantedAt = !empty($vbadge['granted_at'])
                        ? \Illuminate\Support\Carbon::parse($vbadge['granted_at'])->translatedFormat('j F Y')
                        : null;
                @endphp
                <li class="govuk-!-margin-bottom-2">
                    <strong class="govuk-tag govuk-tag--green">{{ \Illuminate\Support\Facades\Lang::has('govuk_alpha_members.insights.' . $bkey) ? __('govuk_alpha_members.insights.' . $bkey) : $blabelFallback }}</strong>
                    @if ($grantedAt)
                        <span class="nexus-alpha-meta">{{ __('govuk_alpha_members.insights.verified_on', ['date' => $grantedAt]) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Showcased / earned badges --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha_members.insights.badges_title') }}</h2>
    @if (empty($earnedBadges))
        <div class="govuk-inset-text">{{ __('govuk_alpha_members.insights.badges_empty') }}</div>
    @else
        <ul class="govuk-list nexus-alpha-actions">
            @foreach (array_slice($earnedBadges, 0, 12) as $badge)
                <li>
                    @if (!empty($badge['icon']))<span aria-hidden="true">{{ $badge['icon'] }}</span>@endif
                    <strong class="govuk-tag govuk-tag--blue">{{ $badge['name'] ?? '' }}</strong>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="govuk-body govuk-!-margin-top-7">
        <a class="govuk-link"
           href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">{{ __('govuk_alpha_members.insights.back_to_profile') }}</a>
    </p>
@endsection
