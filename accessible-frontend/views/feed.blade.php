{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasPostError = in_array($status ?? '', ['post-empty', 'post-failed'], true);
        $postErrorMessage = ($status ?? '') === 'post-failed' ? __('govuk_alpha.states.post_failed') : __('govuk_alpha.states.post_empty');
        $successStatuses = ['post-created', 'like-added', 'like-removed', 'comment-created'];
        $errorStatuses = ['comment-empty', 'comment-too-long', 'comment-failed', 'like-failed'];
        $hasItems = !empty($items);
        $visibleCount = count($items);
        $typeOptions = ['all', 'following', 'saved', 'posts', 'listings', 'events', 'goals', 'polls', 'jobs', 'challenges', 'volunteering', 'blogs', 'discussions'];
        $commentableTypes = ['post', 'listing', 'event', 'goal', 'poll', 'review', 'volunteer', 'challenge', 'job', 'blog', 'discussion', 'resource'];
        $feedItemType = fn (?string $type): string => match ($type) {
            'listing' => 'govuk-tag--blue',
            'event' => 'govuk-tag--green',
            'goal' => 'govuk-tag--purple',
            'poll' => 'govuk-tag--yellow',
            default => 'govuk-tag--grey',
        };
        $feedItemTypeLabel = fn (?string $type): string => \Illuminate\Support\Facades\Lang::has('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            ? __('govuk_alpha.feed.item_types.' . ($type ?: 'post'))
            : __('govuk_alpha.feed.item_types.activity');
        $preservedFeedInputs = array_filter([
            'type' => $selectedType,
            'mode' => $selectedMode,
            'subtype' => $selectedSubtype,
            'per_page' => $meta['per_page'] ?? null,
            'cursor' => request()->query('cursor'),
        ], fn ($value) => $value !== null && $value !== '');
        $nextFeedUrl = !empty($meta['cursor'])
            ? route('govuk-alpha.feed', array_filter([
                'tenantSlug' => $tenantSlug,
                'type' => $selectedType,
                'mode' => $selectedMode === 'recent' ? 'recent' : null,
                'subtype' => $selectedSubtype,
                'cursor' => $meta['cursor'],
                'per_page' => $meta['per_page'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''))
            : null;
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.feed.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.feed.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.feed.description') }}</p>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" role="region" aria-labelledby="feed-auth-required-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="feed-auth-required-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.feed.auth_required_detail', ['community' => $communityName]) }}</p>
                <div class="nexus-alpha-actions">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @endif

    @if (in_array($status, $successStatuses, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="post-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="post-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.' . $status) }}</p>
            </div>
        </div>
    @elseif ($status === 'post-empty')
        <div class="govuk-notification-banner" role="region" aria-labelledby="post-empty-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="post-empty-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.post_empty') }}</p>
            </div>
        </div>
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#content">{{ __('govuk_alpha.states.post_empty') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'post-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#content">{{ __('govuk_alpha.states.post_failed') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif (in_array($status, $errorStatuses, true))
        <div class="govuk-notification-banner" role="region" aria-labelledby="feed-action-error-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="feed-action-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.' . $status) }}</p>
            </div>
        </div>
    @endif

    @if (!$requiresAuth)
        <form method="post" action="{{ route('govuk-alpha.feed.posts.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            @csrf
            <div class="govuk-form-group{{ $hasPostError ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--m" for="content">{{ __('govuk_alpha.feed.post_label') }}</label>
                <div id="content-hint" class="govuk-hint">{{ __('govuk_alpha.feed.post_hint') }}</div>
                @if ($hasPostError)
                    <p id="content-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $postErrorMessage }}
                    </p>
                @endif
                <textarea class="govuk-textarea{{ $hasPostError ? ' govuk-textarea--error' : '' }}" id="content" name="content" rows="4" aria-describedby="content-hint{{ $hasPostError ? ' content-error' : '' }}"></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.post') }}</button>
        </form>
    @endif

    <form method="get" action="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6" data-alpha-auto-submit>
        <fieldset class="govuk-fieldset" aria-describedby="feed-filter-hint">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.feed.filters_title') }}</h2>
            </legend>
            <div id="feed-filter-hint" class="govuk-hint">{{ __('govuk_alpha.feed.filters_hint') }}</div>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="type">{{ __('govuk_alpha.feed.filter_label') }}</label>
                        <select class="govuk-select" id="type" name="type">
                            @foreach ($typeOptions as $type)
                                <option value="{{ $type }}" @selected($selectedType === $type)>{{ __('govuk_alpha.feed.types.' . $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="mode">{{ __('govuk_alpha.feed.mode_label') }}</label>
                        <select class="govuk-select" id="mode" name="mode">
                            @foreach (['ranking', 'recent'] as $mode)
                                <option value="{{ $mode }}" @selected($selectedMode === $mode)>{{ __('govuk_alpha.feed.modes.' . $mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="subtype">{{ __('govuk_alpha.feed.subtype_label') }}</label>
                        <select class="govuk-select" id="subtype" name="subtype">
                            <option value="">{{ __('govuk_alpha.feed.subtypes.all') }}</option>
                            @foreach (['offer', 'request'] as $subtype)
                                <option value="{{ $subtype }}" @selected($selectedSubtype === $subtype)>{{ __('govuk_alpha.feed.subtypes.' . $subtype) }}</option>
                            @endforeach
                        </select>
                        <div class="govuk-hint">{{ __('govuk_alpha.feed.subtype_hint') }}</div>
                    </div>
                </div>
            </div>
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.apply_filters') }}</button>
        </fieldset>
    </form>

    @if ($error)
        <div class="govuk-notification-banner" role="region" aria-labelledby="feed-load-error-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="feed-load-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.feed.error_detail') }}</p>
            </div>
        </div>
    @elseif (!$hasItems)
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.feed.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.feed.result_count', 0, ['count' => 0]) }}
        </p>
        <div class="govuk-inset-text">
            <h3 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h3>
            <p class="govuk-body">{{ __('govuk_alpha.feed.empty') }}</p>
        </div>
    @else
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.feed.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.feed.result_count', $visibleCount, ['count' => $visibleCount]) }}
        </p>
        <div class="nexus-alpha-card-list">
            @foreach ($items as $item)
                @php
                    $itemType = $item['type'] ?? 'post';
                    $itemId = (int) ($item['id'] ?? 0);
                    $itemTitle = $item['title'] ?? $feedItemTypeLabel($itemType);
                    $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha.feed.unknown_author');
                    $createdAt = !empty($item['created_at']) ? \Illuminate\Support\Carbon::parse($item['created_at']) : null;
                    $comments = $commentsByTarget[$itemType][$itemId] ?? [];
                    $commentCount = (int) ($item['comments_count'] ?? 0);
                    $likeCount = (int) ($item['likes_count'] ?? 0);
                    $isLiked = (bool) ($item['is_liked'] ?? false);
                    $isCommentable = in_array($itemType, $commentableTypes, true);
                    $detailUrl = ($itemType === 'listing' && !empty($item['id']))
                        ? route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']])
                        : null;
                @endphp
                <article class="nexus-alpha-card" id="feed-item-{{ preg_replace('/[^a-z0-9_-]/i', '-', $itemType) }}-{{ $itemId }}">
                    <div class="nexus-alpha-feed-row">
                        <div>
                            <strong class="govuk-tag {{ $feedItemType($itemType) }}">{{ $feedItemTypeLabel($itemType) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">{{ $itemTitle }}</h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                                {{ __('govuk_alpha.feed.posted_by', ['name' => $authorName]) }}
                                @if ($createdAt)
                                    <span aria-hidden="true"> | </span>
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.posted_on_prefix') }}</span>
                                    <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->translatedFormat('j F Y, H:i') }}</time>
                                @endif
                            </p>
                        </div>
                        @if ($detailUrl)
                            <div class="nexus-alpha-feed-row__action">
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ $detailUrl }}">
                                    {{ __('govuk_alpha.actions.view_details') }}
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.detail_for', ['title' => $itemTitle]) }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                    @if (!empty($item['content']))
                        @php
                            $plainContent = trim(html_entity_decode(strip_tags((string) $item['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                            $contentParagraphs = preg_split('/\R{2,}/u', $plainContent) ?: [];
                        @endphp
                        @foreach ($contentParagraphs as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p class="govuk-body">{!! nl2br(e(trim($paragraph))) !!}</p>
                            @endif
                        @endforeach
                    @endif
                    @if (!empty($item['image_url']))
                        <p class="govuk-body">
                            <img src="{{ $item['image_url'] }}" alt="{{ __('govuk_alpha.feed.image_alt', ['title' => $itemTitle]) }}" class="nexus-alpha-feed-image" loading="lazy">
                        </p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ trans_choice('govuk_alpha.feed.likes', $likeCount, ['count' => $likeCount]) }}
                        ·
                        {{ trans_choice('govuk_alpha.feed.comments', $commentCount, ['count' => $commentCount]) }}
                    </p>
                    @if (!$requiresAuth)
                        <div class="nexus-alpha-actions govuk-!-margin-bottom-3">
                            <form method="post" action="{{ route('govuk-alpha.feed.items.like', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]) }}">
                                @csrf
                                @foreach ($preservedFeedInputs as $name => $value)
                                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                @endforeach
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                                    {{ $isLiked ? __('govuk_alpha.actions.unlike') : __('govuk_alpha.actions.like') }}
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.action_for', ['title' => $itemTitle]) }}</span>
                                </button>
                            </form>
                        </div>
                    @endif
                    @if ($isCommentable)
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details" @if (($status ?? '') === 'comment-created') open @endif>
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.comments_summary') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                @if (!empty($comments))
                                    @include('accessible-frontend::partials.feed-comments', ['comments' => $comments, 'depth' => 0])
                                @else
                                    <p class="govuk-body">{{ __('govuk_alpha.feed.no_comments') }}</p>
                                @endif
                                @if (!$requiresAuth)
                                    <form method="post" action="{{ route('govuk-alpha.feed.items.comments.store', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]) }}">
                                        @csrf
                                        @foreach ($preservedFeedInputs as $name => $value)
                                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                        @endforeach
                                        <div class="govuk-form-group">
                                            <label class="govuk-label" for="comment-{{ $itemType }}-{{ $itemId }}">{{ __('govuk_alpha.feed.comment_label') }}</label>
                                            <textarea class="govuk-textarea" id="comment-{{ $itemType }}-{{ $itemId }}" name="content" rows="3"></textarea>
                                        </div>
                                        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.comment') }}</button>
                                    </form>
                                @endif
                            </div>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>
        @if (!empty($meta['has_more']) && $nextFeedUrl)
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.feed.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ $nextFeedUrl }}" rel="next">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                        <span class="govuk-visually-hidden">:</span>
                        <span class="govuk-pagination__link-label">{{ __('govuk_alpha.feed.more_results_label') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
