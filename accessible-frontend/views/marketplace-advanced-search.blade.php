{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $priceLabel = function (array $i): string {
            $tc = (float) ($i['time_credit_price'] ?? 0);
            $money = (float) ($i['price'] ?? 0);
            if ($tc > 0) {
                return rtrim(rtrim(number_format($tc, 2), '0'), '.') . ' ' . __('govuk_alpha.marketplace.credits_label');
            }
            if ($money > 0) {
                return \App\Support\MarketplaceMoneyFormatter::format(
                    $money,
                    (string) ($i['price_currency'] ?? ''),
                );
            }
            return __('govuk_alpha.marketplace.free');
        };
        $selectedConditions = is_array($selectedConditions ?? null) ? $selectedConditions : [];
        $conditionOptions = ['new', 'like_new', 'good', 'fair', 'poor'];
        $deliveryOptions = ['pickup', 'shipping', 'both', 'community_delivery'];
        $postedOptions = ['1', '7', '30', '90'];
        $sortOptions = ['newest', 'price_asc', 'price_desc', 'popular'];
        $activeCategoryId = (int) ($marketplaceCategoryId ?? 0);
    @endphp

    @includeIf('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'browse'])

    <span class="govuk-caption-xl">{{ __('govuk_alpha.marketplace.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.marketplace_advanced.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.marketplace_advanced.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.marketplace.search', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.marketplace.search_label') }}</label>
                    <input class="govuk-input" id="q" name="q" type="search" value="{{ $marketplaceQuery ?? '' }}">
                </div>
            </div>
            @if (!empty($categories))
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="category_id">{{ __('govuk_alpha.polish_commerce.marketplace_category_label') }}</label>
                        <select class="govuk-select" id="category_id" name="category_id">
                            <option value="">{{ __('govuk_alpha.polish_commerce.marketplace_category_all') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ (int) $cat['id'] }}" {{ $activeCategoryId === (int) $cat['id'] ? 'selected' : '' }}>{{ $cat['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="price_min">{{ __('govuk_alpha_commerce.marketplace_advanced.price_min') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="price_min" name="price_min" type="number" min="0" step="0.01" inputmode="decimal" value="{{ $priceMin !== null ? $priceMin : '' }}">
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="price_max">{{ __('govuk_alpha_commerce.marketplace_advanced.price_max') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="price_max" name="price_max" type="number" min="0" step="0.01" inputmode="decimal" value="{{ $priceMax !== null ? $priceMax : '' }}">
                </div>
            </div>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.marketplace_advanced.condition_label') }}</legend>
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    @foreach ($conditionOptions as $cond)
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="condition-{{ $cond }}" name="condition[]" type="checkbox" value="{{ $cond }}" {{ in_array($cond, $selectedConditions, true) ? 'checked' : '' }}>
                            <label class="govuk-label govuk-checkboxes__label" for="condition-{{ $cond }}">{{ __('govuk_alpha_commerce.marketplace_advanced.condition_' . $cond) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="seller_type">{{ __('govuk_alpha_commerce.marketplace_advanced.seller_type_label') }}</label>
                    <select class="govuk-select" id="seller_type" name="seller_type">
                        <option value="">{{ __('govuk_alpha_commerce.marketplace_advanced.any') }}</option>
                        <option value="private" {{ ($sellerType ?? '') === 'private' ? 'selected' : '' }}>{{ __('govuk_alpha_commerce.marketplace_advanced.seller_private') }}</option>
                        <option value="business" {{ ($sellerType ?? '') === 'business' ? 'selected' : '' }}>{{ __('govuk_alpha_commerce.marketplace_advanced.seller_business') }}</option>
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="delivery_method">{{ __('govuk_alpha_commerce.marketplace_advanced.delivery_label') }}</label>
                    <select class="govuk-select" id="delivery_method" name="delivery_method">
                        <option value="">{{ __('govuk_alpha_commerce.marketplace_advanced.any') }}</option>
                        @foreach ($deliveryOptions as $dm)
                            <option value="{{ $dm }}" {{ ($deliveryMethod ?? '') === $dm ? 'selected' : '' }}>{{ __('govuk_alpha_commerce.marketplace_advanced.delivery_' . $dm) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="posted_within">{{ __('govuk_alpha_commerce.marketplace_advanced.posted_within_label') }}</label>
                    <select class="govuk-select" id="posted_within" name="posted_within">
                        <option value="">{{ __('govuk_alpha_commerce.marketplace_advanced.any_time') }}</option>
                        @foreach ($postedOptions as $pw)
                            <option value="{{ $pw }}" {{ (string) ($postedWithin ?? '') === $pw ? 'selected' : '' }}>{{ __('govuk_alpha_commerce.marketplace_advanced.posted_' . $pw) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="sort">{{ __('govuk_alpha_commerce.marketplace_advanced.sort_label') }}</label>
            <select class="govuk-select" id="sort" name="sort">
                @foreach ($sortOptions as $so)
                    <option value="{{ $so }}" {{ ($sort ?? 'newest') === $so ? 'selected' : '' }}>{{ __('govuk_alpha_commerce.marketplace_advanced.sort_' . $so) }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.marketplace_advanced.submit') }}</button>
        <a class="govuk-link govuk-!-margin-left-3" href="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.marketplace_advanced.back_to_browse') }}</a>
    </form>

    @if (empty($listings))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.marketplace_advanced.no_results') }}</p></div>
    @else
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_commerce.marketplace_advanced.results_heading') }}</h2>
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $i)
                @php
                    $iId = (int) ($i['id'] ?? 0);
                    $iTitle = trim((string) ($i['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
                    $iCondition = trim((string) ($i['condition'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                            @if ($iId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $iId]) }}">{{ $iTitle }}</a>@else{{ $iTitle }}@endif
                        </h3>
                        <strong class="govuk-tag govuk-tag--blue">{{ $priceLabel($i) }}</strong>
                    </div>
                    @if ($iCondition !== '' && \Illuminate\Support\Facades\Lang::has('govuk_alpha_commerce.marketplace_advanced.condition_' . $iCondition))
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha_commerce.marketplace_advanced.condition_' . $iCondition) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
