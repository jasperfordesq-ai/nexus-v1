{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $listings = $listings ?? [];
        $query = (string) ($query ?? '');
        $moreHref = function () use ($tenantSlug, $query, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            return route('govuk-alpha.federation.listings.index', $params);
        };
        $typeLabel = fn (string $t): string => $t === 'request'
            ? __('govuk_alpha.federation.listings_browse.type_request')
            : __('govuk_alpha.federation.listings_browse.type_offer');
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.listings_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.listings_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.listings_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.listings_browse.description') }}</p>

    @if (!$allowed)
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.listings_browse.not_available') }}</p>
    @else
        <form method="get" action="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="q">{{ __('govuk_alpha.federation.listings_browse.search_label') }}</label>
                <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.federation.listings_browse.search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        @if (empty($listings))
            <p class="govuk-inset-text">{{ __('govuk_alpha.federation.listings_browse.empty') }}</p>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($listings as $l)
                    @php
                        $lTitle = trim((string) ($l['title'] ?? '')) ?: __('govuk_alpha.federation.listings_browse.title');
                        $loc = trim((string) ($l['location'] ?? ''));
                        $cat = trim((string) ($l['category_name'] ?? ''));
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $lTitle }}</h2>
                            <strong class="govuk-tag {{ ($l['type'] ?? 'offer') === 'request' ? 'govuk-tag--blue' : 'govuk-tag--green' }}">{{ $typeLabel((string) ($l['type'] ?? 'offer')) }}</strong>
                        </div>
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.listings_browse.community_label') }}: {{ $l['tenant_name'] ?? '' }}</p>
                        @if (trim((string) ($l['description'] ?? '')) !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($l['description'], 160) }}</p>
                        @endif
                        @if ($cat !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $cat }}@if ($loc !== '') &middot; {{ $loc }}@endif</p>
                        @elseif ($loc !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $loc }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            @if (!empty($nextCursor))
                <p class="govuk-body govuk-!-margin-top-4">
                    <a class="govuk-link" href="{{ $moreHref() }}">{{ __('govuk_alpha.federation.listings_browse.load_more') }}</a>
                </p>
            @endif
        @endif
    @endif
@endsection
