{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hashtags = $hashtags ?? [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_feed.hashtags.back_to_feed') }}</a>

    @if (!empty($error))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1" role="alert">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_feed.states.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <p class="govuk-body">{{ $error }}</p>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_feed.hashtags.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_feed.hashtags.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_feed.hashtags.subtitle') }}</p>

    <form method="get" action="{{ route('govuk-alpha.feed.hashtags', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="hashtag-search">{{ __('govuk_alpha_feed.hashtags.search_label') }}</label>
            <div id="hashtag-search-hint" class="govuk-hint">{{ __('govuk_alpha_feed.hashtags.search_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="hashtag-search" name="q" type="text" value="{{ $searchQuery ?? '' }}" aria-describedby="hashtag-search-hint" maxlength="100">
        </div>
        <div class="govuk-button-group">
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_feed.hashtags.search_button') }}</button>
            @if ($isSearching ?? false)
                <a class="govuk-link" href="{{ route('govuk-alpha.feed.hashtags', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_feed.hashtags.clear_search') }}</a>
            @endif
        </div>
    </form>

    <h2 class="govuk-heading-l">
        {{ ($isSearching ?? false) ? __('govuk_alpha_feed.hashtags.results_heading') : __('govuk_alpha_feed.hashtags.trending_heading') }}
    </h2>

    @if (empty($hashtags))
        <p class="govuk-body">
            @if ($isSearching ?? false)
                {{ __('govuk_alpha_feed.hashtags.empty_search', ['query' => $searchQuery ?? '']) }}
            @else
                {{ __('govuk_alpha_feed.hashtags.empty_trending') }}
            @endif
        </p>
    @else
        <ul class="nexus-alpha-card-list govuk-list">
            @foreach ($hashtags as $hashtag)
                @php
                    $tagName = (string) ($hashtag['tag'] ?? '');
                    $tagCount = (int) ($hashtag['post_count'] ?? 0);
                @endphp
                @if ($tagName !== '')
                    <li class="nexus-alpha-card nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.feed.hashtag', ['tenantSlug' => $tenantSlug, 'tag' => $tagName]) }}">
                                #{{ $tagName }}
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_feed.hashtags.view_tag', ['tag' => '#' . $tagName]) }}</span>
                            </a>
                        </h3>
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                            {{ trans_choice('govuk_alpha_feed.hashtags.post_count', $tagCount, ['count' => $tagCount]) }}
                        </p>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
@endsection
