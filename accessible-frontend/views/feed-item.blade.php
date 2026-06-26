{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $itemId = (int) ($itemId ?? ($item['id'] ?? 0));
        $itemType = $itemType ?? ($item['type'] ?? 'post');
        $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha_feed.item.unknown_author');
        $authorAvatar = $item['author']['avatar_url'] ?? null;
        $createdAt = !empty($item['created_at']) ? \Illuminate\Support\Carbon::parse($item['created_at']) : null;
        $itemTitle = $item['title'] ?? null;
        $likeCount = (int) ($item['likes_count'] ?? 0);
        $commentCount = (int) ($item['comments_count'] ?? 0);
        $isLiked = (bool) ($item['is_liked'] ?? false);
        $reactionCounts = is_array($itemReactions ?? null) ? ($itemReactions['counts'] ?? []) : [];
        $userReaction = is_array($itemReactions ?? null) ? ($itemReactions['user_reaction'] ?? null) : null;
        $itemMedia = $item['media'] ?? [];
        if (empty($itemMedia) && !empty($item['image_url'])) {
            $itemMedia = [['file_url' => $item['image_url'], 'thumbnail_url' => null, 'alt_text' => null]];
        }
        $typeLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_feed.item_types.' . $itemType)
            ? __('govuk_alpha_feed.item_types.' . $itemType)
            : __('govuk_alpha_feed.item_types.activity');

        // Deep-link to the full module page for the typed item, where one exists.
        $deepLink = null;
        $deepLinkLabel = null;
        try {
            $deepLink = match ($itemType) {
                'listing'   => route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                'post'      => route('govuk-alpha.feed.posts.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                default     => null,
            };
            $deepLinkLabel = match ($itemType) {
                'listing'   => __('govuk_alpha_feed.item.view_listing'),
                'post'      => __('govuk_alpha_feed.item.open_full'),
                default     => null,
            };
        } catch (\Throwable $e) {
            $deepLink = null;
        }

        $contentParagraphs = [];
        if (!empty($item['content'])) {
            $plainContent = trim(html_entity_decode(strip_tags((string) $item['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $contentParagraphs = preg_split('/\R{2,}/u', $plainContent) ?: [];
        }

        $statusKeyMap = [
            'reaction-added'         => 'govuk_alpha_feed.states.reaction_added',
            'reaction-removed'       => 'govuk_alpha_feed.states.reaction_removed',
            'reaction-failed'        => 'govuk_alpha_feed.states.reaction_failed',
            'not-interested'         => 'govuk_alpha_feed.states.not_interested',
            'not-interested-failed'  => 'govuk_alpha_feed.states.not_interested_failed',
            'comment-created'        => 'govuk_alpha_feed.states.success_title',
        ];
        $errorStatuses = ['reaction-failed', 'not-interested-failed', 'like-failed', 'comment-failed', 'auth-required'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_feed.item.back_to_feed') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha_feed.item.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $itemTitle ?: __('govuk_alpha_feed.item.heading') }}</h1>

    @php $statusKey = $statusKeyMap[$status ?? ''] ?? null; @endphp
    @if ($statusKey !== null)
        @php $isError = in_array($status, $errorStatuses, true); @endphp
        <div class="govuk-notification-banner {{ $isError ? '' : 'govuk-notification-banner--success' }}" data-module="govuk-notification-banner" role="{{ $isError ? 'region' : 'alert' }}" aria-labelledby="item-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="item-status-title">{{ $isError ? __('govuk_alpha_feed.states.error_title') : __('govuk_alpha_feed.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($statusKey) }}</p>
            </div>
        </div>
    @endif

    @if ($requiresAuth)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="item-auth-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="item-auth-title">{{ __('govuk_alpha_feed.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_feed.states.auth_required') }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                </div>
            </div>
        </div>
    @endif

    <article class="nexus-alpha-card" id="feed-item-{{ $itemType }}-{{ $itemId }}">
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
            <strong class="govuk-tag govuk-tag--grey">{{ $typeLabel }}</strong>
            @if (!empty($authorAvatar))
                <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $authorAvatar }}" alt="" loading="lazy" decoding="async" width="32" height="32">
            @endif
            {{ __('govuk_alpha_feed.item.posted_by', ['name' => $authorName]) }}
            @if ($createdAt)
                <span aria-hidden="true"> | </span>
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_feed.item.posted_on') }} </span>
                <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->translatedFormat('j F Y, H:i') }}</time>
            @endif
        </p>

        @foreach ($contentParagraphs as $paragraph)
            @if (trim($paragraph) !== '')
                <p class="govuk-body">{!! nl2br(e(trim($paragraph))) !!}</p>
            @endif
        @endforeach

        @if (is_array($item['quoted_post'] ?? null))
            @include('accessible-frontend::partials.feed-quoted-post', [
                'quoted' => $item['quoted_post'],
                'tenantSlug' => $tenantSlug,
            ])
        @endif

        @if (!empty($itemMedia))
            <ul class="nexus-alpha-feed-media" data-count="{{ min(count($itemMedia), 4) }}">
                @foreach (array_slice($itemMedia, 0, 4) as $media)
                    @php
                        $fullUrl = $media['file_url'] ?? null;
                        $thumbUrl = $media['thumbnail_url'] ?? $fullUrl;
                        $altText = !empty($media['alt_text']) ? $media['alt_text'] : __('govuk_alpha_feed.item.image_alt');
                    @endphp
                    @if ($fullUrl)
                        <li>
                            <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer"><span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span>
                                <img src="{{ $thumbUrl }}" alt="{{ $altText }}" class="nexus-alpha-feed-image" loading="lazy">
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        @if ($deepLink !== null && $deepLinkLabel !== null)
            <p class="govuk-body-s nexus-alpha-meta">
                <a class="govuk-link govuk-link--no-visited-state" href="{{ $deepLink }}">{{ $deepLinkLabel }}</a>
            </p>
        @endif

        <p class="govuk-body-s nexus-alpha-meta">
            {{ trans_choice('govuk_alpha_feed.engagement.likes', $likeCount, ['count' => $likeCount]) }}
            ·
            {{ trans_choice('govuk_alpha_feed.engagement.comments', $commentCount, ['count' => $commentCount]) }}
        </p>

        @unless ($requiresAuth)
            <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-3">
                <form method="post" action="{{ route('govuk-alpha.feed.items.like', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                        {{ $isLiked ? __('govuk_alpha_feed.engagement.unlike') : __('govuk_alpha_feed.engagement.like') }}
                    </button>
                </form>
            </div>

            {{-- Emoji reactions for ANY reactable feed item — the typed-item
                 react route extends parity beyond posts/comments. --}}
            @include('accessible-frontend::partials.feed-reactions', [
                'reactionAction' => route('govuk-alpha.feed.items.react', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]),
                'alphaReactions' => $alphaReactions,
                'reactionLegend' => __('govuk_alpha_feed.engagement.reactions_legend'),
                'reactionTargetLabel' => __('govuk_alpha_feed.engagement.reaction_for', ['name' => $authorName]),
                'reactionCounts' => $reactionCounts,
                'userReactionTypes' => !empty($userReaction) ? [$userReaction] : [],
                'reactionPreserved' => [],
            ])

            <details class="govuk-details govuk-!-margin-bottom-3" data-module="govuk-details">
                <summary class="govuk-details__summary">
                    <span class="govuk-details__summary-text">{{ __('govuk_alpha_feed.engagement.not_interested') }}</span>
                </summary>
                <div class="govuk-details__text">
                    <p class="govuk-body-s">{{ __('govuk_alpha_feed.engagement.not_interested_hint') }}</p>
                    <form method="post" action="{{ route('govuk-alpha.feed.items.not-interested', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]) }}">
                        @csrf
                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_feed.engagement.not_interested') }}</button>
                    </form>
                </div>
            </details>
        @endunless

        @if ($isCommentable ?? false)
            <h2 class="govuk-heading-m govuk-!-margin-top-4">{{ __('govuk_alpha_feed.item.comments_heading') }}</h2>
            @if (!empty($comments))
                @include('accessible-frontend::partials.feed-comments', [
                    'comments' => $comments,
                    'depth' => 0,
                    'targetType' => $itemType,
                    'targetId' => $itemId,
                    'tenantSlug' => $tenantSlug,
                    'requiresAuth' => $requiresAuth,
                    'currentUserId' => $currentUserId ?? null,
                    'preservedFeedInputs' => [],
                    'alphaReactions' => $alphaReactions,
                ])
            @else
                <p class="govuk-body">{{ __('govuk_alpha_feed.item.no_comments') }}</p>
            @endif

            @unless ($requiresAuth)
                <form method="post" action="{{ route('govuk-alpha.feed.items.comments.store', ['tenantSlug' => $tenantSlug, 'type' => $itemType, 'id' => $itemId]) }}">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="item-comment-{{ $itemId }}">{{ __('govuk_alpha_feed.item.comment_label') }}</label>
                        <div id="item-comment-{{ $itemId }}-hint" class="govuk-hint">{{ __('govuk_alpha_feed.item.comment_hint') }}</div>
                        <textarea class="govuk-textarea" id="item-comment-{{ $itemId }}" name="content" rows="3" aria-describedby="item-comment-{{ $itemId }}-hint"></textarea>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_feed.item.comment_submit') }}</button>
                </form>
            @endunless
        @endif
    </article>
@endsection
