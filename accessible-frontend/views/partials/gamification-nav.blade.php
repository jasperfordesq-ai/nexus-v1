{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Gamification sub-navigation — a server-rendered GOV.UK tab strip (plain links, no JS).
    Two strips share this partial: the "achievements" family and the "leaderboard" family.
    Pass $gamificationNavGroup = 'achievements' | 'leaderboard' and
    $gamificationActiveTab = the active tab key.
--}}
@php
    $gamGroup = $gamificationNavGroup ?? 'achievements';
    $gamActive = $gamificationActiveTab ?? '';

    if ($gamGroup === 'leaderboard') {
        $gamHeading = __('govuk_alpha_gamification.nav.leaderboard_heading');
        $gamTabs = [
            'competitive' => ['label' => __('govuk_alpha_gamification.nav.competitive'), 'route' => 'govuk-alpha.gamification.competitive'],
            'seasons' => ['label' => __('govuk_alpha_gamification.nav.seasons'), 'route' => 'govuk-alpha.gamification.seasons'],
            'journey' => ['label' => __('govuk_alpha_gamification.nav.journey'), 'route' => 'govuk-alpha.gamification.journey'],
            'spotlight' => ['label' => __('govuk_alpha_gamification.nav.spotlight'), 'route' => 'govuk-alpha.gamification.spotlight'],
        ];
    } else {
        $gamHeading = __('govuk_alpha_gamification.nav.heading');
        $gamTabs = [
            'shop' => ['label' => __('govuk_alpha_gamification.nav.shop'), 'route' => 'govuk-alpha.gamification.shop'],
            'collections' => ['label' => __('govuk_alpha_gamification.nav.collections'), 'route' => 'govuk-alpha.gamification.collections'],
            'showcase' => ['label' => __('govuk_alpha_gamification.nav.showcase'), 'route' => 'govuk-alpha.gamification.showcase'],
            'engagement' => ['label' => __('govuk_alpha_gamification.nav.engagement'), 'route' => 'govuk-alpha.gamification.engagement'],
        ];
    }
@endphp
<nav class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-4" aria-label="{{ $gamHeading }}">
    <h2 class="govuk-tabs__title">{{ $gamHeading }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($gamTabs as $tabKey => $tab)
            @if (\Illuminate\Support\Facades\Route::has($tab['route']))
                <li class="govuk-tabs__list-item{{ $gamActive === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ route($tab['route'], ['tenantSlug' => $tenantSlug]) }}" @if ($gamActive === $tabKey) aria-current="page" @endif>{{ $tab['label'] }}</a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
