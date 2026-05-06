{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>
    <span class="govuk-caption-l">{{ __('govuk_alpha.listings.detail_title') }}</span>
    <h1 class="govuk-heading-xl">{{ $listing['title'] }}</h1>

    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.type') }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.' . ($listing['type'] ?? 'offer')) }}</dd>
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
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.hours', ['count' => $listing['hours_estimate']]) }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['hours_estimate'] }}</dd>
            </div>
        @endif
    </dl>

    <div class="govuk-body">{!! nl2br(e((string) ($listing['description'] ?? ''))) !!}</div>

    @foreach (['member_offers', 'member_requests'] as $section)
        @if (!empty($listing[$section]))
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.listings.' . $section) }}</h2>
            <ul class="govuk-list govuk-list--bullet">
                @foreach ($listing[$section] as $related)
                    <li>
                        <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $related['id']]) }}">{{ $related['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endsection
