{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $articles = $articles ?? [];
        $searchQuery = $searchQuery ?? '';
        $hasMore = $hasMore ?? false;
        $nextCursor = $nextCursor ?? null;
        $nextHref = null;
        if ($hasMore && $nextCursor) {
            $nextHref = route('govuk-alpha.kb.index', array_filter([
                'tenantSlug' => $tenantSlug,
                'q' => $searchQuery !== '' ? $searchQuery : null,
                'cursor' => $nextCursor,
            ]));
        }
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.kb.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.kb.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.kb.subtitle', ['name' => $communityName]) }}</p>

            <form method="get" action="{{ route('govuk-alpha.kb.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.kb.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.kb.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="q-hint">
                </div>
                <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('govuk_alpha.kb.search_button') }}</button>
            </form>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.kb.results_title') }}</h2>

            @if (empty($articles))
                <div class="govuk-inset-text">
                    {{ $searchQuery !== '' ? __('govuk_alpha.kb.no_results') : __('govuk_alpha.kb.empty') }}
                </div>
            @else
                <ul class="govuk-list nexus-alpha-card-list">
                    @foreach ($articles as $article)
                        <li class="nexus-alpha-card">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                                <a class="govuk-link" href="{{ route('govuk-alpha.kb.show', ['tenantSlug' => $tenantSlug, 'id' => $article['id']]) }}">{{ $article['title'] ?? '' }}</a>
                            </h3>
                            @if (! empty($article['content_preview']))
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ $article['content_preview'] }}</p>
                            @endif
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                                @if (! empty($article['category_name'])){{ $article['category_name'] }} · @endif
                                {{ trans_choice('govuk_alpha.kb.views', (int) ($article['views_count'] ?? 0), ['count' => (int) ($article['views_count'] ?? 0)]) }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                @if ($nextHref)
                    <nav class="govuk-pagination govuk-pagination--block" role="navigation" aria-label="{{ __('govuk_alpha.kb.more_results_label') }}">
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
