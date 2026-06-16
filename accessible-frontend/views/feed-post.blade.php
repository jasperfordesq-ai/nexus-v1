{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $itemId = (int) ($item['id'] ?? 0);
        $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha.feed.unknown_author');
        $authorAvatar = $item['author']['avatar_url'] ?? null;
        $authorId = (int) ($item['author']['id'] ?? 0);
        $createdAt = !empty($item['created_at']) ? \Illuminate\Support\Carbon::parse($item['created_at']) : null;
        $isOwnPost = !$requiresAuth && ($currentUserId ?? 0) > 0 && $authorId === (int) $currentUserId;
        $likeCount = (int) ($item['likes_count'] ?? 0);
        $commentCount = (int) ($item['comments_count'] ?? 0);
        $isLiked = (bool) ($item['is_liked'] ?? false);
        $reactionCounts = is_array($postReactions ?? null) ? ($postReactions['counts'] ?? []) : [];
        $userReaction = is_array($postReactions ?? null) ? ($postReactions['user_reaction'] ?? null) : null;
        $itemMedia = $item['media'] ?? [];
        if (empty($itemMedia) && !empty($item['image_url'])) {
            $itemMedia = [['file_url' => $item['image_url'], 'thumbnail_url' => null, 'alt_text' => null]];
        }
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.feed_t1.permalink_back') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha.feed.caption', ['community' => ($tenant['name'] ?? $tenantSlug)]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.feed_t1.permalink_heading') }}</h1>

    @php
        $successMessages = [
            'reaction-added'    => 'govuk_alpha.feed_t1.status_reaction_added',
            'reaction-removed'  => 'govuk_alpha.feed_t1.status_reaction_removed',
            'comment-created'   => 'govuk_alpha.states.comment-created',
            'comment-updated'   => 'govuk_alpha.states.comment-updated',
            'comment-deleted'   => 'govuk_alpha.states.comment-deleted',
            'share-added'       => 'govuk_alpha.feed_t1.status_share_added',
            'share-removed'     => 'govuk_alpha.feed_t1.status_share_removed',
            'save-added'        => 'govuk_alpha.feed_t1.status_save_added',
            'save-removed'      => 'govuk_alpha.feed_t1.status_save_removed',
        ];
    @endphp
    @if (isset($successMessages[$status ?? '']))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="permalink-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="permalink-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($successMessages[$status]) }}</p>
            </div>
        </div>
    @endif

    @if ($requiresAuth)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="permalink-auth-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="permalink-auth-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.auth_required') }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                </div>
            </div>
        </div>
    @endif

    <article class="nexus-alpha-card" id="feed-item-post-{{ $itemId }}">
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

        @if (!empty($itemMedia))
            <ul class="nexus-alpha-feed-media" data-count="{{ min(count($itemMedia), 4) }}">
                @foreach (array_slice($itemMedia, 0, 4) as $media)
                    @php
                        $fullUrl = $media['file_url'] ?? null;
                        $thumbUrl = $media['thumbnail_url'] ?? $fullUrl;
                        $altText = !empty($media['alt_text']) ? $media['alt_text'] : __('govuk_alpha.feed.image_alt', ['title' => __('govuk_alpha.feed_t1.permalink_heading')]);
                    @endphp
                    @if ($fullUrl)
                        <li>
                            <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $thumbUrl }}" alt="{{ $altText }}" class="nexus-alpha-feed-image" loading="lazy">
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        <p class="govuk-body-s nexus-alpha-meta">
            {{ trans_choice('govuk_alpha.feed.likes', $likeCount, ['count' => $likeCount]) }}
            ·
            {{ trans_choice('govuk_alpha.feed.comments', $commentCount, ['count' => $commentCount]) }}
        </p>

        @unless ($requiresAuth)
            <div class="nexus-alpha-actions govuk-!-margin-bottom-3">
                <form method="post" action="{{ route('govuk-alpha.feed.items.like', ['tenantSlug' => $tenantSlug, 'type' => 'post', 'id' => $itemId]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                        {{ $isLiked ? __('govuk_alpha.actions.unlike') : __('govuk_alpha.actions.like') }}
                    </button>
                </form>
            </div>

            @include('accessible-frontend::partials.feed-reactions', [
                'reactionAction' => route('govuk-alpha.feed.posts.react', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                'alphaReactions' => $alphaReactions,
                'reactionLegend' => __('govuk_alpha.feed_t1.reactions_legend'),
                'reactionTargetLabel' => __('govuk_alpha.feed_t1.reaction_for', ['name' => $authorName]),
                'reactionCounts' => $reactionCounts,
                'userReactionTypes' => !empty($userReaction) ? [$userReaction] : [],
                'reactionPreserved' => [],
            ])

            @include('accessible-frontend::partials.feed-post-engagement', [
                'engagementPostId' => $itemId,
                'engagementTitle' => __('govuk_alpha.feed_t1.permalink_heading'),
                'engagementTenant' => $tenantSlug,
                'engagementShared' => (bool) ($item['is_shared'] ?? false),
                'engagementSaved' => (bool) ($item['is_bookmarked'] ?? false),
                'engagementShareCount' => (int) ($item['share_count'] ?? 0),
                'engagementOwn' => $isOwnPost,
                'engagementPreserved' => [],
            ])
        @endunless

        <h2 class="govuk-heading-m govuk-!-margin-top-4">{{ __('govuk_alpha.feed.comments_summary') }}</h2>
        @if (!empty($comments))
            @include('accessible-frontend::partials.feed-comments', [
                'comments' => $comments,
                'depth' => 0,
                'targetType' => 'post',
                'targetId' => $itemId,
                'tenantSlug' => $tenantSlug,
                'requiresAuth' => $requiresAuth,
                'currentUserId' => $currentUserId ?? null,
                'preservedFeedInputs' => [],
                'alphaReactions' => $alphaReactions,
            ])
        @else
            <p class="govuk-body">{{ __('govuk_alpha.feed.no_comments') }}</p>
        @endif

        @unless ($requiresAuth)
            <form method="post" action="{{ route('govuk-alpha.feed.items.comments.store', ['tenantSlug' => $tenantSlug, 'type' => 'post', 'id' => $itemId]) }}">
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label" for="permalink-comment-{{ $itemId }}">{{ __('govuk_alpha.feed.comment_label') }}</label>
                    <div id="permalink-comment-{{ $itemId }}-hint" class="govuk-hint">{{ __('govuk_alpha.feed.comment_hint') }}</div>
                    <textarea class="govuk-textarea" id="permalink-comment-{{ $itemId }}" name="content" rows="3" aria-describedby="permalink-comment-{{ $itemId }}-hint"></textarea>
                </div>
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.comment') }}</button>
            </form>
        @endunless
    </article>
@endsection
