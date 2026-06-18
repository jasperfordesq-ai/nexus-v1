{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $items = $items ?? [];
        $reactionStatuses = ['reaction-added', 'reaction-removed', 'not-interested'];
        $errorStatuses = ['reaction-failed', 'not-interested-failed', 'like-failed'];
        $statusKeyMap = [
            'reaction-added'         => 'govuk_alpha_feed.states.reaction_added',
            'reaction-removed'       => 'govuk_alpha_feed.states.reaction_removed',
            'reaction-failed'        => 'govuk_alpha_feed.states.reaction_failed',
            'not-interested'         => 'govuk_alpha_feed.states.not_interested',
            'not-interested-failed'  => 'govuk_alpha_feed.states.not_interested_failed',
            'auth-required'          => 'govuk_alpha_feed.states.auth_required',
        ];
        $nextPageUrl = ($hasMore ?? false)
            ? route('govuk-alpha.feed.hashtag', ['tenantSlug' => $tenantSlug, 'tag' => $tag, 'page' => ($page ?? 1) + 1, 'per_page' => $perPage ?? 20])
            : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.feed.hashtags', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_feed.hashtag.back_to_hashtags') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha_feed.hashtag.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">#{{ $tag }}</h1>
    <p class="govuk-body-l">{{ trans_choice('govuk_alpha_feed.hashtag.total_posts', (int) ($totalCount ?? 0), ['count' => (int) ($totalCount ?? 0)]) }}</p>

    @if (!empty($error))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_feed.states.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <p class="govuk-body">{{ $error }}</p>
            </div>
        </div>
    @endif

    @php $statusKey = $statusKeyMap[$status ?? ''] ?? null; @endphp
    @if ($statusKey !== null)
        @php $isError = in_array($status, $errorStatuses, true) || ($status ?? '') === 'auth-required'; @endphp
        <div class="govuk-notification-banner {{ $isError ? '' : 'govuk-notification-banner--success' }}" data-module="govuk-notification-banner" role="{{ $isError ? 'region' : 'alert' }}" aria-labelledby="hashtag-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="hashtag-status-title">{{ $isError ? __('govuk_alpha_feed.states.error_title') : __('govuk_alpha_feed.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($statusKey) }}</p>
            </div>
        </div>
    @endif

    @if (empty($items))
        <div class="nexus-alpha-card">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_feed.hashtag.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_feed.hashtag.empty_body', ['tag' => '#' . $tag]) }}</p>
        </div>
    @else
        @foreach ($items as $item)
            @php
                $itemId = (int) ($item['id'] ?? 0);
                $authorName = $item['author']['name'] ?? $item['author_name'] ?? __('govuk_alpha_feed.item.unknown_author');
                $authorAvatar = $item['author']['avatar_url'] ?? null;
                $createdAt = !empty($item['created_at']) ? \Illuminate\Support\Carbon::parse($item['created_at']) : null;
                $likeCount = (int) ($item['likes_count'] ?? 0);
                $commentCount = (int) ($item['comments_count'] ?? 0);
                $isLiked = (bool) ($item['is_liked'] ?? false);
                $itemReactions = $reactionsByTarget['post'][$itemId] ?? null;
                $itemMedia = $item['media'] ?? [];
                if (empty($itemMedia) && !empty($item['image_url'])) {
                    $itemMedia = [['file_url' => $item['image_url'], 'thumbnail_url' => null, 'alt_text' => null]];
                }
                $contentParagraphs = [];
                if (!empty($item['content'])) {
                    $plainContent = trim(html_entity_decode(strip_tags((string) $item['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $contentParagraphs = preg_split('/\R{2,}/u', $plainContent) ?: [];
                }
            @endphp
            @if ($itemId > 0)
                <article class="nexus-alpha-card" id="feed-item-post-{{ $itemId }}">
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
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
                                        <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer">
                                            <img src="{{ $thumbUrl }}" alt="{{ $altText }}" class="nexus-alpha-feed-image" loading="lazy">
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif

                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ trans_choice('govuk_alpha_feed.engagement.likes', $likeCount, ['count' => $likeCount]) }}
                        ·
                        {{ trans_choice('govuk_alpha_feed.engagement.comments', $commentCount, ['count' => $commentCount]) }}
                    </p>

                    @unless ($requiresAuth)
                        <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-3">
                            <form method="post" action="{{ route('govuk-alpha.feed.items.like', ['tenantSlug' => $tenantSlug, 'type' => 'post', 'id' => $itemId]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                                    {{ $isLiked ? __('govuk_alpha_feed.engagement.unlike') : __('govuk_alpha_feed.engagement.like') }}
                                </button>
                            </form>
                        </div>

                        @if ($itemReactions !== null)
                            @include('accessible-frontend::partials.feed-reactions', [
                                'reactionAction' => route('govuk-alpha.feed.posts.react', ['tenantSlug' => $tenantSlug, 'id' => $itemId]),
                                'alphaReactions' => $alphaReactions,
                                'reactionLegend' => __('govuk_alpha_feed.engagement.reactions_legend'),
                                'reactionTargetLabel' => __('govuk_alpha_feed.engagement.reaction_for', ['name' => $authorName]),
                                'reactionCounts' => $itemReactions['counts'] ?? [],
                                'userReactionTypes' => !empty($itemReactions['user_reaction']) ? [$itemReactions['user_reaction']] : [],
                                'reactionPreserved' => [],
                            ])
                        @endif
                    @endunless

                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.feed.posts.show', ['tenantSlug' => $tenantSlug, 'id' => $itemId]) }}">
                            {{ __('govuk_alpha_feed.hashtag.view_post') }}
                        </a>
                    </p>
                </article>
            @endif
        @endforeach

        @if ($nextPageUrl !== null)
            <p class="govuk-body">
                <a class="govuk-link govuk-link--no-visited-state" href="{{ $nextPageUrl }}">{{ __('govuk_alpha_feed.hashtag.show_more') }}</a>
            </p>
        @endif
    @endif
@endsection
