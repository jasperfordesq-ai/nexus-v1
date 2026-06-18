{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Rich blog/review comment node with edit / delete / reply-to-specific and
    emoji reactions. HTML-first, no JS. Mirrors the React CommentsSection.

    Required vars:
      $comment         — threaded comment array (id, content, created_at, edited,
                         is_own, author{id,name}, reactions, user_reactions, replies)
      $depth           — current nesting depth (int)
      $tenantSlug      — tenant slug
      $currentUserId   — viewer user id
      $alphaReactions  — array<string type, string emoji>
      $storeAction     — POST URL to add a comment/reply
      $updateRoute     — route name for updating a comment
      $deleteRoute     — route name for deleting a comment
      $reactRoute      — route name for reacting to a comment
      $hiddenInputs    — array<string name, string value> preserved on every form
                         (e.g. ['slug' => ...]) so the action can redirect back
--}}
@php
    $depth = (int) ($depth ?? 0);
    $commentId = (int) ($comment['id'] ?? 0);
    $authorName = trim((string) ($comment['author']['name'] ?? '')) !== ''
        ? $comment['author']['name']
        : __('govuk_alpha_blogreviews.likers.unknown_member');
    $isOwn = !empty($comment['is_own']) && $commentId > 0;
    $isEdited = !empty($comment['edited']);
    $createdAt = !empty($comment['created_at']) ? \Illuminate\Support\Carbon::parse($comment['created_at']) : null;
    $cReplies = is_array($comment['replies'] ?? null) ? $comment['replies'] : [];
    $hiddenInputs = $hiddenInputs ?? [];
    $reactionCounts = (array) ($comment['reactions'] ?? []);
    $userReactionTypes = array_values((array) ($comment['user_reactions'] ?? []));
    // React shows the reply button only for depth < 2.
    $canReply = $commentId > 0 && $depth < 2;
@endphp
<li class="nexus-alpha-comment" id="comment-{{ $commentId }}">
    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
        <strong>{{ $authorName }}</strong>
        @if ($isEdited)
            <span class="govuk-!-font-weight-regular"> ({{ __('govuk_alpha_blogreviews.comments.edited') }})</span>
        @endif
        @if ($createdAt)
            <span aria-hidden="true"> | </span>
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.comments.posted_on_prefix') }} </span>
            <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->translatedFormat('j F Y, H:i') }}</time>
        @endif
    </p>
    <p class="govuk-body govuk-!-margin-bottom-2">{{ $comment['content'] ?? '' }}</p>

    {{-- Emoji reactions (one plain submit button per type — no JS picker) --}}
    @if ($commentId > 0 && !empty($alphaReactions))
        @php $commentReactionTotal = array_sum(array_map('intval', $reactionCounts)); @endphp
        <div class="nexus-alpha-reactions govuk-!-margin-bottom-2">
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1" aria-live="polite">
                {{ trans_choice('govuk_alpha_blogreviews.reactions.count', $commentReactionTotal, ['count' => $commentReactionTotal]) }}
            </p>
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.reactions.comment_legend') }}</legend>
                <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-0">
                    @foreach ($alphaReactions as $reactionType => $reactionEmoji)
                        @php
                            $isSelected = in_array($reactionType, $userReactionTypes, true);
                            $typeCount = (int) ($reactionCounts[$reactionType] ?? 0);
                            $reactionLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                ? __('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                : $reactionType;
                        @endphp
                        <form method="post" action="{{ route($reactRoute, array_merge(['tenantSlug' => $tenantSlug, 'id' => $commentId])) }}" class="nexus-alpha-reaction-form">
                            @csrf
                            @foreach ($hiddenInputs as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <input type="hidden" name="emoji" value="{{ $reactionType }}">
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0{{ $isSelected ? ' nexus-alpha-reaction--active' : '' }}" data-module="govuk-button" aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                <span aria-hidden="true">{{ $reactionEmoji }}</span>
                                {{ $reactionLabel }}@if ($typeCount > 0) ({{ $typeCount }})@endif
                                <span class="govuk-visually-hidden"> {{ __('govuk_alpha_blogreviews.reactions.for_comment') }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </fieldset>
        </div>
    @endif

    @if (($canReply || $isOwn) && $commentId > 0)
        <div class="nexus-alpha-comment-actions govuk-!-margin-bottom-2">
            @if ($canReply)
                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text">{{ __('govuk_alpha_blogreviews.comments.reply_toggle') }}</span>
                    </summary>
                    <div class="govuk-details__text">
                        <form method="post" action="{{ $storeAction }}">
                            @csrf
                            @foreach ($hiddenInputs as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <input type="hidden" name="parent_id" value="{{ $commentId }}">
                            <div class="govuk-form-group">
                                <label class="govuk-label govuk-label--s" for="reply-{{ $commentId }}">{{ __('govuk_alpha_blogreviews.comments.reply_to', ['name' => $authorName]) }}</label>
                                <textarea class="govuk-textarea" id="reply-{{ $commentId }}" name="body" rows="3" maxlength="5000" required></textarea>
                            </div>
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.comments.reply_submit') }}</button>
                        </form>
                    </div>
                </details>
            @endif
            @if ($isOwn)
                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text">{{ __('govuk_alpha_blogreviews.comments.edit_toggle') }}</span>
                    </summary>
                    <div class="govuk-details__text">
                        <form method="post" action="{{ route($updateRoute, ['tenantSlug' => $tenantSlug, 'id' => $commentId]) }}">
                            @csrf
                            @foreach ($hiddenInputs as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <div class="govuk-form-group">
                                <label class="govuk-label govuk-label--s" for="edit-comment-{{ $commentId }}">{{ __('govuk_alpha_blogreviews.comments.edit_label') }}</label>
                                <textarea class="govuk-textarea" id="edit-comment-{{ $commentId }}" name="content" rows="3" maxlength="5000" required>{{ $comment['content'] ?? '' }}</textarea>
                            </div>
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.comments.edit_submit') }}</button>
                        </form>
                    </div>
                </details>
                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text">{{ __('govuk_alpha_blogreviews.comments.delete_toggle') }}</span>
                    </summary>
                    <div class="govuk-details__text">
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.states.error_prefix') }}</span>
                                {{ __('govuk_alpha_blogreviews.comments.delete_warning') }}
                            </strong>
                        </div>
                        <form method="post" action="{{ route($deleteRoute, ['tenantSlug' => $tenantSlug, 'id' => $commentId]) }}">
                            @csrf
                            @foreach ($hiddenInputs as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.comments.delete_submit') }}</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    @endif

    @if (!empty($cReplies) && $depth < 4)
        <ul class="govuk-list nexus-alpha-comments-list--nested">
            @foreach ($cReplies as $reply)
                @include('accessible-frontend::partials.blogreviews-comment', [
                    'comment' => $reply,
                    'depth' => $depth + 1,
                    'tenantSlug' => $tenantSlug,
                    'currentUserId' => $currentUserId,
                    'alphaReactions' => $alphaReactions,
                    'storeAction' => $storeAction,
                    'updateRoute' => $updateRoute,
                    'deleteRoute' => $deleteRoute,
                    'reactRoute' => $reactRoute,
                    'hiddenInputs' => $hiddenInputs,
                ])
            @endforeach
        </ul>
    @endif
</li>
