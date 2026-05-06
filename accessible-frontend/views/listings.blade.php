{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-l">{{ __('govuk_alpha.service_name') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.listings.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.listings.description') }}</p>

    @if ($moduleDisabled)
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_title') }}</span>
                {{ __('govuk_alpha.states.module_disabled') }}
            </strong>
        </div>
    @else
        <form method="get" action="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="q">{{ __('govuk_alpha.listings.search_label') }}</label>
                        <input class="govuk-input" id="q" name="q" value="{{ $filters['search'] ?? '' }}">
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
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
            <a class="govuk-link govuk-!-margin-left-3" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
        </form>

        <p class="govuk-body">{{ trans_choice('govuk_alpha.listings.result_count', (int) ($meta['total_items'] ?? 0), ['count' => (int) ($meta['total_items'] ?? 0)]) }}</p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                </div>
            </div>
        @elseif (empty($items))
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.listings.empty') }}</p>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $listing)
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-m">
                            <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ $listing['title'] }}</a>
                        </h2>
                        <p class="govuk-body nexus-alpha-meta">{{ __('govuk_alpha.listings.posted_by', ['name' => $listing['user']['name'] ?? $listing['author_name'] ?? '']) }}</p>
                        <p class="govuk-body">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($listing['description'] ?? '')), 220) }}</p>
                        <p class="govuk-body-s nexus-alpha-meta">
                            {{ __('govuk_alpha.listings.type') }}: {{ __('govuk_alpha.listings.' . ($listing['type'] ?? 'offer')) }}
                            @if (!empty($listing['category_name']))
                                · {{ __('govuk_alpha.listings.category') }}: {{ $listing['category_name'] }}
                            @endif
                        </p>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']) && !empty($meta['cursor']))
                <p class="govuk-body govuk-!-margin-top-6">
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.listings.index', array_filter(['tenantSlug' => $tenantSlug, 'q' => $filters['search'] ?? null, 'type' => $filters['type'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'cursor' => $meta['cursor']])) }}">{{ __('govuk_alpha.actions.load_more') }}</a>
                </p>
            @endif
        @endif
    @endif
@endsection
