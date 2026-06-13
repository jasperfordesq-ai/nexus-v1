{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $posts = $posts ?? [];
        $categories = $categories ?? [];
        $searchQuery = $searchQuery ?? '';
        $categoryId = $categoryId ?? null;
        $hasMore = $hasMore ?? false;
        $nextCursor = $nextCursor ?? null;
        $nextHref = null;
        if ($hasMore && $nextCursor) {
            $nextHref = route('govuk-alpha.blog.index', array_filter([
                'tenantSlug' => $tenantSlug,
                'q' => $searchQuery !== '' ? $searchQuery : null,
                'category' => $categoryId,
                'cursor' => $nextCursor,
            ]));
        }
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.blog.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.blog.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.blog.subtitle', ['name' => $communityName]) }}</p>

            <form method="get" action="{{ route('govuk-alpha.blog.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.blog.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.blog.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="q-hint">
                </div>
                @if (! empty($categories))
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="category">{{ __('govuk_alpha.blog.category_label') }}</label>
                        <select class="govuk-select" id="category" name="category">
                            <option value="">{{ __('govuk_alpha.blog.all_categories') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}" @selected((string) $categoryId === (string) $category['id'])>{{ $category['name'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('govuk_alpha.blog.search_button') }}</button>
            </form>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.blog.results_title') }}</h2>

            @if (empty($posts))
                <div class="govuk-inset-text">
                    {{ ($searchQuery !== '' || $categoryId) ? __('govuk_alpha.blog.no_results') : __('govuk_alpha.blog.empty') }}
                </div>
            @else
                <ul class="govuk-list nexus-alpha-card-list">
                    @foreach ($posts as $index => $post)
                        <li class="nexus-alpha-card">
                            @if (! empty($post['featured_image']))
                                <p class="govuk-!-margin-bottom-2">
                                    <img class="nexus-alpha-card-thumb" src="{{ $post['featured_image'] }}" alt="{{ __('govuk_alpha.blog.image_alt', ['title' => $post['title'] ?? '']) }}" width="120" height="90" loading="lazy" decoding="async">
                                </p>
                            @endif
                            @if ($index === 0)
                                <strong class="govuk-tag govuk-tag--blue govuk-!-margin-bottom-2">{{ __('govuk_alpha.blog.featured_label') }}</strong>
                            @endif
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-1">
                                <a class="govuk-link" href="{{ route('govuk-alpha.blog.show', ['tenantSlug' => $tenantSlug, 'slug' => $post['slug']]) }}">{{ $post['title'] ?? '' }}</a>
                            </h3>
                            @if (! empty($post['excerpt']))
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ $post['excerpt'] }}</p>
                            @endif
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                                @if (! empty($post['category']['name'])){{ $post['category']['name'] }} · @endif
                                @if (! empty($post['author']['name'])){{ $post['author']['name'] }} · @endif
                                @if (! empty($post['reading_time'])){{ __('govuk_alpha.blog.read_time', ['count' => (int) $post['reading_time']]) }}@endif
                            </p>
                        </li>
                    @endforeach
                </ul>

                @if ($nextHref)
                    <nav class="govuk-pagination govuk-pagination--block" role="navigation" aria-label="{{ __('govuk_alpha.blog.more_results_label') }}">
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="{{ $nextHref }}" rel="next">
                                <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.55 4.5488h-12.846v2h12.846l-4.55 4.5488 1.4136 1.414 6.9706-6.9627z"></path>
                                </svg>
                            </a>
                        </div>
                    </nav>
                @endif
            @endif
        </div>
    </div>
@endsection
