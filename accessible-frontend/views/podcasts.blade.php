{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $podcastQuery = trim((string) ($podcastQuery ?? ''));
        $podcastSort  = trim((string) ($podcastSort ?? ''));
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.podcasts.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.podcasts.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.podcasts.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.podcasts.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.polish_commerce.podcast_search_label') }}</label>
                    <div id="podcast-q-hint" class="govuk-hint">{{ __('govuk_alpha.polish_commerce.podcast_search_hint') }}</div>
                    <input class="govuk-input" id="q" name="q" type="search" value="{{ $podcastQuery }}" aria-describedby="podcast-q-hint">
                </div>
            </div>
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="sort">{{ __('govuk_alpha.polish_commerce.podcast_sort_label') }}</label>
                    <select class="govuk-select" id="sort" name="sort">
                        <option value="newest" {{ ($podcastSort === 'newest' || $podcastSort === '') ? 'selected' : '' }}>{{ __('govuk_alpha.polish_commerce.podcast_sort_newest') }}</option>
                        <option value="title" {{ $podcastSort === 'title' ? 'selected' : '' }}>{{ __('govuk_alpha.polish_commerce.podcast_sort_title') }}</option>
                        <option value="episodes" {{ $podcastSort === 'episodes' ? 'selected' : '' }}>{{ __('govuk_alpha.polish_commerce.podcast_sort_episodes') }}</option>
                        <option value="followers" {{ $podcastSort === 'followers' ? 'selected' : '' }}>{{ __('govuk_alpha.polish_commerce.podcast_sort_followers') }}</option>
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_commerce.podcast_search_submit') }}</button>
    </form>

    @if (empty($shows))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.podcasts.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($shows as $s)
                @php
                    $sTitle  = trim((string) ($s['title'] ?? '')) ?: __('govuk_alpha.podcasts.title');
                    $owner   = trim((string) ($s['owner']['name'] ?? ''));
                    $count   = (int) ($s['approved_episode_count'] ?? ($s['episodes_count'] ?? 0));
                    $artwork = $asUrl(trim((string) ($s['artwork_url'] ?? '')));
                @endphp
                <article class="nexus-alpha-card">
                    @if ($artwork !== '')
                        <img class="nexus-alpha-avatar" src="{{ $artwork }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                    @endif
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.podcasts.show', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}">{{ $sTitle }}</a></h2>
                    @if (trim((string) ($s['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit(strip_tags((string) $s['description']), 160) }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        @if ($owner !== ''){{ __('govuk_alpha.podcasts.by_label', ['name' => $owner]) }} · @endif{{ __('govuk_alpha.podcasts.episodes_count', ['count' => $count]) }}
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
