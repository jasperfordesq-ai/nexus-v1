{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-l">{{ __('govuk_alpha.service_name') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.feed.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.feed.description') }}</p>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" role="region" aria-labelledby="auth-required-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="auth-required-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
            </div>
        </div>
    @endif

    @if ($status === 'post-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="post-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="post-created-title">{{ __('govuk_alpha.states.post_created') }}</h2>
            </div>
        </div>
    @elseif ($status === 'post-empty' || $status === 'post-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ $status === 'post-empty' ? __('govuk_alpha.states.post_empty') : __('govuk_alpha.states.post_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (!$requiresAuth)
        <form method="post" action="{{ route('govuk-alpha.feed.posts.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="content">{{ __('govuk_alpha.feed.post_label') }}</label>
                <div id="content-hint" class="govuk-hint">{{ __('govuk_alpha.feed.post_hint') }}</div>
                <textarea class="govuk-textarea" id="content" name="content" rows="4" aria-describedby="content-hint"></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.post') }}</button>
        </form>
    @endif

    <form method="get" action="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6" data-alpha-auto-submit>
        <div class="govuk-form-group">
            <label class="govuk-label" for="type">{{ __('govuk_alpha.feed.filter_label') }}</label>
            <select class="govuk-select" id="type" name="type">
                @foreach (['all', 'posts', 'listings', 'events', 'goals', 'polls'] as $type)
                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ __('govuk_alpha.feed.types.' . $type) }}</option>
                @endforeach
            </select>
        </div>
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.apply_filters') }}</button>
    </form>

    @if ($error)
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
        </div>
    @elseif (empty($items))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha.feed.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($items as $item)
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m">{{ $item['title'] ?? __('govuk_alpha.feed.types.' . ($item['type'] ?? 'posts')) }}</h2>
                    <p class="govuk-body nexus-alpha-meta">
                        {{ __('govuk_alpha.feed.posted_by', ['name' => $item['author']['name'] ?? $tenant['name'] ?? __('govuk_alpha.service_name')]) }}
                    </p>
                    @if (!empty($item['content']))
                        <p class="govuk-body">{{ $item['content'] }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ trans_choice('govuk_alpha.feed.likes', (int) ($item['likes_count'] ?? 0), ['count' => (int) ($item['likes_count'] ?? 0)]) }}
                        ·
                        {{ trans_choice('govuk_alpha.feed.comments', (int) ($item['comments_count'] ?? 0), ['count' => (int) ($item['comments_count'] ?? 0)]) }}
                    </p>
                </article>
            @endforeach
        </div>
        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <p class="govuk-body govuk-!-margin-top-6">
                <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'type' => $selectedType, 'cursor' => $meta['cursor']]) }}">{{ __('govuk_alpha.actions.load_more') }}</a>
            </p>
        @endif
    @endif
@endsection
