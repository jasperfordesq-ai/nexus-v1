{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $badgeName = trim((string) ($badge['name'] ?? ''));
        $badgeIcon = trim((string) ($badge['icon'] ?? ''));
        $badgeDesc = trim((string) ($badge['description'] ?? ''));
        $earned = (bool) ($badge['earned'] ?? false);
        $earnedAt = $badge['earned_at'] ?? null;
        $rarity = trim((string) ($badge['rarity'] ?? ''));
        $xpValue = $badge['xp_value'] ?? null;
        $tier = trim((string) (is_array($badge['tier'] ?? null) ? ($badge['tier']['name'] ?? '') : ($badge['tier'] ?? '')));
        $type = trim((string) ($badge['type'] ?? ''));
        $isShowcased = (bool) ($badge['is_showcased'] ?? false);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_achievements') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.badge.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">@if ($badgeIcon !== ''){{ $badgeIcon }} @endif{{ $badgeName }}</h1>

    @if ($badgeDesc !== '')
        <p class="govuk-body-l">{{ $badgeDesc }}</p>
    @endif

    @if ($earned)
        <strong class="govuk-tag govuk-tag--green govuk-!-margin-bottom-4">{{ __('govuk_alpha_gamification.badge.earned_status') }}</strong>
    @else
        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-bottom-4">{{ __('govuk_alpha_gamification.badge.not_earned_status') }}</strong>
    @endif

    <dl class="govuk-summary-list">
        @if ($earned && $earnedAt)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.badge.earned_on', ['date' => '']) }}</dt>
                <dd class="govuk-summary-list__value">{{ \Illuminate\Support\Carbon::parse($earnedAt)->translatedFormat('j F Y') }}</dd>
            </div>
        @endif
        @if ($rarity !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.badge.rarity_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ ucfirst($rarity) }}</dd>
            </div>
        @endif
        @if ($xpValue !== null && (int) $xpValue > 0)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.badge.xp_value_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ number_format((int) $xpValue) }}</dd>
            </div>
        @endif
        @if ($tier !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.badge.tier_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ ucfirst($tier) }}</dd>
            </div>
        @endif
        @if ($type !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_gamification.badge.type_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ ucfirst($type) }}</dd>
            </div>
        @endif
    </dl>

    @if ($earned && $isShowcased)
        <p class="govuk-body">{{ __('govuk_alpha_gamification.badge.showcased') }}</p>
    @endif

    <p class="govuk-body govuk-!-margin-top-4">
        <a class="govuk-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.badge.view_all') }}</a>
    </p>
@endsection
