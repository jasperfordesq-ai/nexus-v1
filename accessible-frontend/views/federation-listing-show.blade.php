{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $listing = $listing ?? [];

        $title = trim((string) ($listing['title'] ?? ''));
        $type = (string) ($listing['type'] ?? '');
        $categoryName = trim((string) ($listing['category_name'] ?? ''));
        $location = trim((string) ($listing['location'] ?? ''));
        $imageUrl = trim((string) ($listing['image_url'] ?? ''));
        $description = (string) ($listing['description'] ?? '');
        $authorName = trim((string) ($listing['author_name'] ?? ''));
        $tenantName = trim((string) ($listing['tenant_name'] ?? ''));
        $userId = (int) ($listing['user_id'] ?? 0);
        $listingTenantId = (int) ($listing['tenant_id'] ?? 0);
        $canContact = (bool) ($listing['can_contact'] ?? false);

        $estimatedHoursRaw = $listing['estimated_hours'] ?? null;
        $hasHours = ($estimatedHoursRaw !== null && $estimatedHoursRaw !== '' && (float) $estimatedHoursRaw > 0);

        $createdAt = $listing['created_at'] ?? null;
        $postedOn = $createdAt ? \Illuminate\Support\Carbon::parse($createdAt)->translatedFormat('j F Y') : '';

        $typeLabel = $type === 'request'
            ? __('govuk_alpha.federation.listings_browse.type_request')
            : __('govuk_alpha.federation.listings_browse.type_offer');

        $posterName = $authorName !== '' ? $authorName : __('govuk_alpha.federation.listings_browse.anonymous_user');

        $memberHref = route('govuk-alpha.federation.members.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $userId,
            'tenant_id' => $listingTenantId,
        ]);
    @endphp

    <a href="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.listings_browse.detail_back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.listings_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ $title }}</h1>

    @include('accessible-frontend::partials.federation-nav')

    <p class="govuk-body-s govuk-!-margin-bottom-3 nexus-alpha-inline-list">
        <strong class="govuk-tag {{ $type === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue' }} govuk-!-margin-right-1">{{ $typeLabel }}</strong>
        @if ($categoryName !== '')
            <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $categoryName }}</strong>
        @endif
        @if ($tenantName !== '')
            <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.listings_browse.community_label') }}: {{ $tenantName }}</strong>
        @endif
    </p>

    @if ($imageUrl !== '')
        <img src="{{ $imageUrl }}" alt="{{ $title }}" loading="lazy" class="govuk-!-margin-bottom-4" style="max-width: 100%; height: auto;">
    @endif

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.listings_browse.category_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $categoryName !== '' ? $categoryName : '—' }}</dd>
        </div>

        @if ($hasHours)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.listings_browse.hours_estimated', ['hours' => '']) }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.federation.listings_browse.hours_estimated', ['hours' => $estimatedHoursRaw]) }}</dd>
            </div>
        @endif

        @if ($location !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $location }}</dd>
            </div>
        @endif

        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.listings_browse.posted_by', ['name' => '']) }}</dt>
            <dd class="govuk-summary-list__value">{{ __('govuk_alpha.federation.listings_browse.posted_by', ['name' => $posterName]) }}</dd>
        </div>

        @if ($postedOn !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.listings_browse.posted_on', ['date' => '']) }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.federation.listings_browse.posted_on', ['date' => $postedOn]) }}</dd>
            </div>
        @endif
    </dl>

    @if (trim($description) !== '')
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.federation.listings_browse.detail_description_heading') }}</h2>
        <p class="govuk-body">{!! nl2br(e($description)) !!}</p>
    @endif

    <div class="govuk-button-group">
        <a class="govuk-link" href="{{ $memberHref }}">{{ __('govuk_alpha.federation.listings_browse.view_profile') }}</a>
        @if ($canContact)
            <a class="govuk-button" href="{{ $memberHref }}" data-module="govuk-button">{{ __('govuk_alpha.federation.listings_browse.contact_author') }}</a>
        @endif
    </div>
@endsection
