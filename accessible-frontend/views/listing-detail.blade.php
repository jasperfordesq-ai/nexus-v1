{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
        $typeClass = ($type === 'request') ? 'govuk-tag--purple' : 'govuk-tag--blue';
        $authorName = $listing['author_name'] ?? $listing['user']['name'] ?? null;
        $createdAt = !empty($listing['created_at']) ? \Illuminate\Support\Carbon::parse($listing['created_at'])->translatedFormat('j F Y') : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.listings.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ $listing['title'] }}</h1>
            <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.listings.description_title') }}</h2>
            <div class="govuk-body">{!! nl2br(e((string) ($listing['description'] ?? ''))) !!}</div>
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="govuk-inset-text">
                {{ __('govuk_alpha.listings.detail_inset', ['type' => __('govuk_alpha.listings.' . $type)]) }}
            </div>
        </div>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.listings.summary_title') }}</h2>
    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.type') }}</dt>
            <dd class="govuk-summary-list__value">
                <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
            </dd>
        </div>
        @if (!empty($listing['category_name']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.category') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['category_name'] }}</dd>
            </div>
        @endif
        @if (!empty($listing['location']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.location') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['location'] }}</dd>
            </div>
        @endif
        @if (!empty($listing['hours_estimate']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.hours_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.hours', ['count' => $listing['hours_estimate']]) }}</dd>
            </div>
        @endif
        @if ($authorName)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.posted_by_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ $authorName }}
                    @if (!empty($listing['author_verified']))
                        <strong class="govuk-tag govuk-tag--green govuk-!-margin-left-2">{{ __('govuk_alpha.members.verified') }}</strong>
                    @endif
                </dd>
            </div>
        @endif
        @if ($createdAt)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.created') }}</dt>
                <dd class="govuk-summary-list__value">{{ $createdAt }}</dd>
            </div>
        @endif
        @if (!empty($listing['author_rating']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.author_rating') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.members.rating', ['rating' => $listing['author_rating']]) }}</dd>
            </div>
        @endif
    </dl>

    <div class="nexus-alpha-actions govuk-!-margin-top-6 govuk-!-margin-bottom-8">
        <a class="govuk-button" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>
        <a class="govuk-button govuk-button--secondary" href="/{{ $tenantSlug }}">{{ __('govuk_alpha.home.current_app_action') }}</a>
    </div>

    @foreach (['member_offers', 'member_requests'] as $section)
        @if (!empty($listing[$section]))
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.listings.' . $section) }}</h2>
            <ul class="govuk-list govuk-list--spaced">
                @foreach ($listing[$section] as $related)
                    <li>
                        <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $related['id']]) }}">{{ $related['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endsection
