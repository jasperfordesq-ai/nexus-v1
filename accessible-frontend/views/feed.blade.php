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
        $successStatuses = ['post-created', 'like-added', 'like-removed', 'comment-created', 'poll-voted', 'post-updated', 'post-deleted', 'comment-updated', 'comment-deleted', 'content-hidden', 'author-muted', 'content-reported', 'reaction-added', 'reaction-removed', 'share-added', 'share-removed', 'save-added', 'save-removed'];
        $errorStatuses = ['comment-empty', 'comment-too-long', 'comment-failed', 'like-failed', 'poll-vote-failed', 'post-update-failed', 'post-delete-failed', 'comment-update-failed', 'comment-delete-failed', 'moderation-failed', 'reaction-failed', 'share-failed', 'save-failed', 'share-own'];
        // WAVE T1-FEED statuses keep their message under the feed_t1 namespace.
        $t1StatusKeys = ['reaction-added', 'reaction-removed', 'reaction-failed', 'share-added', 'share-removed', 'share-failed', 'share-own', 'save-added', 'save-removed', 'save-failed'];
        $statusMessage = fn (?string $s): string => in_array($s, $t1StatusKeys, true)
            ? __('govuk_alpha.feed_t1.status_' . str_replace('-', '_', (string) $s))
            : __('govuk_alpha.states.' . $s);
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

    <p class="govuk-body govuk-!-margin-bottom-6">
        <a class="govuk-link" href="{{ route('govuk-alpha.feed.hashtags', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_feed.nav.hashtags') }}</a>
    </p>

    @if ($requiresAuth)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="feed-auth-required-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="feed-auth-required-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.feed.auth_required_detail', ['community' => $communityName]) }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @endif

    @if (in_array($status, $successStatuses, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="post-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="post-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessage($status) }}</p>
            </div>
        </div>
    @elseif ($status === 'post-empty')
        {{-- Only the error-summary (matches the post-failed branch); the duplicate
             notification banner announced the same error twice. --}}
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#content">{{ __('govuk_alpha.polish_discovery.feed_error_link_text') }}</a> — {{ __('govuk_alpha.states.post_empty') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'post-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#content">{{ __('govuk_alpha.polish_discovery.feed_error_link_text') }}</a> — {{ __('govuk_alpha.states.post_failed') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif (in_array($status, $errorStatuses, true))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="feed-action-error-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="feed-action-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessage($status) }}</p>
            </div>
        </div>
    @endif

    @if (!$requiresAuth)
        @if (\App\Core\TenantContext::hasModule('listings'))
            {{-- The feed compose box is for community updates, NOT offers/requests —
                 those are listings, an entirely separate mechanism. Make that obvious
                 and route people to the listing form. --}}
            <div class="govuk-inset-text">
                <p class="govuk-body">{{ __('govuk_alpha.feed.compose_listing_notice') }}</p>
                <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" role="button" draggable="false" data-module="govuk-button" href="{{ route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.feed.post_offer_request') }}</a>
            </div>
        @endif
        <form method="post" action="{{ route('govuk-alpha.feed.posts.store', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" class="govuk-!-margin-bottom-7">
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
            <div class="govuk-form-group">
                <label class="govuk-label" for="image">{{ __('govuk_alpha.feed.image_label') }}</label>
                <div id="image-hint" class="govuk-hint">{{ __('govuk_alpha.feed.image_hint') }}</div>
                <input class="govuk-file-upload" id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="image-hint">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="image_alt">{{ __('govuk_alpha.feed.image_alt_label') }}</label>
                <div id="image-alt-hint" class="govuk-hint">{{ __('govuk_alpha.feed.image_alt_hint') }}</div>
                <input class="govuk-input" id="image_alt" name="image_alt" type="text" maxlength="500" aria-describedby="image-alt-hint">
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
                        <div id="subtype-hint" class="govuk-hint">{{ __('govuk_alpha.feed.subtype_hint') }}</div>
                        <select class="govuk-select" id="subtype" name="subtype" aria-describedby="subtype-hint">
                            <option value="">{{ __('govuk_alpha.feed.subtypes.all') }}</option>
                            @foreach (['offer', 'request'] as $subtype)
                                <option value="{{ $subtype }}" @selected($selectedSubtype === $subtype)>{{ __('govuk_alpha.feed.subtypes.' . $subtype) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.apply_filters') }}</button>
        </fieldset>
    </form>

    @if ($error)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="feed-load-error-title">
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
                    // Link typed cards through to the accessible detail page for that
                    // module ($itemId is the feed source_id = the source entity id).
                    // post/poll are interactive in-feed; blog/discussion need a slug or
                    // group context the feed row does not carry, so they stay in-feed.
                    $detailUrl = $itemId > 0 ? match ($itemType) {
                        'listing' => route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'event' => route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'volunteer' => route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'goal' => route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'job' => route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'challenge' => route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        'course' => route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                        default => null,
                    } : null;
                    // Self-describing call-to-action ("View this listing") so the link
                    // purpose is clear out of context (WCAG 2.4.4); falls back to a
                    // generic label for any typed item without a bespoke string.
                    $viewTypedLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha.feed.view_typed.' . $itemType)
                        ? __('govuk_alpha.feed.view_typed.' . $itemType)
                        : __('govuk_alpha.actions.view_details');
                    $authorAvatar = $item['author']['avatar_url'] ?? null;
                    $authorId = (int) ($item['author']['id'] ?? 0);
                    $isOwnPost = $itemType === 'post' && !$requiresAuth && ($currentUserId ?? 0) > 0 && $authorId === (int) $currentUserId;
                @endphp
                <article class="nexus-alpha-card" id="feed-item-{{ preg_replace('/[^a-z0-9_-]/i', '-', $itemType) }}-{{ $itemId }}">
                    <div class="nexus-alpha-feed-row">
                        <div>
                            <strong class="govuk-tag {{ $feedItemType($itemType) }}">{{ $feedItemTypeLabel($itemType) }}</strong>
                            <h3 class="govuk-heading-m govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                                @if ($detailUrl)<a class="govuk-link" href="{{ $detailUrl }}">{{ $itemTitle }}</a>@else{{ $itemTitle }}@endif
                            </h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                                @if (!empty($authorAvatar))
                                    <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $authorAvatar }}" alt="" loading="lazy" decoding="async" width="32" height="32">
                                @endif
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
                                <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" role="button" draggable="false" data-module="govuk-button" href="{{ $detailUrl }}">
                                    {{ $viewTypedLabel }}<span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.detail_for', ['title' => $itemTitle]) }}</span>
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
                    @php
                        $itemMedia = $item['media'] ?? [];
                        if (empty($itemMedia) && !empty($item['image_url'])) {
                            $itemMedia = [['file_url' => $item['image_url'], 'thumbnail_url' => null, 'alt_text' => null]];
                        }
                        $totalMedia = count($itemMedia);
                        $visibleMedia = array_slice($itemMedia, 0, 4);
                        $extraMedia = max(0, $totalMedia - 4);
                        $gridCount = min($totalMedia, 4);
                    @endphp
                    @if (!empty($visibleMedia))
                        <ul class="nexus-alpha-feed-media" data-count="{{ $gridCount }}">
                            @foreach ($visibleMedia as $mediaIndex => $media)
                                @php
                                    $fullUrl = $media['file_url'] ?? null;
                                    $thumbUrl = $media['thumbnail_url'] ?? $fullUrl;
                                    $altText = !empty($media['alt_text'])
                                        ? $media['alt_text']
                                        : __('govuk_alpha.feed.image_alt', ['title' => $itemTitle]);
                                    $isLast = $mediaIndex === count($visibleMedia) - 1;
                                @endphp
                                @if ($fullUrl)
                                    <li>
                                        <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer"><span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span>
                                            <img src="{{ $thumbUrl }}" alt="{{ $altText }}" class="nexus-alpha-feed-image" loading="lazy">
                                            @if ($isLast && $extraMedia > 0)
                                                <span class="nexus-alpha-feed-media__more" aria-hidden="true">+{{ $extraMedia }}</span>
                                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.media_more', ['count' => $extraMedia]) }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                    @if ($itemType === 'poll' && is_array($item['poll_data'] ?? null))
                        @php
                            $poll = $item['poll_data'];
                            $pollOptions = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                            $myVote = $poll['user_vote_option_id'] ?? null;
                            $pollActive = (bool) ($poll['is_active'] ?? false);
                            $pollTotal = $poll['total_votes'] ?? null;
                        @endphp
                        @if (!empty($pollOptions))
                            @if ($myVote === null && $pollActive && !$requiresAuth)
                                <form method="post" action="{{ route('govuk-alpha.feed.polls.vote', ['tenantSlug' => $tenantSlug, 'pollId' => $itemId]) }}" class="govuk-!-margin-bottom-3">
                                    @csrf
                                    @foreach ($preservedFeedInputs as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <fieldset class="govuk-fieldset">
                                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.feed.poll_vote_legend') }}</legend>
                                        <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                            @foreach ($pollOptions as $opt)
                                                @php $optId = (int) ($opt['id'] ?? 0); @endphp
                                                @if ($optId > 0)
                                                    <div class="govuk-radios__item">
                                                        <input class="govuk-radios__input" id="poll-{{ $itemId }}-opt-{{ $optId }}" name="option_id" type="radio" value="{{ $optId }}" required>
                                                        <label class="govuk-label govuk-radios__label" for="poll-{{ $itemId }}-opt-{{ $optId }}">{{ $opt['text'] ?? $opt['label'] ?? '' }}</label>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.poll_vote_submit') }}</button>
                                </form>
                            @else
                                <ul class="govuk-list govuk-!-margin-bottom-2">
                                    @foreach ($pollOptions as $opt)
                                        @php
                                            $optId = (int) ($opt['id'] ?? 0);
                                            $voted = $myVote !== null && (int) $myVote === $optId;
                                            $count = $opt['vote_count'] ?? null;
                                            $pct = $opt['percentage'] ?? null;
                                        @endphp
                                        @php $optLabel = $opt['text'] ?? $opt['label'] ?? ''; @endphp
                                        <li>
                                            <span class="govuk-!-font-weight-bold">{{ $optLabel }}</span>
                                            @if ($voted)
                                                <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.feed.poll_your_choice') }}</strong>
                                            @endif
                                            @if ($count !== null)
                                                <span class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha.feed.poll_votes', (int) $count, ['count' => (int) $count]) }}@if ($pct !== null) ({{ (int) round((float) $pct) }}%)@endif</span>
                                            @endif
                                            @if ($pct !== null)
                                                @php $pctRounded = max(0, min(100, (int) round((float) $pct))); @endphp
                                                <progress class="nexus-alpha-poll-bar" max="100" value="{{ $pctRounded }}" aria-label="{{ __('govuk_alpha.feed.poll_result_share', ['option' => $optLabel, 'percent' => $pctRounded]) }}">{{ $pctRounded }}%</progress>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                                    @if ($myVote !== null){{ __('govuk_alpha.feed.poll_you_voted') }}@elseif (!$pollActive){{ __('govuk_alpha.feed.poll_closed') }}@endif
                                </p>
                            @endif
                        @endif
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ trans_choice('govuk_alpha.feed.likes', $likeCount, ['count' => $likeCount]) }}
                        ·
                        {{ trans_choice('govuk_alpha.feed.comments', $commentCount, ['count' => $commentCount]) }}
                    </p>
                    @if (!$requiresAuth)
                        <div class="govuk-button-group govuk-!-margin-bottom-3">
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
                        @php
                            $itemReactions = $reactionsByTarget[$itemType][$itemId] ?? null;
                        @endphp
                        @if ($itemType === 'post' && $itemReactions !== null)
                            @include('accessible-frontend::partials.feed-reactions', [
                                'reactionAction' => route('govuk-alpha.feed.posts.react', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                                'alphaReactions' => $alphaReactions,
                                'reactionLegend' => __('govuk_alpha.feed_t1.reactions_legend'),
                                'reactionTargetLabel' => __('govuk_alpha.feed_t1.reaction_for', ['name' => $authorName]),
                                'reactionCounts' => $itemReactions['counts'] ?? [],
                                'userReactionTypes' => !empty($itemReactions['user_reaction']) ? [$itemReactions['user_reaction']] : [],
                                'reactionPreserved' => $preservedFeedInputs,
                            ])
                        @endif
                        @if ($itemType === 'post')
                            @include('accessible-frontend::partials.feed-post-engagement', [
                                'engagementPostId' => $itemId,
                                'engagementTitle' => $itemTitle,
                                'engagementTenant' => $tenantSlug,
                                'engagementShared' => (bool) ($item['is_shared'] ?? false),
                                'engagementSaved' => (bool) ($item['is_bookmarked'] ?? false),
                                'engagementShareCount' => (int) ($item['share_count'] ?? 0),
                                'engagementOwn' => $isOwnPost,
                                'engagementPreserved' => $preservedFeedInputs,
                            ])
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.feed.posts.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}">
                                    {{ __('govuk_alpha.feed_t1.view_post') }}
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed_t1.view_post_for', ['name' => $authorName]) }}</span>
                                </a>
                            </p>
                        @endif
                        @if ($isOwnPost)
                            <div class="govuk-!-margin-bottom-3">
                                <details class="govuk-details govuk-!-margin-bottom-2" data-module="govuk-details">
                                    <summary class="govuk-details__summary">
                                        <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.edit_post') }}</span>
                                    </summary>
                                    <div class="govuk-details__text">
                                        <form method="post" action="{{ route('govuk-alpha.feed.posts.update', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}">
                                            @csrf
                                            <div class="govuk-form-group">
                                                <label class="govuk-label" for="edit-post-{{ $itemId }}">{{ __('govuk_alpha.feed.edit_post_label') }}</label>
                                                <div id="edit-post-{{ $itemId }}-hint" class="govuk-hint">{{ __('govuk_alpha.feed.edit_post_hint') }}</div>
                                                <textarea class="govuk-textarea" id="edit-post-{{ $itemId }}" name="content" rows="4" aria-describedby="edit-post-{{ $itemId }}-hint" required>{{ $item['content'] ?? '' }}</textarea>
                                            </div>
                                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.actions.save_changes') }}</button>
                                        </form>
                                    </div>
                                </details>
                                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                                    <summary class="govuk-details__summary">
                                        <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.delete_post') }}</span>
                                    </summary>
                                    <div class="govuk-details__text">
                                        <div class="govuk-warning-text">
                                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                            <strong class="govuk-warning-text__text">
                                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                                                {{ __('govuk_alpha.polish_discovery.delete_post_warning') }}
                                            </strong>
                                        </div>
                                        <form method="post" action="{{ route('govuk-alpha.feed.posts.delete', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}">
                                            @csrf
                                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.delete_post_button') }}</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        @elseif (!$requiresAuth && ($currentUserId ?? 0) > 0 && $authorId !== (int) ($currentUserId ?? 0))
                            <div class="govuk-!-margin-bottom-3">
                                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                                    <summary class="govuk-details__summary">
                                        <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.moderation_summary') }}</span>
                                    </summary>
                                    <div class="govuk-details__text">
                                        <form method="post" action="{{ route('govuk-alpha.feed.hide', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}" class="govuk-!-margin-bottom-4">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $itemType }}">
                                            <p class="govuk-body govuk-!-margin-bottom-2">{{ __('govuk_alpha.feed.hide_hint') }}</p>
                                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.hide_button') }}</button>
                                        </form>
                                        @if ($authorId > 0)
                                            <form method="post" action="{{ route('govuk-alpha.feed.mute', ['tenantSlug' => $tenantSlug, 'id' => $authorId]) }}" class="govuk-!-margin-bottom-4">
                                                @csrf
                                                <p class="govuk-body govuk-!-margin-bottom-2">{{ __('govuk_alpha.feed.mute_hint', ['name' => $authorName]) }}</p>
                                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.mute_button') }}<span class="govuk-visually-hidden"> {{ $authorName }}</span></button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('govuk-alpha.feed.report', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $itemType }}">
                                            <div class="govuk-form-group govuk-!-margin-bottom-2">
                                                <label class="govuk-label" for="report-reason-{{ $itemType }}-{{ $itemId }}">{{ __('govuk_alpha.feed.report_reason_label') }}</label>
                                                <div class="govuk-hint" id="report-hint-{{ $itemType }}-{{ $itemId }}">{{ __('govuk_alpha.feed.report_hint') }}</div>
                                                <input class="govuk-input" id="report-reason-{{ $itemType }}-{{ $itemId }}" name="reason" type="text" maxlength="500" aria-describedby="report-hint-{{ $itemType }}-{{ $itemId }}" required>
                                            </div>
                                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.report_button') }}</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        @endif
                    @endif
                    @if ($isCommentable)
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details" @if (($status ?? '') === 'comment-created') open @endif>
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.comments_summary') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                @if (!empty($comments))
                                    @include('accessible-frontend::partials.feed-comments', [
                                        'comments' => $comments,
                                        'depth' => 0,
                                        'targetType' => $itemType,
                                        'targetId' => $itemId,
                                        'tenantSlug' => $tenantSlug,
                                        'requiresAuth' => $requiresAuth,
                                        'currentUserId' => $currentUserId ?? null,
                                        'preservedFeedInputs' => $preservedFeedInputs,
                                        'alphaReactions' => $alphaReactions,
                                    ])
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
                                            <div id="comment-{{ $itemType }}-{{ $itemId }}-hint" class="govuk-hint">{{ __('govuk_alpha.feed.comment_hint') }}</div>
                                            <textarea class="govuk-textarea" id="comment-{{ $itemType }}-{{ $itemId }}" name="content" rows="3" aria-describedby="comment-{{ $itemType }}-{{ $itemId }}-hint"></textarea>
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
