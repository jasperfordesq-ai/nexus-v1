{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $listings = $listings ?? [];
        $query = trim((string) ($query ?? ''));
        $type = (string) ($type ?? '');
        $partnerId = (int) ($partnerId ?? 0);
        $partnerOptions = $partnerOptions ?? [];
        $loadError = (bool) ($loadError ?? false);
        $nextCursor = $nextCursor ?? null;

        $indexHref = route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]);

        $typeLabel = fn (string $t): string => $t === 'request'
            ? __('govuk_alpha.federation.listings_browse.type_request')
            : __('govuk_alpha.federation.listings_browse.type_offer');

        $moreHref = function () use ($tenantSlug, $query, $type, $partnerId, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            if ($type !== '') { $params['type'] = $type; }
            if ($partnerId > 0) { $params['partner_id'] = $partnerId; }
            return route('govuk-alpha.federation.listings.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.listings_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.listings_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.listings_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.listings_browse.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if (!$allowed)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.listings_browse.not_available') }}</p></div>
    @else
        <form method="get" action="{{ $indexHref }}" class="govuk-!-margin-bottom-6">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.listings_browse.filters_legend') }}</legend>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.federation.listings_browse.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.federation.listings_browse.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="type">{{ __('govuk_alpha.federation.listings_browse.filter_type_label') }}</label>
                    <select class="govuk-select" id="type" name="type">
                        <option value="" @selected($type === '')>{{ __('govuk_alpha.federation.listings_browse.filter_type_all') }}</option>
                        <option value="offer" @selected($type === 'offer')>{{ __('govuk_alpha.federation.listings_browse.type_offer') }}</option>
                        <option value="request" @selected($type === 'request')>{{ __('govuk_alpha.federation.listings_browse.type_request') }}</option>
                    </select>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="partner_id">{{ __('govuk_alpha.federation.listings_browse.filter_partner_label') }}</label>
                    <select class="govuk-select" id="partner_id" name="partner_id">
                        <option value="" @selected($partnerId === 0)>{{ __('govuk_alpha.federation.listings_browse.filter_partner_all') }}</option>
                        @foreach ($partnerOptions as $partner)
                            <option value="{{ $partner['id'] }}" @selected($partnerId === (int) ($partner['id'] ?? 0))>{{ $partner['name'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.federation.listings_browse.apply_filters') }}</button>
            </fieldset>
        </form>

        @if ($loadError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.federation.listings_browse.unable_to_load') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="{{ $indexHref }}">{{ __('govuk_alpha.federation.listings_browse.try_again') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @elseif (empty($listings))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.listings_browse.empty') }}</p></div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($listings as $l)
                    @php
                        $lTitle = trim((string) ($l['title'] ?? '')) ?: __('govuk_alpha.federation.listings_browse.title');
                        $lType = (string) ($l['type'] ?? 'offer');
                        $lImage = trim((string) ($l['image_url'] ?? ''));
                        $lCategory = trim((string) ($l['category_name'] ?? ''));
                        $lLocation = trim((string) ($l['location'] ?? ''));
                        $lAuthor = trim((string) ($l['author_name'] ?? ''));
                        $lHours = $l['estimated_hours'] ?? null;
                        $lCreated = $l['created_at'] ?? null;
                        $showHref = route('govuk-alpha.federation.listings.show', [
                            'tenantSlug' => $tenantSlug,
                            'tenantId' => (int) ($l['tenant_id'] ?? 0),
                            'id' => (int) ($l['id'] ?? 0),
                        ]);
                    @endphp
                    <article class="nexus-alpha-card">
                        @if ($lImage !== '')
                            <img src="{{ $lImage }}" alt="{{ $lTitle }}" loading="lazy" class="govuk-!-margin-bottom-2" width="160" style="max-width:160px;height:auto;">
                        @endif

                        <div class="nexus-alpha-module-row">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $showHref }}">{{ $lTitle }}</a></h2>
                            <strong class="govuk-tag {{ $lType === 'request' ? 'govuk-tag--blue' : 'govuk-tag--green' }}">{{ $typeLabel($lType) }}</strong>
                        </div>

                        @if ($lCategory !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.listings_browse.category_label') }}: {{ $lCategory }}</p>
                        @endif

                        @if ($lHours !== null)
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.listings_browse.hours_estimated', ['hours' => $lHours]) }}</p>
                        @endif

                        @if ($lLocation !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.location_label') }}: {{ $lLocation }}</p>
                        @endif

                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.listings_browse.posted_by', ['name' => $lAuthor !== '' ? $lAuthor : __('govuk_alpha.federation.listings_browse.anonymous_user')]) }}</p>

                        @if ($lCreated !== null)
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.listings_browse.posted_on', ['date' => \Illuminate\Support\Carbon::parse($lCreated)->translatedFormat('j F Y')]) }}</p>
                        @endif

                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <strong class="govuk-tag govuk-tag--grey">{{ $l['tenant_name'] ?? '' }}</strong>
                        </p>
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
