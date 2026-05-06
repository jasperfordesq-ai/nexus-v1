{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = !empty($filters['search']) || !empty($filters['type']) || !empty($filters['category_id']);
        $listingTypeClass = fn (?string $type): string => ($type === 'request') ? 'govuk-tag--purple' : 'govuk-tag--blue';
        $listingTypeLabel = fn (?string $type): string => __('govuk_alpha.listings.' . (($type === 'request') ? 'request' : 'offer'));
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.listings.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.listings.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.listings.description') }}</p>

    @if ($moduleDisabled)
        <div class="govuk-notification-banner" role="region" aria-labelledby="listings-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listings-disabled-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.module_disabled') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.listings.module_disabled_detail', ['community' => $communityName]) }}</p>
            </div>
        </div>
    @else
        <form method="get" action="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            <fieldset class="govuk-fieldset" aria-describedby="listings-filter-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.listings.filters_title') }}</h2>
                </legend>
                <div id="listings-filter-hint" class="govuk-hint">{{ __('govuk_alpha.listings.filters_hint') }}</div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="q">{{ __('govuk_alpha.listings.search_label') }}</label>
                            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.listings.search_hint') }}</div>
                            <input class="govuk-input" id="q" name="q" type="search" value="{{ $filters['search'] ?? '' }}" aria-describedby="q-hint">
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="type">{{ __('govuk_alpha.listings.type_label') }}</label>
                            <select class="govuk-select" id="type" name="type">
                                <option value="">{{ __('govuk_alpha.listings.all_types') }}</option>
                                <option value="offer" @selected(($filters['type'] ?? null) === 'offer')>{{ __('govuk_alpha.listings.offer') }}</option>
                                <option value="request" @selected(($filters['type'] ?? null) === 'request')>{{ __('govuk_alpha.listings.request') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="category_id">{{ __('govuk_alpha.listings.category_label') }}</label>
                            <select class="govuk-select" id="category_id" name="category_id">
                                <option value="">{{ __('govuk_alpha.listings.all_categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category['id'] }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="nexus-alpha-actions">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </fieldset>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha.listings.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.listings.result_count', (int) ($meta['total_items'] ?? 0), ['count' => (int) ($meta['total_items'] ?? 0)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.listings.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.listings.empty') }}</p>
                @if ($hasFilters)
                    <p class="govuk-body">
                        <a class="govuk-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    </p>
                @endif
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $listing)
                    @php
                        $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
                        $authorName = $listing['user']['name'] ?? $listing['author_name'] ?? null;
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-listing-row">
                            <div>
                                <strong class="govuk-tag {{ $listingTypeClass($type) }}">{{ $listingTypeLabel($type) }}</strong>
                                <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                                    <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ $listing['title'] }}</a>
                                </h3>
                                @if ($authorName)
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.listings.posted_by', ['name' => $authorName]) }}</p>
                                @endif
                                <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($listing['description'] ?? '')), 220) }}</p>
                                <dl class="nexus-alpha-inline-list">
                                    @if (!empty($listing['category_name']))
                                        <div>
                                            <dt>{{ __('govuk_alpha.listings.category') }}</dt>
                                            <dd>{{ $listing['category_name'] }}</dd>
                                        </div>
                                    @endif
                                    @if (!empty($listing['location']))
                                        <div>
                                            <dt>{{ __('govuk_alpha.listings.location') }}</dt>
                                            <dd>{{ $listing['location'] }}</dd>
                                        </div>
                                    @endif
                                    @if (!empty($listing['hours_estimate']))
                                        <div>
                                            <dt>{{ __('govuk_alpha.listings.hours_label') }}</dt>
                                            <dd>{{ __('govuk_alpha.listings.hours', ['count' => $listing['hours_estimate']]) }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                            <div class="nexus-alpha-listing-row__action">
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                                    {{ __('govuk_alpha.actions.view_details') }}
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.listings.for_listing', ['title' => $listing['title']]) }}</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']) && !empty($meta['cursor']))
                <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.listings.pagination_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.listings.index', array_filter(['tenantSlug' => $tenantSlug, 'q' => $filters['search'] ?? null, 'type' => $filters['type'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'cursor' => $meta['cursor']])) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha.listings.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
    @endif
@endsection
