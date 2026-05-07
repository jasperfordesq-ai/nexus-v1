{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $feedItemTypeLabel = fn (?string $type): string => \Illuminate\Support\Facades\Lang::has('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            ? __('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            : __('govuk_alpha.feed.item_types.activity');
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.dashboard.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.dashboard.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.dashboard.description') }}</p>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.profile.activity_title') }}</h2>
            <dl class="nexus-alpha-stat-grid">
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.hours_given_label') }}</dt>
                    <dd>{{ number_format((float) $profileStats['hours_given'], 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.hours_received_label') }}</dt>
                    <dd>{{ number_format((float) $profileStats['hours_received'], 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.profile.active_listings_label') }}</dt>
                    <dd>{{ (int) $profileStats['listings_count'] }}</dd>
                </div>
            </dl>
        </div>
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.quick_links_title') }}</h2>
            <ul class="govuk-list govuk-list--spaced">
                <li><a class="govuk-link" href="{{ route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_profile') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.edit_profile') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_feed') }}</a></li>
                @if (\App\Core\TenantContext::hasModule('listings'))
                    <li><a class="govuk-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.dashboard.view_listings') }}</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="govuk-grid-row govuk-!-margin-top-8">
        <div class="govuk-grid-column-one-half">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.recent_feed_title') }}</h2>
            @if (empty($feedItems))
                <div class="govuk-inset-text">{{ __('govuk_alpha.dashboard.empty_feed') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($feedItems as $item)
                        @php
                            $itemType = $item['type'] ?? 'post';
                            $itemTitle = $item['title'] ?? $feedItemTypeLabel($itemType);
                            $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha.feed.unknown_author');
                        @endphp
                        <article class="nexus-alpha-card">
                            <strong class="govuk-tag govuk-tag--grey">{{ $feedItemTypeLabel($itemType) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">{{ $itemTitle }}</h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.feed.posted_by', ['name' => $authorName]) }}</p>
                            @if (!empty($item['content']))
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) $item['content']), 180) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="govuk-grid-column-one-half">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.dashboard.recent_listings_title') }}</h2>
            @if (empty($listings))
                <div class="govuk-inset-text">{{ __('govuk_alpha.dashboard.empty_listings') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($listings as $listing)
                        @php
                            $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
                            $typeClass = $type === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue';
                        @endphp
                        <article class="nexus-alpha-card">
                            <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ $listing['title'] }}</a>
                            </h3>
                            @if (!empty($listing['description']))
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing['description']), 180) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
