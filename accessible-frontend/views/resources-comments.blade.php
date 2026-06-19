{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $resource = $resource ?? [];
        $resourceId = (int) ($resourceId ?? 0);
        $resourceTitle = (string) ($resource['title'] ?? '');
        $comments = is_array($comments ?? null) ? $comments : [];
        $alphaReactions = $alphaReactions ?? [];
        $resourceReactionCounts = (array) ($resourceReactionCounts ?? []);
        $resourceReactionTotal = (int) ($resourceReactionTotal ?? 0);
        $resourceUserReaction = $resourceUserReaction ?? null;
        $storeAction = route('govuk-alpha.resources.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $resourceId]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.social.back_to_library') }}</a>

            {{-- Status banner --}}
            @if (in_array($status ?? null, ['comment-added', 'reply-added', 'reaction-added', 'reaction-removed', 'comment-deleted'], true))
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="resources-social-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="resources-social-status-title">{{ __('govuk_alpha_resources.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_resources.social.status.' . ($status ?? ''), [], null, '') ?: __('govuk_alpha_resources.social.status.comment-added') }}</p>
                    </div>
                </div>
            @elseif (in_array($status ?? null, ['comment-failed', 'comment-delete-failed', 'reaction-failed'], true))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_resources.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <p>{{ __('govuk_alpha_resources.social.status.' . ($status ?? ''), [], null, '') ?: __('govuk_alpha_resources.social.status.comment-failed') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <span class="govuk-caption-l">{{ __('govuk_alpha_resources.social.comments_caption') }}</span>
            <h1 class="govuk-heading-xl">{{ $resourceTitle }}</h1>

            {{-- Reaction panel — mirroring SocialInteractionPanel (targetType='resource') --}}
            <section id="resource-reactions" class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha_resources.social.reactions_heading') }}</h2>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1" aria-live="polite">
                    {{ trans_choice('govuk_alpha_resources.social.reaction_count', $resourceReactionTotal, ['count' => $resourceReactionTotal]) }}
                </p>
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s govuk-visually-hidden">{{ __('govuk_alpha_resources.social.reactions_heading') }}</legend>
                    <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-2">
                        @foreach ($alphaReactions as $reactionType => $reactionEmoji)
                            @php
                                $isSelected = $resourceUserReaction === $reactionType;
                                $typeCount = (int) ($resourceReactionCounts[$reactionType] ?? 0);
                                $reactionLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_resources.social.reaction_types.' . $reactionType)
                                    ? __('govuk_alpha_resources.social.reaction_types.' . $reactionType)
                                    : $reactionType;
                            @endphp
                            <form method="post" action="{{ route('govuk-alpha.resources.react', ['tenantSlug' => $tenantSlug, 'id' => $resourceId]) }}" class="nexus-alpha-reaction-form">
                                @csrf
                                <input type="hidden" name="emoji" value="{{ $reactionType }}">
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0{{ $isSelected ? ' nexus-alpha-reaction--active' : '' }}" data-module="govuk-button" aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                    <span aria-hidden="true">{{ $reactionEmoji }}</span>
                                    {{ $reactionLabel }}@if ($typeCount > 0) ({{ $typeCount }})@endif
                                    <span class="govuk-visually-hidden"> {{ __('govuk_alpha_resources.social.reaction_for_resource') }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

            {{-- Comment thread --}}
            <section id="comments">
                <h2 class="govuk-heading-l">
                    {{ __('govuk_alpha_resources.social.comments_heading') }}
                    <span class="govuk-!-font-weight-regular">({{ (int) ($commentsCount ?? 0) }})</span>
                </h2>

                @if (empty($comments))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_resources.social.comments_empty') }}</p></div>
                @else
                    <ol class="govuk-list nexus-alpha-comments-list">
                        @foreach ($comments as $comment)
                            @php
                                $cId = (int) ($comment['id'] ?? 0);
                                $cBody = (string) ($comment['content'] ?? '');
                                $cAuthor = (string) ($comment['author_name'] ?? __('govuk_alpha_resources.social.unknown_author'));
                                $cDate = $comment['created_at'] ?? null;
                                $cUserId = (int) ($comment['user_id'] ?? 0);
                                $isOwner = $cUserId > 0 && $cUserId === (int) ($currentUserId ?? 0);
                                $replies = is_array($comment['replies'] ?? null) ? $comment['replies'] : [];
                            @endphp
                            <li id="comment-{{ $cId }}" class="nexus-alpha-comment">
                                <p class="nexus-alpha-meta govuk-body-s govuk-!-margin-bottom-1">
                                    <strong>{{ $cAuthor }}</strong>
                                    @if ($cDate)
                                        &middot; {{ \Illuminate\Support\Carbon::parse($cDate)->diffForHumans() }}
                                    @endif
                                </p>
                                <p class="govuk-body govuk-!-margin-bottom-2">{{ $cBody }}</p>
                                @if ($isOwner)
                                    <form method="post" action="{{ route('govuk-alpha.resources.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $resourceId, 'commentId' => $cId]) }}" class="govuk-!-display-inline">
                                        @csrf
                                        <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button"
                                                aria-label="{{ __('govuk_alpha_resources.social.delete_comment_aria') }}">{{ __('govuk_alpha_resources.social.delete_comment') }}</button>
                                    </form>
                                @endif

                                {{-- Replies (one level deep) --}}
                                @if (!empty($replies))
                                    <ol class="govuk-list nexus-alpha-comments-list govuk-!-margin-top-3 govuk-!-padding-left-4">
                                        @foreach ($replies as $reply)
                                            @php
                                                $rId = (int) ($reply['id'] ?? 0);
                                                $rBody = (string) ($reply['content'] ?? '');
                                                $rAuthor = (string) ($reply['author_name'] ?? __('govuk_alpha_resources.social.unknown_author'));
                                                $rDate = $reply['created_at'] ?? null;
                                                $rUserId = (int) ($reply['user_id'] ?? 0);
                                                $rIsOwner = $rUserId > 0 && $rUserId === (int) ($currentUserId ?? 0);
                                            @endphp
                                            <li id="comment-{{ $rId }}" class="nexus-alpha-comment">
                                                <p class="nexus-alpha-meta govuk-body-s govuk-!-margin-bottom-1">
                                                    <strong>{{ $rAuthor }}</strong>
                                                    @if ($rDate)
                                                        &middot; {{ \Illuminate\Support\Carbon::parse($rDate)->diffForHumans() }}
                                                    @endif
                                                </p>
                                                <p class="govuk-body govuk-!-margin-bottom-2">{{ $rBody }}</p>
                                                @if ($rIsOwner)
                                                    <form method="post" action="{{ route('govuk-alpha.resources.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $resourceId, 'commentId' => $rId]) }}" class="govuk-!-display-inline">
                                                        @csrf
                                                        <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button"
                                                                aria-label="{{ __('govuk_alpha_resources.social.delete_comment_aria') }}">{{ __('govuk_alpha_resources.social.delete_comment') }}</button>
                                                    </form>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif

                {{-- Add comment form --}}
                <h3 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_resources.social.add_comment_heading') }}</h3>
                <form method="post" action="{{ $storeAction }}">
                    @csrf
                    <div class="govuk-form-group {{ ($status ?? null) === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_resources.social.comment_body_label') }}</label>
                        <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha_resources.social.comment_body_hint') }}</div>
                        @if (($status ?? null) === 'comment-invalid')
                            <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_resources.states.error_title') }}:</span> {{ __('govuk_alpha_resources.social.status.comment-invalid') }}</p>
                        @endif
                        <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="5000" aria-describedby="body-hint{{ ($status ?? null) === 'comment-invalid' ? ' body-error' : '' }}"></textarea>
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_resources.social.comment_submit') }}</button>
                </form>
            </section>
        </div>
    </div>
@endsection
