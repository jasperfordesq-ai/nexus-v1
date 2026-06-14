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
                return trim(trim((string) ($i['price_currency'] ?? '')) . ' ' . number_format($money, 2));
            }
            return __('govuk_alpha.marketplace.free');
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.marketplace.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.marketplace.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.marketplace.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.marketplace.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.marketplace.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.marketplace.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $marketplaceQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($listings))
        <p class="govuk-inset-text">{{ __('govuk_alpha.marketplace.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($listings as $i)
                @php
                    $iTitle = trim((string) ($i['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
                    $thumb = $asUrl(trim((string) ($i['image']['thumbnail_url'] ?? ($i['image']['url'] ?? ''))));
                    $loc = trim((string) ($i['location'] ?? ''));
                    $href = route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $i['id']]);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-listing-row">
                        @if ($thumb !== '')
                            <div class="nexus-alpha-listing-row__media">
                                <img class="nexus-alpha-card-thumb" src="{{ $thumb }}" alt="{{ $iTitle }}" width="120" height="90" loading="lazy" decoding="async">
                            </div>
                        @endif
                        <div class="nexus-alpha-listing-row__body">
                            <div class="nexus-alpha-module-row">
                                <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $href }}">{{ $iTitle }}</a></h2>
                                <strong class="govuk-tag govuk-tag--green">{{ $priceLabel($i) }}</strong>
                            </div>
                            @if (trim((string) ($i['tagline'] ?? '')) !== '')
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($i['tagline'], 160) }}</p>
                            @endif
                            @if ($loc !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $loc }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
