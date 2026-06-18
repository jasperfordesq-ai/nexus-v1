{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_achievements') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.shop.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.shop.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.shop.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'achievements', 'gamificationActiveTab' => 'shop'])

    @if ($status === 'purchased')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="shop-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="shop-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.shop.states.purchased') }}</p></div>
        </div>
    @elseif ($status === 'purchase-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_gamification.shop.states.purchase-failed') }}</p></div>
            </div>
        </div>
    @endif

    <div class="govuk-panel govuk-panel--confirmation nexus-alpha-panel govuk-!-margin-bottom-6">
        <div class="govuk-panel__body">
            {{ __('govuk_alpha_gamification.shop.balance_label') }}
            <br><strong>{{ __('govuk_alpha_gamification.shop.balance_value', ['xp' => number_format((int) $shopUserXp)]) }}</strong>
        </div>
    </div>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_gamification.states.error_title') }}</span>
            {{ __('govuk_alpha_gamification.shop.warning') }}
        </strong>
    </div>

    @if (empty($shopItems))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.shop.empty') }}</p></div>
    @else
        <ul class="nexus-alpha-card-list govuk-list">
            @foreach ($shopItems as $item)
                @php
                    $itemId = (int) ($item['id'] ?? 0);
                    $itemName = trim((string) ($item['name'] ?? ''));
                    $cost = (int) ($item['cost_xp'] ?? ($item['xp_cost'] ?? 0));
                    $owned = (int) ($item['user_purchases'] ?? 0) > 0;
                    $canBuy = (bool) ($item['can_purchase'] ?? false);
                    $itemType = (string) ($item['item_type'] ?? 'perk');
                    $typeLabelKey = in_array($itemType, ['badge', 'perk', 'feature', 'cosmetic'], true) ? $itemType : 'perk';
                @endphp
                <li class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $itemName }}</h2>
                        <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_gamification.shop.type_' . $typeLabelKey) }}</strong>
                    </div>
                    @if (!empty($item['description']))
                        <p class="govuk-body">{{ $item['description'] }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_gamification.shop.cost', ['xp' => number_format($cost)]) }}</p>

                    @if ($owned)
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_gamification.shop.owned') }}</strong>
                    @elseif ($canBuy)
                        <form method="post" action="{{ route('govuk-alpha.gamification.shop.purchase', ['tenantSlug' => $tenantSlug]) }}">
                            @csrf
                            <input type="hidden" name="item_id" value="{{ $itemId }}">
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_gamification.shop.buy_button', ['xp' => number_format($cost)]) }}</button>
                        </form>
                    @else
                        <strong class="govuk-tag govuk-tag--red">{{ ($cost > (int) $shopUserXp) ? __('govuk_alpha_gamification.shop.cannot_afford') : __('govuk_alpha_gamification.shop.out_of_stock') }}</strong>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
