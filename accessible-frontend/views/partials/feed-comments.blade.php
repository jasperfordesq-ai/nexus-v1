{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@php
    $depth = (int) ($depth ?? 0);
    $requiresAuth = $requiresAuth ?? true;
    $currentUserId = $currentUserId ?? null;
    $preservedFeedInputs = $preservedFeedInputs ?? [];
    $canReply = !$requiresAuth && ($depth < 4);
    // The accessible reaction set (type => emoji). Passed down from the feed /
    // permalink page; default to empty so the partial stays render-safe if absent.
    $alphaReactions = $alphaReactions ?? [];
@endphp

<ol class="govuk-list nexus-alpha-comments-list{{ $depth > 0 ? ' nexus-alpha-comments-list--nested' : '' }}">
    @foreach ($comments as $comment)
        @php
            $commentId = (int) ($comment['id'] ?? 0);
            $authorName = $comment['author']['name'] ?? __('govuk_alpha.feed.unknown_author');
            $authorAvatar = $comment['author']['avatar'] ?? null;
            $commentAuthorId = (int) ($comment['author']['id'] ?? 0);
            $isOwnComment = !$requiresAuth && ($currentUserId ?? 0) > 0 && $commentAuthorId === (int) $currentUserId && $commentId > 0;
            $createdAt = !empty($comment['created_at']) ? \Illuminate\Support\Carbon::parse($comment['created_at']) : null;
        @endphp
        <li class="nexus-alpha-comment">
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                @if (!empty($authorAvatar))
                    <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $authorAvatar }}" alt="" loading="lazy" decoding="async" width="32" height="32">
                @endif
                <strong>{{ $authorName }}</strong>
                @if ($createdAt)
                    <span aria-hidden="true"> | </span>
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.comment_posted_on_prefix') }}</span>
                    <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->translatedFormat('j F Y, H:i') }}</time>
                @endif
            </p>
            <p class="govuk-body govuk-!-margin-bottom-2">{{ $comment['content'] ?? '' }}</p>

            @if (!$requiresAuth && $commentId > 0 && !empty($alphaReactions))
                @php
                    // CommentService::getForEntity returns reactions as an emoji=>count
                    // map (an empty object when there are none) and user_reactions as a
                    // list of the viewer's chosen reaction types.
                    $commentReactionCounts = (array) ($comment['reactions'] ?? []);
                    $commentUserReactions = array_values((array) ($comment['user_reactions'] ?? []));
                @endphp
                @include('accessible-frontend::partials.feed-reactions', [
                    'reactionAction' => route('govuk-alpha.feed.comments.react', ['tenantSlug' => $tenantSlug, 'id' => $commentId]),
                    'alphaReactions' => $alphaReactions,
                    'reactionLegend' => __('govuk_alpha.feed_t1.reactions_comment_legend'),
                    'reactionTargetLabel' => __('govuk_alpha.feed_t1.reaction_for', ['name' => $authorName]),
                    'reactionCounts' => $commentReactionCounts,
                    'userReactionTypes' => $commentUserReactions,
                    'reactionPreserved' => $preservedFeedInputs,
                ])
            @endif

            @if (($canReply || $isOwnComment) && $commentId > 0)
                <div class="nexus-alpha-comment-actions govuk-!-margin-bottom-2">
                    @if ($canReply)
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.actions.reply') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                <form method="post" action="{{ route('govuk-alpha.feed.items.comments.store', ['tenantSlug' => $tenantSlug, 'type' => $targetType, 'id' => $targetId]) }}">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $commentId }}">
                                    @foreach ($preservedFeedInputs as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <div class="govuk-form-group">
                                        <label class="govuk-label" for="reply-{{ $commentId }}">{{ __('govuk_alpha.feed.reply_label') }}</label>
                                        <textarea class="govuk-textarea" id="reply-{{ $commentId }}" name="content" rows="3" required></textarea>
                                    </div>
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.actions.reply') }}</button>
                                </form>
                            </div>
                        </details>
                    @endif
                    @if ($isOwnComment)
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.edit_comment') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                <form method="post" action="{{ route('govuk-alpha.feed.comments.update', ['tenantSlug' => $tenantSlug, 'id' => $commentId]) }}">
                                    @csrf
                                    <div class="govuk-form-group">
                                        <label class="govuk-label" for="edit-comment-{{ $commentId }}">{{ __('govuk_alpha.feed.edit_comment_label') }}</label>
                                        <textarea class="govuk-textarea" id="edit-comment-{{ $commentId }}" name="content" rows="3" required>{{ $comment['content'] ?? '' }}</textarea>
                                    </div>
                                    <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.actions.save_changes') }}</button>
                                </form>
                            </div>
                        </details>
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.feed.delete_comment') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                <p class="govuk-body">{{ __('govuk_alpha.feed.delete_comment_confirm') }}</p>
                                <form method="post" action="{{ route('govuk-alpha.feed.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $commentId]) }}">
                                    @csrf
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.feed.delete_comment_button') }}</button>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>
            @endif

            @if (!empty($comment['replies']))
                @include('accessible-frontend::partials.feed-comments', [
                    'comments' => $comment['replies'],
                    'depth' => $depth + 1,
                    'targetType' => $targetType,
                    'targetId' => $targetId,
                    'tenantSlug' => $tenantSlug,
                    'requiresAuth' => $requiresAuth,
                    'currentUserId' => $currentUserId,
                    'preservedFeedInputs' => $preservedFeedInputs,
                    'alphaReactions' => $alphaReactions,
                ])
            @endif
        </li>
    @endforeach
</ol>
